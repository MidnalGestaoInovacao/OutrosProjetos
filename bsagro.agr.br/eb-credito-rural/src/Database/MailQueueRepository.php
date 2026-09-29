<?php
/**
 * Fila de e-mails.
 *
 * @package EBCR
 */

namespace EBCR\Database;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_mail_queue.
 */
class MailQueueRepository extends Db {

	/**
	 * Enfileira.
	 *
	 * @param array $data Dados.
	 * @return int
	 */
	public function enqueue( array $data ) {
		$now = self::now();
		return self::insert_row(
			'mail_queue',
			array_merge(
				array(
					'status'       => 'pending',
					'attempts'     => 0,
					'scheduled_at' => $now,
					'created_at'   => $now,
				),
				$data
			)
		);
	}

	/**
	 * Próximos pendentes.
	 *
	 * @param int $limit Limite.
	 * @return array
	 */
	public function due( $limit = 25 ) {
		global $wpdb;
		$t = self::table( 'mail_queue' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE status = 'pending' AND scheduled_at <= %s ORDER BY id ASC LIMIT %d", self::now(), (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Atualiza item.
	 *
	 * @param int   $id   ID.
	 * @param array $data Dados.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		return self::update_row( 'mail_queue', $id, $data );
	}

	/**
	 * Estatísticas.
	 *
	 * @return array
	 */
	public function stats() {
		global $wpdb;
		$t    = self::table( 'mail_queue' );
		$rows = (array) $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM `{$t}` GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		$out  = array(
			'pending' => 0,
			'sent'    => 0,
			'failed'  => 0,
		);
		foreach ( $rows as $r ) {
			$out[ $r['status'] ] = (int) $r['n'];
		}
		return $out;
	}

	/**
	 * Falhas recentes.
	 *
	 * @param int $limit Limite.
	 * @return array
	 */
	public function failures( $limit = 20 ) {
		global $wpdb;
		$t = self::table( 'mail_queue' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, recipient, subject, attempts, last_error, created_at FROM `{$t}` WHERE status = 'failed' ORDER BY id DESC LIMIT %d", (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Reenfileira falhas.
	 *
	 * @return int
	 */
	public function retry_failed() {
		global $wpdb;
		$t = self::table( 'mail_queue' );
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE `{$t}` SET status = 'pending', attempts = 0, scheduled_at = %s WHERE status = 'failed'", self::now() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Limpa enviados antigos.
	 *
	 * @param int $days Dias.
	 * @return int
	 */
	public function purge_sent( $days = 30 ) {
		global $wpdb;
		$t = self::table( 'mail_queue' );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM `{$t}` WHERE status = 'sent' AND sent_at < %s", gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}
}
