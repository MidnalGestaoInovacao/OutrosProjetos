<?php
/**
 * Ações do painel da equipe no portal (POST para a própria página do portal, action=ebcr_team_*):
 * status, atribuição, carteira, pedido de documento, revisão, mensagem, conferência, dossiê, exportações e CRM.
 * Cada handler: login + capacidade + nonce + autorização por objeto, reutilizando SubmissionService/Crm\Service.
 *
 * Os métodos run_*() fazem o trabalho e devolvem { ok, msg, to } (testáveis); os handle_*() redirecionam.
 *
 * @package EBCR
 */

namespace EBCR\Frontend\Team;

use EBCR\Admin\Reports;
use EBCR\Crm\Service;
use EBCR\Database\CheckRepository;
use EBCR\Database\CrmActivityRepository;
use EBCR\Database\CrmContactRepository;
use EBCR\Database\DocumentRepository;
use EBCR\Database\SubmissionDataRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Forms\SubmissionService;
use EBCR\Reports\Metrics;
use EBCR\Roles\Capabilities;
use EBCR\Security\AuditLog;
use EBCR\Security\Authorization;
use EBCR\Security\Nonces;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Handlers admin_post_ebcr_team_* (despachados pelo FormRouter na página do portal).
 */
// phpcs:disable WordPress.Security.NonceVerification -- todos os handlers verificam o nonce (Nonces::verify) antes de agir; a leitura de $_POST é feita uma vez em post() e sanitizada campo a campo nos run_*().
final class Actions {

	/**
	 * Ações registradas (sufixo de admin_post_ebcr_team_*).
	 *
	 * @var string[]
	 */
	const HANDLERS = array( 'status', 'assign', 'fund', 'request_document', 'review_document', 'message', 'checks', 'dossier', 'export_one', 'export_list', 'crm_contact', 'crm_activity', 'crm_task_done', 'crm_stage', 'crm_export', 'reports_csv' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		foreach ( self::HANDLERS as $a ) {
			add_action( 'admin_post_ebcr_team_' . $a, array( $this, 'handle_' . $a ) );
		}
	}

	// ------------------------------------------------------------------ infraestrutura

	/**
	 * Entrada do formulário (sem barras); cada run_*() sanitiza o que usa.
	 *
	 * @return array
	 */
	private static function post() {
		return (array) wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizado campo a campo nos run_*().
	}

	/**
	 * Resultado de sucesso.
	 *
	 * @param string $msg Mensagem.
	 * @param array  $to  Parâmetros do destino (tela, id…).
	 * @return array
	 */
	private static function done( $msg, array $to ) {
		return array(
			'ok'  => true,
			'msg' => $msg,
			'to'  => $to,
		);
	}

	/**
	 * Resultado de erro.
	 *
	 * @param string $msg Mensagem.
	 * @param array  $to  Parâmetros do destino.
	 * @return array
	 */
	private static function fail( $msg, array $to ) {
		return array(
			'ok'  => false,
			'msg' => $msg,
			'to'  => $to,
		);
	}

	/**
	 * Destino padrão: detalhe da solicitação.
	 *
	 * @param array  $submission Linha.
	 * @param string $tab        Aba.
	 * @return array
	 */
	private static function detail( array $submission, $tab = '' ) {
		$to = array(
			'tela' => 'solicitacao',
			'id'   => $submission['public_id'],
		);
		if ( $tab ) {
			$to['tab'] = $tab;
		}
		return $to;
	}

	/**
	 * Redireciona ao painel com a mensagem do resultado e encerra.
	 *
	 * @param array $r Resultado de um run_*().
	 * @return void
	 */
	private function finish( array $r ) {
		$args                = isset( $r['to'] ) && is_array( $r['to'] ) ? $r['to'] : array();
		$args['ebcr_notice'] = mb_substr( (string) $r['msg'], 0, 300 );
		$args['ebcr_type']   = empty( $r['ok'] ) ? 'error' : 'success';
		wp_safe_redirect( Panel::url( $args ) );
		exit;
	}

