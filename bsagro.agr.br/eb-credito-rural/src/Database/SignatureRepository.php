<?php
/**
 * Assinaturas eletrônicas (evidências de cada assinatura feita no portal).
 *
 * @package EBCR
 */

namespace EBCR\Database;

use EBCR\Support\Uuid;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_signatures: uma linha por tentativa de assinatura (pending → signed | cancelled).
 */
class SignatureRepository extends Db {

	const STATUS_PENDING   = 'pending';
	const STATUS_SIGNED    = 'signed';
	const STATUS_CANCELLED = 'cancelled';

	/**
	 * Insere.
	 *
	 * @param array $data Dados.
	 * @return array Linha.
	 */
	public function create( array $data ) {
		$data = array_merge(
			array(
				'public_id'  => Uuid::v4(),
				'method'     => 'email_otp',
				'status'     => self::STATUS_PENDING,
				'created_at' => self::now(),
			),
			$data
		);
		$id   = self::insert_row( 'signatures', $data );
		return $this->find( $id );
	}

	/**
	 * Por ID.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public function find( $id ) {
		return self::row_by_id( 'signatures', $id );
	}

	/**
	 * Por UUID.
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
		$t   = self::table( 'signatures' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE public_id = %s", $public_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
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
		return self::update_row( 'signatures', $id, $data );
	}

	/**
	 * Assinatura concluída vinculada a um documento.
	 *
	 * @param int $document_id Documento.
	 * @return array|null
	 */
	public function find_by_document( $document_id ) {
		global $wpdb;
		$t   = self::table( 'signatures' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE document_id = %d AND status = %s ORDER BY signed_at DESC LIMIT 1", (int) $document_id, self::STATUS_SIGNED ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		return $row ? $row : null;
	}

	/**
	 * Assinaturas de uma solicitação (opcionalmente só as concluídas), mais recentes primeiro.
	 *
	 * @param int  $submission_id Solicitação.
	 * @param bool $signed_only   Apenas status signed.
	 * @return array
	 */
	public function for_submission( $submission_id, $signed_only = false ) {
		global $wpdb;
		$t = self::table( 'signatures' );
		if ( $signed_only ) {
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE submission_id = %d AND status = %s ORDER BY created_at DESC, id DESC", (int) $submission_id, self::STATUS_SIGNED ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		}
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE submission_id = %d ORDER BY created_at DESC, id DESC", (int) $submission_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Assinatura pendente (aguardando código) do usuário para um tipo de documento na solicitação.
	 *
	 * @param int    $submission_id Solicitação.
	 * @param int    $user_id       Usuário.
	 * @param string $doc_type      Tipo.
	 * @return array|null
	 */
	public function pending_for( $submission_id, $user_id, $doc_type ) {
		global $wpdb;
		$t   = self::table( 'signatures' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE submission_id = %d AND user_id = %d AND doc_type = %s AND status = %s ORDER BY id DESC LIMIT 1", (int) $submission_id, (int) $user_id, sanitize_key( $doc_type ), self::STATUS_PENDING ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		return $row ? $row : null;
	}

	/**
	 * Cancela todas as assinaturas pendentes do usuário para um tipo na solicitação.
	 *
	 * @param int    $submission_id Solicitação.
	 * @param int    $user_id       Usuário.
	 * @param string $doc_type      Tipo.
	 * @return int Linhas afetadas.
	 */
	public function cancel_pending( $submission_id, $user_id, $doc_type ) {
		global $wpdb;
		$r = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabela própria.
			self::table( 'signatures' ),
			array( 'status' => self::STATUS_CANCELLED ),
			array(
				'submission_id' => (int) $submission_id,
				'user_id'       => (int) $user_id,
				'doc_type'      => sanitize_key( $doc_type ),
				'status'        => self::STATUS_PENDING,
			)
		);
		return false === $r ? 0 : (int) $r;
	}

	/**
	 * Todas as assinaturas de um usuário (exportação LGPD).
	 *
	 * @param int $user_id Usuário.
	 * @return array
	 */
	public function for_user( $user_id ) {
		global $wpdb;
		$t = self::table( 'signatures' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE user_id = %d ORDER BY created_at DESC", (int) $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}
}
