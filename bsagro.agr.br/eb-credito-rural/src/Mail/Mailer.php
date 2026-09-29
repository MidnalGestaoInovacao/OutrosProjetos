<?php
/**
 * Renderização de templates e envio (via fila).
 *
 * @package EBCR
 */

namespace EBCR\Mail;

use EBCR\Support\Options;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Templates editáveis no admin com placeholders; corpo em HTML simples dentro de um layout.
 */
final class Mailer {

	/**
	 * Template efetivo de um evento.
	 *
	 * @param string $event Evento.
	 * @return array{subject:string,body:string}
	 */
	public static function template( $event ) {
		$defaults = Events::all();
		$saved    = Options::get( 'email_templates', array() );
		$subject  = isset( $saved[ $event ]['subject'] ) && '' !== trim( $saved[ $event ]['subject'] ) ? $saved[ $event ]['subject'] : ( isset( $defaults[ $event ] ) ? $defaults[ $event ]['subject'] : '' );
		$body     = isset( $saved[ $event ]['body'] ) && '' !== trim( $saved[ $event ]['body'] ) ? $saved[ $event ]['body'] : ( isset( $defaults[ $event ] ) ? $defaults[ $event ]['body'] : '' );
		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Substitui placeholders (valores escapados para HTML no corpo).
	 *
	 * @param string $text Texto.
	 * @param array  $vars Variáveis sem chaves.
	 * @param bool   $html Escapar para HTML.
	 * @return string
	 */
	public static function fill( $text, array $vars, $html = true ) {
		$vars = array_merge(
			array(
				'site'             => \EBCR\Admin\Branding::brand_name(),
				'data'             => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
				'nome'             => '',
				'protocolo'        => '',
				'status'           => '',
				'comentario'       => '',
				'link_portal'      => \EBCR\Support\Helpers::portal_url(),
				'pendencias'       => '',
				'valor'            => '',
				'documento'        => '',
				'mensagem'         => '',
				'link_confirmacao' => '',
			),
			$vars
		);
		foreach ( $vars as $k => $v ) {
			$v = (string) $v;
			if ( $html ) {
				$v = in_array( $k, array( 'link_portal', 'link_confirmacao' ), true ) ? esc_url( $v ) : nl2br( esc_html( $v ) );
			}
			$text = str_replace( '{' . $k . '}', $v, $text );
		}
		return $text;
	}

	/**
	 * Monta e enfileira o e-mail de um evento.
	 *
	 * @param string       $event       Evento.
	 * @param string|array $to          Destinatário(s).
	 * @param array        $vars        Variáveis.
	 * @param array        $attachments Caminhos de anexos (raramente; ver configuração).
	 * @return int[] IDs na fila.
	 */
	public static function send_event( $event, $to, array $vars, array $attachments = array() ) {
		$tpl     = self::template( $event );
		$subject = wp_strip_all_tags( self::fill( $tpl['subject'], $vars, false ) );
		$body    = self::fill( $tpl['body'], $vars, true );
		// Converte quebras de linha do template em parágrafos e permite links/negrito simples.
		$body_html = wpautop(
			wp_kses(
				$body,
				array(
					'a'      => array( 'href' => true ),
					'strong' => array(),
					'em'     => array(),
					'br'     => array(),
					'p'      => array(),
					'ul'     => array(),
					'li'     => array(),
				)
			)
		);
		$html      = View::render(
			'emails/layout',
			array(
				'subject'  => $subject,
				'body'     => $body_html,
				'logo_url' => (string) Options::get( 'email_logo_url', '' ),
				'site'     => \EBCR\Admin\Branding::brand_name(),
				'colors'   => \EBCR\Admin\Branding::email_colors(),
				'home'     => home_url( '/' ),
			)
		);
		$ids       = array();
		foreach ( (array) $to as $recipient ) {
			$recipient = sanitize_email( $recipient );
			if ( ! $recipient || ! is_email( $recipient ) ) {
				continue;
			}
			$ids[] = Queue::enqueue( $event, $recipient, $subject, $html, $attachments );
			/**
			 * Dispara após enfileirar um e-mail de evento (ex.: espelhar por WhatsApp).
			 *
			 * @param string $event     Evento.
			 * @param string $recipient E-mail do destinatário.
			 * @param array  $vars      Variáveis do template (protocolo, nome, status…).
			 */
			do_action( 'ebcr_notification_sent', $event, $recipient, $vars );
		}
		return $ids;
	}

	/**
	 * Enfileira um e-mail com corpo HTML já escapado, dentro do layout da marca (sem template de evento).
	 *
	 * @param string   $event   Nome do evento (registro na fila).
	 * @param string   $to      Destinatário.
	 * @param string   $subject Assunto (texto).
	 * @param string   $body    Corpo HTML (já escapado pelo chamador).
	 * @param string[] $headers Cabeçalhos extras (Reply-To/Cc/Bcc).
	 * @return int ID na fila (0 se o destinatário for inválido).
	 */
	public static function send_raw( $event, $to, $subject, $body, array $headers = array() ) {
		$to = sanitize_email( (string) $to );
		if ( ! $to || ! is_email( $to ) ) {
			return 0;
		}
		$subject = wp_strip_all_tags( str_replace( array( "\r", "\n" ), ' ', (string) $subject ) );
		$html    = View::render(
			'emails/layout',
			array(
				'subject'  => $subject,
				'body'     => $body,
				'logo_url' => (string) Options::get( 'email_logo_url', '' ),
				'site'     => \EBCR\Admin\Branding::brand_name(),
				'colors'   => \EBCR\Admin\Branding::email_colors(),
				'home'     => home_url( '/' ),
			)
		);
		return Queue::enqueue( $event, $to, $subject, $html, array(), $headers );
	}

	/**
	 * Cabeçalhos padrão.
	 *
	 * @return string[]
	 */
	public static function headers() {
		$name  = (string) Options::get( 'from_name', get_bloginfo( 'name' ) );
		$email = sanitize_email( (string) Options::get( 'from_email', get_option( 'admin_email' ) ) );
		$h     = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( $email && is_email( $email ) ) {
			$h[] = sprintf( 'From: %s <%s>', str_replace( array( "\r", "\n", '<', '>' ), '', $name ), $email );
		}
		return $h;
	}

	/**
	 * Envio de teste imediato (sem fila).
	 *
	 * @param string $to Destinatário.
	 * @return bool
	 */
	public static function send_test( $to ) {
		$html = View::render(
			'emails/layout',
			array(
				'subject'  => __( 'E-mail de teste — EB Crédito Rural', 'eb-credito-rural' ),
				'body'     => '<p>' . esc_html__( 'Este é um e-mail de teste enviado pelo plugin EB Crédito Rural. Se você o recebeu, o envio está funcionando.', 'eb-credito-rural' ) . '</p>',
				'logo_url' => (string) Options::get( 'email_logo_url', '' ),
				'site'     => \EBCR\Admin\Branding::brand_name(),
				'colors'   => \EBCR\Admin\Branding::email_colors(),
				'home'     => home_url( '/' ),
			)
		);
		return (bool) wp_mail( $to, __( 'E-mail de teste — EB Crédito Rural', 'eb-credito-rural' ), $html, self::headers() );
	}
}
