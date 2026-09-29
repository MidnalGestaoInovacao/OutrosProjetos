<?php
/**
 * Regras de nova submissão (intervalo mínimo, bloqueio se houver outra em andamento) e exigência de
 * bens (imóveis rurais, etapa 2) e garantias (etapa 5) conforme a configuração "Bens e garantias".
 *
 * @package EBCR
 */

namespace EBCR\Forms;

use EBCR\Database\SubmissionRepository;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Sempre verificadas no servidor.
 */
final class SubmissionRules {

	const MODE_REQUIRED = 'required';
	const MODE_OPTIONAL = 'optional';
	const MODE_DISABLED = 'disabled';

	/**
	 * O usuário pode iniciar/enviar uma nova solicitação?
	 *
	 * @param int      $user_id           Usuário.
	 * @param int|null $ignore_submission ID da própria solicitação (ao enviar um rascunho).
	 * @return array{allowed:bool,reason:string,wait_seconds:int,message:string}
	 */
	public static function can_submit( $user_id, $ignore_submission = null ) {
		$repo = new SubmissionRepository();
		if ( ! get_user_meta( $user_id, 'ebcr_email_verified', true ) ) {
			return array(
				'allowed'      => false,
				'reason'       => 'unverified',
				'wait_seconds' => 0,
				'message'      => __( 'Confirme seu e-mail antes de enviar uma solicitação.', 'eb-credito-rural' ),
			);
		}
		if ( Options::bool( 'block_if_in_progress' ) ) {
			foreach ( $repo->in_progress_for_user( $user_id ) as $s ) {
				if ( $ignore_submission && (int) $s['id'] === (int) $ignore_submission ) {
					continue;
				}
				return array(
					'allowed'      => false,
					'reason'       => 'in_progress',
					'wait_seconds' => 0,
					'message'      => sprintf( /* translators: %s: protocolo */ __( 'Você já tem uma solicitação em andamento (%s). Aguarde a conclusão para enviar outra.', 'eb-credito-rural' ), $s['protocol'] ? $s['protocol'] : '—' ),
				);
			}
		}
		$days = max( 0, Options::int( 'min_interval_days' ) );
		$last = $repo->last_submitted( $user_id );
		if ( $days > 0 && $last && ( ! $ignore_submission || (int) $last['id'] !== (int) $ignore_submission ) ) {
			$next = strtotime( $last['submitted_at'] . ' UTC' ) + $days * DAY_IN_SECONDS;
			if ( $next > time() ) {
				return array(
					'allowed'      => false,
					'reason'       => 'interval',
					'wait_seconds' => $next - time(),
					'message'      => sprintf( /* translators: 1: dias, 2: data */ __( 'O intervalo mínimo entre solicitações é de %1$d dias. Você poderá enviar uma nova a partir de %2$s.', 'eb-credito-rural' ), $days, wp_date( get_option( 'date_format' ), $next ) ),
				);
			}
		}
		return array(
			'allowed'      => true,
			'reason'       => '',
			'wait_seconds' => 0,
			'message'      => '',
		);
	}

	// ------------------------------------------------------------------ bens e garantias

	/**
	 * Modos aceitos para garantias e bens.
	 *
	 * @return array chave => rótulo
	 */
	public static function modes() {
		return array(
			self::MODE_REQUIRED => __( 'Obrigatório', 'eb-credito-rural' ),
			self::MODE_OPTIONAL => __( 'Opcional', 'eb-credito-rural' ),
			self::MODE_DISABLED => __( 'Desativado (não perguntar)', 'eb-credito-rural' ),
		);
	}

	/**
	 * Normaliza um modo.
	 *
	 * @param mixed $mode Valor salvo.
	 * @return string
	 */
	private static function normalize_mode( $mode ) {
		$mode = (string) $mode;
		return isset( self::modes()[ $mode ] ) ? $mode : self::MODE_REQUIRED;
	}

	/**
	 * Modo das garantias (etapa 5): required (padrão, comportamento até a 1.2.x) | optional | disabled.
	 *
	 * @return string
	 */
	public static function guarantees_mode() {
		return self::normalize_mode( Options::get( 'guarantees_mode', self::MODE_REQUIRED ) );
	}

	/**
	 * Modo dos bens/imóveis rurais (etapa 2): required (padrão) | optional | disabled.
	 *
	 * @return string
	 */
	public static function assets_mode() {
		return self::normalize_mode( Options::get( 'assets_mode', self::MODE_REQUIRED ) );
	}

