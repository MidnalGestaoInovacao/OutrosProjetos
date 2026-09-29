<?php
/**
 * Linha do tempo de status.
 *
 * @package EBCR
 */

namespace EBCR\Database;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_status_history.
 */
class StatusHistoryRepository extends Db {

	/**
	 * Registra transição.
	 *
	 * @param int         $submission_id    Solicitação.
	 * @param string|null $from             Origem.
	 * @param string      $to               Destino.
	 * @param int|null    $changed_by       Autor.
	 * @param string      $comment_internal Comentário interno.
	 * @param string      $comment_client   Comentário ao cliente.
	 * @return int
	 */
	public function add( $submission_id, $from, $to, $changed_by, $comment_internal = '', $comment_client = '' ) {
		return self::insert_row(
			'status_history',
			array(
				'submission_id'    => (int) $submission_id,
				'from_status'      => $from,
				'to_status'        => $to,
				'changed_by'       => $changed_by ? (int) $changed_by : null,
				'comment_internal' => $comment_internal,
				'comment_client'   => $comment_client,
				'created_at'       => self::now(),
			)
		);
	}

	/**
	 * Histórico da solicitação.
	 *
	 * @param int $submission_id Solicitação.
	 * @return array
	 */
	public function for_submission( $submission_id ) {
		global $wpdb;
		$t = self::table( 'status_history' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE submission_id = %d ORDER BY created_at ASC, id ASC", (int) $submission_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}
}
