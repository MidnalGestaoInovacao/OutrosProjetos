<?php
/**
 * Assinatura eletrônica simples no portal: texto do documento, confirmação de nome/CPF, código por e-mail, PDF assinado
 * com evidências (IP, data/hora, hash) que entra na lista de documentos. Entregue pelo módulo "Assinatura eletrônica".
 *
 * @package EBCR
 */

namespace EBCR\Esign;

use EBCR\Database\DocumentRepository;
use EBCR\Database\SignatureRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Files\DownloadController;
use EBCR\Frontend\Portal;
use EBCR\Security\AuditLog;
use EBCR\Security\Authorization;
use EBCR\Security\Honeypot;
use EBCR\Security\Nonces;
use EBCR\Support\Helpers;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks do portal (view "assinar", ações admin_post_ebcr_esign_*), REST opcional e helpers para o admin (selo/evidências).
 */
// phpcs:disable WordPress.Security.NonceVerification -- handlers verificam o nonce (guard) antes de ler a entrada; a view só lê parâmetros de navegação.
final class Esign {

	/**
	 * Nome da view no portal (?ebcr_view=assinar).
	 */
	const VIEW = 'assinar';

	/**
	 * Ação do nonce dos formulários.
	 */
	const NONCE = 'esign';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_ebcr_esign_request', array( $this, 'handle_request' ) );
		add_action( 'admin_post_ebcr_esign_sign', array( $this, 'handle_sign' ) );
		add_action( 'admin_post_ebcr_esign_cancel', array( $this, 'handle_cancel' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'ebcr_rest_routes', array( $this, 'rest_routes' ) );
	}

	// ------------------------------------------------------------------ assets

	/**
	 * Registra CSS/JS e enfileira quando a tela de assinatura está sendo exibida.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style( 'ebcr-esign', EBCR_URL . 'assets/css/esign.css', array(), EBCR_VERSION );
		wp_register_script( 'ebcr-esign', EBCR_URL . 'assets/js/esign.js', array( 'ebcr-portal' ), EBCR_VERSION, true );
		$view = isset( $_GET['ebcr_view'] ) ? sanitize_key( wp_unslash( $_GET['ebcr_view'] ) ) : '';
		global $post;
		if ( self::VIEW === $view && $post instanceof \WP_Post && has_shortcode( $post->post_content, 'ebcr_portal' ) ) {
			self::enqueue();
		}
	}

	/**
	 * Enfileira os assets da assinatura (também funciona tarde, durante o render: o WP imprime no rodapé).
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! wp_style_is( 'ebcr-esign', 'registered' ) ) {
			wp_register_style( 'ebcr-esign', EBCR_URL . 'assets/css/esign.css', array(), EBCR_VERSION );
		}
		if ( ! wp_script_is( 'ebcr-esign', 'registered' ) ) {
			wp_register_script( 'ebcr-esign', EBCR_URL . 'assets/js/esign.js', array( 'ebcr-portal' ), EBCR_VERSION, true );
		}
		wp_enqueue_style( 'ebcr-esign' );
		wp_enqueue_script( 'ebcr-esign' );
	}

	/**
	 * CSS do selo/evidências nas telas do plugin no admin.
	 *
	 * @param string $hook Sufixo da tela.
	 * @return void
	 */
	public function admin_assets( $hook ) {
		if ( false !== strpos( (string) $hook, 'ebcr' ) ) {
			wp_enqueue_style( 'ebcr-esign', EBCR_URL . 'assets/css/esign.css', array(), EBCR_VERSION );
		}
	}

	// ------------------------------------------------------------------ helpers para templates

	/**
	 * O tipo é assinável nesta solicitação?
	 *
	 * @param string $doc_type   Tipo.
	 * @param array  $submission Solicitação.
	 * @return bool
	 */
	public static function signable( $doc_type, array $submission ) {
		return Service::is_signable( $doc_type, $submission );
	}

	/**
	 * URL da tela de assinatura.
	 *
	 * @param array  $submission Solicitação.
	 * @param string $doc_type   Tipo.
	 * @return string
	 */
	public static function sign_url( array $submission, $doc_type ) {
		return Helpers::portal_url(
			array(
				'ebcr_view' => self::VIEW,
				'id'        => $submission['public_id'],
				'doc'       => sanitize_key( $doc_type ),
			)
		);
	}