	/**
	 * Login + capacidade de ver + nonce; devolve o usuário ou WP_Error.
	 *
	 * @param int    $user_id Usuário.
	 * @param array  $post    Entrada.
	 * @param string $action  Ação do nonce (sem prefixo team_).
	 * @return true|\WP_Error
	 */
	private static function guard( $user_id, array $post, $action ) {
		if ( ! Panel::can_access( $user_id ) ) {
			return new \WP_Error( 'forbidden', __( 'Sem permissão.', 'eb-credito-rural' ) );
		}
		if ( ! Nonces::verify( 'team_' . $action, $post ) ) {
			return new \WP_Error( 'nonce', __( 'Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.', 'eb-credito-rural' ) );
		}
		return true;
	}

	/**
	 * Guard + solicitação do POST (por UUID) que o usuário pode ver.
	 *
	 * @param int    $user_id Usuário.
	 * @param array  $post    Entrada.
	 * @param string $action  Ação do nonce.
	 * @return array|\WP_Error
	 */
	public static function guard_submission( $user_id, array $post, $action ) {
		$g = self::guard( $user_id, $post, $action );
		if ( is_wp_error( $g ) ) {
			return $g;
		}
		$pid = isset( $post['id'] ) ? sanitize_text_field( (string) $post['id'] ) : '';
		$s   = ( new SubmissionRepository() )->find_by_public_id( $pid );
		if ( ! $s || ! Authorization::can_view_submission( $user_id, $s ) ) {
			AuditLog::log( 'access_denied', 'submission', $pid, array( 'op' => 'team_' . $action ), $user_id );
			return new \WP_Error( 'not_found', __( 'Solicitação não encontrada.', 'eb-credito-rural' ) );
		}
		return $s;
	}

	/**
	 * Guard + ficha do CRM do POST (contact_id).
	 *
	 * @param int    $user_id Usuário.
	 * @param array  $post    Entrada.
	 * @param string $action  Ação do nonce.
	 * @return array|\WP_Error
	 */
	public static function guard_contact( $user_id, array $post, $action ) {
		$g = self::guard( $user_id, $post, $action );
		if ( is_wp_error( $g ) ) {
			return $g;
		}
		if ( ! user_can( $user_id, Capabilities::CAP_CRM ) ) {
			return new \WP_Error( 'forbidden', __( 'Sem permissão.', 'eb-credito-rural' ) );
		}
		$contact = ( new CrmContactRepository() )->find( isset( $post['contact_id'] ) ? absint( $post['contact_id'] ) : 0 );
		if ( ! $contact ) {
			return new \WP_Error( 'not_found', __( 'Ficha não encontrada.', 'eb-credito-rural' ) );
		}
		return $contact;
	}

	/**
	 * Destino após falha do guard: detalhe (se o UUID for válido) ou lista.
	 *
	 * @param array $post Entrada.
	 * @return array
	 */
	private static function fallback_to( array $post ) {
		$pid = isset( $post['id'] ) ? sanitize_text_field( (string) $post['id'] ) : '';
		$s   = $pid ? ( new SubmissionRepository() )->find_by_public_id( $pid ) : null;
		return $s ? self::detail( $s ) : array( 'tela' => 'solicitacoes' );
	}

	/**
	 * Texto do POST (linha única).
	 *
	 * @param array  $post Entrada.
	 * @param string $key  Chave.
	 * @return string
	 */
	private static function text( array $post, $key ) {
		return isset( $post[ $key ] ) && is_scalar( $post[ $key ] ) ? sanitize_text_field( (string) $post[ $key ] ) : '';
	}

	/**
	 * Texto do POST (várias linhas).
	 *
	 * @param array  $post Entrada.
	 * @param string $key  Chave.
	 * @return string
	 */
	private static function area( array $post, $key ) {
		return isset( $post[ $key ] ) && is_scalar( $post[ $key ] ) ? sanitize_textarea_field( (string) $post[ $key ] ) : '';
	}

	/**
	 * Envia um CSV ao navegador e encerra.
	 *
	 * @param string $filename Nome do arquivo.
	 * @param string $csv      Conteúdo.
	 * @return void
	 */
	private static function send_csv( $filename, $csv ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV gerado pelo plugin.
		exit;
	}

