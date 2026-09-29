<?php
/**
 * Mensagens entre cliente e equipe.
 *
 * @package EBCR
 */

namespace EBCR\Database;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_messages.
 */
class MessageRepository extends Db {

	/**
	 * Adiciona mensagem.
	 *
	 * @param int    $submission_id Solicitação.
	 * @param int    $author_id     Autor.
	 * @param string $body          Texto (já sanitizado).
	 * @param string $visibility    interno|cliente.
	 * @return array
	 */
	public function add( $submission_id, $author_id, $body, $visibility = 'cliente' ) {
		$id = self::insert_row(
			'messages',
			array(
				'submission_id' => (int) $submission_id,
				'author_id'     => (int) $author_id,
				'visibility'    => 'interno' === $visibility ? 'interno' : 'cliente',
				'body'          => $body,
				'created_at'    => self::now(),
			)
		);
		return self::row_by_id( 'messages', $id );
	}

	/**
	 * Mensagens da solicitação.
	 *
	 * @param int  $submission_id  Solicitação.
	 * @param bool $client_visible Apenas visíveis ao cliente.
	 * @return array
	 */
	public function for_submission( $submission_id, $client_visible = false ) {
		global $wpdb;
		$t   = self::table( 'messages' );
		$sql = "SELECT * FROM `{$t}` WHERE submission_id = %d" . ( $client_visible ? " AND visibility = 'cliente'" : '' ) . ' ORDER BY created_at ASC, id ASC';
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, (int) $submission_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- placeholder acima.
	}

	/**
	 * Marca como lidas as mensagens de outros autores.
	 *
	 * @param int $submission_id Solicitação.
	 * @param int $reader_id     Leitor.
	 * @return void
	 */
	public function mark_read( $submission_id, $reader_id ) {
		global $wpdb;
		$t = self::table( 'messages' );
		$wpdb->query( $wpdb->prepare( "UPDATE `{$t}` SET read_at = %s WHERE submission_id = %d AND author_id <> %d AND read_at IS NULL", self::now(), (int) $submission_id, (int) $reader_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Não lidas para o cliente (mensagens da equipe visíveis).
	 *
	 * @param int $submission_id Solicitação.
	 * @param int $user_id       Cliente.
	 * @return int
	 */
	public function unread_for_client( $submission_id, $user_id ) {
		global $wpdb;
		$t = self::table( 'messages' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE submission_id = %d AND visibility = 'cliente' AND author_id <> %d AND read_at IS NULL", (int) $submission_id, (int) $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}
}
