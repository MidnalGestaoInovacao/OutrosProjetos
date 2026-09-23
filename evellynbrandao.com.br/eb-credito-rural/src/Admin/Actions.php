<?php
/**
 * Ações administrativas via admin-post (status, atribuição, pedido de documento, revisão, mensagem, checklist, exportação).
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Database\CheckRepository;
use EBCR\Database\DocumentRepository;
use EBCR\Database\SubmissionDataRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Forms\SubmissionService;
use EBCR\Roles\Capabilities;
use EBCR\Security\AuditLog;
use EBCR\Security\Authorization;
use EBCR\Security\Nonces;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Cada handler: nonce + capacidade + autorização por solicitação.
 */
// phpcs:disable WordPress.Security.NonceVerification -- todos os handlers verificam o nonce (Nonces::verify/check_admin_referer) antes de ler a entrada.
final class Actions {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		foreach ( array( 'status', 'assign', 'request_document', 'review_document', 'message', 'checks', 'export_one', 'fund', 'dossier' ) as $a ) {
			add_action( 'admin_post_ebcr_admin_' . $a, array( $this, 'handle_' . $a ) );
		}
	}

	/**
	 * Volta ao detalhe com mensagem.
	 *
	 * @param string $public_id UUID.
	 * @param string $msg       Mensagem.
	 * @param string $type      success|error.
	 * @return void
	 */
	private function back( $public_id, $msg, $type = 'success' ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'ebcr-submissions',
					'view'        => rawurlencode( $public_id ),
					'ebcr_notice' => rawurlencode( $msg ),
					'ebcr_type'   => $type,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Lê a solicitação do POST com verificação de nonce/capacidade.
	 *
	 * @param string $action Ação do nonce.
	 * @return array
	 */
	private function guard( $action ) {
		if ( ! current_user_can( Capabilities::CAP_VIEW ) || ! Nonces::verify( $action ) ) {
			wp_die( esc_html__( 'Sem permissão ou sessão expirada.', 'eb-credito-rural' ), 403 );
		}
		$pid = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$s   = ( new SubmissionRepository() )->find_by_public_id( $pid );
		if ( ! $s || ! Authorization::can_view_submission( get_current_user_id(), $s ) ) {
			wp_die( esc_html__( 'Solicitação não encontrada.', 'eb-credito-rural' ), 404 );
		}
		return $s;
	}

	/**
	 * Mudança de status.
	 *
	 * @return void
	 */
	public function handle_status() {
		$s = $this->guard( 'admin_status' );
		$r = SubmissionService::change_status( get_current_user_id(), $s, isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '', isset( $_POST['comment_internal'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment_internal'] ) ) : '', isset( $_POST['comment_client'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment_client'] ) ) : '' );
		$this->back( $s['public_id'], is_wp_error( $r ) ? $r->get_error_message() : __( 'Status atualizado e notificações enviadas.', 'eb-credito-rural' ), is_wp_error( $r ) ? 'error' : 'success' );
	}

	/**
	 * Atribuição.
	 *
	 * @return void
	 */
	public function handle_assign() {
		$s = $this->guard( 'admin_assign' );
		$r = SubmissionService::assign( get_current_user_id(), $s, isset( $_POST['analyst_id'] ) ? absint( $_POST['analyst_id'] ) : 0 );
		$this->back( $s['public_id'], is_wp_error( $r ) ? $r->get_error_message() : __( 'Analista atribuído.', 'eb-credito-rural' ), is_wp_error( $r ) ? 'error' : 'success' );
	}

	/**
	 * Vincula a solicitação a um fundo/carteira.
	 *
	 * @return void
	 */
	public function handle_fund() {
		$s = $this->guard( 'admin_fund' );
		if ( ! current_user_can( Capabilities::CAP_EDIT ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'eb-credito-rural' ), 403 );
		}
		$fund  = isset( $_POST['fund'] ) ? sanitize_key( wp_unslash( $_POST['fund'] ) ) : '';
		$funds = \EBCR\Support\Options::pairs( 'funds' );
		if ( $fund && ! isset( $funds[ $fund ] ) ) {
			$this->back( $s['public_id'], __( 'Carteira inválida.', 'eb-credito-rural' ), 'error' );
		}
		( new SubmissionRepository() )->update( (int) $s['id'], array( 'fund' => $fund ) );
		\EBCR\Security\AuditLog::log( 'fund_set', 'submission', $s['public_id'], array( 'fund' => $fund ) );
		$this->back( $s['public_id'], __( 'Carteira atualizada.', 'eb-credito-rural' ) );
	}

	/**
	 * Dossiê em PDF para o comitê.
	 *
	 * @return void
	 */
	public function handle_dossier() {
		$s = $this->guard( 'admin_dossier' );
		\EBCR\Security\AuditLog::log( 'dossier', 'submission', $s['public_id'], array() );
		\EBCR\Reports\Dossier::download( $s );
	}

	/**
	 * Pedido de documento.
	 *
	 * @return void
	 */
	public function handle_request_document() {
		$s = $this->guard( 'admin_request' );
		$r = SubmissionService::request_document( get_current_user_id(), $s, isset( $_POST['doc_type'] ) ? sanitize_key( wp_unslash( $_POST['doc_type'] ) ) : 'outro', isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '', isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '', ! empty( $_POST['set_status'] ) );
		$this->back( $s['public_id'], is_wp_error( $r ) ? $r->get_error_message() : __( 'Documento solicitado; o cliente foi notificado.', 'eb-credito-rural' ), is_wp_error( $r ) ? 'error' : 'success' );
	}

	/**
	 * Revisão de documento.
	 *
	 * @return void
	 */
	public function handle_review_document() {
		$s   = $this->guard( 'admin_review' );
		$doc = ( new DocumentRepository() )->find_by_public_id( isset( $_POST['doc'] ) ? sanitize_text_field( wp_unslash( $_POST['doc'] ) ) : '' );
		if ( ! $doc || (int) $doc['submission_id'] !== (int) $s['id'] ) {
			$this->back( $s['public_id'], __( 'Documento não encontrado.', 'eb-credito-rural' ), 'error' );
		}
		$r = SubmissionService::review_document( get_current_user_id(), $doc, isset( $_POST['result'] ) ? sanitize_key( wp_unslash( $_POST['result'] ) ) : '', isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '' );
		$this->back( $s['public_id'], is_wp_error( $r ) ? $r->get_error_message() : __( 'Revisão registrada.', 'eb-credito-rural' ), is_wp_error( $r ) ? 'error' : 'success' );
	}

	/**
	 * Mensagem (interna ou ao cliente).
	 *
	 * @return void
	 */
	public function handle_message() {
		$s = $this->guard( 'admin_message' );
		$r = SubmissionService::send_message( get_current_user_id(), $s, isset( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : '', isset( $_POST['visibility'] ) ? sanitize_key( wp_unslash( $_POST['visibility'] ) ) : 'interno' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizado no serviço.
		$this->back( $s['public_id'], is_wp_error( $r ) ? $r->get_error_message() : __( 'Mensagem registrada.', 'eb-credito-rural' ), is_wp_error( $r ) ? 'error' : 'success' );
	}

	/**
	 * Checklist de conferência.
	 *
	 * @return void
	 */
	public function handle_checks() {
		$s = $this->guard( 'admin_checks' );
		if ( ! Authorization::team_can_edit( get_current_user_id(), $s ) ) {
			$this->back( $s['public_id'], __( 'Sem permissão.', 'eb-credito-rural' ), 'error' );
		}
		$repo   = new CheckRepository();
		$checks = isset( $_POST['check'] ) && is_array( $_POST['check'] ) ? wp_unslash( $_POST['check'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizado abaixo.
		foreach ( CheckRepository::defaults() as $key => $label ) {
			if ( ! isset( $checks[ $key ] ) || ! is_array( $checks[ $key ] ) ) {
				continue;
			}
			$repo->save( (int) $s['id'], $key, sanitize_key( $checks[ $key ]['result'] ?? 'na' ), sanitize_textarea_field( $checks[ $key ]['note'] ?? '' ), get_current_user_id() );
		}
		AuditLog::log( 'checks_saved', 'submission', $s['public_id'], array(), get_current_user_id() );
		$this->back( $s['public_id'], __( 'Checklist salvo.', 'eb-credito-rural' ) );
	}

	/**
	 * Exportação de uma solicitação (JSON).
	 *
	 * @return void
	 */
	public function handle_export_one() {
		$s = $this->guard( 'admin_export' );
		if ( ! current_user_can( Capabilities::CAP_EXPORT ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'eb-credito-rural' ), 403 );
		}
		$data = array(
			'solicitacao' => $s,
			'dados'       => ( new SubmissionDataRepository() )->all( (int) $s['id'] ),
			'imoveis'     => ( new \EBCR\Database\PropertyRepository() )->for_submission( (int) $s['id'] ),
			'garantias'   => ( new \EBCR\Database\GuaranteeRepository() )->for_submission( (int) $s['id'] ),
			'documentos'  => array_map(
				static function ( $d ) {
					unset( $d['stored_name'], $d['storage_dir'] );
					return $d; },
				( new DocumentRepository() )->for_submission( (int) $s['id'] )
			),
		);
		AuditLog::log( 'export', 'submission', $s['public_id'], array( 'format' => 'json' ) );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . ( $s['protocol'] ? $s['protocol'] : $s['public_id'] ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON.
		exit;
	}

	/**
	 * Exporta CSV de várias solicitações (chamado pela lista).
	 *
	 * @param string[] $public_ids UUIDs.
	 * @return void
	 */
	public static function export_csv( array $public_ids ) {
		$repo = new SubmissionRepository();
		$data = new SubmissionDataRepository();
		$rows = array( array( 'protocolo', 'status', 'cliente', 'email', 'tipo', 'documento', 'valor', 'finalidade', 'prazo_meses', 'uf', 'analista', 'enviada_em', 'atualizada_em' ) );
		$uid  = get_current_user_id();
		foreach ( $public_ids as $pid ) {
			$s = $repo->find_by_public_id( $pid );
			if ( ! $s || ! Authorization::can_view_submission( $uid, $s ) ) {
				continue;
			}
			$u      = get_userdata( (int) $s['user_id'] );
			$ident  = $data->get( (int) $s['id'], 'identificacao' );
			$rows[] = array( $s['protocol'], Status::label( $s['status'] ), $u ? $u->display_name : '', $u ? $u->user_email : '', $s['person_type'], isset( $ident['cnpj'] ) && $ident['cnpj'] ? Helpers::mask_document( $ident['cnpj'] ) : ( isset( $ident['cpf'] ) ? Helpers::mask_document( $ident['cpf'] ) : '' ), $s['requested_amount'], $s['purpose'], $s['term_months'], isset( $ident['uf'] ) ? $ident['uf'] : '', $s['assigned_to'] ? Helpers::user_name( (int) $s['assigned_to'] ) : '', $s['submitted_at'], $s['updated_at'] );
		}
		AuditLog::log(
			'export',
			'submission',
			'',
			array(
				'format' => 'csv',
				'count'  => count( $rows ) - 1,
			)
		);
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="solicitacoes-' . gmdate( 'Ymd-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- saída padrão.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- BOM para Excel.
		foreach ( $rows as $r ) {
			fputcsv(
				$out,
				array_map(
					static function ( $v ) {
						return is_string( $v ) && preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v;
					},
					$r
				),
				';'
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- idem.
		exit;
	}
}
