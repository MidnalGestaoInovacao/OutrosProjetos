<?php
/**
 * Imóveis rurais de uma solicitação.
 *
 * @package EBCR
 */

namespace EBCR\Database;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_properties.
 */
class PropertyRepository extends Db {

	/**
	 * Campos preenchidos pelo cliente (comparados para registrar updated_at).
	 */
	const CLIENT_FIELDS = array( 'name', 'city', 'uf', 'registration_number', 'registry_office', 'total_area', 'usable_area', 'car_code', 'ccir', 'nirf', 'sigef', 'tenure', 'lease_end' );

	/**
	 * Algum campo mudou? Áreas, valores e IDs são comparados como números (2 casas); vazio e NULL equivalem.
	 *
	 * @param array    $old    Linha atual.
	 * @param array    $new    Valores novos.
	 * @param string[] $fields Campos.
	 * @return bool
	 */
	public static function changed( array $old, array $new, array $fields ) {
		$numeric = array( 'total_area', 'usable_area', 'declared_value', 'property_id' );
		foreach ( $fields as $f ) {
			$a = isset( $old[ $f ] ) ? (string) $old[ $f ] : '';
			$b = isset( $new[ $f ] ) ? (string) $new[ $f ] : '';
			if ( in_array( $f, $numeric, true ) && is_numeric( $a ) && is_numeric( $b ) ) {
				if ( round( (float) $a, 2 ) !== round( (float) $b, 2 ) ) {
					return true;
				}
				continue;
			}
			if ( trim( $a ) !== trim( $b ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Substitui todos os imóveis da solicitação, preservando IDs quando informados.
	 *
	 * @param int   $submission_id Solicitação.
	 * @param array $items         Lista de imóveis validados (com 'id' opcional).
	 * @return array IDs finais na ordem.
	 */
	public function replace_all( $submission_id, array $items ) {
		global $wpdb;
		$current  = array();
		foreach ( $this->for_submission( $submission_id ) as $r ) {
			$current[ (int) $r['id'] ] = $r;
		}
		$existing = array_keys( $current );
		$keep     = array();
		$ids      = array();
		foreach ( array_values( $items ) as $i => $item ) {
			$row = array(
				'submission_id'       => (int) $submission_id,
				'name'                => $item['name'],
				'city'                => $item['city'],
				'uf'                  => $item['uf'],
				'registration_number' => $item['registration_number'],
				'registry_office'     => $item['registry_office'],
				'total_area'          => $item['total_area'],
				'usable_area'         => $item['usable_area'],
				'car_code'            => $item['car_code'],
				'ccir'                => $item['ccir'],
				'nirf'                => $item['nirf'],
				'sigef'               => $item['sigef'],
				'tenure'              => $item['tenure'],
				'lease_end'           => $item['lease_end'] ? $item['lease_end'] : null,
				'sort_order'          => $i,
			);
			$id  = isset( $item['id'] ) ? (int) $item['id'] : 0;
			if ( $id && isset( $current[ $id ] ) ) {
				if ( self::changed( $current[ $id ], $row, self::CLIENT_FIELDS ) ) {
					$row['updated_at'] = self::now();
				}
				self::update_row( 'properties', $id, $row );
			} else {
				$row['created_at'] = self::now();
				$id                = self::insert_row( 'properties', $row );
			}
			$keep[] = $id;
			$ids[]  = $id;
		}
		foreach ( $existing as $old ) {
			if ( ! in_array( (int) $old, $keep, true ) ) {
				$wpdb->delete( self::table( 'properties' ), array( 'id' => (int) $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabela própria.
			}
		}
		return $ids;
	}

	/**
	 * Imóveis da solicitação.
	 *
	 * @param int $submission_id Solicitação.
	 * @return array
	 */
	public function for_submission( $submission_id ) {
		global $wpdb;
		$t = self::table( 'properties' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE submission_id = %d ORDER BY sort_order ASC, id ASC", (int) $submission_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Remove todos (anonimização).
	 *
	 * @param int $submission_id Solicitação.
	 * @return void
	 */
	public function delete_all( $submission_id ) {
		global $wpdb;
		$wpdb->delete( self::table( 'properties' ), array( 'submission_id' => (int) $submission_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabela própria.
	}
}
