<?php
/**
 * Trilha de auditoria.
 *
 * @package EBCR
 */

namespace EBCR\Database;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_audit_log.
 */
class AuditLogRepository extends Db {

	/**
	 * Insere.
	 *
	 * @param array $data Dados.
	 * @return int
	 */
	public function insert( array $data ) {
		return self::insert_row( 'audit_log', $data );
	}

	/**
	 * Consulta com filtros.
	 *
	 * @param array $args actor_id, action, object_type, object_id, date_from, date_to, per_page, page.
	 * @return array [items, total]
	 */
	public function query( array $args ) {
		global $wpdb;
		$t     = self::table( 'audit_log' );
		$where = array( '1=1' );
		$vals  = array();
		if ( ! empty( $args['actor_id'] ) ) {
			$where[] = 'actor_id = %d';
			$vals[]  = (int) $args['actor_id'];
		}
		if ( ! empty( $args['action'] ) ) {
			$where[] = 'action = %s';
			$vals[]  = $args['action'];
		}
		if ( ! empty( $args['object_type'] ) ) {
			$where[] = 'object_type = %s';
			$vals[]  = $args['object_type'];
		}
		if ( ! empty( $args['object_id'] ) ) {
			$where[] = 'object_id = %s';
			$vals[]  = (string) $args['object_id'];
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[] = 'created_at >= %s';
			$vals[]  = $args['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[] = 'created_at <= %s';
			$vals[]  = $args['date_to'] . ' 23:59:59';
		}
		$per_page = max( 1, min( 500, isset( $args['per_page'] ) ? (int) $args['per_page'] : 50 ) );
		$page     = max( 1, isset( $args['page'] ) ? (int) $args['page'] : 1 );
		$w        = implode( ' AND ', $where );
		$sql      = "SELECT * FROM `{$t}` WHERE {$w} ORDER BY id DESC LIMIT %d OFFSET %d";
		$items    = (array) $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $vals, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- placeholders acima.
		$csql     = "SELECT COUNT(*) FROM `{$t}` WHERE {$w}";
		$total    = $vals ? (int) $wpdb->get_var( $wpdb->prepare( $csql, $vals ) ) : (int) $wpdb->get_var( $csql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- idem.
		return array( $items, $total );
	}

	/**
	 * Ações distintas (para filtro).
	 *
	 * @return string[]
	 */
	public function actions() {
		global $wpdb;
		$t = self::table( 'audit_log' );
		return (array) $wpdb->get_col( "SELECT DISTINCT action FROM `{$t}` ORDER BY action ASC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Remove registros mais antigos que N dias.
	 *
	 * @param int $days Dias.
	 * @return int
	 */
	public function purge_older_than( $days ) {
		global $wpdb;
		$t = self::table( 'audit_log' );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM `{$t}` WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}
}
