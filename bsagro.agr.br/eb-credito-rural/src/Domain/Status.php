<?php
/**
 * Fluxo de status das solicitações.
 *
 * @package EBCR
 */

namespace EBCR\Domain;

use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Status padrão, transições e metadados (editáveis nas configurações).
 */
final class Status {

	const DRAFT         = 'rascunho';
	const SUBMITTED     = 'enviada';
	const PRE_ANALYSIS  = 'pre_analise';
	const PENDING_DOCS  = 'pendencia_documental';
	const CREDIT        = 'analise_credito';
	const COMMITTEE     = 'comite';
	const APPROVED      = 'aprovada';
	const FORMALIZATION = 'formalizacao';
	const DONE          = 'concluida';
	const REJECTED      = 'reprovada';
	const CANCELLED     = 'cancelada';

	/**
	 * Definição padrão.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			self::DRAFT         => array(
				'label'                     => 'Rascunho',
				'color'                     => '#6b7280',
				'client_visible'            => true,
				'client_text'               => 'Sua solicitação ainda não foi enviada. Complete as etapas e envie para análise.',
				'transitions'               => array( self::SUBMITTED, self::CANCELLED ),
				'final'                     => false,
				'requires_final_cap'        => false,
				'requires_internal_comment' => false,
			),
			self::SUBMITTED     => array(
				'label'                     => 'Enviada',
				'color'                     => '#2563eb',
				'client_visible'            => true,
				'client_text'               => 'Recebemos sua solicitação. Em breve a equipe iniciará a pré-análise.',
				'transitions'               => array( self::PRE_ANALYSIS, self::CANCELLED ),
				'final'                     => false,
				'requires_final_cap'        => false,
				'requires_internal_comment' => false,
			),
			self::PRE_ANALYSIS  => array(
				'label'                     => 'Pré-análise',
				'color'                     => '#7c3aed',
				'client_visible'            => true,
				'client_text'               => 'Sua solicitação está em pré-análise. Se algum documento for necessário, você será avisado.',
				'transitions'               => array( self::PENDING_DOCS, self::CREDIT, self::REJECTED, self::CANCELLED ),
				'final'                     => false,
				'requires_final_cap'        => false,
				'requires_internal_comment' => false,
			),
			self::PENDING_DOCS  => array(
				'label'                     => 'Pendência documental',
				'color'                     => '#d97706',
				'client_visible'            => true,
				'client_text'               => 'Há pendências para você resolver. Veja os itens solicitados e envie os documentos pela sua área.',
				'transitions'               => array( self::PRE_ANALYSIS, self::CANCELLED ),
				'final'                     => false,
				'requires_final_cap'        => false,
				'requires_internal_comment' => false,
			),
			self::CREDIT        => array(
				'label'                     => 'Análise de crédito',
				'color'                     => '#0891b2',
				'client_visible'            => true,
				'client_text'               => 'Sua solicitação está em análise de crédito.',
				'transitions'               => array( self::COMMITTEE, self::PENDING_DOCS, self::REJECTED, self::CANCELLED ),
				'final'                     => false,
				'requires_final_cap'        => false,
				'requires_internal_comment' => false,
			),
			self::COMMITTEE     => array(
				'label'                     => 'Comitê',
				'color'                     => '#4f46e5',
				'client_visible'            => true,
				'client_text'               => 'Sua solicitação foi encaminhada ao comitê de crédito.',
				'transitions'               => array( self::APPROVED, self::REJECTED, self::PENDING_DOCS, self::CANCELLED ),
				'final'                     => false,
				'requires_final_cap'        => false,
				'requires_internal_comment' => false,
			),
			self::APPROVED      => array(
				'label'                     => 'Aprovada',
				'color'                     => '#16a34a',
				'client_visible'            => true,
				'client_text'               => 'Boa notícia: sua solicitação foi aprovada. A equipe entrará em contato para a formalização.',
				'transitions'               => array( self::FORMALIZATION, self::CANCELLED ),
				'final'                     => false,
				'requires_final_cap'        => true,
				'requires_internal_comment' => true,
			),
			self::FORMALIZATION => array(
				'label'                     => 'Formalização',
				'color'                     => '#059669',
				'client_visible'            => true,
				'client_text'               => 'Estamos na etapa de formalização dos contratos e garantias.',
				'transitions'               => array( self::DONE, self::CANCELLED ),
				'final'                     => false,
				'requires_final_cap'        => false,
				'requires_internal_comment' => false,
			),
			self::DONE          => array(
				'label'                     => 'Concluída',
				'color'                     => '#15803d',
				'client_visible'            => true,
				'client_text'               => 'Operação concluída. Obrigado pela confiança.',
				'transitions'               => array(),
				'final'                     => true,
				'requires_final_cap'        => false,
				'requires_internal_comment' => false,
			),
			self::REJECTED      => array(
				'label'                     => 'Não aprovada',
				'color'                     => '#dc2626',
				'client_visible'            => true,
				'client_text'               => 'Após análise, não foi possível aprovar sua solicitação neste momento. Agradecemos a confiança e permanecemos à disposição para avaliar uma nova solicitação no futuro.',
				'transitions'               => array(),
				'final'                     => true,
				'requires_final_cap'        => true,
				'requires_internal_comment' => true,
			),
			self::CANCELLED     => array(
				'label'                     => 'Cancelada',
				'color'                     => '#374151',
				'client_visible'            => true,
				'client_text'               => 'Solicitação cancelada.',
				'transitions'               => array(),
				'final'                     => true,
				'requires_final_cap'        => false,
				'requires_internal_comment' => false,
			),
		);
	}

	/**
	 * Fluxo efetivo (padrão mesclado com configurações), sempre com as chaves obrigatórias.
	 *
	 * @return array
	 */
	public static function all() {
		$defaults = self::defaults();
		$saved    = Options::get( 'statuses', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$out = array();
		foreach ( $defaults as $key => $def ) {
			$out[ $key ] = array_merge( $def, isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array() );
			// Transições sempre válidas.
			$out[ $key ]['transitions'] = array_values( array_intersect( (array) $out[ $key ]['transitions'], array_keys( $defaults ) ) );
		}
		return $out;
	}

	/**
	 * Existe?
	 *
	 * @param string $key Status.
	 * @return bool
	 */
	public static function exists( $key ) {
		return array_key_exists( (string) $key, self::defaults() );
	}

	/**
	 * Rótulo.
	 *
	 * @param string $key Status.
	 * @return string
	 */
	public static function label( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ]['label'] : (string) $key;
	}

