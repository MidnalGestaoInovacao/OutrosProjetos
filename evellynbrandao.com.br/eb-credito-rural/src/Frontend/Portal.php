<?php
/**
 * Área do cliente: roteamento de views e ações (admin-post).
 *
 * @package EBCR
 */

namespace EBCR\Frontend;

use EBCR\Database\DocumentRepository;
use EBCR\Database\DocumentRequestRepository;
use EBCR\Database\MessageRepository;
use EBCR\Database\StatusHistoryRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Consent;
use EBCR\Domain\Status;
use EBCR\Files\UploadHandler;
use EBCR\Forms\Steps;
use EBCR\Forms\SubmissionRules;
use EBCR\Forms\SubmissionService;
use EBCR\Forms\Wizard;
use EBCR\Mail\Notifier;
use EBCR\Roles\Capabilities;
use EBCR\Security\AuditLog;
use EBCR\Security\Authorization;
use EBCR\Security\Honeypot;
use EBCR\Security\MathCaptcha;
use EBCR\Security\Nonces;
use EBCR\Security\PrivacyIntegration;
use EBCR\Support\Helpers;
use EBCR\Support\Options;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Views: painel | solicitacao | nova | formulario | perfil | privacidade | cadastro.
 */
// phpcs:disable WordPress.Security.NonceVerification -- todos os handlers verificam o nonce (Nonces::verify/check_admin_referer) antes de ler a entrada.
final class Portal {

	/**
	 * Hooks das ações.
	 *
	 * @return void
	 */
	public function register() {
		$actions = array( 'new_submission', 'wizard_step', 'wizard_upload', 'wizard_submit', 'delete_document', 'cancel_submission', 'client_message', 'profile', 'password', 'lgpd_export', 'lgpd_request', 'request_upload' );
		foreach ( $actions as $a ) {
			add_action( 'admin_post_ebcr_' . $a, array( $this, 'handle_' . $a ) );
		}
	}

	/**
	 * Redireciona ao portal.
	 *
	 * @param array $args Args.
	 * @return void
	 */
	private function go( array $args ) {
		wp_safe_redirect( Helpers::portal_url( $args ) );
		exit;
	}

