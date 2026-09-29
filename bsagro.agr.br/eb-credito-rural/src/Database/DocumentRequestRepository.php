<?php
/**
 * Documentos solicitados pela equipe (pendências).
 *
 * @package EBCR
 */

namespace EBCR\Database;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_document_requests.
 */
class DocumentRequestRepository extends Db {

	/**
	 * Cria pedido.
	 *
	 * @param array $data Dados.
	 * @return array
	 */
	public function create( array $data ) {
		$data['requested_at'] = self::now();
		$id                   = self::insert_row( 'document_requests', $data );
		return self::row_by_id( 'document_requests', $id );
	}

	/**
	 * Por ID.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public function find( $id ) {
		return self::row_by_id( 'document_requests', $id );
	}

	/**
	 * Pedidos da solicitação.
	 *
	 * @param int  $submission_id Solicitação.
	 * @param bool $open_only     Apenas não atendidos.
	 * @return array
	 */
	public function for_submission( $submission_id, $open_only = false ) {
		global $wpdb;
		$t   = self::table( 'document_requests' );
		$sql = "SELECT * FROM `{$t}` WHERE submission_id = %d" . ( $open_only ? ' AND fulfilled_at IS NULL' : '' ) . ' ORDER BY requested_at ASC';
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, (int) $submission_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- placeholder acima.
	}

	/**
	 * Marca como atendido.
	 *
	 * @param int $id          Pedido.
	 * @param int $document_id Documento enviado.
	 * @return bool
	 */
	public function fulfill( $id, $document_id ) {
		return self::update_row(
			'document_requests',
			$id,
			array(
				'fulfilled_document_id' => (int) $document_id,
				'fulfilled_at'          => self::now(),
			)
		);
	}

	/**
	 * Reabre (documento recusado).
	 *
	 * @param int $id Pedido.
	 * @return bool
	 */
	public function reopen( $id ) {
		return self::update_row(
			'document_requests',
			$id,
			array(
				'fulfilled_document_id' => null,
				'fulfilled_at'          => null,
			)
		);
	}

	/**
	 * Pedidos abertos há mais de N dias sem lembrete recente (para o cron).
	 *
	 * @param int $days Dias.
	 * @return array
	 */
	public function stale_open( $days ) {
		global $wpdb;
		$t     = self::table( 'document_requests' );
		$limit = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE fulfilled_at IS NULL AND requested_at < %s AND (reminded_at IS NULL OR reminded_at < %s)", $limit, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Marca lembrete enviado.
	 *
	 * @param int $id Pedido.
	 * @return void
	 */
	public function mark_reminded( $id ) {
		self::update_row( 'document_requests', $id, array( 'reminded_at' => self::now() ) );
	}
}