	/**
	 * Cor.
	 *
	 * @param string $key Status.
	 * @return string
	 */
	public static function color( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ]['color'] : '#6b7280';
	}

	/**
	 * Texto padrão ao cliente.
	 *
	 * @param string $key Status.
	 * @return string
	 */
	public static function client_text( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? (string) $all[ $key ]['client_text'] : '';
	}

	/**
	 * Estado final?
	 *
	 * @param string $key Status.
	 * @return bool
	 */
	public static function is_final( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) && ! empty( $all[ $key ]['final'] );
	}

	/**
	 * Status "em andamento" (nem rascunho nem finais).
	 *
	 * @return string[]
	 */
	public static function in_progress() {
		$out = array();
		foreach ( self::all() as $key => $def ) {
			if ( self::DRAFT !== $key && empty( $def['final'] ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/**
	 * A transição é permitida no fluxo?
	 *
	 * @param string $from Origem.
	 * @param string $to   Destino.
	 * @return bool
	 */
	public static function can_transition( $from, $to ) {
		$all = self::all();
		if ( ! isset( $all[ $from ], $all[ $to ] ) ) {
			return false;
		}
		// Qualquer estado (exceto concluída) pode ir para cancelada.
		if ( self::CANCELLED === $to && self::DONE !== $from ) {
			return true;
		}
		return in_array( $to, $all[ $from ]['transitions'], true );
	}

	/**
	 * A transição exige a capacidade de status final?
	 *
	 * @param string $to Destino.
	 * @return bool
	 */
	public static function requires_final_cap( $to ) {
		$all = self::all();
		return isset( $all[ $to ] ) && ! empty( $all[ $to ]['requires_final_cap'] );
	}

	/**
	 * A transição exige comentário interno?
	 *
	 * @param string $to Destino.
	 * @return bool
	 */
	public static function requires_internal_comment( $to ) {
		$all = self::all();
		return isset( $all[ $to ] ) && ! empty( $all[ $to ]['requires_internal_comment'] );
	}

	/**
	 * O cliente pode cancelar neste status? (apenas antes da análise de crédito)
	 *
	 * @param string $status Status atual.
	 * @return bool
	 */
	public static function client_can_cancel( $status ) {
		return in_array( $status, array( self::DRAFT, self::SUBMITTED, self::PRE_ANALYSIS, self::PENDING_DOCS ), true );
	}

	/**
	 * O cliente pode editar dados/documentos neste status?
	 *
	 * @param string $status Status atual.
	 * @return bool
	 */
	public static function client_can_edit( $status ) {
		return in_array( $status, array( self::DRAFT, self::PENDING_DOCS ), true );
	}

	/**
	 * Visível ao cliente?
	 *
	 * @param string $key Status.
	 * @return bool
	 */
	public static function client_visible( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) && ! empty( $all[ $key ]['client_visible'] );
	}

	/**
	 * Badge HTML escapado.
	 *
	 * @param string $key Status.
	 * @return string
	 */
	public static function badge( $key ) {
		return sprintf( '<span class="ebcr-badge" style="--ebcr-badge:%s">%s</span>', esc_attr( self::color( $key ) ), esc_html( self::label( $key ) ) );
	}
}
