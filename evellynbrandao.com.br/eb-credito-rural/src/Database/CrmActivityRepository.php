<?php
/**
 * Atividades e tarefas do CRM.
 *
 * @package EBCR
 */

namespace EBCR\Database;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_crm_activities.
 */
class CrmActivityRepository extends Db {

	/**
	 * Adiciona atividade/tarefa.
	 *
	 * @param array $data Dados.
	 * @return int
	 */
	public function add( array $data ) {
		$data['created_at'] = self::now();
		return self::insert_row( 'crm_activities', $data );
	}

	/**
	 * Atividades de um contato.
	 *
	 * @param int $contact_id Contato.
	 * @return array
	 */
	public function for_contact( $contact_id ) {
		global $wpdb;
		$t = self::table( 'crm_activities' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE contact_id = %d ORDER BY created_at DESC", (int) $contact_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Tarefas abertas (para o painel).
	 *
	 * @param int $limit Limite.
	 * @return array
	 */
	public function open_tasks( $limit = 20 ) {
		global $wpdb;
		$t = self::table( 'crm_activities' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE done_at IS NULL ORDER BY due_at IS NULL, due_at ASC LIMIT %d", (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Conclui tarefa.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public function complete( $id ) {
		return self::update_row( 'crm_activities', $id, array( 'done_at' => self::now() ) );
	}
}
