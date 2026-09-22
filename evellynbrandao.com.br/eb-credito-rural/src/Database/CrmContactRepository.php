<?php
/**
 * Ficha CRM do cliente.
 *
 * @package EBCR
 */

namespace EBCR\Database;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_crm_contacts.
 */
class CrmContactRepository extends Db {

	/**
	 * Ficha por usuário (cria se não existir).
	 *
	 * @param int   $user_id  Usuário.
	 * @param array $defaults Dados iniciais.
	 * @return array
	 */
	public function get_or_create( $user_id, array $defaults = array() ) {
		global $wpdb;
		$t   = self::table( 'crm_contacts' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE user_id = %d", (int) $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		if ( $row ) {
			return $row;
		}
		$now = self::now();
		$id  = self::insert_row(
			'crm_contacts',
			array_merge(
				array(
					'user_id'    => (int) $user_id,
					'stage'      => 'novo',
					'created_at' => $now,
					'updated_at' => $now,
				),
				$defaults
			)
		);
		return self::row_by_id( 'crm_contacts', $id );
	}

	/**
	 * Atualiza.
	 *
	 * @param int   $id   ID.
	 * @param array $data Dados.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		$data['updated_at'] = self::now();
		return self::update_row( 'crm_contacts', $id, $data );
	}
}