	/**
	 * Encerra com erro (para exportações, que não redirecionam).
	 *
	 * @param \WP_Error $e Erro.
	 * @return void
	 */
	private function abort( \WP_Error $e ) {
		$this->finish( self::fail( $e->get_error_message(), array( 'tela' => 'visao' ) ) );
	}

	// ------------------------------------------------------------------ solicitação

	/**
	 * Mudança de status (com comentário interno e ao cliente).
	 *
	 * @param int   $user_id Usuário.
	 * @param array $post    Entrada.
	 * @return array
	 */
	public static function run_status( $user_id, array $post ) {
		$s = self::guard_submission( $user_id, $post, 'status' );
		if ( is_wp_error( $s ) ) {
			return self::fail( $s->get_error_message(), self::fallback_to( $post ) );
		}
		$r = SubmissionService::change_status( $user_id, $s, sanitize_key( self::text( $post, 'status' ) ), self::area( $post, 'comment_internal' ), self::area( $post, 'comment_client' ) );
		if ( is_wp_error( $r ) ) {
			return self::fail( $r->get_error_message(), self::detail( $s ) );
		}
		return self::done( __( 'Status atualizado e notificações enviadas.', 'eb-credito-rural' ), self::detail( $s, 'historico' ) );
	}

	/**
	 * Atribuição de analista.
	 *
	 * @param int   $user_id Usuário.
	 * @param array $post    Entrada.
	 * @return array
	 */
	public static function run_assign( $user_id, array $post ) {
		$s = self::guard_submission( $user_id, $post, 'assign' );
		if ( is_wp_error( $s ) ) {
			return self::fail( $s->get_error_message(), self::fallback_to( $post ) );
		}
		$r = SubmissionService::assign( $user_id, $s, isset( $post['analyst_id'] ) ? absint( $post['analyst_id'] ) : 0 );
		if ( is_wp_error( $r ) ) {
			return self::fail( $r->get_error_message(), self::detail( $s ) );
		}
		return self::done( __( 'Analista atribuído.', 'eb-credito-rural' ), self::detail( $s ) );
	}

	/**
	 * Fundo/carteira.
	 *
	 * @param int   $user_id Usuário.
	 * @param array $post    Entrada.
	 * @return array
	 */
	public static function run_fund( $user_id, array $post ) {
		$s = self::guard_submission( $user_id, $post, 'fund' );
		if ( is_wp_error( $s ) ) {
			return self::fail( $s->get_error_message(), self::fallback_to( $post ) );
		}
		if ( ! Authorization::team_can_edit( $user_id, $s ) ) {
			return self::fail( __( 'Sem permissão.', 'eb-credito-rural' ), self::detail( $s ) );
		}
		$fund  = sanitize_key( self::text( $post, 'fund' ) );
		$funds = Options::pairs( 'funds' );
		if ( $fund && ! isset( $funds[ $fund ] ) ) {
			return self::fail( __( 'Carteira inválida.', 'eb-credito-rural' ), self::detail( $s ) );
		}
		( new SubmissionRepository() )->update( (int) $s['id'], array( 'fund' => $fund ) );
		AuditLog::log( 'fund_set', 'submission', $s['public_id'], array( 'fund' => $fund ), $user_id );
		return self::done( __( 'Carteira atualizada.', 'eb-credito-rural' ), self::detail( $s ) );
	}

	/**
	 * Pedido de documento ao cliente.
	 *
	 * @param int   $user_id Usuário.
	 * @param array $post    Entrada.
	 * @return array
	 */
	public static function run_request_document( $user_id, array $post ) {
		$s = self::guard_submission( $user_id, $post, 'request' );
		if ( is_wp_error( $s ) ) {
			return self::fail( $s->get_error_message(), self::fallback_to( $post ) );
		}
		$type = sanitize_key( self::text( $post, 'doc_type' ) );
		$r    = SubmissionService::request_document( $user_id, $s, $type ? $type : 'outro', self::text( $post, 'label' ), self::area( $post, 'note' ), ! empty( $post['set_status'] ) );
		if ( is_wp_error( $r ) ) {
			return self::fail( $r->get_error_message(), self::detail( $s ) );
		}
		return self::done( __( 'Documento solicitado; o cliente foi notificado.', 'eb-credito-rural' ), self::detail( $s, 'documentos' ) );
	}

