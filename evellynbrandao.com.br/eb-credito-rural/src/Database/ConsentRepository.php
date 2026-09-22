<?php
/**
 * Aceites registrados.
 *
 * @package EBCR
 */

namespace EBCR\Database;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_consents.
 */
class ConsentRepository extends Db {

	/**
	 * Insere.
	 *
	 * @param array $data Dados.
	 * @return int
	 */
	public function insert( array $data ) {
		return self::insert_row( 'consents', $data );
	}

	/**
	 * Última versão aceita por política.
	 *
	 * @param int $user_id Usuário.
	 * @return array chave => versão
	 */
	public function latest_versions( $user_id ) {
		global $wpdb;
		$t    = self::table( 'consents' );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT policy_key, policy_version FROM `{$t}` WHERE user_id = %d ORDER BY accepted_at ASC, id ASC", (int) $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		$out  = array();
		foreach ( $rows as $r ) {
			$out[ $r['policy_key'] ] = $r['policy_version'];
		}
		return $out;
	}

	/**
	 * Aceites de um usuário.
	 *
	 * @param int $user_id Usuário.
	 * @return array
	 */
	public function for_user( $user_id ) {
		global $wpdb;
		$t = self::table( 'consents' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE user_id = %d ORDER BY accepted_at ASC", (int) $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Aceites de uma solicitação.
	 *
	 * @param int $submission_id Solicitação.
	 * @return array
	 */
	public function for_submission( $submission_id ) {
		global $wpdb;
		$t = self::table( 'consents' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE submission_id = %d ORDER BY accepted_at ASC", (int) $submission_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}
}
