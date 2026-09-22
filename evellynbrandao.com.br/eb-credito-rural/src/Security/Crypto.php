<?php
/**
 * Criptografia em repouso (libsodium secretbox) com chave em wp-config.php.
 *
 * @package EBCR
 */

namespace EBCR\Security;

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64 usado apenas para serializar chave/nonce/cifra, não para ofuscar código.

defined( 'ABSPATH' ) || exit;

/**
 * define( 'EBCR_ENCRYPTION_KEY', 'base64:...' ) — 32 bytes.
 */
final class Crypto {

	const PREFIX = 'ebcr1:';

	/**
	 * Chave binária ou null.
	 *
	 * @return string|null
	 */
	private static function key() {
		if ( ! defined( 'EBCR_ENCRYPTION_KEY' ) || ! is_string( EBCR_ENCRYPTION_KEY ) ) {
			return null;
		}
		$raw = EBCR_ENCRYPTION_KEY;
		if ( 0 === strpos( $raw, 'base64:' ) ) {
			$bin = base64_decode( substr( $raw, 7 ), true );
		} elseif ( preg_match( '/^[a-f0-9]{64}$/i', $raw ) ) {
			$bin = hex2bin( $raw );
		} else {
			$bin = $raw;
		}
		return ( is_string( $bin ) && SODIUM_CRYPTO_SECRETBOX_KEYBYTES === strlen( $bin ) ) ? $bin : null;
	}

	/**
	 * Sodium presente e chave válida?
	 *
	 * @return bool
	 */
	public static function is_available() {
		return function_exists( 'sodium_crypto_secretbox' ) && null !== self::key();
	}

	/**
	 * Estado para a tela de configurações.
	 *
	 * @return string none|invalid|ok|nosodium
	 */
	public static function status() {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return 'nosodium';
		}
		if ( ! defined( 'EBCR_ENCRYPTION_KEY' ) ) {
			return 'none';
		}
		return null === self::key() ? 'invalid' : 'ok';
	}

	/**
	 * Gera uma chave nova (para exibir ao administrador).
	 *
	 * @return string
	 */
	public static function generate_key() {
		return 'base64:' . base64_encode( random_bytes( SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) );
	}

	/**
	 * Criptografa (retorna string com prefixo + base64(nonce|cipher)).
	 *
	 * @param string $plain Texto.
	 * @return string
	 * @throws \RuntimeException Se a chave não estiver disponível.
	 */
	public static function encrypt( $plain ) {
		$key = self::key();
		if ( null === $key ) {
			throw new \RuntimeException( esc_html( 'EBCR_ENCRYPTION_KEY indisponível.' ) );
		}
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( (string) $plain, $nonce, $key );
		$out    = self::PREFIX . base64_encode( $nonce . $cipher );
		sodium_memzero( $key );
		return $out;
	}

	/**
	 * Descriptografa. Retorna null em caso de falha.
	 *
	 * @param string $payload Texto criptografado.
	 * @return string|null
	 */
	public static function decrypt( $payload ) {
		$key = self::key();
		if ( null === $key || ! is_string( $payload ) || 0 !== strpos( $payload, self::PREFIX ) ) {
			return null;
		}
		$bin = base64_decode( substr( $payload, strlen( self::PREFIX ) ), true );
		if ( false === $bin || strlen( $bin ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) {
			return null;
		}
		$nonce  = substr( $bin, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $bin, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $key );
		sodium_memzero( $key );
		return false === $plain ? null : $plain;
	}

	/**
	 * Criptografa conteúdo binário de arquivo (mesmo formato, sem base64 para economizar espaço).
	 *
	 * @param string $bytes Conteúdo.
	 * @return string
	 */
	public static function encrypt_bytes( $bytes ) {
		$key = self::key();
		if ( null === $key ) {
			throw new \RuntimeException( esc_html( 'EBCR_ENCRYPTION_KEY indisponível.' ) );
		}
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$out   = "EBCRF1\0" . $nonce . sodium_crypto_secretbox( $bytes, $nonce, $key );
		sodium_memzero( $key );
		return $out;
	}

	/**
	 * Descriptografa conteúdo de arquivo.
	 *
	 * @param string $bytes Conteúdo criptografado.
	 * @return string|null
	 */
	public static function decrypt_bytes( $bytes ) {
		$key = self::key();
		if ( null === $key || 0 !== strpos( $bytes, "EBCRF1\0" ) ) {
			return null;
		}
		$body   = substr( $bytes, 7 );
		$nonce  = substr( $body, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $body, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $key );
		sodium_memzero( $key );
		return false === $plain ? null : $plain;
	}
}