	/**
	 * Revisão de documento (aceitar/recusar/pendente).
	 *
	 * @param int   $user_id Usuário.
	 * @param array $post    Entrada.
	 * @return array
	 */
	public static function run_review_document( $user_id, array $post ) {
		$s = self::guard_submission( $user_id, $post, 'review' );
		if ( is_wp_error( $s ) ) {
			return self::fail( $s->get_error_message(), self::fallback_to( $post ) );
		}
		$doc = ( new DocumentRepository() )->find_by_public_id( self::text( $post, 'doc' ) );
		if ( ! $doc || (int) $doc['submission_id'] !== (int) $s['id'] ) {
			return self::fail( __( 'Documento não encontrado.', 'eb-credito-rural' ), self::detail( $s, 'documentos' ) );
		}
		$r = SubmissionService::review_document( $user_id, $doc, sanitize_key( self::text( $post, 'result' ) ), self::area( $post, 'note' ) );
		if ( is_wp_error( $r ) ) {
			return self::fail( $r->get_error_message(), self::detail( $s, 'documentos' ) );
		}
		return self::done( __( 'Revisão registrada.', 'eb-credito-rural' ), self::detail( $s, 'documentos' ) );
	}

	/**
	 * Mensagem interna ou ao cliente.
	 *
	 * @param int   $user_id Usuário.
	 * @param array $post    Entrada.
	 * @return array
	 */
	public static function run_message( $user_id, array $post ) {
		$s = self::guard_submission( $user_id, $post, 'message' );
		if ( is_wp_error( $s ) ) {
			return self::fail( $s->get_error_message(), self::fallback_to( $post ) );
		}
		$visibility = 'cliente' === self::text( $post, 'visibility' ) ? 'cliente' : 'interno';
		$r          = SubmissionService::send_message( $user_id, $s, isset( $post['body'] ) && is_scalar( $post['body'] ) ? (string) $post['body'] : '', $visibility );
		if ( is_wp_error( $r ) ) {
			return self::fail( $r->get_error_message(), self::detail( $s, 'mensagens' ) );
		}
		return self::done( 'cliente' === $visibility ? __( 'Mensagem enviada ao cliente.', 'eb-credito-rural' ) : __( 'Nota interna registrada.', 'eb-credito-rural' ), self::detail( $s, 'mensagens' ) );
	}

	/**
	 * Checklist de conferência (ok/alerta/reprovado/n.a. + observação por item).
	 *
	 * @param int   $user_id Usuário.
	 * @param array $post    Entrada.
	 * @return array
	 */
	public static function run_checks( $user_id, array $post ) {
		$s = self::guard_submission( $user_id, $post, 'checks' );
		if ( is_wp_error( $s ) ) {
			return self::fail( $s->get_error_message(), self::fallback_to( $post ) );
		}
		if ( ! Authorization::team_can_edit( $user_id, $s ) ) {
			return self::fail( __( 'Sem permissão.', 'eb-credito-rural' ), self::detail( $s, 'conferencia' ) );
		}
		$repo   = new CheckRepository();
		$checks = isset( $post['check'] ) && is_array( $post['check'] ) ? $post['check'] : array();
		$saved  = 0;
		foreach ( array_keys( CheckRepository::defaults() ) as $key ) {
			if ( ! isset( $checks[ $key ] ) || ! is_array( $checks[ $key ] ) ) {
				continue;
			}
			$repo->save( (int) $s['id'], $key, sanitize_key( isset( $checks[ $key ]['result'] ) ? (string) $checks[ $key ]['result'] : 'na' ), sanitize_textarea_field( isset( $checks[ $key ]['note'] ) ? (string) $checks[ $key ]['note'] : '' ), $user_id );
			++$saved;
		}
		AuditLog::log( 'checks_saved', 'submission', $s['public_id'], array( 'items' => $saved ), $user_id );
		return self::done( __( 'Checklist salvo.', 'eb-credito-rural' ), self::detail( $s, 'conferencia' ) );
	}

	/**
	 * Handler: status.
	 *
	 * @return void
	 */
	public function handle_status() {
		$this->finish( self::run_status( get_current_user_id(), self::post() ) );
	}

