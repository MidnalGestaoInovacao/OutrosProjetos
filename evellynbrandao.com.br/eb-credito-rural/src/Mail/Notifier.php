<?php
/**
 * Dispara os e-mails de cada evento (cliente e administração).
 *
 * @package EBCR
 */

namespace EBCR\Mail;

use EBCR\Database\DocumentRequestRepository;
use EBCR\Domain\Status;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Nunca anexa documentos por padrão (ver configuração "anexar documentos ao e-mail do administrador").
 */
final class Notifier {

	/**
	 * Variáveis básicas de uma solicitação.
	 *
	 * @param array $submission Linha.
	 * @return array
	 */
	private static function vars( array $submission ) {
		$user = get_userdata( (int) $submission['user_id'] );
		return array(
			'nome'        => $user ? $user->display_name : '',
			'protocolo'   => $submission['protocol'] ? $submission['protocol'] : '—',
			'status'      => Status::label( $submission['status'] ),
			'valor'       => Helpers::money( $submission['requested_amount'] ),
			'link_portal' => Helpers::portal_url(
				array(
					'ebcr_view' => 'solicitacao',
					'id'        => $submission['public_id'],
				)
			),
		);
	}

	/**
	 * Link do painel admin para a solicitação.
	 *
	 * @param array $submission Linha.
	 * @return string
	 */
	private static function admin_link( array $submission ) {
		return admin_url( 'admin.php?page=ebcr-submissions&view=' . rawurlencode( $submission['public_id'] ) );
	}

	/**
	 * Destinatários administrativos (+ analista atribuído, se configurado).
	 *
	 * @param array $submission Linha.
	 * @return string[]
	 */
	private static function admin_recipients( array $submission ) {
		$list = Options::admin_emails();
		if ( Options::bool( 'notify_assigned_analyst' ) && ! empty( $submission['assigned_to'] ) ) {
			$a = get_userdata( (int) $submission['assigned_to'] );
			if ( $a ) {
				$list[] = $a->user_email;
			}
		}
		return array_values( array_unique( $list ) );
	}

	/**
	 * E-mail do cliente.
	 *
	 * @param array $submission Linha.
	 * @return string
	 */
	private static function client_email( array $submission ) {
		$user = get_userdata( (int) $submission['user_id'] );
		return $user ? $user->user_email : '';
	}

	/**
	 * Cadastro: confirmação.
	 *
	 * @param \WP_User $user Usuário.
	 * @param string   $link Link de confirmação.
	 * @return void
	 */
	public static function register_confirm( $user, $link ) {
		Mailer::send_event(
			'register_confirm',
			$user->user_email,
			array(
				'nome'             => $user->display_name,
				'link_confirmacao' => $link,
			)
		);
	}

	/**
	 * Solicitação enviada.
	 *
	 * @param array $submission Linha.
	 * @return void
	 */
	public static function submission_submitted( array $submission ) {
		$vars = self::vars( $submission );
		Mailer::send_event( 'submission_client', self::client_email( $submission ), $vars );
		$admin_vars  = array_merge( $vars, array( 'link_portal' => self::admin_link( $submission ) ) );
		$attachments = array();
		if ( Options::bool( 'attach_documents_admin' ) ) {
			$attachments = self::attachments_for( $submission );
		}
		Mailer::send_event( 'submission_admin', self::admin_recipients( $submission ), $admin_vars, $attachments );
	}

