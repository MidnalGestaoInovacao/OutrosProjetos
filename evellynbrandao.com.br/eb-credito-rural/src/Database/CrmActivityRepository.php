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
	 * Por ID.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public function find( $id ) {
		return self::row_by_id( 'crm_activities', $id );
	}

	/**
	 * Atividades de um contato (mais recentes primeiro).
	 *
	 * @param int $contact_id Contato.
	 * @return array
	 */
	public function for_contact( $contact_id ) {
		global $wpdb;
		$t = self::table( 'crm_activities' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE contact_id = %d ORDER BY created_at DESC, id DESC", (int) $contact_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Tarefas abertas de um contato (vencimento mais próximo primeiro).
	 *
	 * @param int $contact_id Contato.
	 * @return array
	 */
	public function open_tasks_for_contact( $contact_id ) {
		global $wpdb;
		$t = self::table( 'crm_activities' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE contact_id = %d AND type = 'tarefa' AND done_at IS NULL ORDER BY due_at IS NULL, due_at ASC, id ASC", (int) $contact_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
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
	 * Tarefas não concluídas com vencimento até a data/hora informada (UTC), com responsável e usuário da ficha.
	 *
	 * @param string $until_utc Data/hora limite em UTC (formato MySQL).
	 * @return array Linhas com as colunas da atividade + owner_id e user_id do contato.
	 */
	public function due_tasks( $until_utc ) {
		global $wpdb;
		$t  = self::table( 'crm_activities' );
		$tc = self::table( 'crm_contacts' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT a.*, c.owner_id, c.user_id FROM `{$t}` a INNER JOIN `{$tc}` c ON c.id = a.contact_id WHERE a.type = 'tarefa' AND a.done_at IS NULL AND a.due_at IS NOT NULL AND a.due_at <= %s ORDER BY a.due_at ASC, a.id ASC", $until_utc ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nomes de tabela internos.
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

	/**
	 * Remove todas as atividades de um contato (limpezas/testes).
	 *
	 * @param int $contact_id Contato.
	 * @return int Linhas removidas.
	 */
	public function delete_for_contact( $contact_id ) {
		global $wpdb;
		$n = $wpdb->delete( self::table( 'crm_activities' ), array( 'contact_id' => (int) $contact_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabela própria.
		return false === $n ? 0 : (int) $n;
	}
}