	/**
	 * Handler: atribuição.
	 *
	 * @return void
	 */
	public function handle_assign() {
		$this->finish( self::run_assign( get_current_user_id(), self::post() ) );
	}

	/**
	 * Handler: carteira.
	 *
	 * @return void
	 */
	public function handle_fund() {
		$this->finish( self::run_fund( get_current_user_id(), self::post() ) );
	}

	/**
	 * Handler: pedido de documento.
	 *
	 * @return void
	 */
	public function handle_request_document() {
		$this->finish( self::run_request_document( get_current_user_id(), self::post() ) );
	}

	/**
	 * Handler: revisão de documento.
	 *
	 * @return void
	 */
	public function handle_review_document() {
		$this->finish( self::run_review_document( get_current_user_id(), self::post() ) );
	}

	/**
	 * Handler: mensagem.
	 *
	 * @return void
	 */
	public function handle_message() {
		$this->finish( self::run_message( get_current_user_id(), self::post() ) );
	}

	/**
	 * Handler: checklist.
	 *
	 * @return void
	 */
	public function handle_checks() {
		$this->finish( self::run_checks( get_current_user_id(), self::post() ) );
	}

	/**
	 * Handler: dossiê em PDF (download).
	 *
	 * @return void
	 */
	public function handle_dossier() {
		$uid = get_current_user_id();
		$s   = self::guard_submission( $uid, self::post(), 'dossier' );
		if ( is_wp_error( $s ) ) {
			$this->abort( $s );
		}
		AuditLog::log( 'dossier', 'submission', $s['public_id'], array( 'origin' => 'portal' ), $uid );
		\EBCR\Reports\Dossier::download( $s );
	}

	/**
	 * Handler: exportação de uma solicitação (JSON).
	 *
	 * @return void
	 */
	public function handle_export_one() {
		$uid = get_current_user_id();
		$s   = self::guard_submission( $uid, self::post(), 'export' );
		if ( is_wp_error( $s ) ) {
			$this->abort( $s );
		}
		if ( ! user_can( $uid, Capabilities::CAP_EXPORT ) ) {
			$this->abort( new \WP_Error( 'forbidden', __( 'Sem permissão para exportar.', 'eb-credito-rural' ) ) );
		}
		$data = array(
			'solicitacao' => $s,
			'dados'       => ( new SubmissionDataRepository() )->all( (int) $s['id'] ),
			'imoveis'     => ( new \EBCR\Database\PropertyRepository() )->for_submission( (int) $s['id'] ),
			'garantias'   => ( new \EBCR\Database\GuaranteeRepository() )->for_submission( (int) $s['id'] ),
			'documentos'  => array_map(
				static function ( $d ) {
					unset( $d['stored_name'], $d['storage_dir'] );
					return $d;
				},
				( new DocumentRepository() )->for_submission( (int) $s['id'] )
			),
		);
		AuditLog::log( 'export', 'submission', $s['public_id'], array( 'format' => 'json' ), $uid );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $s['protocol'] ? $s['protocol'] : $s['public_id'] ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON.
		exit;
	}

	/**
	 * Handler: exportação CSV da lista (filtro atual).
	 *
	 * @return void
	 */
	public function handle_export_list() {
		$uid  = get_current_user_id();
		$post = self::post();
		$g    = self::guard( $uid, $post, 'export_list' );
		if ( is_wp_error( $g ) ) {
			$this->abort( $g );
		}
		if ( ! user_can( $uid, Capabilities::CAP_EXPORT ) ) {
			$this->abort( new \WP_Error( 'forbidden', __( 'Sem permissão para exportar.', 'eb-credito-rural' ) ) );
		}
		$args             = Screens::list_args( $post );
		$args['per_page'] = 5000;
		$args['page']     = 1;
		list( $items )    = Screens::query_submissions( $uid, $args );
		$csv              = Export::submissions_csv( $uid, $items );
		AuditLog::log(
			'export',
			'submission',
			'',
			array(
				'format' => 'csv',
				'count'  => count( $items ),
				'origin' => 'portal',
			),
			$uid
		);
		self::send_csv( 'solicitacoes-' . gmdate( 'Ymd-His' ) . '.csv', $csv );
	}

