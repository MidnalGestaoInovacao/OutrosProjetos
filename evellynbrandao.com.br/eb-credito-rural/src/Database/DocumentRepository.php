<?php
/**
 * Metadados dos documentos enviados.
 *
 * @package EBCR
 */

namespace EBCR\Database;

use EBCR\Support\Uuid;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_documents.
 */
class DocumentRepository extends Db {

	/**
	 * Insere.
	 *
	 * @param array $data Dados.
	 * @return array Linha.
	 */
	public function create( array $data ) {
		$data = array_merge(
			array(
				'public_id'     => Uuid::v4(),
				'ref_key'       => '',
				'review_status' => 'pendente',
				'uploaded_at'   => self::now(),
			),
			$data
		);
		$id   = self::insert_row( 'documents', $data );
		return $this->find( $id );
	}

	/**
	 * Por ID.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public function find( $id ) {
		return self::row_by_id( 'documents', $id );
	}

	/**
	 * Por UUID (ignora excluídos).
	 *
	 * @param string $public_id UUID.
	 * @return array|null
	 */
	public function find_by_public_id( $public_id ) {
		global $wpdb;
		$public_id = Uuid::sanitize( $public_id );
		if ( ! $public_id ) {
			return null;
		}
		$t   = self::table( 'documents' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE public_id = %s AND deleted_at IS NULL", $public_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		return $row ? $row : null;
	}

	/**
	 * Atualiza.
	 *
	 * @param int   $id   ID.
	 * @param array $data Dados.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		return self::update_row( 'documents', $id, $data );
	}

	/**
	 * Documentos de uma solicitação (ativos).
	 *
	 * @param int $submission_id Solicitação.
	 * @return array
	 */
	public function for_submission( $submission_id ) {
		global $wpdb;
		$t = self::table( 'documents' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE submission_id = %d AND deleted_at IS NULL ORDER BY uploaded_at ASC", (int) $submission_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Quantidade de documentos ativos na solicitação.
	 *
	 * @param int $submission_id Solicitação.
	 * @return int
	 */
	public function count_for_submission( $submission_id ) {
		global $wpdb;
		$t = self::table( 'documents' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE submission_id = %d AND deleted_at IS NULL", (int) $submission_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Bytes usados por um usuário (cota).
	 *
	 * @param int $user_id Usuário.
	 * @return int
	 */
	public function bytes_for_user( $user_id ) {
		global $wpdb;
		$t = self::table( 'documents' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(size),0) FROM `{$t}` WHERE user_id = %d AND deleted_at IS NULL", (int) $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Duplicado por hash na mesma solicitação.
	 *
	 * @param int    $submission_id Solicitação.
	 * @param string $sha256        Hash.
	 * @return array|null
	 */
	public function find_duplicate( $submission_id, $sha256 ) {
		global $wpdb;
		$t   = self::table( 'documents' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE submission_id = %d AND sha256 = %s AND deleted_at IS NULL LIMIT 1", (int) $submission_id, $sha256 ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		return $row ? $row : null;
	}

	/**
	 * Exclusão lógica.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public function soft_delete( $id ) {
		return $this->update( $id, array( 'deleted_at' => self::now() ) );
	}

	/**
	 * Certidões que vencem até uma data (para alertas), em solicitações em andamento.
	 *
	 * @param string   $until    Data Y-m-d.
	 * @param string[] $statuses Status em andamento.
	 * @return array
	 */
	public function expiring_until( $until, array $statuses ) {
		global $wpdb;
		if ( ! $statuses ) {
			return array();
		}
		$t  = self::table( 'documents' );
		$s  = self::table( 'submissions' );
		$in = self::in_placeholders( $statuses );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT d.*, s.protocol, s.public_id AS submission_public_id FROM `{$t}` d INNER JOIN `{$s}` s ON s.id = d.submission_id WHERE d.deleted_at IS NULL AND d.expires_at IS NOT NULL AND d.expires_at <= %s AND s.status IN ($in) ORDER BY d.expires_at ASC", array_merge( array( $until ), $statuses ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders gerados.
	}

	/**
	 * Existe arquivo criptografado?
	 *
	 * @return bool
	 */
	public function has_encrypted() {
		global $wpdb;
		$t = self::table( 'documents' );
		return (bool) $wpdb->get_var( "SELECT 1 FROM `{$t}` WHERE encrypted = 1 AND deleted_at IS NULL LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Todos os documentos de um usuário (exportação LGPD/anonimização).
	 *
	 * @param int $user_id Usuário.
	 * @return array
	 */
	public function for_user( $user_id ) {
		global $wpdb;
		$t = self::table( 'documents' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE user_id = %d AND deleted_at IS NULL", (int) $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}
}