	/**
	 * Botão "Assinar eletronicamente" (HTML escapado) para um slot; vazio quando o tipo não é assinável.
	 * Enfileira o CSS do módulo quando exibido.
	 *
	 * @param array  $submission Solicitação.
	 * @param string $doc_type   Tipo.
	 * @return string
	 */
	public static function sign_link( array $submission, $doc_type ) {
		if ( ! self::signable( $doc_type, $submission ) ) {
			return '';
		}
		self::enqueue();
		$signed = Service::signed_documents( $submission, $doc_type );
		$label  = $signed ? __( 'Assinar novamente', 'eb-credito-rural' ) : __( 'Assinar eletronicamente', 'eb-credito-rural' );
		return sprintf(
			'<p class="ebcr-esign-cta"><a class="ebcr-btn ebcr-btn--small ebcr-esign-btn" href="%s">%s</a> <span class="ebcr-muted ebcr-small">%s</span></p>',
			esc_url( self::sign_url( $submission, $doc_type ) ),
			esc_html( $label ),
			esc_html( $signed ? __( 'já assinado eletronicamente; se preferir, envie outro arquivo abaixo', 'eb-credito-rural' ) : __( 'sem imprimir: código por e-mail, IP e data/hora ficam registrados no PDF. Ou envie o arquivo assinado abaixo.', 'eb-credito-rural' ) )
		);
	}

	// ------------------------------------------------------------------ view

	/**
	 * Renderiza a tela de assinatura (?ebcr_view=assinar&id=<uuid>&doc=<tipo>) para o cliente logado.
	 *
	 * @param int $user_id Usuário atual (cliente).
	 * @return string
	 */
	public static function render( $user_id ) {
		$id       = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		$doc_type = isset( $_GET['doc'] ) ? sanitize_key( wp_unslash( $_GET['doc'] ) ) : '';
		$msg      = isset( $_GET['esign_msg'] ) ? sanitize_key( wp_unslash( $_GET['esign_msg'] ) ) : '';
		$s        = ( new SubmissionRepository() )->find_by_public_id( $id );
		if ( ! $s || ! Authorization::owns( $user_id, $s ) ) {
			AuditLog::log( 'access_denied', 'submission', $id, array( 'op' => 'esign_view' ), $user_id );
			return '<div class="ebcr-alert ebcr-alert--error">' . esc_html__( 'Solicitação não encontrada.', 'eb-credito-rural' ) . '</div>';
		}
		self::enqueue();
		$back        = self::back_url( $s );
		$flash       = Portal::unflash( self::flash_key( $s, $doc_type ) );
		$errors      = isset( $flash['errors'] ) ? (array) $flash['errors'] : array();
		$values      = isset( $flash['values'] ) ? (array) $flash['values'] : array();
		$ok          = Service::eligibility( $user_id, $s, $doc_type );
		$data        = array(
			's'            => $s,
			'doc_type'     => $doc_type,
			'title'        => Service::title( $doc_type ),
			'back_url'     => $back,
			'sign_url'     => self::sign_url( $s, $doc_type ),
			'errors'       => $errors,
			'values'       => $values,
			'notice'       => self::message( $msg ),
			'blocker'      => is_wp_error( $ok ) ? $ok->get_error_message() : '',
			'blocker_code' => is_wp_error( $ok ) ? $ok->get_error_code() : '',
			'step1_url'    => Helpers::portal_url(
				array(
					'ebcr_view' => 'formulario',
					'id'        => $s['public_id'],
					'etapa'     => 1,
				)
			),
			'ttl_minutes'  => (int) ( Service::CODE_TTL / 60 ),
			'max_attempts' => Service::MAX_ATTEMPTS,
		);
		$signed_docs = Service::signed_documents( $s, $doc_type );
		if ( 'signed' === $msg && $signed_docs ) {
			// Conclusão: mostra o resumo mesmo que a elegibilidade já não valha (ex.: a assinatura atendeu o último pedido aberto).
			return View::render(
				'portal/esign',
				array_merge(
					$data,
					array(
						'blocker'      => '',
						'blocker_code' => '',
						'state'        => 'done',
						'signed_docs'  => $signed_docs,
					)
				)
			);
		}
		if ( is_wp_error( $ok ) ) {
			return View::render( 'portal/esign', $data );
		}
		$signer  = Service::signer( $s );
		$text    = Service::text( $doc_type, $s );
		$pending = Service::pending( $user_id, $s, $doc_type );
		if ( $pending && ! hash_equals( $pending['text_hash'], Service::text_hash( $text ) ) ) {
			// O texto mudou depois do envio do código: invalida e pede um novo.
			Service::cancel( $user_id, $s, $doc_type );
			$pending        = null;
			$data['notice'] = self::message( 'text_changed' );
		}
		$state = 'signed' === $msg ? 'done' : ( $pending ? 'code' : 'intro' );
		return View::render(
			'portal/esign',
			array_merge(
				$data,
				array(
					'state'        => $state,
					'text'         => $text,
					'signer'       => $signer,
					'email_masked' => Service::mask_email( $signer['email'] ),
					'pending'      => $pending,
					'expires_in'   => $pending ? max( 0, $pending['expires'] - time() ) : 0,
					'signed_docs'  => $signed_docs,
					'can_edit'     => Status::client_can_edit( $s['status'] ),
				)
			)
		);
	}

