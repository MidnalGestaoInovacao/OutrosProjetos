<?php
/**
 * Detalhe da solicitação (abas + painel lateral de ações).
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Database\CheckRepository;
use EBCR\Database\ConsentRepository;
use EBCR\Database\CrmContactRepository;
use EBCR\Database\DocumentRepository;
use EBCR\Database\DocumentRequestRepository;
use EBCR\Database\MessageRepository;
use EBCR\Database\StatusHistoryRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Forms\DocumentMatrix;
use EBCR\Forms\Wizard;
use EBCR\Roles\Capabilities;
use EBCR\Security\AuditLog;
use EBCR\Security\Authorization;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Autorização centralizada em Authorization::can_view_submission.
 */
final class SubmissionView {

	/**
	 * Renderiza.
	 *
	 * @param string $public_id UUID.
	 * @return void
	 */
	public function render( $public_id ) {
		$uid = get_current_user_id();
		$s   = ( new SubmissionRepository() )->find_by_public_id( $public_id );
		if ( ! $s || ! Authorization::can_view_submission( $uid, $s ) || ! Authorization::is_team( $uid ) ) {
			AuditLog::log( 'access_denied', 'submission', $public_id, array( 'op' => 'admin_view' ), $uid );
			wp_die( esc_html__( 'Solicitação não encontrada ou sem permissão.', 'eb-credito-rural' ), 404 );
		}
		( new MessageRepository() )->mark_read( (int) $s['id'], $uid );
		$wizard      = new Wizard();
		$saved       = $wizard->saved( $s );
		$transitions = array();
		foreach ( Status::all() as $k => $def ) {
			if ( Authorization::can_change_status( $uid, $s, $k ) ) {
				$transitions[ $k ] = $def;
			}
		}
		View::show(
			'admin/submission',
			array(
				's'           => $s,
				'user'        => get_userdata( (int) $s['user_id'] ),
				'contact'     => ( new CrmContactRepository() )->get_or_create( (int) $s['user_id'] ),
				'saved'       => $saved,
				'slots'       => $wizard->document_slots( $s ),
				'documents'   => ( new DocumentRepository() )->for_submission( (int) $s['id'] ),
				'requests'    => ( new DocumentRequestRepository() )->for_submission( (int) $s['id'] ),
				'history'     => ( new StatusHistoryRepository() )->for_submission( (int) $s['id'] ),
				'messages'    => ( new MessageRepository() )->for_submission( (int) $s['id'] ),
				'checks'      => ( new CheckRepository() )->for_submission( (int) $s['id'] ),
				'check_items' => CheckRepository::defaults(),
				'consents'    => ( new ConsentRepository() )->for_submission( (int) $s['id'] ),
				'transitions' => $transitions,
				'can_edit'    => Authorization::team_can_edit( $uid, $s ),
				'analysts'    => get_users(
					array(
					'capability' => Capabilities::CAP_VIEW,
					'fields'     => array( 'ID', 'display_name' ),
					)
				),
				'matrix'      => DocumentMatrix::all(),
				'notice'      => isset( $_GET['ebcr_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['ebcr_notice'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- mensagem informativa.
				'notice_type' => isset( $_GET['ebcr_type'] ) && 'error' === $_GET['ebcr_type'] ? 'error' : 'success', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idem.
			)
		);
	}
}
