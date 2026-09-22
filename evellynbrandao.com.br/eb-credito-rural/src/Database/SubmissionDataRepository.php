<?php
/**
 * Dados do formulário por seção (JSON, opcionalmente criptografado).
 *
 * @package EBCR
 */

namespace EBCR\Database;

use EBCR\Security\Crypto;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_submission_data.
 */
class SubmissionDataRepository extends Db {

	/**
	 * Seções com dados sensíveis (criptografadas quando a opção está ligada).
	 *
	 * @var string[]
	 */
	const SENSITIVE = array( 'identificacao', 'financeiro' );

	/**
	 * Salva (insere ou atualiza) uma seção.
	 *
	 * @param int    $submission_id Solicitação.
	 * @param string $section       Seção.
	 * @param array  $data          Dados validados.
	 * @return void
	 */
	public function save( $submission_id, $section, array $data ) {
		global $wpdb;
		$section = sanitize_key( $section );
		$json    = wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
		$enc     = 0;
		if ( Options::bool( 'encrypt_fields' ) && in_array( $section, self::SENSITIVE, true ) && Crypto::is_available() ) {
			$json = Crypto::encrypt( $json );
			$enc  = 1;
		}
		$t        = self::table( 'submission_data' );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t}` WHERE submission_id = %d AND section = %s", (int) $submission_id, $section ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		$row      = array(
			'data'       => $json,
			'encrypted'  => $enc,
			'updated_at' => self::now(),
		);
		if ( $existing ) {
			self::update_row( 'submission_data', (int) $existing, $row );
		} else {
			$row['submission_id'] = (int) $submission_id;
			$row['section']       = $section;
			self::insert_row( 'submission_data', $row );
		}
	}

	/**
	 * Lê uma seção.
	 *
	 * @param int    $submission_id Solicitação.
	 * @param string $section       Seção.
	 * @return array
	 */
	public function get( $submission_id, $section ) {
		global $wpdb;
		$t   = self::table( 'submission_data' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT data, encrypted FROM `{$t}` WHERE submission_id = %d AND section = %s", (int) $submission_id, sanitize_key( $section ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		return $row ? $this->decode( $row ) : array();
	}

	/**
	 * Todas as seções.
	 *
	 * @param int $submission_id Solicitação.
	 * @return array seção => dados
	 */
	public function all( $submission_id ) {
		global $wpdb;
		$t    = self::table( 'submission_data' );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT section, data, encrypted FROM `{$t}` WHERE submission_id = %d", (int) $submission_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		$out  = array();
		foreach ( $rows as $r ) {
			$out[ $r['section'] ] = $this->decode( $r );
		}
		return $out;
	}

	/**
	 * Remove todas as seções (anonimização).
	 *
	 * @param int $submission_id Solicitação.
	 * @return void
	 */
	public function delete_all( $submission_id ) {
		global $wpdb;
		$wpdb->delete( self::table( 'submission_data' ), array( 'submission_id' => (int) $submission_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabela própria.
	}

	/**
	 * Existe alguma seção criptografada? (impede desligar a criptografia sem migração)
	 *
	 * @return bool
	 */
	public function has_encrypted() {
		global $wpdb;
		$t = self::table( 'submission_data' );
		return (bool) $wpdb->get_var( "SELECT 1 FROM `{$t}` WHERE encrypted = 1 LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Decodifica linha.
	 *
	 * @param array $row Linha.
	 * @return array
	 */
	private function decode( array $row ) {
		$json = (string) $row['data'];
		if ( ! empty( $row['encrypted'] ) ) {
			$json = Crypto::decrypt( $json );
			if ( null === $json ) {
				return array( '_error' => 'unreadable' );
			}
		}
		$data = json_decode( $json, true );
		return is_array( $data ) ? $data : array();
	}
}