	/**
	 * Para onde voltar: etapa 6 do formulário (se editável) ou a tela da solicitação.
	 *
	 * @param array $submission Solicitação.
	 * @return string
	 */
	public static function back_url( array $submission ) {
		if ( Status::client_can_edit( $submission['status'] ) ) {
			return Helpers::portal_url(
				array(
					'ebcr_view' => 'formulario',
					'id'        => $submission['public_id'],
					'etapa'     => 6,
				)
			);
		}
		return Helpers::portal_url(
			array(
				'ebcr_view' => 'solicitacao',
				'id'        => $submission['public_id'],
			)
		);
	}

	/**
	 * Mensagens da tela (esign_msg).
	 *
	 * @param string $code Código.
	 * @return array|null type, text.
	 */
	public static function message( $code ) {
		$map = array(
			'code_sent'    => array( 'success', __( 'Enviamos um código de 6 dígitos para o seu e-mail. Ele vale por 10 minutos.', 'eb-credito-rural' ) ),
			'signed'       => array( 'success', __( 'Documento assinado eletronicamente. O PDF com as evidências já está na sua lista de documentos.', 'eb-credito-rural' ) ),
			'cancelled'    => array( 'info', __( 'Assinatura cancelada. Você pode recomeçar quando quiser.', 'eb-credito-rural' ) ),
			'error'        => array( 'error', __( 'Não foi possível concluir. Veja o motivo abaixo.', 'eb-credito-rural' ) ),
			'text_changed' => array( 'warning', __( 'O texto do documento foi atualizado depois do envio do código. Leia o novo texto e peça outro código.', 'eb-credito-rural' ) ),
		);
		return isset( $map[ $code ] ) ? array(
			'type' => $map[ $code ][0],
			'text' => $map[ $code ][1],
		) : null;
	}

	// ------------------------------------------------------------------ ações (POST na página do portal → FormRouter → admin_post_ebcr_esign_*)

