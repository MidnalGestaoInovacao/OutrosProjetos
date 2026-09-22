<?php
/**
 * Checklist de conferência do analista.
 *
 * @package EBCR
 */

namespace EBCR\Database;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_checks.
 */
class CheckRepository extends Db {

	/**
	 * Itens padrão do checklist.
	 *
	 * @return array chave => rótulo
	 */
	public static function defaults() {
		return array(
			'area'           => __( 'Área: matrícula × CAR × SIGEF × imagem de satélite', 'eb-credito-rural' ),
			'produtividade'  => __( 'Produtividade declarada × média municipal (Conab/IBGE)', 'eb-credito-rural' ),
			'faturamento'    => __( 'Faturamento declarado × IRPF/LCDPR × notas', 'eb-credito-rural' ),
			'dividas'        => __( 'Dívidas declaradas × SCR × ônus na matrícula', 'eb-credito-rural' ),
			'socioambiental' => __( 'Consultas socioambientais (IBAMA/ICMBio, MTE, TI/UC/quilombolas, desmatamento)', 'eb-credito-rural' ),
			'certidoes'      => __( 'Certidões dentro da validade', 'eb-credito-rural' ),
			'outorga'        => __( 'Outorga do cônjuge', 'eb-credito-rural' ),
			'pep_sancoes'    => __( 'PEP / sanções', 'eb-credito-rural' ),
			'ltv'            => __( 'LTV por garantia (valor avaliado × solicitado × haircut)', 'eb-credito-rural' ),
		);
	}

	/**
	 * Salva resultado.
	 *
	 * @param int    $submission_id Solicitação.
	 * @param string $key           Item.
	 * @param string $result        ok|alerta|reprovado|na.
	 * @param string $note          Observação.
	 * @param int    $user_id       Quem conferiu.
	 * @return void
	 */
	public function save( $submission_id, $key, $result, $note, $user_id ) {
		global $wpdb;
		$t        = self::table( 'checks' );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t}` WHERE submission_id = %d AND check_key = %s", (int) $submission_id, $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		$row      = array(
			'result'     => in_array( $result, array( 'ok', 'alerta', 'reprovado', 'na' ), true ) ? $result : 'na',
			'note'       => $note,
			'checked_by' => (int) $user_id,
			'checked_at' => self::now(),
		);
		if ( $existing ) {
			self::update_row( 'checks', (int) $existing, $row );
		} else {
			$row['submission_id'] = (int) $submission_id;
			$row['check_key']     = $key;
			self::insert_row( 'checks', $row );
		}
	}

	/**
	 * Resultados da solicitação.
	 *
	 * @param int $submission_id Solicitação.
	 * @return array chave => linha
	 */
	public function for_submission( $submission_id ) {
		global $wpdb;
		$t    = self::table( 'checks' );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE submission_id = %d", (int) $submission_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		$out  = array();
		foreach ( $rows as $r ) {
			$out[ $r['check_key'] ] = $r;
		}
		return $out;
	}
}
