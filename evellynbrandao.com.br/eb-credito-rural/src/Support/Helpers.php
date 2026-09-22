<?php
/**
 * Funções utilitárias.
 *
 * @package EBCR
 */

namespace EBCR\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Utilitários estáticos.
 */
final class Helpers {

	/**
	 * Data/hora atual em UTC no formato MySQL.
	 *
	 * @return string
	 */
	public static function now() {
		return current_time( 'mysql', true );
	}

	/**
	 * Formata data/hora (UTC do banco) no fuso do site.
	 *
	 * @param string|null $mysql_utc Data MySQL em UTC.
	 * @param string      $format    Formato PHP (padrão: formatos do site).
	 * @return string
	 */
	public static function date( $mysql_utc, $format = '' ) {
		if ( empty( $mysql_utc ) || '0000-00-00 00:00:00' === $mysql_utc ) {
			return '—';
		}
		$format = $format ? $format : get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		return wp_date( $format, strtotime( $mysql_utc . ' UTC' ) );
	}

	/**
	 * Formata valor monetário em R$.
	 *
	 * @param mixed $value Valor.
	 * @return string
	 */
	public static function money( $value ) {
		if ( null === $value || '' === $value ) {
			return '—';
		}
		return 'R$ ' . number_format( (float) $value, 2, ',', '.' );
	}

	/**
	 * Converte valor monetário/decimal em formato brasileiro para float.
	 *
	 * @param mixed $value Entrada.
	 * @return float|null
	 */
	public static function parse_decimal( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (float) $value;
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		$value = preg_replace( '/[^\d,.\-]/', '', $value );
		if ( false !== strpos( $value, ',' ) ) {
			$value = str_replace( '.', '', $value );
			$value = str_replace( ',', '.', $value );
		}
		return is_numeric( $value ) ? (float) $value : null;
	}

	/**
	 * Somente dígitos.
	 *
	 * @param mixed $value Entrada.
	 * @return string
	 */
	public static function digits( $value ) {
		return preg_replace( '/\D+/', '', (string) $value );
	}

	/**
	 * Mascara CPF/CNPJ para exibição (mantém só o final).
	 *
	 * @param string $doc Documento.
	 * @return string
	 */
	public static function mask_document( $doc ) {
		$d = self::digits( $doc );
		if ( 11 === strlen( $d ) ) {
			return '***.***.' . substr( $d, 6, 3 ) . '-' . substr( $d, 9, 2 );
		}
		if ( 14 === strlen( $d ) ) {
			return '**.***.***/' . substr( $d, 8, 4 ) . '-' . substr( $d, 12, 2 );
		}
		return $d ? str_repeat( '*', max( 0, strlen( $d ) - 4 ) ) . substr( $d, -4 ) : '—';
	}

	/**
	 * Formata CPF ou CNPJ.
	 *
	 * @param string $doc Documento.
	 * @return string
	 */
	public static function format_document( $doc ) {
		$d = self::digits( $doc );
		if ( 11 === strlen( $d ) ) {
			return substr( $d, 0, 3 ) . '.' . substr( $d, 3, 3 ) . '.' . substr( $d, 6, 3 ) . '-' . substr( $d, 9, 2 );
		}
		if ( 14 === strlen( $d ) ) {
			return substr( $d, 0, 2 ) . '.' . substr( $d, 2, 3 ) . '.' . substr( $d, 5, 3 ) . '/' . substr( $d, 8, 4 ) . '-' . substr( $d, 12, 2 );
		}
		return $doc;
	}

	/**
	 * UFs oficiais.
	 *
	 * @return array
	 */
	public static function ufs() {
		return array(
			'AC' => 'Acre',
			'AL' => 'Alagoas',
			'AP' => 'Amapá',
			'AM' => 'Amazonas',
			'BA' => 'Bahia',
			'CE' => 'Ceará',
			'DF' => 'Distrito Federal',
			'ES' => 'Espírito Santo',
			'GO' => 'Goiás',
			'MA' => 'Maranhão',
			'MT' => 'Mato Grosso',
			'MS' => 'Mato Grosso do Sul',
			'MG' => 'Minas Gerais',
			'PA' => 'Pará',
			'PB' => 'Paraíba',
			'PR' => 'Paraná',
			'PE' => 'Pernambuco',
			'PI' => 'Piauí',
			'RJ' => 'Rio de Janeiro',
			'RN' => 'Rio Grande do Norte',
			'RS' => 'Rio Grande do Sul',
			'RO' => 'Rondônia',
			'RR' => 'Roraima',
			'SC' => 'Santa Catarina',
			'SP' => 'São Paulo',
			'SE' => 'Sergipe',
			'TO' => 'Tocantins',
		);
	}

	/**
	 * URL do portal do cliente (página configurada) com parâmetros.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function portal_url( array $args = array() ) {
		$page_id = Options::int( 'portal_page_id' );
		$base    = $page_id ? get_permalink( $page_id ) : home_url( '/' );
		return $args ? add_query_arg( array_map( 'rawurlencode', $args ), $base ) : $base;
	}

	/**
	 * Gera uma string aleatória hexadecimal segura.
	 *
	 * @param int $bytes Bytes de entropia.
	 * @return string
	 */
	public static function random_hex( $bytes = 16 ) {
		return bin2hex( random_bytes( $bytes ) );
	}

	/**
	 * Verifica se o usuário pertence à equipe (analista, gestor ou admin).
	 *
	 * @param int|null $user_id ID (padrão: atual).
	 * @return bool
	 */
	public static function is_team( $user_id = null ) {
		return $user_id ? user_can( $user_id, 'ebcr_view_submissions' ) : current_user_can( 'ebcr_view_submissions' );
	}

	/**
	 * Nome de exibição de um usuário.
	 *
	 * @param int $user_id ID.
	 * @return string
	 */
	public static function user_name( $user_id ) {
		$u = $user_id ? get_userdata( (int) $user_id ) : false;
		return $u ? $u->display_name : __( 'Sistema', 'eb-credito-rural' );
	}

	/**
	 * Tamanho legível.
	 *
	 * @param int $bytes Bytes.
	 * @return string
	 */
	public static function size( $bytes ) {
		return size_format( (int) $bytes, 1 );
	}
}