	/**
	 * Envia (ou reenvia) o código.
	 *
	 * @return void
	 */
	public function handle_request() {
		list( $uid, $s, $doc_type ) = $this->guard();
		$hp                         = Honeypot::check( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- só lê campos técnicos do honeypot.
		if ( true !== $hp ) {
			$this->fail( $s, $doc_type, array( '_' => __( 'Não foi possível validar o envio. Recarregue a página e tente novamente.', 'eb-credito-rural' ) ) );
		}
		$r = Service::issue_code( $uid, $s, $doc_type );
		if ( is_wp_error( $r ) ) {
			$this->fail( $s, $doc_type, array( '_' => $r->get_error_message() ) );
		}
		$this->go( $s, $doc_type, 'code_sent' );
	}

	/**
	 * Verifica o código e assina.
	 *
	 * @return void
	 */
	public function handle_sign() {
		list( $uid, $s, $doc_type ) = $this->guard();
		$input                      = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizado campo a campo no Service.
		$hp                         = Honeypot::check( $input );
		if ( true !== $hp ) {
			$this->fail( $s, $doc_type, array( '_' => __( 'Não foi possível validar o envio. Recarregue a página e tente novamente.', 'eb-credito-rural' ) ) );
		}
		$r = Service::sign( $uid, $s, $doc_type, $input );
		if ( is_wp_error( $r ) ) {
			$data   = (array) $r->get_error_data();
			$errors = ! empty( $data['errors'] ) ? (array) $data['errors'] : array( '_' => $r->get_error_message() );
			$this->fail( $s, $doc_type, $errors, array( 'nome_confirmacao' => isset( $input['nome_confirmacao'] ) ? sanitize_text_field( (string) $input['nome_confirmacao'] ) : '' ) );
		}
		$this->go( $s, $doc_type, 'signed' );
	}

	/**
	 * Cancela a emissão pendente.
	 *
	 * @return void
	 */
	public function handle_cancel() {
		list( $uid, $s, $doc_type ) = $this->guard();
		Service::cancel( $uid, $s, $doc_type );
		$this->go( $s, $doc_type, 'cancelled' );
	}

	/**
	 * Exige login, nonce e propriedade da solicitação; devolve [usuário, solicitação, tipo].
	 *
	 * @return array
	 */
	private function guard() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( Helpers::portal_url( array( 'ebcr_msg' => 'nonce' ) ) );
			exit;
		}
		if ( ! Nonces::verify( self::NONCE ) ) {
			wp_safe_redirect( Helpers::portal_url( array( 'ebcr_msg' => 'nonce' ) ) );
			exit;
		}
		$uid      = get_current_user_id();
		$id       = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$doc_type = isset( $_POST['doc'] ) ? sanitize_key( wp_unslash( $_POST['doc'] ) ) : '';
		$s        = ( new SubmissionRepository() )->find_by_public_id( $id );
		if ( ! $s || ! Authorization::owns( $uid, $s ) || '' === $doc_type ) {
			AuditLog::log( 'access_denied', 'submission', $id, array( 'op' => 'esign' ), $uid );
			wp_safe_redirect( Helpers::portal_url( array( 'ebcr_msg' => 'forbidden' ) ) );
			exit;
		}
		return array( $uid, $s, $doc_type );
	}

	/**
	 * Guarda erros/valores e volta para a tela.
	 *
	 * @param array  $submission Solicitação.
	 * @param string $doc_type   Tipo.
	 * @param array  $errors     Erros.
	 * @param array  $values     Valores para reexibição.
	 * @return void
	 */
	private function fail( array $submission, $doc_type, array $errors, array $values = array() ) {
		set_transient(
			'ebcr_flash_' . get_current_user_id() . '_' . self::flash_key( $submission, $doc_type ),
			array(
				'errors' => $errors,
				'values' => $values,
			),
			10 * MINUTE_IN_SECONDS
		);
		$this->go( $submission, $doc_type, 'error' );
	}

	/**
	 * Redireciona para a tela de assinatura com mensagem.
	 *
	 * @param array  $submission Solicitação.
	 * @param string $doc_type   Tipo.
	 * @param string $msg        Código da mensagem.
	 * @return void
	 */
	private function go( array $submission, $doc_type, $msg ) {
		wp_safe_redirect(
			Helpers::portal_url(
				array(
					'ebcr_view' => self::VIEW,
					'id'        => $submission['public_id'],
					'doc'       => sanitize_key( $doc_type ),
					'esign_msg' => $msg,
				)
			)
		);
		exit;
	}

	/**
	 * Chave do flash (lida por Portal::unflash).
	 *
	 * @param array  $submission Solicitação.
	 * @param string $doc_type   Tipo.
	 * @return string
	 */
	private static function flash_key( array $submission, $doc_type ) {
		return 'esign_' . $submission['public_id'] . '_' . sanitize_key( $doc_type );
	}

	// ------------------------------------------------------------------ REST (opcional; melhora a experiência com JS)

	/**
	 * Rotas ebcr/v1/esign/{uuid}/{tipo}/code|sign.
	 *
	 * @param string $ns Namespace REST.
	 * @return void
	 */
	public function rest_routes( $ns ) {
		$base = '/esign/(?P<id>[a-f0-9-]{36})/(?P<doc>[a-z0-9_]{1,60})';
		register_rest_route(
			$ns,
			$base . '/code',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_code' ),
				'permission_callback' => array( $this, 'rest_permission' ),
			)
		);
		register_rest_route(
			$ns,
			$base . '/sign',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_sign' ),
				'permission_callback' => array( $this, 'rest_permission' ),
			)
		);
	}

	/**
	 * Logado (cookie + X-WP-Nonce) e dono da solicitação.
	 *
	 * @param \WP_REST_Request $request Requisição.
	 * @return true|\WP_Error
	 */
	public function rest_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'unauthorized', __( 'Faça login para continuar.', 'eb-credito-rural' ), array( 'status' => 401 ) );
		}
		$s = ( new SubmissionRepository() )->find_by_public_id( (string) $request['id'] );
		if ( ! $s || ! Authorization::owns( get_current_user_id(), $s ) ) {
			AuditLog::log( 'access_denied', 'submission', (string) $request['id'], array( 'op' => 'esign_rest' ) );
			return new \WP_Error( 'forbidden', __( 'Solicitação não encontrada.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Envia/reenvia o código.
	 *
	 * @param \WP_REST_Request $request Requisição.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_code( $request ) {
		$s = ( new SubmissionRepository() )->find_by_public_id( (string) $request['id'] );
		$r = Service::issue_code( get_current_user_id(), $s, sanitize_key( (string) $request['doc'] ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return rest_ensure_response(
			array(
				'ok'         => true,
				'expires_in' => max( 0, (int) $r['expires'] - time() ),
				'email'      => $r['email'],
				'message'    => self::message( 'code_sent' )['text'],
			)
		);
	}

	/**
	 * Verifica e assina.
	 *
	 * @param \WP_REST_Request $request Requisição.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_sign( $request ) {
		$s     = ( new SubmissionRepository() )->find_by_public_id( (string) $request['id'] );
		$input = array(
			'codigo'           => sanitize_text_field( (string) $request->get_param( 'codigo' ) ),
			'nome_confirmacao' => sanitize_text_field( (string) $request->get_param( 'nome_confirmacao' ) ),
			'aceite'           => (string) $request->get_param( 'aceite' ),
		);
		$r     = Service::sign( get_current_user_id(), $s, sanitize_key( (string) $request['doc'] ), $input );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return rest_ensure_response(
			array(
				'ok'       => true,
				'message'  => self::message( 'signed' )['text'],
				'document' => array(
					'id'   => $r['document']['public_id'],
					'name' => $r['document']['original_name'],
					'url'  => DownloadController::url( $r['document'] ),
				),
				'redirect' => add_query_arg( 'esign_msg', 'signed', self::sign_url( $s, (string) $request['doc'] ) ),
			)
		);
	}

	// ------------------------------------------------------------------ admin (chamados pelos templates compartilhados)

	/**
	 * Selo "Assinado eletronicamente" + link para as evidências de um documento (HTML escapado; vazio se não for assinado).
	 *
	 * @param array $document Linha do documento.
	 * @return string
	 */
	public static function badge_for_document( array $document ) {
		if ( empty( $document['id'] ) ) {
			return '';
		}
		$sig = ( new SignatureRepository() )->find_by_document( (int) $document['id'] );
		if ( ! $sig ) {
			return '';
		}
		return sprintf(
			'<span class="ebcr-esign-badge" title="%s">%s</span> <a class="ebcr-esign-badge-link" href="#ebcr-esign-%s">%s</a>',
			esc_attr( sprintf( /* translators: 1: nome, 2: data */ __( 'Assinado por %1$s em %2$s', 'eb-credito-rural' ), $sig['signer_name'], Service::sao_paulo_date( strtotime( $sig['signed_at'] . ' UTC' ) ) ) ),
			esc_html__( 'Assinado eletronicamente', 'eb-credito-rural' ),
			esc_attr( $sig['public_id'] ),
			esc_html__( 'ver evidências', 'eb-credito-rural' )
		);
	}

	/**
	 * Assinaturas concluídas de uma solicitação (para listar evidências no admin).
	 *
	 * @param array $submission Solicitação.
	 * @return array
	 */
	public static function signatures( array $submission ) {
		return ( new SignatureRepository() )->for_submission( (int) $submission['id'], true );
	}

	/**
	 * Bloco de evidências de uma assinatura (HTML escapado), com âncora #ebcr-esign-<uuid> usada pelo selo.
	 *
	 * @param array $signature Linha da assinatura.
	 * @return string
	 */
	public static function evidence_html( array $signature ) {
		$doc       = ! empty( $signature['document_id'] ) ? ( new DocumentRepository() )->find( (int) $signature['document_id'] ) : null;
		$signed_ts = ! empty( $signature['signed_at'] ) ? strtotime( $signature['signed_at'] . ' UTC' ) : 0;
		$sent_ts   = ! empty( $signature['created_at'] ) ? strtotime( $signature['created_at'] . ' UTC' ) : 0;
		$status    = array(
			SignatureRepository::STATUS_SIGNED    => __( 'Assinada', 'eb-credito-rural' ),
			SignatureRepository::STATUS_PENDING   => __( 'Aguardando código', 'eb-credito-rural' ),
			SignatureRepository::STATUS_CANCELLED => __( 'Cancelada', 'eb-credito-rural' ),
		);
		$rows      = array(
			__( 'Documento', 'eb-credito-rural' )         => Service::title( $signature['doc_type'] ),
			__( 'Assinante', 'eb-credito-rural' )         => $signature['signer_name'] . ' — ' . Helpers::format_document( $signature['signer_document'] ),
			__( 'E-mail', 'eb-credito-rural' )            => $signature['signer_email'],
			__( 'Usuário', 'eb-credito-rural' )           => Helpers::user_name( (int) $signature['user_id'] ) . ' (#' . (int) $signature['user_id'] . ')',
			__( 'Assinado em (UTC)', 'eb-credito-rural' ) => $signed_ts ? gmdate( 'd/m/Y H:i:s', $signed_ts ) . ' UTC' : '—',
			__( 'Assinado em (São Paulo)', 'eb-credito-rural' ) => $signed_ts ? Service::sao_paulo_date( $signed_ts ) : '—',
			__( 'Endereço IP', 'eb-credito-rural' )       => $signature['ip'],
			__( 'Navegador (user agent)', 'eb-credito-rural' ) => $signature['user_agent'],
			__( 'Método', 'eb-credito-rural' )            => 'email_otp' === $signature['method'] ? sprintf( /* translators: 1: e-mail, 2: data */ __( 'código de uso único enviado ao e-mail %1$s em %2$s', 'eb-credito-rural' ), Service::mask_email( $signature['signer_email'] ), $sent_ts ? Service::sao_paulo_date( $sent_ts ) : '—' ) : $signature['method'],
			__( 'Hash SHA-256 do texto', 'eb-credito-rural' ) => $signature['text_hash'],
			__( 'Hash SHA-256 das evidências', 'eb-credito-rural' ) => $signature['evidence_hash'],
			__( 'Identificador público', 'eb-credito-rural' ) => $signature['public_id'],
			__( 'Situação', 'eb-credito-rural' )          => isset( $status[ $signature['status'] ] ) ? $status[ $signature['status'] ] : $signature['status'],
		);
		$html      = sprintf( '<div class="ebcr-esign-evidence" id="ebcr-esign-%s"><h4>%s</h4><table class="ebcr-esign-evidence-table"><tbody>', esc_attr( $signature['public_id'] ), esc_html( sprintf( /* translators: %s: título do documento */ __( 'Evidências da assinatura eletrônica — %s', 'eb-credito-rural' ), Service::title( $signature['doc_type'] ) ) ) );
		foreach ( $rows as $label => $value ) {
			$html .= sprintf( '<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( (string) $value ) );
		}
		if ( $doc && empty( $doc['deleted_at'] ) ) {
			$html .= sprintf( '<tr><th scope="row">%s</th><td><a href="%s">%s</a> <span class="description">(SHA-256 %s)</span></td></tr>', esc_html__( 'PDF assinado', 'eb-credito-rural' ), esc_url( DownloadController::url( $doc ) ), esc_html( $doc['original_name'] ), esc_html( (string) $doc['sha256'] ) );
		} elseif ( $doc ) {
			$html .= sprintf( '<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html__( 'PDF assinado', 'eb-credito-rural' ), esc_html__( 'substituído por uma assinatura mais recente (arquivo removido)', 'eb-credito-rural' ) );
		}
		$html .= '</tbody></table><p class="description">' . esc_html__( 'Assinatura eletrônica simples nos termos da Lei 14.063/2020 e da MP 2.200-2/2001; a integridade pode ser conferida pelos hashes acima.', 'eb-credito-rural' ) . '</p></div>';
		return $html;
	}
}