	// ------------------------------------------------------------------ CRM

	/**
	 * Salva a ficha do cliente.
	 *
	 * @param int   $user_id Usuário.
	 * @param array $post    Entrada.
	 * @return array
	 */
	public static function run_crm_contact( $user_id, array $post ) {
		$c = self::guard_contact( $user_id, $post, 'crm_contact' );
		if ( is_wp_error( $c ) ) {
			return self::fail( $c->get_error_message(), array( 'tela' => 'crm' ) );
		}
		$to = array(
			'tela' => 'crm',
			'sub'  => 'contato',
			'id'   => (int) $c['id'],
		);
		$r  = Service::update_contact(
			$user_id,
			(int) $c['id'],
			array(
				'phone'          => self::text( $post, 'phone' ),
				'whatsapp'       => self::text( $post, 'whatsapp' ),
				'lead_source'    => self::text( $post, 'lead_source' ),
				'tags'           => self::text( $post, 'tags' ),
				'stage'          => self::text( $post, 'stage' ),
				'owner_id'       => (int) self::text( $post, 'owner_id' ),
				'next_action'    => self::text( $post, 'next_action' ),
				'next_action_at' => self::text( $post, 'next_action_at' ),
				'notes'          => self::area( $post, 'notes' ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return self::fail( $r->get_error_message(), $to );
		}
		return self::done( __( 'Ficha atualizada.', 'eb-credito-rural' ), $to );
	}

	/**
	 * Registra atividade ou tarefa.
	 *
	 * @param int   $user_id Usuário.
	 * @param array $post    Entrada.
	 * @return array
	 */
	public static function run_crm_activity( $user_id, array $post ) {
		$c = self::guard_contact( $user_id, $post, 'crm_activity' );
		if ( is_wp_error( $c ) ) {
			return self::fail( $c->get_error_message(), array( 'tela' => 'crm' ) );
		}
		$to = array(
			'tela' => 'crm',
			'sub'  => 'contato',
			'id'   => (int) $c['id'],
			'tab'  => 'atividades',
		);
		$r  = Service::add_activity(
			$user_id,
			(int) $c['id'],
			array(
				'type'          => self::text( $post, 'type' ),
				'description'   => self::area( $post, 'description' ),
				'due_at'        => self::text( $post, 'due_at' ),
				'assignee_id'   => (int) self::text( $post, 'assignee_id' ),
				'submission_id' => (int) self::text( $post, 'submission_id' ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return self::fail( $r->get_error_message(), $to );
		}
		return self::done( Service::TYPE_TASK === self::text( $post, 'type' ) ? __( 'Tarefa criada.', 'eb-credito-rural' ) : __( 'Atividade registrada.', 'eb-credito-rural' ), $to );
	}

	/**
	 * Conclui tarefa.
	 *
	 * @param int   $user_id Usuário.
	 * @param array $post    Entrada.
	 * @return array
	 */
	public static function run_crm_task_done( $user_id, array $post ) {
		$c = self::guard_contact( $user_id, $post, 'crm_task' );
		if ( is_wp_error( $c ) ) {
			return self::fail( $c->get_error_message(), array( 'tela' => 'crm' ) );
		}
		$to   = array(
			'tela' => 'crm',
			'sub'  => 'contato',
			'id'   => (int) $c['id'],
			'tab'  => 'atividades',
		);
		$task = ( new CrmActivityRepository() )->find( isset( $post['activity_id'] ) ? absint( $post['activity_id'] ) : 0 );
		if ( ! $task || (int) $task['contact_id'] !== (int) $c['id'] ) {
			return self::fail( __( 'Tarefa não encontrada.', 'eb-credito-rural' ), $to );
		}
		$r = Service::complete_task( $user_id, (int) $task['id'] );
		if ( is_wp_error( $r ) ) {
			return self::fail( $r->get_error_message(), $to );
		}
		return self::done( __( 'Tarefa concluída.', 'eb-credito-rural' ), $to );
	}

	/**
	 * Muda o estágio (fallback do quadro sem JavaScript).
	 *
	 * @param int   $user_id Usuário.
	 * @param array $post    Entrada.
	 * @return array
	 */
	public static function run_crm_stage( $user_id, array $post ) {
		$c = self::guard_contact( $user_id, $post, 'crm_stage' );
		if ( is_wp_error( $c ) ) {
			return self::fail( $c->get_error_message(), array( 'tela' => 'crm' ) );
		}
		$to = array(
			'tela' => 'crm',
			'sub'  => 'quadro',
		);
		$r  = Service::change_stage( $user_id, (int) $c['id'], self::text( $post, 'stage' ) );
		if ( is_wp_error( $r ) ) {
			return self::fail( $r->get_error_message(), $to );
		}
		return self::done( __( 'Estágio atualizado.', 'eb-credito-rural' ), $to );
	}

	/**
	 * Handler: ficha.
	 *
	 * @return void
	 */
	public function handle_crm_contact() {
		$this->finish( self::run_crm_contact( get_current_user_id(), self::post() ) );
	}

	/**
	 * Handler: atividade/tarefa.
	 *
	 * @return void
	 */
	public function handle_crm_activity() {
		$this->finish( self::run_crm_activity( get_current_user_id(), self::post() ) );
	}

	/**
	 * Handler: concluir tarefa.
	 *
	 * @return void
	 */
	public function handle_crm_task_done() {
		$this->finish( self::run_crm_task_done( get_current_user_id(), self::post() ) );
	}

	/**
	 * Handler: estágio (fallback sem JS).
	 *
	 * @return void
	 */
	public function handle_crm_stage() {
		$this->finish( self::run_crm_stage( get_current_user_id(), self::post() ) );
	}

	/**
	 * Handler: exportação CSV do CRM (filtro atual; exige ebcr_export).
	 *
	 * @return void
	 */
	public function handle_crm_export() {
		$uid  = get_current_user_id();
		$post = self::post();
		$g    = self::guard( $uid, $post, 'crm_export' );
		if ( is_wp_error( $g ) ) {
			$this->abort( $g );
		}
		if ( ! user_can( $uid, Capabilities::CAP_CRM ) || ! user_can( $uid, Capabilities::CAP_EXPORT ) ) {
			$this->abort( new \WP_Error( 'forbidden', __( 'Sem permissão para exportar.', 'eb-credito-rural' ) ) );
		}
		$rows = Service::export_rows( Screens::crm_args( $post ) );
		AuditLog::log(
			'export',
			'crm_contact',
			'',
			array(
				'format' => 'csv',
				'count'  => count( $rows ) - 1,
				'origin' => 'portal',
			),
			$uid
		);
		self::send_csv( 'crm-contatos-' . gmdate( 'Ymd-His' ) . '.csv', Service::csv_string( $rows ) );
	}

	// ------------------------------------------------------------------ relatórios

	/**
	 * Handler: CSV dos relatórios (mesmos blocos da tela; exige painel + exportação).
	 *
	 * @return void
	 */
	public function handle_reports_csv() {
		$uid  = get_current_user_id();
		$post = self::post();
		$g    = self::guard( $uid, $post, 'reports_csv' );
		if ( is_wp_error( $g ) ) {
			$this->abort( $g );
		}
		if ( ! user_can( $uid, Capabilities::CAP_DASHBOARD ) || ! user_can( $uid, Capabilities::CAP_EXPORT ) ) {
			$this->abort( new \WP_Error( 'forbidden', __( 'Sem permissão para exportar.', 'eb-credito-rural' ) ) );
		}
		$filters = Metrics::normalize( Reports::read_filters( $post ), $uid );
		AuditLog::log(
			'export',
			'reports',
			'',
			array(
				'format'  => 'csv',
				'filters' => array_filter( $filters ),
				'origin'  => 'portal',
			),
			$uid
		);
		self::send_csv( 'relatorios-' . gmdate( 'Ymd-His' ) . '.csv', Reports::csv( $filters ) );
	}

	/**
	 * Nome do usuário (atalho para templates/CSV).
	 *
	 * @param int $user_id ID.
	 * @return string
	 */
	public static function user_name( $user_id ) {
		return $user_id ? Helpers::user_name( (int) $user_id ) : '';
	}
}