	/**
	 * Exige login + nonce; devolve o usuário.
	 *
	 * @param string $action Ação do nonce.
	 * @return int
	 */
	private function guard( $action ) {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( Helpers::portal_url( array( 'ebcr_msg' => 'nonce' ) ) );
			exit;
		}
		if ( ! Nonces::verify( $action ) ) {
			$this->go( array( 'ebcr_msg' => 'nonce' ) );
		}
		return get_current_user_id();
	}

	/**
	 * Solicitação do POST (por UUID), do usuário atual.
	 *
	 * @return array|null
	 */
	private function posted_submission() {
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() verifica o nonce antes.
		$s  = ( new SubmissionRepository() )->find_by_public_id( $id );
		return $s && Authorization::owns( get_current_user_id(), $s ) ? $s : null;
	}

	/**
	 * Guarda estado de formulário (erros/valores) para reexibição.
	 *
	 * @param string $key    Chave.
	 * @param array  $errors Erros.
	 * @param array  $values Valores.
	 * @return void
	 */
	private function flash( $key, array $errors, array $values = array() ) {
		set_transient(
			'ebcr_flash_' . get_current_user_id() . '_' . $key,
			array(
				'errors' => $errors,
				'values' => $values,
			),
			10 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Lê e apaga estado de formulário.
	 *
	 * @param string $key Chave.
	 * @return array
	 */
	public static function unflash( $key ) {
		$k = 'ebcr_flash_' . get_current_user_id() . '_' . $key;
		$d = get_transient( $k );
		if ( $d ) {
			delete_transient( $k );
			return $d;
		}
		return array(
			'errors' => array(),
			'values' => array(),
		);
	}

	// ------------------------------------------------------------------ views

	/**
	 * Renderiza o portal.
	 *
	 * @return string
	 */
	public function render() {
		Frontend::enqueue_portal();
		$view = isset( $_GET['ebcr_view'] ) ? sanitize_key( wp_unslash( $_GET['ebcr_view'] ) ) : 'painel'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navegação.
		$msg  = isset( $_GET['ebcr_msg'] ) ? sanitize_key( wp_unslash( $_GET['ebcr_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navegação.
		$out  = '<div class="ebcr-portal" id="ebcr-portal">';
		$m    = self::message( $msg );
		if ( $m ) {
			$out .= sprintf( '<div class="ebcr-alert ebcr-alert--%s" role="status">%s</div>', esc_attr( $m['type'] ), esc_html( $m['text'] ) );
		}
		if ( Options::bool( 'require_https' ) && ! is_ssl() && ! defined( 'EBCR_TESTING' ) ) {
			$out .= '<div class="ebcr-alert ebcr-alert--error" role="alert">' . esc_html__( 'Atenção: esta página não está em HTTPS. Não envie dados sensíveis até que o site esteja protegido.', 'eb-credito-rural' ) . '</div>';
		}
		if ( ! is_user_logged_in() ) {
			$out .= $this->render_auth( 'cadastro' === $view ? 'register' : 'login' );
			return $out . '</div>';
		}
		$user = wp_get_current_user();
		if ( user_can( $user, Capabilities::CAP_VIEW ) ) {
			$out .= View::render( 'portal/team', array( 'user' => $user ) );
			return $out . '</div>';
		}
		if ( ! user_can( $user, Capabilities::CAP_CLIENT ) ) {
			$out .= '<div class="ebcr-alert ebcr-alert--info">' . esc_html__( 'Sua conta não é de cliente desta plataforma.', 'eb-credito-rural' ) . '</div>';
			return $out . '</div>';
		}
		$pending = Consent::pending_reaccept( $user->ID );
		if ( $pending ) {
			$out .= View::render( 'portal/reaccept', array( 'policies' => $pending ) );
			return $out . '</div>';
		}
		$verified = (bool) get_user_meta( $user->ID, 'ebcr_email_verified', true );
		$out     .= View::render(
			'portal/header',
			array(
				'user'     => $user,
				'view'     => $view,
				'verified' => $verified,
			)
		);
		switch ( $view ) {
			case 'solicitacao':
				$out .= $this->render_submission( $user->ID );
				break;
			case 'formulario':
				$out .= $this->render_wizard( $user->ID );
				break;
			case 'assinar':
				$out .= \EBCR\Esign\Esign::render( $user->ID );
				break;
			case 'perfil':
				$out .= View::render(
					'portal/profile',
					array(
						'user'     => $user,
						'flash'    => self::unflash( 'profile' ),
						'flash_pw' => self::unflash( 'password' ),
						'phone'    => get_user_meta( $user->ID, 'ebcr_phone', true ),
						'whatsapp' => get_user_meta( $user->ID, 'ebcr_whatsapp', true ),
					)
				);
				break;
			case 'privacidade':
				$out .= View::render(
					'portal/privacy',
					array(
						'user'      => $user,
						'policies'  => Consent::policies(),
						'dpo_name'  => Options::get( 'dpo_name' ),
						'dpo_email' => Options::get( 'dpo_email' ),
						'flash'     => self::unflash( 'lgpd' ),
					)
				);
				break;
			default:
				$out .= $this->render_dashboard( $user->ID, $verified );
		}
		return $out . '</div>';
	}

	/**
	 * Login/cadastro.
	 *
	 * @param string $tab Aba ativa.
	 * @return string
	 */
	public function render_auth( $tab = 'login' ) {
		return View::render(
			'portal/auth',
			array(
				'tab'           => $tab,
				'login'         => Auth::recall( 'login' ),
				'register'      => Auth::recall( 'register' ),
				'policies'      => Consent::for_moment( 'register' ),
				'captcha'       => MathCaptcha::provider(),
				'captcha_login' => Options::bool( 'captcha_on_login' ),
				'min_pw'        => max( 8, Options::int( 'password_min_length' ) ),
				'redirect'      => isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navegação.
			)
		);
	}

	/**
	 * Painel.
	 *
	 * @param int  $user_id  Usuário.
	 * @param bool $verified E-mail confirmado.
	 * @return string
	 */
	private function render_dashboard( $user_id, $verified ) {
		$subs = ( new SubmissionRepository() )->for_user( $user_id );
		$reqs = new DocumentRequestRepository();
		$msgs = new MessageRepository();
		$rows = array();
		foreach ( $subs as $s ) {
			$open   = Status::is_final( $s['status'] ) ? array() : $reqs->for_submission( (int) $s['id'], true );
			$rows[] = array(
				'submission' => $s,
				'open'       => $open,
				'unread'     => $msgs->unread_for_client( (int) $s['id'], $user_id ),
				'next'       => $this->next_action( $s, $open ),
			);
		}
		return View::render(
			'portal/dashboard',
			array(
				'rows'          => $rows,
				'rules'         => SubmissionRules::can_submit( $user_id ),
				'draft'         => ( new SubmissionRepository() )->open_draft( $user_id ),
				'quota'         => UploadHandler::quota_usage( $user_id ),
				'verified'      => $verified,
				'can_duplicate' => Options::bool( 'allow_duplicate_previous' ) && (bool) ( new SubmissionRepository() )->last_submitted( $user_id ),
			)
		);
	}

	/**
	 * Próxima ação esperada.
	 *
	 * @param array $s    Solicitação.
	 * @param array $open Pedidos abertos.
	 * @return string
	 */
	private function next_action( array $s, array $open ) {
		if ( Status::DRAFT === $s['status'] ) {
			return __( 'Continuar o preenchimento e enviar', 'eb-credito-rural' );
		}
		if ( $open ) {
			return sprintf( /* translators: %d: quantidade */ _n( 'Enviar %d documento pendente', 'Enviar %d documentos pendentes', count( $open ), 'eb-credito-rural' ), count( $open ) );
		}
		if ( Status::is_final( $s['status'] ) ) {
			return '—';
		}
		return __( 'Aguardar a equipe', 'eb-credito-rural' );
	}

	/**
	 * Detalhe da solicitação.
	 *
	 * @param int $user_id Usuário.
	 * @return string
	 */
	private function render_submission( $user_id ) {
		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navegação.
		$s  = ( new SubmissionRepository() )->find_by_public_id( $id );
		if ( ! $s || ! Authorization::owns( $user_id, $s ) ) {
			AuditLog::log( 'access_denied', 'submission', $id, array( 'op' => 'view' ), $user_id );
			return '<div class="ebcr-alert ebcr-alert--error">' . esc_html__( 'Solicitação não encontrada.', 'eb-credito-rural' ) . '</div>';
		}
		$msgs = new MessageRepository();
		$msgs->mark_read( (int) $s['id'], $user_id );
		$wizard = new Wizard();
		return View::render(
			'portal/submission',
			array(
				's'           => $s,
				'history'     => array_filter(
					( new StatusHistoryRepository() )->for_submission( (int) $s['id'] ),
					static function ( $h ) {
						return Status::client_visible( $h['to_status'] ); }
				),
				'requests'    => ( new DocumentRequestRepository() )->for_submission( (int) $s['id'] ),
				'documents'   => ( new DocumentRepository() )->for_submission( (int) $s['id'] ),
				'messages'    => $msgs->for_submission( (int) $s['id'], true ),
				'slots'       => $wizard->document_slots( $s ),
				'can_edit'    => Authorization::client_can_edit( $user_id, $s ),
				'can_upload'  => Authorization::client_can_upload( $user_id, $s ),
				'can_cancel'  => Authorization::client_can_cancel( $user_id, $s ),
				'can_message' => ! Status::is_final( $s['status'] ),
				'flash'       => self::unflash( 'submission_' . $s['public_id'] ),
				'just_sent'   => ! empty( $_GET['enviada'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navegação.
			)
		);
	}

	/**
	 * Formulário em etapas.
	 *
	 * @param int $user_id Usuário.
	 * @return string
	 */
	private function render_wizard( $user_id ) {
		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navegação.
		$s  = ( new SubmissionRepository() )->find_by_public_id( $id );
		if ( ! $s || ! Authorization::owns( $user_id, $s ) ) {
			return '<div class="ebcr-alert ebcr-alert--error">' . esc_html__( 'Solicitação não encontrada.', 'eb-credito-rural' ) . '</div>';
		}
		if ( ! Authorization::client_can_edit( $user_id, $s ) ) {
			return '<div class="ebcr-alert ebcr-alert--info">' . esc_html__( 'Esta solicitação já foi enviada e não pode ser editada. Se a equipe abrir uma pendência, você poderá enviar os documentos pedidos.', 'eb-credito-rural' ) . '</div>' . sprintf(
				'<p><a class="ebcr-btn" href="%s">%s</a></p>',
				esc_url(
					Helpers::portal_url(
						array(
							'ebcr_view' => 'solicitacao',
							'id'        => $s['public_id'],
						)
					)
				),
				esc_html__( 'Ver solicitação', 'eb-credito-rural' )
			);
		}
		$step = isset( $_GET['etapa'] ) ? max( 1, min( 7, (int) $_GET['etapa'] ) ) : (int) $s['current_step']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navegação.
		// A etapa 6 (documentos) não tem envio próprio: a partir dela a revisão (7) fica liberada.
		$max_step = (int) $s['current_step'] >= 6 ? 7 : max( 1, (int) $s['current_step'] );
		$step     = min( $step, $max_step );
		$wizard   = new Wizard();
		$saved    = $wizard->saved( $s );
		$key      = Steps::all()[ $step ]['key'];
		$flash    = self::unflash( 'wizard_' . $s['public_id'] . '_' . $step );
		$data     = $flash['values'] ? $flash['values'] : ( isset( $saved[ $key ] ) ? $saved[ $key ] : array() );
		$errors   = $flash['errors'];
		$add      = isset( $_GET['add'] ) ? sanitize_key( wp_unslash( $_GET['add'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navegação.
		Frontend::enqueue_wizard( $s, $step );
		return View::render(
			'wizard/layout',
			array(
				's'        => $s,
				'step'     => $step,
				'steps'    => Steps::all(),
				'data'     => $data,
				'errors'   => $errors,
				'saved'    => $saved,
				'add'      => $add,
				'wizard'   => $wizard,
				'max_step' => $max_step,
				'help'     => (array) Options::get( 'step_help', array() ),
				'content'  => View::render(
					'wizard/step-' . $step,
					array(
						's'       => $s,
						'data'    => $data,
						'errors'  => $errors,
						'saved'   => $saved,
						'add'     => $add,
						'wizard'  => $wizard,
						'captcha' => MathCaptcha::provider(),
					)
				),
			)
		);
	}

	// ------------------------------------------------------------------ ações

	/**
	 * Nova solicitação.
	 *
	 * @return void
	 */
	public function handle_new_submission() {
		$uid = $this->guard( 'new_submission' );
		$r   = SubmissionService::start( $uid, ! empty( $_POST['duplicar'] ) );
		if ( is_wp_error( $r ) ) {
			$this->flash( 'dashboard', array( '_' => $r->get_error_message() ) );
			$this->go( array( 'ebcr_msg' => 'rules' ) );
		}
		$this->go(
			array(
				'ebcr_view' => 'formulario',
				'id'        => $r['public_id'],
			)
		);
	}

	/**
	 * Etapa do formulário (sem JS).
	 *
	 * @return void
	 */
	public function handle_wizard_step() {
		$uid  = $this->guard( 'wizard' );
		$s    = $this->posted_submission();
		$step = isset( $_POST['etapa'] ) ? (int) $_POST['etapa'] : 1;
		if ( ! $s ) {
			$this->go( array( 'ebcr_msg' => 'nonce' ) );
		}
		$input = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizado campo a campo pelo Validator.
		if ( ! empty( $input['ebcr_add'] ) ) {
			( new Wizard() )->handle_step( $uid, $s, $step, $input, true );
			$this->go(
				array(
					'ebcr_view' => 'formulario',
					'id'        => $s['public_id'],
					'etapa'     => $step,
					'add'       => sanitize_key( $input['ebcr_add'] ),
				)
			);
		}
		if ( ! empty( $input['ebcr_back'] ) ) {
			( new Wizard() )->handle_step( $uid, $s, $step, $input, true );
			$this->go(
				array(
					'ebcr_view' => 'formulario',
					'id'        => $s['public_id'],
					'etapa'     => max( 1, $step - 1 ),
				)
			);
		}
		if ( ! empty( $input['ebcr_save'] ) ) {
			( new Wizard() )->handle_step( $uid, $s, $step, $input, true );
			$this->go( array( 'ebcr_msg' => 'draft_saved' ) );
		}
		list( $ok, $errors ) = ( new Wizard() )->handle_step( $uid, $s, $step, $input, false );
		if ( ! $ok ) {
			$this->flash( 'wizard_' . $s['public_id'] . '_' . $step, $errors, $this->strip_meta( $input ) );
			$this->go(
				array(
					'ebcr_view' => 'formulario',
					'id'        => $s['public_id'],
					'etapa'     => $step,
					'ebcr_msg'  => 'step_errors',
				)
			);
		}
		$this->go(
			array(
				'ebcr_view' => 'formulario',
				'id'        => $s['public_id'],
				'etapa'     => min( 7, $step + 1 ),
			)
		);
	}

	/**
	 * Remove campos técnicos do input antes de guardar para reexibição.
	 *
	 * @param array $input Entrada.
	 * @return array
	 */
	private function strip_meta( array $input ) {
		foreach ( array( 'ebcr_nonce', '_wp_http_referer', 'action', 'id', 'etapa', 'senha', 'senha2', 'ebcr_captcha_answer', 'ebcr_captcha_token', 'ebcr_ts', 'ebcr_website' ) as $k ) {
			unset( $input[ $k ] );
		}
		return array_map(
			static function ( $v ) {
				return is_array( $v ) ? $v : sanitize_text_field( (string) $v );
			},
			$input
		);
	}

	/**
	 * Upload (sem JS) na etapa 6.
	 *
	 * @return void
	 */
	public function handle_wizard_upload() {
		$uid = $this->guard( 'wizard' );
		$s   = $this->posted_submission();
		if ( ! $s ) {
			$this->go( array( 'ebcr_msg' => 'nonce' ) );
		}
		$this->do_upload(
			$uid,
			$s,
			array(
				'ebcr_view' => 'formulario',
				'id'        => $s['public_id'],
				'etapa'     => 6,
			),
			'wizard_' . $s['public_id'] . '_6'
		);
	}

	/**
	 * Upload atendendo pedido, a partir da tela da solicitação.
	 *
	 * @return void
	 */
	public function handle_request_upload() {
		$uid = $this->guard( 'wizard' );
		$s   = $this->posted_submission();
		if ( ! $s ) {
			$this->go( array( 'ebcr_msg' => 'nonce' ) );
		}
		$this->do_upload(
			$uid,
			$s,
			array(
				'ebcr_view' => 'solicitacao',
				'id'        => $s['public_id'],
			),
			'submission_' . $s['public_id']
		);
	}

	/**
	 * Executa o upload de $_FILES['arquivo'].
	 *
	 * @param int    $uid       Usuário.
	 * @param array  $s         Solicitação.
	 * @param array  $back      Destino.
	 * @param string $flash_key Chave do flash.
	 * @return void
	 */
	private function do_upload( $uid, array $s, array $back, $flash_key ) {
		$file = isset( $_FILES['arquivo'] ) ? $_FILES['arquivo'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validado por UploadHandler.
		$row  = ( new UploadHandler() )->handle(
			$uid,
			$s,
			$file,
			isset( $_POST['doc_type'] ) ? sanitize_key( wp_unslash( $_POST['doc_type'] ) ) : '',
			isset( $_POST['ref_key'] ) ? sanitize_text_field( wp_unslash( $_POST['ref_key'] ) ) : '',
			isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0
		);
		if ( is_wp_error( $row ) ) {
			$this->flash( $flash_key, array( 'upload' => $row->get_error_message() ) );
			$this->go( array_merge( $back, array( 'ebcr_msg' => 'upload_error' ) ) );
		}
		if ( ! empty( $row['request_id'] ) ) {
			Notifier::client_responded( $s, $row );
		}
		$this->go( array_merge( $back, array( 'ebcr_msg' => 'uploaded' ) ) );
	}

	/**
	 * Envio final (sem JS).
	 *
	 * @return void
	 */
	public function handle_wizard_submit() {
		$uid = $this->guard( 'wizard' );
		$s   = $this->posted_submission();
		if ( ! $s ) {
			$this->go( array( 'ebcr_msg' => 'nonce' ) );
		}
		$input = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verificado abaixo.
		$hp    = Honeypot::check( $input );
		if ( true !== $hp ) {
			$this->flash( 'wizard_' . $s['public_id'] . '_7', array( '_' => __( 'Não foi possível validar o envio. Recarregue a página e tente novamente.', 'eb-credito-rural' ) ) );
			$this->go(
				array(
					'ebcr_view' => 'formulario',
					'id'        => $s['public_id'],
					'etapa'     => 7,
				)
			);
		}
		if ( ! MathCaptcha::verify_request( $input ) ) {
			$this->flash( 'wizard_' . $s['public_id'] . '_7', array( 'captcha' => __( 'Resposta da verificação de segurança incorreta ou expirada.', 'eb-credito-rural' ) ) );
			$this->go(
				array(
					'ebcr_view' => 'formulario',
					'id'        => $s['public_id'],
					'etapa'     => 7,
				)
			);
		}
		$r = ( new Wizard() )->submit( $uid, $s, $input );
		if ( is_wp_error( $r ) ) {
			$errors = array( '_' => $r->get_error_message() );
			$data   = $r->get_error_data();
			if ( ! empty( $data['steps'] ) ) {
				foreach ( $data['steps'] as $step => $errs ) {
					$errors[ 'step_' . $step ] = sprintf( /* translators: 1: etapa, 2: erros */ __( 'Etapa %1$d: %2$s', 'eb-credito-rural' ), $step, implode( ' ', $errs ) );
				}
			}
			$this->flash( 'wizard_' . $s['public_id'] . '_7', $errors );
			$this->go(
				array(
					'ebcr_view' => 'formulario',
					'id'        => $s['public_id'],
					'etapa'     => 7,
				)
			);
		}
		$this->go(
			array(
				'ebcr_view' => 'solicitacao',
				'id'        => $r['public_id'],
				'enviada'   => '1',
			)
		);
	}

	/**
	 * Remove documento (cliente).
	 *
	 * @return void
	 */
	public function handle_delete_document() {
		$uid  = $this->guard( 'wizard' );
		$doc  = ( new DocumentRepository() )->find_by_public_id( isset( $_POST['doc'] ) ? sanitize_text_field( wp_unslash( $_POST['doc'] ) ) : '' );
		$back = array(
			'ebcr_view' => isset( $_POST['back'] ) && 'solicitacao' === $_POST['back'] ? 'solicitacao' : 'formulario',
			'etapa'     => 6,
		);
		if ( ! $doc ) {
			$this->go( array( 'ebcr_msg' => 'nonce' ) );
		}
		$s          = ( new SubmissionRepository() )->find( (int) $doc['submission_id'] );
		$r          = SubmissionService::client_delete_document( $uid, $doc );
		$back['id'] = $s ? $s['public_id'] : '';
		$this->go( array_merge( $back, array( 'ebcr_msg' => is_wp_error( $r ) ? 'forbidden' : 'deleted' ) ) );
	}

	/**
	 * Cancelamento pelo cliente.
	 *
	 * @return void
	 */
	public function handle_cancel_submission() {
		$uid = $this->guard( 'cancel' );
		$s   = $this->posted_submission();
		if ( ! $s ) {
			$this->go( array( 'ebcr_msg' => 'nonce' ) );
		}
		$r = SubmissionService::client_cancel( $uid, $s, isset( $_POST['motivo'] ) ? sanitize_textarea_field( wp_unslash( $_POST['motivo'] ) ) : '' );
		$this->go(
			array(
				'ebcr_view' => 'solicitacao',
				'id'        => $s['public_id'],
				'ebcr_msg'  => is_wp_error( $r ) ? 'forbidden' : 'cancelled',
			)
		);
	}

	/**
	 * Mensagem do cliente.
	 *
	 * @return void
	 */
	public function handle_client_message() {
		$uid = $this->guard( 'message' );
		$s   = $this->posted_submission();
		if ( ! $s ) {
			$this->go( array( 'ebcr_msg' => 'nonce' ) );
		}
		$r = SubmissionService::send_message( $uid, $s, isset( $_POST['mensagem'] ) ? wp_unslash( $_POST['mensagem'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizado no serviço.
		if ( is_wp_error( $r ) ) {
			$this->flash( 'submission_' . $s['public_id'], array( 'mensagem' => $r->get_error_message() ) );
		}
		$this->go(
			array(
				'ebcr_view' => 'solicitacao',
				'id'        => $s['public_id'],
				'ebcr_msg'  => is_wp_error( $r ) ? 'message_error' : 'message_sent',
			)
		);
	}

	/**
	 * Perfil (nome, telefone, WhatsApp).
	 *
	 * @return void
	 */
	public function handle_profile() {
		$uid  = $this->guard( 'profile' );
		$nome = isset( $_POST['nome'] ) ? sanitize_text_field( wp_unslash( $_POST['nome'] ) ) : '';
		$tel  = isset( $_POST['telefone'] ) ? sanitize_text_field( wp_unslash( $_POST['telefone'] ) ) : '';
		$wa   = isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '';
		$errs = array();
		if ( mb_strlen( $nome ) < 5 ) {
			$errs['nome'] = __( 'Informe seu nome completo.', 'eb-credito-rural' );
		}
		if ( ! \EBCR\Forms\Validators\Rules::phone( $tel ) ) {
			$errs['telefone'] = __( 'Telefone inválido.', 'eb-credito-rural' );
		}
		if ( $wa && ! \EBCR\Forms\Validators\Rules::phone( $wa ) ) {
			$errs['whatsapp'] = __( 'WhatsApp inválido.', 'eb-credito-rural' );
		}
		if ( $errs ) {
			$this->flash( 'profile', $errs, compact( 'nome', 'telefone', 'whatsapp' ) );
			$this->go(
				array(
					'ebcr_view' => 'perfil',
					'ebcr_msg'  => 'profile_errors',
				)
			);
		}
		wp_update_user(
			array(
				'ID'           => $uid,
				'display_name' => $nome,
			)
		);
		update_user_meta( $uid, 'ebcr_phone', Helpers::digits( $tel ) );
		update_user_meta( $uid, 'ebcr_whatsapp', Helpers::digits( $wa ) );
		$crm = ( new \EBCR\Database\CrmContactRepository() )->get_or_create( $uid );
		( new \EBCR\Database\CrmContactRepository() )->update(
			(int) $crm['id'],
			array(
				'phone'    => Helpers::digits( $tel ),
				'whatsapp' => Helpers::digits( $wa ),
			)
		);
		$this->go(
			array(
				'ebcr_view' => 'perfil',
				'ebcr_msg'  => 'profile_saved',
			)
		);
	}

	/**
	 * Troca de senha.
	 *
	 * @return void
	 */
	public function handle_password() {
		$uid  = $this->guard( 'password' );
		$user = get_userdata( $uid );
		$cur  = isset( $_POST['senha_atual'] ) ? (string) wp_unslash( $_POST['senha_atual'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- senha.
		$new  = isset( $_POST['senha'] ) ? (string) wp_unslash( $_POST['senha'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- senha.
		$conf = isset( $_POST['senha2'] ) ? (string) wp_unslash( $_POST['senha2'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- senha.
		if ( ! $user || ! wp_check_password( $cur, $user->user_pass, $uid ) ) {
			$this->flash( 'password', array( 'senha_atual' => __( 'Senha atual incorreta.', 'eb-credito-rural' ) ) );
			$this->go(
				array(
					'ebcr_view' => 'perfil',
					'ebcr_msg'  => 'password_errors',
				)
			);
		}
		$err = Auth::password_error( $new, array( $user->display_name, $user->user_email ) );
		if ( $err || $new !== $conf ) {
			$this->flash( 'password', array( 'senha' => $err ? $err : __( 'As senhas não conferem.', 'eb-credito-rural' ) ) );
			$this->go(
				array(
					'ebcr_view' => 'perfil',
					'ebcr_msg'  => 'password_errors',
				)
			);
		}
		wp_set_password( $new, $uid );
		wp_set_current_user( $uid );
		wp_set_auth_cookie( $uid, false, is_ssl() );
		AuditLog::log( 'password_changed', 'user', $uid, array(), $uid );
		$this->go(
			array(
				'ebcr_view' => 'perfil',
				'ebcr_msg'  => 'password_saved',
			)
		);
	}

	/**
	 * Cópia dos próprios dados (JSON).
	 *
	 * @return void
	 */
	public function handle_lgpd_export() {
		$uid  = $this->guard( 'lgpd' );
		$data = PrivacyIntegration::collect( $uid );
		AuditLog::log( 'lgpd_export', 'user', $uid, array(), $uid );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="meus-dados-ebcr.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON.
		exit;
	}

	/**
	 * Solicitação LGPD (correção/exclusão) → tarefa para a equipe + e-mail.
	 *
	 * @return void
	 */
	public function handle_lgpd_request() {
		$uid   = $this->guard( 'lgpd' );
		$user  = get_userdata( $uid );
		$kind  = isset( $_POST['tipo'] ) ? sanitize_key( wp_unslash( $_POST['tipo'] ) ) : '';
		$det   = isset( $_POST['detalhes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['detalhes'] ) ) : '';
		$kinds = array(
			'correcao' => __( 'Correção de dados', 'eb-credito-rural' ),
			'exclusao' => __( 'Exclusão de dados', 'eb-credito-rural' ),
			'outro'    => __( 'Outro direito do titular', 'eb-credito-rural' ),
		);
		if ( ! isset( $kinds[ $kind ] ) || mb_strlen( $det ) < 10 ) {
			$this->flash( 'lgpd', array( 'detalhes' => __( 'Descreva sua solicitação (mínimo 10 caracteres).', 'eb-credito-rural' ) ) );
			$this->go(
				array(
					'ebcr_view' => 'privacidade',
					'ebcr_msg'  => 'lgpd_errors',
				)
			);
		}
		$crm = ( new \EBCR\Database\CrmContactRepository() )->get_or_create( $uid );
		( new \EBCR\Database\CrmActivityRepository() )->add(
			array(
				'contact_id'  => (int) $crm['id'],
				'type'        => 'tarefa',
				'description' => sprintf( 'LGPD — %s: %s', $kinds[ $kind ], $det ),
				'due_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '+15 days' ) ),
				'created_by'  => null,
			)
		);
		AuditLog::log( 'lgpd_request', 'user', $uid, array( 'kind' => $kind ), $uid );
		Notifier::lgpd_request( $user, $kinds[ $kind ], $det );
		$this->go(
			array(
				'ebcr_view' => 'privacidade',
				'ebcr_msg'  => 'lgpd_sent',
			)
		);
	}

	/**
	 * Mensagens do portal.
	 *
	 * @param string $code Código.
	 * @return array|null
	 */
	public static function message( $code ) {
		$auth = Auth::message( $code );
		if ( $auth ) {
			return $auth;
		}
		$map = array(
			'draft_saved'     => array( 'success', __( 'Rascunho salvo. Você pode continuar quando quiser.', 'eb-credito-rural' ) ),
			'step_errors'     => array( 'error', __( 'Alguns campos precisam de correção.', 'eb-credito-rural' ) ),
			'uploaded'        => array( 'success', __( 'Documento enviado com sucesso.', 'eb-credito-rural' ) ),
			'upload_error'    => array( 'error', __( 'O documento não foi aceito. Veja o motivo abaixo.', 'eb-credito-rural' ) ),
			'deleted'         => array( 'success', __( 'Documento removido.', 'eb-credito-rural' ) ),
			'forbidden'       => array( 'error', __( 'Ação não permitida.', 'eb-credito-rural' ) ),
			'cancelled'       => array( 'info', __( 'Solicitação cancelada.', 'eb-credito-rural' ) ),
			'message_sent'    => array( 'success', __( 'Mensagem enviada à equipe.', 'eb-credito-rural' ) ),
			'message_error'   => array( 'error', __( 'Não foi possível enviar a mensagem.', 'eb-credito-rural' ) ),
			'profile_saved'   => array( 'success', __( 'Dados atualizados.', 'eb-credito-rural' ) ),
			'profile_errors'  => array( 'error', __( 'Verifique os campos.', 'eb-credito-rural' ) ),
			'password_saved'  => array( 'success', __( 'Senha alterada.', 'eb-credito-rural' ) ),
			'password_errors' => array( 'error', __( 'Não foi possível alterar a senha.', 'eb-credito-rural' ) ),
			'lgpd_sent'       => array( 'success', __( 'Solicitação registrada. Responderemos no prazo legal.', 'eb-credito-rural' ) ),
			'lgpd_errors'     => array( 'error', __( 'Verifique o formulário.', 'eb-credito-rural' ) ),
			'rules'           => array( 'error', __( 'Não é possível iniciar uma nova solicitação agora. Veja o motivo no painel.', 'eb-credito-rural' ) ),
		);
		return isset( $map[ $code ] ) ? array(
			'type' => $map[ $code ][0],
			'text' => $map[ $code ][1],
		) : null;
	}
}
