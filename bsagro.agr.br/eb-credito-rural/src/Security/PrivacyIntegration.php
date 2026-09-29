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
		$user   = get_user_by( 'email', $email );
		$data   = array();
		$groups = $user ? self::collect( $user->ID ) : array( 'canais' => self::channel_items( $email ) );
		if ( $groups ) {
			foreach ( $groups as $group => $items ) {
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
		// Mensagens dos canais (pelo e-mail, mesmo sem conta): mantidas como registro do atendimento, sem os dados de
		// identificação (nome, e-mail, telefone, empresa) nem hash de IP/user agent. Relatos anônimos não são afetados.
		$channels = new \EBCR\Database\ChannelMessageRepository();
		foreach ( $channels->for_email( $email ) as $m ) {
			$fields = \EBCR\Domain\ChannelMessage::fields_of( $m );
			foreach ( \EBCR\Domain\ChannelMessage::IDENTIFYING as $k ) {
				unset( $fields[ $k ] );
			}
			$fields['_anonimizado'] = 'sim';
			$channels->anonymize( (int) $m['id'], $fields );
			$removed    = true;
			$messages[] = sprintf( /* translators: %s: protocolo */ __( 'A mensagem %s foi mantida sem os dados de identificação.', 'eb-credito-rural' ), $m['protocol'] );
		}
		if ( $user ) {
			// Consentimentos de cookies: desvincula do usuário (o registro anônimo permanece como prova do consentimento).
			if ( ( new \EBCR\Database\CookieConsentRepository() )->anonymize_user( $user->ID ) ) {
				$removed = true;
			}
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
	 * Mensagens identificadas dos canais públicos enviadas com um e-mail (anônimas nunca entram).
	 *
	 * @param string $email E-mail.
	 * @return array
	 */
	public static function channel_items( $email ) {
		$out    = array();
		$labels = \EBCR\Domain\ChannelMessage::channels();
		foreach ( ( new \EBCR\Database\ChannelMessageRepository() )->for_email( $email ) as $m ) {
			$out[] = array(
				'protocolo'   => $m['protocol'],
				'canal'       => isset( $labels[ $m['channel'] ] ) ? $labels[ $m['channel'] ]['label'] : $m['channel'],
				'status'      => $m['status'],
				'recebida_em' => $m['created_at'],
				'pagina'      => $m['page'],
				'campos'      => \EBCR\Domain\ChannelMessage::fields_of( $m ),
			);
		}
		return $out;
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
			'cookies'        => array(),
			'canais'         => array(),
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
		if ( $u ) {
			$out['canais'] = self::channel_items( $u->user_email );
		}
		// Consentimentos de cookies registrados com o usuário conectado (o IP é guardado só como hash).
		foreach ( ( new \EBCR\Database\CookieConsentRepository() )->for_user( $user_id ) as $c ) {
			$out['cookies'][] = array(
				'id_consentimento' => $c['consent_id'],
				'acao'             => $c['action'],
				'categorias'       => json_decode( (string) $c['categories'], true ),
				'versao'           => (int) $c['version'],
				'pagina'           => $c['path'],
				'registrado_em'    => $c['created_at'],
			);
		}
		return $out;
	}
}
