<?php
/**
 * Operações de negócio sobre solicitações (criar, enviar, status, atribuição, pedidos de documento, revisão, mensagens, cancelamento).
 *
 * @package EBCR
 */

namespace EBCR\Forms;

use EBCR\Database\DocumentRepository;
use EBCR\Database\DocumentRequestRepository;
use EBCR\Database\GuaranteeRepository;
use EBCR\Database\MessageRepository;
use EBCR\Database\PropertyRepository;
use EBCR\Database\StatusHistoryRepository;
use EBCR\Database\SubmissionDataRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Consent;
use EBCR\Domain\Protocol;
use EBCR\Domain\Status;
use EBCR\Mail\Notifier;
use EBCR\Roles\Capabilities;
use EBCR\Security\AuditLog;
use EBCR\Security\Authorization;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Todas as verificações de autorização acontecem aqui (ou em Authorization), nunca só na interface.
 */
final class SubmissionService {

	/**
	 * Cria rascunho (ou devolve o rascunho aberto). Aplica as regras de nova submissão.
	 *
	 * @param int  $user_id            Usuário.
	 * @param bool $duplicate_previous Copiar dados da última solicitação.
	 * @return array|\WP_Error
	 */
	public static function start( $user_id, $duplicate_previous = false ) {
		$repo  = new SubmissionRepository();
		$draft = $repo->open_draft( $user_id );
		if ( $draft ) {
			return $draft;
		}
		$rules = SubmissionRules::can_submit( $user_id );
		if ( ! $rules['allowed'] ) {
			return new \WP_Error(
				'rules',
				$rules['message'],
				array(
					'status'       => 422,
					'reason'       => $rules['reason'],
					'wait_seconds' => $rules['wait_seconds'],
				)
			);
		}
		$new = $repo->create_draft( $user_id );
		AuditLog::log( 'submission_created', 'submission', $new['public_id'], array(), $user_id );
		if ( $duplicate_previous && Options::bool( 'allow_duplicate_previous' ) ) {
			$last = $repo->last_submitted( $user_id );
			if ( $last ) {
				self::duplicate_into( $last, $new );
			}
		}
		return $repo->find( (int) $new['id'] );
	}

	/**
	 * Copia dados (sem documentos) de uma solicitação para outra.
	 *
	 * @param array $from Origem.
	 * @param array $to   Destino.
	 * @return void
	 */
	private static function duplicate_into( array $from, array $to ) {
		$data = new SubmissionDataRepository();
		foreach ( $data->all( (int) $from['id'] ) as $section => $values ) {
			if ( in_array( $section, array( 'imoveis', 'garantias', 'envio' ), true ) || isset( $values['_error'] ) ) {
				continue;
			}
			$data->save( (int) $to['id'], $section, $values );
		}
		$props = new PropertyRepository();
		$items = array();
		foreach ( $props->for_submission( (int) $from['id'] ) as $p ) {
			unset( $p['id'], $p['submission_id'], $p['created_at'] );
			$items[] = $p;
		}
		$props->replace_all( (int) $to['id'], $items );
		$gar = new GuaranteeRepository();
		$gs  = array();
		foreach ( $gar->for_submission( (int) $from['id'] ) as $g ) {
			$g['property_id'] = null; // imóveis novos têm outros IDs; o cliente revincula.
			$gs[]             = $g;
		}
		$gar->replace_all( (int) $to['id'], $gs );
		( new SubmissionRepository() )->update(
			(int) $to['id'],
			array(
				'person_type'      => $from['person_type'],
				'requested_amount' => $from['requested_amount'],
				'purpose'          => $from['purpose'],
				'term_months'      => $from['term_months'],
				'duplicated_from'  => (int) $from['id'],
				'current_step'     => 1,
			)
		);
	}

