<?php
/**
 * Protocolo legível (EB-2026-000123).
 *
 * @package EBCR
 */

namespace EBCR\Domain;

use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Geração sequencial por ano (apenas para exibição; URLs usam UUID).
 */
final class Protocol {

	const OPTION = 'ebcr_protocol_seq';

	/**
	 * Próximo protocolo do ano corrente.
	 *
	 * @return string
	 */
	public static function next() {
		global $wpdb;
		$year = (string) wp_date( 'Y' );
		$seq  = get_option( self::OPTION, array() );
		if ( ! is_array( $seq ) ) {
			$seq = array();
		}
		// Incremento atômico simples via opção (volume baixo); em caso de conflito, o UNIQUE KEY do banco protege.
		$seq[ $year ] = isset( $seq[ $year ] ) ? (int) $seq[ $year ] + 1 : 1;
		update_option( self::OPTION, $seq, false );
		$prefix = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) Options::get( 'protocol_prefix', 'EB' ) ) );
		return sprintf( '%s-%s-%06d', $prefix ? $prefix : 'EB', $year, $seq[ $year ] );
	}
}
