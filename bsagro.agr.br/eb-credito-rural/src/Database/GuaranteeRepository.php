<?php
/**
 * Garantias oferecidas.
 *
 * @package EBCR
 */

namespace EBCR\Database;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_guarantees.
 */
class GuaranteeRepository extends Db {

	/**
	 * Campos preenchidos pelo cliente. Os demais (valor avaliado, LTV, formalização, extra) são da equipe e são
	 * preservados quando a garantia já existe.
	 */
	const CLIENT_FIELDS = array( 'type', 'description', 'property_id', 'declared_value' );

	/**
	 * Substitui as garantias da solicitação. Linhas com "id" de uma garantia desta solicitação são atualizadas (sem
	 * perder a data de inclusão nem os campos da equipe); as demais são inseridas; as ausentes são removidas.
	 *
	 * @param int   $submission_id Solicitação.
	 * @param array $items         Garantias.
	 * @return int[] IDs na ordem recebida.
	 */
	public function replace_all( $submission_id, array $items ) {
		global $wpdb;
		$current = array();
		foreach ( $this->for_submission( $submission_id ) as $r ) {
			$current[ (int) $r['id'] ] = $r;
		}
		$keep = array();
		foreach ( array_values( $items ) as $i => $item ) {
			$id     = isset( $item['id'] ) ? (int) $item['id'] : 0;
			$client = array(
				'type'           => $item['type'],
				'description'    => $item['description'],
				'property_id'    => ! empty( $item['property_id'] ) ? (int) $item['property_id'] : null,
				'declared_value' => $item['declared_value'],
				'sort_order'     => $i,
			);
			if ( $id && isset( $current[ $id ] ) ) {
				if ( PropertyRepository::changed( $current[ $id ], $client, self::CLIENT_FIELDS ) ) {
					$client['updated_at'] = self::now();
				}
				self::update_row( 'guarantees', $id, $client );
				$keep[] = $id;
				continue;
			}
			$keep[] = self::insert_row(
				'guarantees',
				array_merge(
					$client,
					array(
						'submission_id'        => (int) $submission_id,
						'appraised_value'      => isset( $item['appraised_value'] ) ? $item['appraised_value'] : null,
						'ltv'                  => isset( $item['ltv'] ) ? $item['ltv'] : null,
						'formalization_status' => isset( $item['formalization_status'] ) ? $item['formalization_status'] : 'pendente',
						'extra'                => wp_json_encode( isset( $item['extra'] ) ? $item['extra'] : array(), JSON_UNESCAPED_UNICODE ),
						'created_at'           => self::now(),
					)
				)
			);
		}
		foreach ( array_keys( $current ) as $old ) {
			if ( ! in_array( $old, $keep, true ) ) {
				$wpdb->delete( self::table( 'guarantees' ), array( 'id' => $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabela própria.
			}
		}
		return $keep;
	}

	/**
	 * Garantias da solicitação.
	 *
	 * @param int $submission_id Solicitação.
	 * @return array
	 */
	public function for_submission( $submission_id ) {
		global $wpdb;
		$t    = self::table( 'guarantees' );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE submission_id = %d ORDER BY sort_order ASC, id ASC", (int) $submission_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		foreach ( $rows as &$r ) {
			$r['extra'] = json_decode( (string) $r['extra'], true );
			if ( ! is_array( $r['extra'] ) ) {
				$r['extra'] = array();
			}
		}
		return $rows;
	}

	/**
	 * Atualiza avaliação/LTV/formalização (equipe).
	 *
	 * @param int   $id   ID.
	 * @param array $data Campos.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		return self::update_row( 'guarantees', $id, $data );
	}

	/**
	 * Remove todas.
	 *
	 * @param int $submission_id Solicitação.
	 * @return void
	 */
	public function delete_all( $submission_id ) {
		global $wpdb;
		$wpdb->delete( self::table( 'guarantees' ), array( 'submission_id' => (int) $submission_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabela própria.
	}
}