	/**
	 * Envio final (após validações do Wizard).
	 *
	 * @param int      $user_id    Usuário.
	 * @param array    $submission Linha.
	 * @param string[] $accepted   Aceites.
	 * @return array
	 */
	public static function submit( $user_id, array $submission, array $accepted ) {
		$repo     = new SubmissionRepository();
		$protocol = $submission['protocol'] ? $submission['protocol'] : Protocol::next();
		$repo->update(
			(int) $submission['id'],
			array(
				'status'       => Status::SUBMITTED,
				'protocol'     => $protocol,
				'submitted_at' => current_time( 'mysql', true ),
				'current_step' => 7,
			)
		);
		Consent::record( $user_id, $accepted, (int) $submission['id'] );
		( new StatusHistoryRepository() )->add( (int) $submission['id'], Status::DRAFT, Status::SUBMITTED, $user_id, '', Status::client_text( Status::SUBMITTED ) );
		$updated = $repo->find( (int) $submission['id'] );
		AuditLog::log( 'submission_submitted', 'submission', $updated['public_id'], array( 'protocol' => $protocol ), $user_id );
		Notifier::submission_submitted( $updated );
		/**
		 * Solicitação enviada.
		 *
		 * @param array $updated Linha.
		 */
		do_action( 'ebcr_submission_submitted', $updated );
		return $updated;
	}

