<?php
/**
 * TOTP (RFC 6238) sobre HOTP (RFC 4226): HMAC-SHA1, período de 30 s, 6 dígitos, janela ±1, base32 (RFC 4648).
 * Entregue pelo módulo "2FA".
 *
 * @package EBCR
 */

namespace EBCR\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Implementação sem dependências, compatível com Google Authenticator, Authy, Microsoft Authenticator, 1Password etc.
 */
final class Totp {

	const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	const PERIOD   = 30;
	const DIGITS   = 6;
	const WINDOW   = 1;

	/**
	 * Gera um segredo aleatório (20 bytes = 160 bits, o recomendado para SHA1) em base32.
	 *
	 * @param int $bytes Bytes de entropia.
	 * @return string
	 */
	public static function generate_secret( $bytes = 20 ) {
		return self::base32_encode( random_bytes( max( 10, (int) $bytes ) ) );
	}

	/**
	 * Codifica em base32 (sem padding: os aplicativos aceitam e o segredo fica mais curto para digitação manual).
	 *
	 * @param string $binary Bytes.
	 * @return string
	 */
	public static function base32_encode( $binary ) {
		$binary = (string) $binary;
		$out    = '';
		$bits   = 0;
		$value  = 0;
		$len    = strlen( $binary );
		for ( $i = 0; $i < $len; $i++ ) {
			$value = ( $value << 8 ) | ord( $binary[ $i ] );
			$bits += 8;
			while ( $bits >= 5 ) {
				$bits -= 5;
				$out  .= self::ALPHABET[ ( $value >> $bits ) & 31 ];
			}
		}
		if ( $bits > 0 ) {
			$out .= self::ALPHABET[ ( $value << ( 5 - $bits ) ) & 31 ];
		}
		return $out;
	}

	/**
	 * Decodifica base32 (tolera minúsculas, espaços, hífens e padding "="). Retorna null se houver caractere inválido.
	 *
	 * @param string $b32 Texto em base32.
	 * @return string|null
	 */
	public static function base32_decode( $b32 ) {
		$b32 = strtoupper( preg_replace( '/[\s\-=]/', '', (string) $b32 ) );
		if ( '' === $b32 ) {
			return null;
		}
		$out   = '';
		$bits  = 0;
		$value = 0;
		$len   = strlen( $b32 );
		for ( $i = 0; $i < $len; $i++ ) {
			$pos = strpos( self::ALPHABET, $b32[ $i ] );
			if ( false === $pos ) {
				return null;
			}
			$value = ( $value << 5 ) | $pos;
			$bits += 5;
			if ( $bits >= 8 ) {
				$bits -= 8;
				$out  .= chr( ( $value >> $bits ) & 255 );
			}
		}
		return $out;
	}

	/**
	 * HOTP (RFC 4226): HMAC-SHA1 do contador (8 bytes big-endian) com truncamento dinâmico.
	 *
	 * @param string $key     Chave binária.
	 * @param int    $counter Contador.
	 * @param int    $digits  Dígitos.
	 * @return string Código com zeros à esquerda.
	 */
	public static function hotp( $key, $counter, $digits = self::DIGITS ) {
		$counter = (int) $counter;
		$msg     = pack( 'N2', ( $counter >> 32 ) & 0xFFFFFFFF, $counter & 0xFFFFFFFF );
		$hash    = hash_hmac( 'sha1', $msg, (string) $key, true );
		$offset  = ord( $hash[19] ) & 0x0F;
		$binary  = ( ( ord( $hash[ $offset ] ) & 0x7F ) << 24 )
			| ( ( ord( $hash[ $offset + 1 ] ) & 0xFF ) << 16 )
			| ( ( ord( $hash[ $offset + 2 ] ) & 0xFF ) << 8 )
			| ( ord( $hash[ $offset + 3 ] ) & 0xFF );
		$digits  = max( 6, min( 8, (int) $digits ) );
		return str_pad( (string) ( $binary % ( 10 ** $digits ) ), $digits, '0', STR_PAD_LEFT );
	}

