<?php
/**
 * Notificações por WhatsApp (Meta Cloud API) espelhando os eventos de e-mail. Entregue pelo módulo "Integrações".
 *
 * @package EBCR
 */

namespace EBCR\Integrations;

use EBCR\Database\SubmissionDataRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Mail\Events;
use EBCR\Mail\Mailer;
use EBCR\Security\AuditLog;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Ouve ebcr_notification_sent (disparado por Mailer::send_event após enfileirar cada e-mail) e envia mensagens quando habilitado.
 *
 * Nunca lança exceção nem impede o e-mail: o e-mail já está na fila quando o hook dispara, e qualquer falha aqui
 * vira um registro `whatsapp_failed` na auditoria (código HTTP e mensagem da Meta, sem token nem número completo).
 * Eventos com links sensíveis (confirmação de e-mail, redefinição de senha, código de assinatura) ficam só no e-mail.
 */
final class WhatsApp {

	const API_BASE = 'https://graph.facebook.com/v20.0/';
	const TIMEOUT  = 8;
	const MAX_TEXT = 1000;

	/**
	 * Evita repetir o aviso à equipe quando o mesmo evento vai para vários e-mails administrativos.
	 *
	 * @var array<string,bool>
	 */
	private static $team_sent = array();

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'ebcr_notification_sent', array( $this, 'on_notification' ), 10, 3 );
	}

	/**
	 * Integração ligada e com credenciais?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return Options::bool( 'whatsapp_enabled' ) && '' !== self::token() && '' !== self::phone_id();
	}

	/**
	 * Token de acesso (nunca vai para logs).
	 *
	 * @return string
	 */
	private static function token() {
		return trim( (string) Options::get( 'whatsapp_token', '' ) );
	}

	/**
	 * Phone Number ID (só dígitos).
	 *
	 * @return string
	 */
	private static function phone_id() {
		return Helpers::digits( Options::get( 'whatsapp_phone_id', '' ) );
	}

	/**
	 * Nome do template aprovado (minúsculas, dígitos e sublinhado) ou vazio para texto simples.
	 *
	 * @return string
	 */
	private static function template_name() {
		return preg_replace( '/[^a-z0-9_]/', '', strtolower( trim( (string) Options::get( 'whatsapp_template', '' ) ) ) );
	}

	/**
	 * Eventos administrativos espelhados ao número da equipe.
	 *
	 * @return string[]
	 */
	public static function team_events() {
		/**
		 * Eventos (chaves de EBCR\Mail\Events::all()) que avisam a equipe pelo WhatsApp.
		 *
		 * @param string[] $events Padrão: nova solicitação e mensagem do cliente.
		 */
		return (array) apply_filters( 'ebcr_whatsapp_team_events', array( 'submission_admin', 'message_admin' ) );
	}

	/**
	 * Textos curtos por evento (placeholders iguais aos dos e-mails).
	 *
	 * @return array<string,string>
	 */
	public static function texts() {
		return array(
			'submission_client'       => __( 'Crédito Rural — {site}: recebemos sua solicitação {protocolo} ({valor}). Status: {status}. Acompanhe pela sua área: {link_portal}', 'eb-credito-rural' ),
			'status_client'           => __( 'Crédito Rural — {site}: sua solicitação {protocolo} mudou para "{status}". {comentario} Acesse sua área: {link_portal}', 'eb-credito-rural' ),
			'document_requested'      => __( 'Crédito Rural — {site}: a equipe solicitou o documento "{documento}" na solicitação {protocolo}. {comentario} Envie pela sua área: {link_portal}', 'eb-credito-rural' ),
			'document_rejected'       => __( 'Crédito Rural — {site}: o documento "{documento}" da solicitação {protocolo} precisa ser reenviado. Motivo: {comentario}. Acesse sua área: {link_portal}', 'eb-credito-rural' ),
			'message_client'          => __( 'Crédito Rural — {site}: você recebeu uma nova mensagem da equipe sobre a solicitação {protocolo}. Responda pela sua área: {link_portal}', 'eb-credito-rural' ),
			'pending_reminder_client' => __( 'Crédito Rural — {site}: ainda há pendências na solicitação {protocolo}: {pendencias}. Envie pela sua área: {link_portal}', 'eb-credito-rural' ),
			'submission_admin'        => __( 'Crédito Rural: nova solicitação {protocolo} de {nome} ({valor}). Painel: {link_portal}', 'eb-credito-rural' ),
			'message_admin'           => __( 'Crédito Rural: {nome} enviou uma mensagem na solicitação {protocolo}. Painel: {link_portal}', 'eb-credito-rural' ),
		);
	}

	/**
	 * Texto final de um evento (vazio quando o evento não é espelhado).
	 *
	 * @param string $event Evento.
	 * @param array  $vars  Variáveis do template de e-mail.
	 * @return string
	 */
	public static function text_for( $event, array $vars ) {
		$texts = self::texts();
		$text  = isset( $texts[ $event ] ) ? Mailer::fill( $texts[ $event ], $vars, false ) : '';
		/**
		 * Permite ajustar o texto enviado por WhatsApp (vazio = não enviar).
		 *
		 * @param string $text  Texto.
		 * @param string $event Evento.
		 * @param array  $vars  Variáveis.
		 */
		$text = apply_filters( 'ebcr_whatsapp_text', $text, $event, $vars );
		return self::clean_text( $text );
	}

	/**
	 * Remove quebras de linha/espaços repetidos (exigência dos parâmetros de template) e limita o tamanho.
	 *
	 * @param string $text Texto.
	 * @return string
	 */
	public static function clean_text( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
		$text = str_replace( array( ' .', ' ,' ), array( '.', ',' ), $text );
		return mb_substr( $text, 0, self::MAX_TEXT );
	}

	/**
	 * Normaliza para E.164 sem "+": 55 + DDD + número. Números sem DDI com 10–11 dígitos recebem 55; internacionais são mantidos.
	 *
	 * @param string $number Número em qualquer formato.
	 * @return string Dígitos ou vazio se inválido.
	 */
	public static function normalize( $number ) {
		$d = ltrim( Helpers::digits( $number ), '0' );
		$n = strlen( $d );
		if ( 10 === $n || 11 === $n ) {
			$d = '55' . $d;
		} elseif ( $n < 10 || $n > 15 ) {
			return '';
		}
		if ( 0 === strpos( $d, '55' ) && ! preg_match( '/^55[1-9]\d{9,10}$/', $d ) ) {
			return '';
		}
		return $d;
	}

	/**
	 * Número do cliente: WhatsApp/telefone do perfil ou da etapa 1 da solicitação (pelo protocolo).
	 *
	 * @param string $email E-mail do destinatário.
	 * @param array  $vars  Variáveis do evento (protocolo).
	 * @return string
	 */
	public static function client_number( $email, array $vars = array() ) {
		$user = $email ? get_user_by( 'email', $email ) : false;
		if ( $user ) {
			foreach ( array( 'ebcr_whatsapp', 'ebcr_phone' ) as $meta ) {
				$n = self::normalize( (string) get_user_meta( $user->ID, $meta, true ) );
				if ( '' !== $n ) {
					return $n;
				}
			}
		}
		if ( ! empty( $vars['protocolo'] ) && '—' !== $vars['protocolo'] ) {
			$s = ( new SubmissionRepository() )->find_by_protocol( (string) $vars['protocolo'] );
			if ( $s && ( ! $user || (int) $s['user_id'] === (int) $user->ID ) ) {
				$data = ( new SubmissionDataRepository() )->get( (int) $s['id'], 'identificacao' );
				foreach ( array( 'whatsapp', 'telefone' ) as $k ) {
					$n = isset( $data[ $k ] ) ? self::normalize( (string) $data[ $k ] ) : '';
					if ( '' !== $n ) {
						return $n;
					}
				}
			}
		}
		return '';
	}

	/**
	 * Handler de ebcr_notification_sent.
	 *
	 * @param string $event           Evento.
	 * @param string $recipient_email E-mail que recebeu o e-mail.
	 * @param array  $vars            Variáveis do template.
	 * @return void
	 */
	public function on_notification( $event, $recipient_email, $vars ) {
		try {
			if ( ! self::enabled() ) {
				return;
			}
			$event  = sanitize_key( (string) $event );
			$vars   = is_array( $vars ) ? $vars : array();
			$events = Events::all();
			if ( ! isset( $events[ $event ] ) ) {
				return;
			}
			$audience = $events[ $event ]['audience'];
			if ( 'cliente' === $audience && Options::bool( 'whatsapp_notify_client' ) ) {
				$text = self::text_for( $event, $vars );
				if ( '' === $text ) {
					return;
				}
				$number = self::client_number( (string) $recipient_email, $vars );
				if ( '' !== $number ) {
					self::send_text( $number, $text, $event );
				}
				return;
			}
			if ( 'admin' === $audience && Options::bool( 'whatsapp_notify_team' ) && in_array( $event, self::team_events(), true ) ) {
				$key = $event . '|' . ( isset( $vars['protocolo'] ) ? (string) $vars['protocolo'] : '' ) . '|' . ( isset( $vars['mensagem'] ) ? md5( (string) $vars['mensagem'] ) : '' );
				if ( isset( self::$team_sent[ $key ] ) ) {
					return;
				}
				self::$team_sent[ $key ] = true;
				$number                  = self::normalize( (string) Options::get( 'whatsapp_team_number', '' ) );
				$text                    = self::text_for( $event, $vars );
				if ( '' !== $number && '' !== $text ) {
					self::send_text( $number, $text, $event );
				}
			}
		} catch ( \Throwable $e ) {
			AuditLog::log(
				'whatsapp_failed',
				'whatsapp',
				'',
				array(
					'event'   => (string) $event,
					'message' => mb_substr( $e->getMessage(), 0, 200 ),
				)
			);
		}
	}

	/**
	 * Envia uma mensagem (template com um parâmetro de texto, ou texto simples quando não há template).
	 * Reutilizável e testável com o filtro pre_http_request.
	 *
	 * @param string $number Número (qualquer formato; normalizado para 55DDDNÚMERO).
	 * @param string $text   Texto.
	 * @param string $event  Evento (só para auditoria).
	 * @return true|\WP_Error
	 */
	public static function send_text( $number, $text, $event = '' ) {
		$number = self::normalize( $number );
		$text   = self::clean_text( $text );
		if ( '' === $number || '' === $text ) {
			return new \WP_Error( 'whatsapp_invalid', __( 'Número ou texto inválido.', 'eb-credito-rural' ) );
		}
		$token    = self::token();
		$phone_id = self::phone_id();
		if ( '' === $token || '' === $phone_id ) {
			return new \WP_Error( 'whatsapp_unconfigured', __( 'WhatsApp não configurado.', 'eb-credito-rural' ) );
		}
		$template = self::template_name();
		if ( '' !== $template ) {
			$payload = array(
				'messaging_product' => 'whatsapp',
				'recipient_type'    => 'individual',
				'to'                => $number,
				'type'              => 'template',
				'template'          => array(
					'name'       => $template,
					'language'   => array( 'code' => 'pt_BR' ),
					'components' => array(
						array(
							'type'       => 'body',
							'parameters' => array(
								array(
									'type' => 'text',
									'text' => $text,
								),
							),
						),
					),
				),
			);
		} else {
			$payload = array(
				'messaging_product' => 'whatsapp',
				'recipient_type'    => 'individual',
				'to'                => $number,
				'type'              => 'text',
				'text'              => array(
					'preview_url' => false,
					'body'        => $text,
				),
			);
		}
		$r = wp_remote_post(
			self::API_BASE . rawurlencode( $phone_id ) . '/messages',
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ),
			)
		);
		if ( is_wp_error( $r ) ) {
			self::fail( $number, $event, 0, $r->get_error_message() );
			return $r;
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		if ( $code < 200 || $code >= 300 ) {
			$json    = json_decode( (string) wp_remote_retrieve_body( $r ), true );
			$message = isset( $json['error']['message'] ) ? (string) $json['error']['message'] : mb_substr( (string) wp_remote_retrieve_body( $r ), 0, 200 );
			$message = str_replace( $token, '[token]', $message );
			self::fail( $number, $event, $code, $message );
			return new \WP_Error( 'whatsapp_http', $message, array( 'status' => $code ) );
		}
		return true;
	}

	/**
	 * Registra a falha na auditoria (número mascarado, sem token).
	 *
	 * @param string $number  Número.
	 * @param string $event   Evento.
	 * @param int    $code    Código HTTP (0 = erro de rede).
	 * @param string $message Mensagem.
	 * @return void
	 */
	private static function fail( $number, $event, $code, $message ) {
		$masked = Helpers::mask_document( $number );
		AuditLog::log(
			'whatsapp_failed',
			'whatsapp',
			$masked,
			array(
				'to'      => $masked,
				'event'   => (string) $event,
				'code'    => (int) $code,
				'message' => mb_substr( sanitize_text_field( (string) $message ), 0, 200 ),
			)
		);
	}
}
