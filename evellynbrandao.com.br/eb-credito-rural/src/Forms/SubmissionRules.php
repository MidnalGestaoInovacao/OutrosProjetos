<?php
/**
 * Regras de nova submissão (intervalo mínimo, bloqueio se houver outra em andamento).
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
}
