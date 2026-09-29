<?php
/**
 * Tela somente leitura dos consentimentos de cookies, com filtros e exportação CSV.
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Database\CookieConsentRepository;
use EBCR\Domain\CookieConsent;
use EBCR\Roles\Capabilities;
use EBCR\Security\AuditLog;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Crédito Rural → Consentimentos de cookies.
 */
final class CookieConsentsView {

	const PER_PAGE = 50;

	/**
	 * Hooks (exportação via admin-post, antes de qualquer saída HTML).
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_ebcr_cookie_consents_csv', array( $this, 'export' ) );
	}

	/**
	 * Filtros da requisição.
	 *
	 * @return array
	 */
	public static function args() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- filtros de listagem (somente leitura); a exportação verifica nonce.
		$g = static function ( $k ) {
			return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : '';
		};
		// phpcs:enable
		$consent = strtolower( $g( 'consent_id' ) );
		return array(
			'consent_id' => preg_match( CookieConsent::UUID_V4, $consent ) ? $consent : '',
			'action'     => in_array( $g( 'consent_action' ), CookieConsent::ACTIONS, true ) ? $g( 'consent_action' ) : '',
			'date_from'  => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $g( 'date_from' ) ) ? $g( 'date_from' ) : '',
			'date_to'    => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $g( 'date_to' ) ) ? $g( 'date_to' ) : '',
			'per_page'   => self::PER_PAGE,
			'page'       => max( 1, (int) $g( 'paged' ) ),
		);
	}

	/**
	 * Renderiza a lista.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Capabilities::CAP_AUDIT ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'eb-credito-rural' ), 403 );
		}
		$args                  = self::args();
		list( $items, $total ) = ( new CookieConsentRepository() )->query( $args );
		View::show(
			'admin/cookie-consents',
			array(
				'items'   => $items,
				'total'   => $total,
				'args'    => $args,
				'pages'   => (int) ceil( $total / self::PER_PAGE ),
				'actions' => CookieConsent::action_labels(),
			)
		);
	}

	/**
	 * Exporta CSV (admin-post, nonce + ebcr_export).
	 *
	 * @return void
	 */
	public function export() {
		if ( ! current_user_can( Capabilities::CAP_AUDIT ) || ! current_user_can( Capabilities::CAP_EXPORT ) || ! check_admin_referer( 'ebcr_cookie_consents_csv' ) ) {
			wp_die( esc_html__( 'Sem permissão ou sessão expirada.', 'eb-credito-rural' ), 403 );
		}
		$args             = self::args();
		$args['per_page'] = 10000;
		$args['page']     = 1;
		list( $items )    = ( new CookieConsentRepository() )->query( $args );
		AuditLog::log( 'export', 'cookie_consents', '', array( 'count' => count( $items ) ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="consentimentos-cookies-' . gmdate( 'Ymd-His' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- saída.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- BOM para o Excel.
		fputcsv( $out, self::csv_header(), ';' );
		foreach ( $items as $i ) {
			fputcsv( $out, self::csv_row( $i ), ';' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- idem.
		exit;
	}

	/**
	 * Cabeçalho do CSV.
	 *
	 * @return string[]
	 */
	public static function csv_header() {
		return array( 'data_utc', 'id_consentimento', 'acao', 'necessary', 'functional', 'analytics', 'advertising', 'versao', 'caminho', 'usuario_id', 'ip_hash', 'user_agent' );
	}

	/**
	 * Linha do CSV (valores que começam com = + - @ são neutralizados contra injeção de fórmulas).
	 *
	 * @param array $i Registro.
	 * @return array
	 */
	public static function csv_row( array $i ) {
		$cats = json_decode( (string) $i['categories'], true );
		$cats = is_array( $cats ) ? $cats : array();
		$row  = array( $i['created_at'], $i['consent_id'], $i['action'] );
		foreach ( CookieConsent::CATEGORIES as $c ) {
			$row[] = ! empty( $cats[ $c ] ) ? '1' : '0';
		}
		array_push( $row, (int) $i['version'], $i['path'], $i['user_id'] ? (int) $i['user_id'] : '', $i['ip_hash'], $i['user_agent'] );
		return array_map(
			static function ( $v ) {
				$v = (string) $v;
				return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
			},
			$row
		);
	}
}
