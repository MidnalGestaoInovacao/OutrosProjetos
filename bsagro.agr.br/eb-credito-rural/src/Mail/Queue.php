<?php
/**
 * Fila de e-mails com retentativas (Action Scheduler se existir, senão WP-Cron).
 *
 * @package EBCR
 */

namespace EBCR\Mail;

use EBCR\Database\MailQueueRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Processamento assíncrono com log de falhas.
 */
final class Queue {

	const HOOK        = 'ebcr_process_mail_queue';
	const MAX_ATTEMPT = 3;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::HOOK, array( __CLASS__, 'process' ) );
		add_action( 'ebcr_mail_tick', array( __CLASS__, 'process' ) );
		add_filter( 'wp_mail_failed', array( __CLASS__, 'capture_error' ) );
	}

	/**
	 * Último erro do wp_mail (capturado pelo hook).
	 *
	 * @var string
	 */
	private static $last_error = '';

	/**
	 * Captura erro do PHPMailer.
	 *
	 * @param \WP_Error $error Erro.
	 * @return \WP_Error
	 */
	public static function capture_error( $error ) {
		if ( $error instanceof \WP_Error ) {
			self::$last_error = $error->get_error_message();
		}
		return $error;
	}

	/**
	 * Enfileira e agenda processamento imediato.
	 *
	 * @param string $event       Evento.
	 * @param string $recipient   Destinatário.
	 * @param string $subject     Assunto.
	 * @param string $html        Corpo HTML.
	 * @param array  $attachments Anexos.
	 * @return int
	 */
	public static function enqueue( $event, $recipient, $subject, $html, array $attachments = array() ) {
		$id = ( new MailQueueRepository() )->enqueue(
			array(
				'event'       => sanitize_key( $event ),
				'recipient'   => $recipient,
				'subject'     => $subject,
				'body'        => $html,
				'headers'     => wp_json_encode( Mailer::headers() ),
				'attachments' => wp_json_encode( array_values( $attachments ) ),
			)
		);
		self::kick();
		return $id;
	}

	/**
	 * Agenda o processamento o mais cedo possível.
	 *
	 * @return void
	 */
	public static function kick() {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			if ( ! function_exists( 'as_has_scheduled_action' ) || ! as_has_scheduled_action( self::HOOK ) ) {
				as_enqueue_async_action( self::HOOK, array(), 'eb-credito-rural' );
			}
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time(), self::HOOK );
		}
		// Sem cron real (DISABLE_WP_CRON), processa ao final da requisição atual.
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON && ! has_action( 'shutdown', array( __CLASS__, 'process' ) ) ) {
			add_action( 'shutdown', array( __CLASS__, 'process' ) );
		}
	}

	/**
	 * Processa itens pendentes.
	 *
	 * @param int $limit Limite por execução.
	 * @return int Enviados.
	 */
	public static function process( $limit = 25 ) {
		$repo = new MailQueueRepository();
		$sent = 0;
		foreach ( $repo->due( (int) $limit ) as $item ) {
			$headers          = json_decode( (string) $item['headers'], true );
			$attachments      = json_decode( (string) $item['attachments'], true );
			self::$last_error = '';
			$ok               = wp_mail( $item['recipient'], $item['subject'], $item['body'], is_array( $headers ) ? $headers : array(), is_array( $attachments ) ? array_filter( $attachments, 'is_file' ) : array() );
			$attempts         = (int) $item['attempts'] + 1;
			if ( $ok ) {
				$repo->update(
					(int) $item['id'],
					array(
						'status'   => 'sent',
						'attempts' => $attempts,
						'sent_at'  => current_time( 'mysql', true ),
						'body'     => '', // não guardar o corpo após o envio (minimização).
					)
				);
				++$sent;
			} else {
				$failed = $attempts >= self::MAX_ATTEMPT;
				$delay  = 1 === $attempts ? 5 * MINUTE_IN_SECONDS : 30 * MINUTE_IN_SECONDS;
				$repo->update(
					(int) $item['id'],
					array(
						'status'       => $failed ? 'failed' : 'pending',
						'attempts'     => $attempts,
						'last_error'   => mb_substr( self::$last_error ? self::$last_error : __( 'wp_mail retornou falso (verifique o SMTP).', 'eb-credito-rural' ), 0, 1000 ),
						'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ),
					)
				);
			}
		}
		return $sent;
	}
}