	/**
	 * Mudança de status pela equipe.
	 *
	 * @param int    $user_id          Usuário.
	 * @param array  $submission       Linha.
	 * @param string $to               Destino.
	 * @param string $comment_internal Comentário interno.
	 * @param string $comment_client   Comentário ao cliente.
	 * @return array|\WP_Error
	 */
	public static function change_status( $user_id, array $submission, $to, $comment_internal = '', $comment_client = '' ) {
		$to = sanitize_key( $to );
		if ( ! Authorization::can_change_status( $user_id, $submission, $to ) ) {
			AuditLog::log(
				'access_denied',
				'submission',
				$submission['public_id'],
				array(
					'op' => 'status',
					'to' => $to,
				),
				$user_id
			);
			return new \WP_Error( 'forbidden', __( 'Transição não permitida para o seu perfil ou para o status atual.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		if ( Status::requires_internal_comment( $to ) && '' === trim( $comment_internal ) ) {
			return new \WP_Error( 'comment_required', __( 'Esta decisão exige um comentário interno (parecer).', 'eb-credito-rural' ), array( 'status' => 422 ) );
		}
		$from = $submission['status'];
		( new SubmissionRepository() )->update( (int) $submission['id'], array( 'status' => $to ) );
		( new StatusHistoryRepository() )->add( (int) $submission['id'], $from, $to, $user_id, $comment_internal, $comment_client );
		$updated = ( new SubmissionRepository() )->find( (int) $submission['id'] );
		AuditLog::log(
			'submission_status',
			'submission',
			$updated['public_id'],
			array(
				'from' => $from,
				'to'   => $to,
			),
			$user_id
		);
		Notifier::status_changed( $updated, $from, $to, $comment_client, $comment_internal );
		/**
		 * Status alterado.
		 *
		 * @param array  $updated Linha.
		 * @param string $from    Origem.
		 * @param string $to      Destino.
		 */
		do_action( 'ebcr_status_changed', $updated, $from, $to );
		return $updated;
	}

	/**
	 * Cancelamento pelo cliente.
	 *
	 * @param int    $user_id    Usuário.
	 * @param array  $submission Linha.
	 * @param string $reason     Motivo.
	 * @return array|\WP_Error
	 */
	public static function client_cancel( $user_id, array $submission, $reason = '' ) {
		if ( ! Authorization::client_can_cancel( $user_id, $submission ) ) {
			return new \WP_Error( 'forbidden', __( 'Esta solicitação não pode mais ser cancelada por você. Fale com a equipe.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		$from = $submission['status'];
		( new SubmissionRepository() )->update( (int) $submission['id'], array( 'status' => Status::CANCELLED ) );
		( new StatusHistoryRepository() )->add( (int) $submission['id'], $from, Status::CANCELLED, $user_id, __( 'Cancelada pelo cliente.', 'eb-credito-rural' ) . ( $reason ? ' ' . $reason : '' ), __( 'Você cancelou esta solicitação.', 'eb-credito-rural' ) );
		$updated = ( new SubmissionRepository() )->find( (int) $submission['id'] );
		AuditLog::log( 'submission_cancelled', 'submission', $updated['public_id'], array( 'by' => 'client' ), $user_id );
		if ( Status::DRAFT !== $from ) {
			Notifier::status_changed( $updated, $from, Status::CANCELLED, '', '' );
		}
		return $updated;
	}

	/**
	 * Atribui analista.
	 *
	 * @param int   $user_id    Usuário.
	 * @param array $submission Linha.
	 * @param int   $analyst_id Analista (0 = remover).
	 * @return array|\WP_Error
	 */
	public static function assign( $user_id, array $submission, $analyst_id ) {
		if ( ! Authorization::team_can_edit( $user_id, $submission ) ) {
			return new \WP_Error( 'forbidden', __( 'Sem permissão.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		$analyst_id = (int) $analyst_id;
		if ( $analyst_id && ! user_can( $analyst_id, Capabilities::CAP_VIEW ) ) {
			return new \WP_Error( 'bad_user', __( 'O usuário escolhido não faz parte da equipe.', 'eb-credito-rural' ), array( 'status' => 422 ) );
		}
		( new SubmissionRepository() )->update( (int) $submission['id'], array( 'assigned_to' => $analyst_id ? $analyst_id : null ) );
		AuditLog::log( 'submission_assigned', 'submission', $submission['public_id'], array( 'analyst' => $analyst_id ), $user_id );
		$updated = ( new SubmissionRepository() )->find( (int) $submission['id'] );
		if ( $analyst_id && $analyst_id !== $user_id ) {
			Notifier::assigned( $updated, $analyst_id );
		}
		return $updated;
	}

	/**
	 * Solicita documento adicional ao cliente (gera pendência).
	 *
	 * @param int    $user_id    Usuário.
	 * @param array  $submission Linha.
	 * @param string $doc_type   Tipo.
	 * @param string $label      Rótulo exibido.
	 * @param string $note       Instruções.
	 * @param bool   $set_status Mudar status para pendência documental.
	 * @return array|\WP_Error
	 */
	public static function request_document( $user_id, array $submission, $doc_type, $label, $note = '', $set_status = true ) {
		if ( ! Authorization::team_can_edit( $user_id, $submission ) ) {
			return new \WP_Error( 'forbidden', __( 'Sem permissão.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		if ( Status::is_final( $submission['status'] ) || Status::DRAFT === $submission['status'] ) {
			return new \WP_Error( 'bad_state', __( 'Não é possível solicitar documentos neste status.', 'eb-credito-rural' ), array( 'status' => 422 ) );
		}
		$doc_type = sanitize_key( $doc_type );
		if ( ! DocumentMatrix::is_valid_type( $doc_type ) ) {
			$doc_type = 'outro';
		}
		$label = sanitize_text_field( $label );
		$label = $label ? $label : DocumentMatrix::label( $doc_type );
		$req   = ( new DocumentRequestRepository() )->create(
			array(
				'submission_id' => (int) $submission['id'],
				'doc_type'      => $doc_type,
				'label'         => mb_substr( $label, 0, 190 ),
				'note'          => sanitize_textarea_field( $note ),
				'requested_by'  => (int) $user_id,
			)
		);
		AuditLog::log(
			'document_requested',
			'submission',
			$submission['public_id'],
			array(
				'type'  => $doc_type,
				'label' => $label,
			),
			$user_id
		);
		$updated = $submission;
		if ( $set_status && Status::PENDING_DOCS !== $submission['status'] && Authorization::can_change_status( $user_id, $submission, Status::PENDING_DOCS ) ) {
			$updated = self::change_status( $user_id, $submission, Status::PENDING_DOCS, sprintf( /* translators: %s: documento */ __( 'Documento solicitado: %s', 'eb-credito-rural' ), $label ), sprintf( /* translators: %s: documento */ __( 'A equipe solicitou: %s. Envie pela sua área.', 'eb-credito-rural' ), $label ) );
			if ( is_wp_error( $updated ) ) {
				$updated = $submission;
			}
		} else {
			Notifier::document_requested( $submission, $req );
		}
		return array(
			'request'    => $req,
			'submission' => $updated,
		);
	}

	/**
	 * Revisão de documento (aceito/recusado).
	 *
	 * @param int    $user_id  Usuário.
	 * @param array  $document Linha.
	 * @param string $result   aceito|recusado|pendente.
	 * @param string $note     Motivo.
	 * @return array|\WP_Error
	 */
	public static function review_document( $user_id, array $document, $result, $note = '' ) {
		$submission = ( new SubmissionRepository() )->find( (int) $document['submission_id'] );
		if ( ! $submission || ! Authorization::team_can_edit( $user_id, $submission ) ) {
			return new \WP_Error( 'forbidden', __( 'Sem permissão.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		$result = in_array( $result, array( 'aceito', 'recusado', 'pendente' ), true ) ? $result : 'pendente';
		if ( 'recusado' === $result && '' === trim( $note ) ) {
			return new \WP_Error( 'note_required', __( 'Informe o motivo da recusa (o cliente verá esse texto).', 'eb-credito-rural' ), array( 'status' => 422 ) );
		}
		$docs = new DocumentRepository();
		$docs->update(
			(int) $document['id'],
			array(
				'review_status' => $result,
				'review_note'   => sanitize_textarea_field( $note ),
				'reviewed_by'   => (int) $user_id,
				'reviewed_at'   => current_time( 'mysql', true ),
			)
		);
		if ( 'recusado' === $result && ! empty( $document['request_id'] ) ) {
			( new DocumentRequestRepository() )->reopen( (int) $document['request_id'] );
		}
		AuditLog::log( 'document_reviewed', 'document', $document['public_id'], array( 'result' => $result ), $user_id );
		$updated = $docs->find( (int) $document['id'] );
		if ( 'recusado' === $result ) {
			Notifier::document_rejected( $submission, $updated );
		}
		return $updated;
	}

	/**
	 * Cliente remove documento próprio (só enquanto editável e não aceito).
	 *
	 * @param int   $user_id  Usuário.
	 * @param array $document Linha.
	 * @return true|\WP_Error
	 */
	public static function client_delete_document( $user_id, array $document ) {
		$submission = ( new SubmissionRepository() )->find( (int) $document['submission_id'] );
		if ( ! $submission || (int) $document['user_id'] !== (int) $user_id || ! Authorization::client_can_edit( $user_id, $submission ) || 'aceito' === $document['review_status'] ) {
			return new \WP_Error( 'forbidden', __( 'Este documento não pode ser removido.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		( new \EBCR\Files\Storage() )->delete( $document );
		( new DocumentRepository() )->soft_delete( (int) $document['id'] );
		if ( ! empty( $document['request_id'] ) ) {
			( new DocumentRequestRepository() )->reopen( (int) $document['request_id'] );
		}
		AuditLog::log( 'document_deleted', 'document', $document['public_id'], array(), $user_id );
		return true;
	}

	/**
	 * Mensagem (cliente ou equipe).
	 *
	 * @param int    $user_id    Usuário.
	 * @param array  $submission Linha.
	 * @param string $body       Texto.
	 * @param string $visibility interno|cliente.
	 * @return array|\WP_Error
	 */
	public static function send_message( $user_id, array $submission, $body, $visibility = 'cliente' ) {
		$body = trim( sanitize_textarea_field( $body ) );
		if ( '' === $body || mb_strlen( $body ) > 5000 ) {
			return new \WP_Error( 'bad_body', __( 'Escreva uma mensagem (até 5000 caracteres).', 'eb-credito-rural' ), array( 'status' => 422 ) );
		}
		$is_team = Authorization::is_team( $user_id );
		if ( $is_team ) {
			if ( ! Authorization::team_can_edit( $user_id, $submission ) ) {
				return new \WP_Error( 'forbidden', __( 'Sem permissão.', 'eb-credito-rural' ), array( 'status' => 403 ) );
			}
		} else {
			if ( ! Authorization::owns( $user_id, $submission ) || Status::is_final( $submission['status'] ) ) {
				return new \WP_Error( 'forbidden', __( 'Não é possível enviar mensagens nesta solicitação.', 'eb-credito-rural' ), array( 'status' => 403 ) );
			}
			$visibility = 'cliente';
		}
		$msg = ( new MessageRepository() )->add( (int) $submission['id'], $user_id, $body, $visibility );
		AuditLog::log( 'message_sent', 'submission', $submission['public_id'], array( 'visibility' => $msg['visibility'] ), $user_id );
		if ( 'cliente' === $msg['visibility'] ) {
			Notifier::new_message( $submission, $msg, $is_team );
		}
		return $msg;
	}
}
