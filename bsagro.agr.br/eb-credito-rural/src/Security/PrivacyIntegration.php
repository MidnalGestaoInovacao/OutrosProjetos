<?php
/**
 * Integração com exportadores/apagadores de dados pessoais do WordPress.
 *
 * @package EBCR
 */

namespace EBCR\Security;

use EBCR\Database\ConsentRepository;
use EBCR\Database\SubmissionDataRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Files\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Ferramentas → Exportar/Apagar dados pessoais.
 */
final class PrivacyIntegration {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Registra exportador.
	 *
	 * @param array $exporters Exportadores.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['eb-credito-rural'] = array(
			'exporter_friendly_name' => __( 'EB Crédito Rural', 'eb-credito-rural' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Registra apagador.
	 *
	 * @param array $erasers Apagadores.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['eb-credito-rural'] = array(
			'eraser_friendly_name' => __( 'EB Crédito Rural', 'eb-credito-rural' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Exporta dados do usuário (solicitações, dados, consentimentos).
	 *
	 * @param string $email Email.
	 * @param int    $page  Página.
	 * @return array
	 */
	public function export( $email, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- assinatura do WordPress.
		$user = get_user_by( 'email', $email );
		$data = array();
		if ( $user ) {
			foreach ( self::collect( $user->ID ) as $group => $items ) {
				foreach ( $items as $i => $item ) {
					$fields = array();
					foreach ( $item as $k => $v ) {
						$fields[] = array(
							'name'  => $k,
							'value' => is_scalar( $v ) ? (string) $v : wp_json_encode( $v, JSON_UNESCAPED_UNICODE ),
						);
					}
					$data[] = array(
						'group_id'    => 'ebcr_' . $group,
						'group_label' => $group,
						'item_id'     => 'ebcr_' . $group . '_' . $i,
						'data'        => $fields,
					);
				}
			}
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Apaga/anonimiza: só solicitações finalizadas; em andamento ficam retidas (obrigação legal) e são informadas.
	 *
	 * @param string $email Email.
	 * @param int    $page  Página.
	 * @return array
	 */
	public function erase( $email, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- assinatura do WordPress.
		$user     = get_user_by( 'email', $email );
		$removed  = false;
		$retained = false;
		$messages = array();
		if ( $user ) {
			$repo = new SubmissionRepository();
			foreach ( $repo->for_user( $user->ID ) as $s ) {
				if ( Status::is_final( $s['status'] ) || Status::DRAFT === $s['status'] ) {
					Retention::anonymize( $s, 'privacy_eraser' );
					$removed = true;
				} else {
					$retained   = true;
					$messages[] = sprintf( /* translators: %s: protocolo */ __( 'A solicitação %s está em andamento e foi retida por obrigação legal.', 'eb-credito-rural' ), $s['protocol'] ? $s['protocol'] : $s['public_id'] );
				}
			}
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Coleta dados do usuário para exportação (usada também pelo portal).
	 *
	 * @param int $user_id Usuário.
	 * @return array
	 */
	public static function collect( $user_id ) {
		$repo = new SubmissionRepository();
		$data = new SubmissionDataRepository();
		$out  = array(
			'perfil'         => array(),
			'solicitacoes'   => array(),
			'consentimentos' => array(),
		);
		$u    = get_userdata( $user_id );
		if ( $u ) {
			$out['perfil'][] = array(
				'nome'     => $u->display_name,
				'email'    => $u->user_email,
				'telefone' => get_user_meta( $user_id, 'ebcr_phone', true ),
				'criado'   => $u->user_registered,
			);
		}
		foreach ( $repo->for_user( $user_id ) as $s ) {
			$out['solicitacoes'][] = array(
				'protocolo'  => $s['protocol'],
				'status'     => Status::label( $s['status'] ),
				'criada_em'  => $s['created_at'],
				'enviada_em' => $s['submitted_at'],
				'valor'      => $s['requested_amount'],
				'dados'      => $data->all( (int) $s['id'] ),
			);
		}
		foreach ( ( new ConsentRepository() )->for_user( $user_id ) as $c ) {
			$out['consentimentos'][] = array(
				'politica' => $c['policy_key'],
				'versao'   => $c['policy_version'],
				'aceito'   => $c['accepted_at'],
				'ip'       => $c['ip'],
			);
		}
		return $out;
	}
}