	/**
	 * Cópias temporárias descriptografadas para anexo (opção desaconselhada).
	 *
	 * @param array $submission Linha.
	 * @return array
	 */
	private static function attachments_for( array $submission ) {
		$out     = array();
		$storage = new \EBCR\Files\Storage();
		$docs    = ( new \EBCR\Database\DocumentRepository() )->for_submission( (int) $submission['id'] );
		$tmp_dir = trailingslashit( get_temp_dir() ) . 'ebcr-mail-' . Helpers::random_hex( 6 );
		wp_mkdir_p( $tmp_dir );
		foreach ( $docs as $d ) {
			$bytes = $storage->read( $d );
			if ( null === $bytes ) {
				continue;
			}
			$path = trailingslashit( $tmp_dir ) . sanitize_file_name( $d['original_name'] );
			file_put_contents( $path, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- cópia temporária.
			$out[] = $path;
		}
		return $out;
	}

	/**
	 * Mudança de status.
	 *
	 * @param array  $submission       Linha.
	 * @param string $from             Origem.
	 * @param string $to               Destino.
	 * @param string $comment_client   Comentário ao cliente.
	 * @param string $comment_internal Comentário interno.
	 * @return void
	 */
	public static function status_changed( array $submission, $from, $to, $comment_client, $comment_internal ) {
		$vars = self::vars( $submission );
		if ( Status::client_visible( $to ) ) {
			$vars['comentario'] = $comment_client ? $comment_client : Status::client_text( $to );
			Mailer::send_event( 'status_client', self::client_email( $submission ), $vars );
		}
		if ( Options::bool( 'notify_admin_status_change' ) ) {
			$admin_vars = array_merge(
				$vars,
				array(
					'comentario'  => $comment_internal ? $comment_internal : $comment_client,
					'link_portal' => self::admin_link( $submission ),
				)
			);
			Mailer::send_event( 'status_admin', self::admin_recipients( $submission ), $admin_vars );
		}
	}

	/**
	 * Documento solicitado.
	 *
	 * @param array $submission Linha.
	 * @param array $request    Pedido.
	 * @return void
	 */
	public static function document_requested( array $submission, array $request ) {
		$vars               = self::vars( $submission );
		$vars['documento']  = $request['label'];
		$vars['comentario'] = $request['note'];
		Mailer::send_event( 'document_requested', self::client_email( $submission ), $vars );
	}

	/**
	 * Documento recusado.
	 *
	 * @param array $submission Linha.
	 * @param array $document   Documento.
	 * @return void
	 */
	public static function document_rejected( array $submission, array $document ) {
		$vars               = self::vars( $submission );
		$vars['documento']  = \EBCR\Forms\DocumentMatrix::label( $document['doc_type'] ) . ' — ' . $document['original_name'];
		$vars['comentario'] = $document['review_note'];
		Mailer::send_event( 'document_rejected', self::client_email( $submission ), $vars );
	}

	/**
	 * Cliente enviou documento atendendo a um pedido (avisa a administração).
	 *
	 * @param array $submission Linha.
	 * @param array $document   Documento.
	 * @return void
	 */
	public static function client_responded( array $submission, array $document ) {
		$vars                = self::vars( $submission );
		$vars['documento']   = \EBCR\Forms\DocumentMatrix::label( $document['doc_type'] ) . ' — ' . $document['original_name'];
		$vars['link_portal'] = self::admin_link( $submission );
		Mailer::send_event( 'client_responded', self::admin_recipients( $submission ), $vars );
	}

	/**
	 * Nova mensagem.
	 *
	 * @param array $submission Linha.
	 * @param array $message    Mensagem.
	 * @param bool  $from_team  Autor é da equipe.
	 * @return void
	 */
	public static function new_message( array $submission, array $message, $from_team ) {
		$vars             = self::vars( $submission );
		$vars['mensagem'] = $message['body'];
		if ( $from_team ) {
			Mailer::send_event( 'message_client', self::client_email( $submission ), $vars );
		} else {
			$vars['link_portal'] = self::admin_link( $submission );
			Mailer::send_event( 'message_admin', self::admin_recipients( $submission ), $vars );
		}
	}

	/**
	 * Lembrete de pendências.
	 *
	 * @param array $submission Linha.
	 * @param array $requests   Pedidos abertos.
	 * @return void
	 */
	public static function pending_reminder( array $submission, array $requests ) {
		$vars               = self::vars( $submission );
		$vars['pendencias'] = implode(
			"\n",
			array_map(
				static function ( $r ) {
					return '• ' . $r['label'];
				},
				$requests
			)
		);
		Mailer::send_event( 'pending_reminder_client', self::client_email( $submission ), $vars );
		$vars['link_portal'] = self::admin_link( $submission );
		Mailer::send_event( 'pending_reminder_admin', self::admin_recipients( $submission ), $vars );
	}

	/**
	 * Certidões a vencer.
	 *
	 * @param array $lines Linhas de texto.
	 * @return void
	 */
	public static function certificates_expiring( array $lines ) {
		Mailer::send_event(
			'certificates_expiring',
			Options::admin_emails(),
			array(
				'pendencias'  => implode( "\n", $lines ),
				'link_portal' => admin_url( 'admin.php?page=ebcr-submissions' ),
			)
		);
	}

	/**
	 * Atribuição.
	 *
	 * @param array $submission Linha.
	 * @param int   $analyst_id Analista.
	 * @return void
	 */
	public static function assigned( array $submission, $analyst_id ) {
		$a = get_userdata( (int) $analyst_id );
		if ( $a ) {
			$vars                = self::vars( $submission );
			$vars['link_portal'] = self::admin_link( $submission );
			Mailer::send_event( 'assigned', $a->user_email, $vars );
		}
	}

	/**
	 * Solicitação LGPD do titular.
	 *
	 * @param \WP_User $user    Usuário.
	 * @param string   $kind    Tipo.
	 * @param string   $details Detalhes.
	 * @return void
	 */
	public static function lgpd_request( $user, $kind, $details ) {
		Mailer::send_event(
			'lgpd_request',
			array_merge( Options::admin_emails(), array_filter( array( sanitize_email( (string) Options::get( 'dpo_email', '' ) ) ) ) ),
			array(
				'nome'        => $user->display_name . ' <' . $user->user_email . '>',
				'mensagem'    => $kind . "\n\n" . $details,
				'link_portal' => admin_url( 'admin.php?page=ebcr-audit' ),
			)
		);
	}
}