	/**
	 * Finalidades (modalidades) que sempre exigem garantia no modo "opcional".
	 *
	 * @return string[]
	 */
	public static function guarantees_required_modalities() {
		$raw = Options::get( 'guarantees_required_modalities', array() );
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/[\s,;|]+/', $raw );
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $raw ) ) ) );
	}

	/**
	 * Contexto de uma solicitação para as regras de exigência (e para o filtro ebcr_guarantees_required).
	 *
	 * @param array $submission Linha da solicitação (pode estar vazia).
	 * @param array $extra      Valores que prevalecem (ex.: finalidade lida dos dados da etapa 4).
	 * @return array
	 */
	public static function context( array $submission = array(), array $extra = array() ) {
		return array_merge(
			array(
				'submission_id'    => isset( $submission['id'] ) ? (int) $submission['id'] : 0,
				'public_id'        => isset( $submission['public_id'] ) ? (string) $submission['public_id'] : '',
				'user_id'          => isset( $submission['user_id'] ) ? (int) $submission['user_id'] : 0,
				'purpose'          => isset( $submission['purpose'] ) ? (string) $submission['purpose'] : '',
				'amount'           => isset( $submission['requested_amount'] ) && '' !== $submission['requested_amount'] ? (float) $submission['requested_amount'] : null,
				'term_months'      => isset( $submission['term_months'] ) ? (int) $submission['term_months'] : 0,
				'person_type'      => isset( $submission['person_type'] ) ? (string) $submission['person_type'] : '',
				'properties_count' => null,
			),
			$extra
		);
	}

	/**
	 * A solicitação precisa informar ao menos uma garantia?
	 * required → sempre; optional → só se a finalidade estiver em "Finalidades que sempre exigem garantia"; disabled → nunca.
	 * O resultado passa pelo filtro `ebcr_guarantees_required` (bool $required, array $submission_context).
	 *
	 * @param array $context Contexto (ver context()).
	 * @return bool
	 */
	public static function guarantees_required( array $context = array() ) {
		$mode     = self::guarantees_mode();
		$required = self::MODE_REQUIRED === $mode;
		if ( self::MODE_OPTIONAL === $mode && ! empty( $context['purpose'] ) && in_array( sanitize_key( (string) $context['purpose'] ), self::guarantees_required_modalities(), true ) ) {
			$required = true;
		}
		$context['mode'] = $mode;
		/**
		 * Define se a solicitação exige ao menos uma garantia.
		 *
		 * @param bool  $required           Exigência calculada pela configuração.
		 * @param array $submission_context submission_id, public_id, user_id, purpose, amount, term_months, person_type, properties_count, mode.
		 */
		return (bool) apply_filters( 'ebcr_guarantees_required', $required, $context );
	}

	/**
	 * A etapa de garantias é exibida? (desativada só some se nenhum filtro exigir garantia.)
	 *
	 * @param array $context Contexto.
	 * @return bool
	 */
	public static function guarantees_active( array $context = array() ) {
		return self::MODE_DISABLED !== self::guarantees_mode() || self::guarantees_required( $context );
	}

	/**
	 * Por que a garantia é exigida: mode | modality | filter | '' (não exigida).
	 *
	 * @param array $context Contexto.
	 * @return string
	 */
	public static function guarantees_reason( array $context = array() ) {
		if ( ! self::guarantees_required( $context ) ) {
			return '';
		}
		$mode = self::guarantees_mode();
		if ( self::MODE_REQUIRED === $mode ) {
			return 'mode';
		}
		if ( self::MODE_OPTIONAL === $mode && ! empty( $context['purpose'] ) && in_array( sanitize_key( (string) $context['purpose'] ), self::guarantees_required_modalities(), true ) ) {
			return 'modality';
		}
		return 'filter';
	}

	/**
	 * A solicitação precisa informar ao menos um imóvel rural? Filtro `ebcr_assets_required` (bool, array $submission_context).
	 *
	 * @param array $context Contexto.
	 * @return bool
	 */
	public static function assets_required( array $context = array() ) {
		$context['mode'] = self::assets_mode();
		/**
		 * Define se a solicitação exige ao menos um imóvel rural (etapa 2).
		 *
		 * @param bool  $required           Exigência calculada pela configuração.
		 * @param array $submission_context Contexto da solicitação.
		 */
		return (bool) apply_filters( 'ebcr_assets_required', self::MODE_REQUIRED === $context['mode'], $context );
	}

	/**
	 * A etapa de imóveis é exibida?
	 *
	 * @param array $context Contexto.
	 * @return bool
	 */
	public static function assets_active( array $context = array() ) {
		return self::MODE_DISABLED !== self::assets_mode() || self::assets_required( $context );
	}

	/**
	 * Resumo das exigências (para telas, e-mails e abilities).
	 *
	 * @param array $context Contexto.
	 * @return array
	 */
	public static function requirements( array $context = array() ) {
		return array(
			'guarantees_mode'                => self::guarantees_mode(),
			'guarantees_active'              => self::guarantees_active( $context ),
			'guarantees_required'            => self::guarantees_required( $context ),
			'guarantees_reason'              => self::guarantees_reason( $context ),
			'guarantees_required_modalities' => self::guarantees_required_modalities(),
			'assets_mode'                    => self::assets_mode(),
			'assets_active'                  => self::assets_active( $context ),
			'assets_required'                => self::assets_required( $context ),
		);
	}

	/**
	 * Texto para a equipe/cliente quando não há garantias na solicitação.
	 *
	 * @param array $section Dados salvos da etapa 5 (garantias + oferece_garantia).
	 * @param array $context Contexto.
	 * @return string
	 */
	public static function guarantees_empty_text( array $section, array $context = array() ) {
		if ( ! self::guarantees_active( $context ) ) {
			return __( 'Garantias não são solicitadas nesta operação (configuração "Bens e garantias": desativadas).', 'eb-credito-rural' );
		}
		if ( isset( $section['oferece_garantia'] ) && 'nao' === $section['oferece_garantia'] ) {
			return __( 'O cliente declarou não ter garantia a oferecer.', 'eb-credito-rural' );
		}
		return __( 'Nenhuma garantia informada.', 'eb-credito-rural' );
	}

	/**
	 * Texto para a equipe/cliente quando não há imóveis na solicitação.
	 *
	 * @param array $section Dados salvos da etapa 2 (imoveis + possui_imoveis).
	 * @param array $context Contexto.
	 * @return string
	 */
	public static function assets_empty_text( array $section, array $context = array() ) {
		if ( ! self::assets_active( $context ) ) {
			return __( 'Imóveis rurais não são solicitados nesta operação (configuração "Bens e garantias": desativados).', 'eb-credito-rural' );
		}
		if ( isset( $section['possui_imoveis'] ) && 'nao' === $section['possui_imoveis'] ) {
			return __( 'O cliente declarou não ter imóvel rural a informar.', 'eb-credito-rural' );
		}
		return __( 'Nenhum imóvel informado.', 'eb-credito-rural' );
	}
}