	/**
	 * Passo de tempo (T) para um instante.
	 *
	 * @param int|null $timestamp Unix time (padrão: agora).
	 * @param int      $period    Período em segundos.
	 * @return int
	 */
	public static function time_step( $timestamp = null, $period = self::PERIOD ) {
		$timestamp = null === $timestamp ? time() : (int) $timestamp;
		return (int) floor( $timestamp / max( 1, (int) $period ) );
	}

	/**
	 * Código TOTP para um segredo base32 em um instante.
	 *
	 * @param string   $secret_b32 Segredo em base32.
	 * @param int|null $timestamp  Unix time (padrão: agora).
	 * @param int      $period     Período.
	 * @param int      $digits     Dígitos.
	 * @return string|null null se o segredo for inválido.
	 */
	public static function code( $secret_b32, $timestamp = null, $period = self::PERIOD, $digits = self::DIGITS ) {
		$key = self::base32_decode( $secret_b32 );
		if ( null === $key || '' === $key ) {
			return null;
		}
		return self::hotp( $key, self::time_step( $timestamp, $period ), $digits );
	}

	/**
	 * Verifica um código aceitando a janela ±N passos (tolerância de relógio). Comparação em tempo constante.
	 *
	 * @param string   $secret_b32 Segredo em base32.
	 * @param string   $code       Código digitado.
	 * @param int      $window     Passos de tolerância para cada lado.
	 * @param int|null $timestamp  Unix time (padrão: agora).
	 * @param int      $period     Período.
	 * @param int      $digits     Dígitos.
	 * @return int|false Passo de tempo que casou (para bloquear reuso) ou false.
	 */
	public static function verify( $secret_b32, $code, $window = self::WINDOW, $timestamp = null, $period = self::PERIOD, $digits = self::DIGITS ) {
		$code = preg_replace( '/\D/', '', (string) $code );
		if ( strlen( $code ) !== (int) $digits ) {
			return false;
		}
		$key = self::base32_decode( $secret_b32 );
		if ( null === $key || '' === $key ) {
			return false;
		}
		$now    = self::time_step( $timestamp, $period );
		$window = max( 0, (int) $window );
		$found  = false;
		for ( $i = -$window; $i <= $window; $i++ ) {
			$step = $now + $i;
			if ( $step < 0 ) {
				continue;
			}
			// Sem "break": percorre toda a janela para não vazar tempo de comparação.
			if ( hash_equals( self::hotp( $key, $step, $digits ), $code ) && false === $found ) {
				$found = $step;
			}
		}
		return $found;
	}

	/**
	 * URI otpauth para QR code / cadastro manual.
	 *
	 * @param string $secret_b32 Segredo.
	 * @param string $account    Login/e-mail do usuário.
	 * @param string $issuer     Nome do site/emissor.
	 * @param int    $period     Período.
	 * @param int    $digits     Dígitos.
	 * @return string
	 */
	public static function uri( $secret_b32, $account, $issuer, $period = self::PERIOD, $digits = self::DIGITS ) {
		$issuer  = trim( str_replace( ':', '-', wp_strip_all_tags( (string) $issuer ) ) );
		$account = trim( str_replace( ':', '-', wp_strip_all_tags( (string) $account ) ) );
		$label   = rawurlencode( $issuer ) . ':' . rawurlencode( $account );
		return 'otpauth://totp/' . $label . '?' . http_build_query(
			array(
				'secret'    => $secret_b32,
				'issuer'    => $issuer,
				'algorithm' => 'SHA1',
				'digits'    => (int) $digits,
				'period'    => (int) $period,
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
	}

	/**
	 * Segredo em grupos de 4 para digitação manual.
	 *
	 * @param string $secret_b32 Segredo.
	 * @return string
	 */
	public static function format_secret( $secret_b32 ) {
		return trim( chunk_split( (string) $secret_b32, 4, ' ' ) );
	}
}
