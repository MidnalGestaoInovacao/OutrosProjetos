<?php
/**
 * Tela do log de auditoria.
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Database\AuditLogRepository;
use EBCR\Roles\Capabilities;
use EBCR\Security\AuditLog;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Filtros e exportação CSV.
 */
final class AuditLogView {

	/**
	 * Renderiza.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Capabilities::CAP_AUDIT ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'eb-credito-rural' ), 403 );
		}
		$g    = static function ( $k ) {
			return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtros de listagem (somente leitura).
		};
		$args = array(
			'actor_id'    => (int) $g( 'actor_id' ),
			'action'      => sanitize_key( $g( 'action' ) ),
			'object_type' => sanitize_key( $g( 'object_type' ) ),
			'object_id'   => $g( 'object_id' ),
			'date_from'   => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $g( 'date_from' ) ) ? $g( 'date_from' ) : '',
			'date_to'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $g( 'date_to' ) ) ? $g( 'date_to' ) : '',
			'per_page'    => 50,
			'page'        => max( 1, (int) $g( 'paged' ) ),
		);
		$repo = new AuditLogRepository();
		if ( 'csv' === $g( 'export' ) && current_user_can( Capabilities::CAP_EXPORT ) && check_admin_referer( 'ebcr_audit_export' ) ) {
			$args['per_page'] = 5000;
			list( $items )    = $repo->query( $args );
			AuditLog::log( 'export', 'audit_log', '', array( 'count' => count( $items ) ) );
			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="auditoria-' . gmdate( 'Ymd-His' ) . '.csv"' );
			$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- saída.
			fputcsv( $out, array( 'data', 'usuario', 'acao', 'objeto', 'id', 'ip', 'meta' ), ';' );
			foreach ( $items as $i ) {
				fputcsv( $out, array( $i['created_at'], $i['actor_id'], $i['action'], $i['object_type'], $i['object_id'], $i['ip'], $i['meta'] ), ';' );
			}
			fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- idem.
			exit;
		}
		list( $items, $total ) = $repo->query( $args );
		View::show(
			'admin/audit',
			array(
				'items'   => $items,
				'total'   => $total,
				'args'    => $args,
				'actions' => $repo->actions(),
				'labels'  => AuditLog::labels(),
				'pages'   => (int) ceil( $total / 50 ),
			)
		);
	}
}
