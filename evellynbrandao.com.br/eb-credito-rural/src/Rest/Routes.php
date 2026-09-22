<?php
/**
 * Endpoints REST internos (todos com permission_callback real).
 *
 * @package EBCR
 */

namespace EBCR\Rest;

use EBCR\Database\DocumentRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Files\UploadHandler;
use EBCR\Forms\SubmissionService;
use EBCR\Forms\Wizard;
use EBCR\Security\Authorization;
use EBCR\Security\Honeypot;
use EBCR\Security\MathCaptcha;
use EBCR\Security\RateLimiter;
use EBCR\Support\Ip;

defined( 'ABSPATH' ) || exit;

/**
 * Namespace ebcr/v1. Autenticação por cookie + nonce (X-WP-Nonce).
 */
final class Routes {

	const NS = 'ebcr/v1';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Registra rotas.
	 *
	 * @return void
	 */
	public function routes() {
		$uuid = '(?P<id>[a-f0-9-]{36})';
		register_rest_route(
			self::NS,
			'/captcha',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'captcha' ),
				'permission_callback' => array( $this, 'allow_public_rate_limited' ),
			)
		);
		register_rest_route(
			self::NS,
			"/submissions/{$uuid}/step/(?P<step>[1-5])",
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'step' ),
				'permission_callback' => array( $this, 'owner_can_edit' ),
			)
		);
		register_rest_route(
			self::NS,
			"/submissions/{$uuid}/documents",
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'upload' ),
					'permission_callback' => array( $this, 'owner_can_upload' ),
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'documents' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			"/documents/{$uuid}",
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_document' ),
				'permission_callback' => array( $this, 'logged_in' ),
			)
		);
		register_rest_route(
			self::NS,
			"/submissions/{$uuid}/submit",
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit' ),
				'permission_callback' => array( $this, 'owner_can_edit' ),
			)
		);
		register_rest_route(
			self::NS,
			"/submissions/{$uuid}/messages",
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'message' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);
		register_rest_route(
			self::NS,
			"/submissions/{$uuid}/status",
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'team_can_edit' ),
			)
		);
		register_rest_route(
			self::NS,
			"/submissions/{$uuid}/assign",
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'assign' ),
				'permission_callback' => array( $this, 'team_can_edit' ),
			)
		);
		register_rest_route(
			self::NS,
			"/submissions/{$uuid}/request-document",
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'request_document' ),
				'permission_callback' => array( $this, 'team_can_edit' ),
			)
		);
		register_rest_route(
			self::NS,
			"/documents/{$uuid}/review",
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'review' ),
				'permission_callback' => array( $this, 'team_can_review' ),
			)
		);
	}

	/**
	 * Solicitação do request (por UUID) ou null.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|null
	 */
	private function submission( $request ) {
		return ( new SubmissionRepository() )->find_by_public_id( (string) $request['id'] );
	}

	/**
	 * Usuário logado? (a autenticação por cookie exige nonce válido; sem nonce o WP trata como visitante).
	 *
	 * @return bool|\WP_Error
	 */
	public function logged_in() {
		return is_user_logged_in() ? true : new \WP_Error( 'rest_forbidden', __( 'É necessário estar autenticado.', 'eb-credito-rural' ), array( 'status' => 401 ) );
	}

	/**
	 * Público com limite por IP (captcha).
	 *
	 * @return bool|\WP_Error
	 */
	public function allow_public_rate_limited() {
		return RateLimiter::hit( 'captcha', Ip::get(), 30, 10 * MINUTE_IN_SECONDS ) ? true : new \WP_Error( 'rest_too_many', __( 'Muitas requisições. Aguarde alguns minutos.', 'eb-credito-rural' ), array( 'status' => 429 ) );
	}

	/**
	 * Dono e status editável.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function owner_can_edit( $request ) {
		$s = $this->submission( $request );
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'rest_forbidden', __( 'É necessário estar autenticado.', 'eb-credito-rural' ), array( 'status' => 401 ) );
		}
		if ( ! $s || ! Authorization::owns( get_current_user_id(), $s ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Solicitação não encontrada.', 'eb-credito-rural' ), array( 'status' => 404 ) );
		}
		return Authorization::client_can_edit( get_current_user_id(), $s ) ? true : new \WP_Error( 'rest_locked', __( 'Esta solicitação não pode ser editada.', 'eb-credito-rural' ), array( 'status' => 403 ) );
	}

	/**
	 * Dono pode enviar documentos (rascunho, pendência ou pedido aberto).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function owner_can_upload( $request ) {
		$s = $this->submission( $request );
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'rest_forbidden', __( 'É necessário estar autenticado.', 'eb-credito-rural' ), array( 'status' => 401 ) );
		}
		if ( ! $s || ! Authorization::owns( get_current_user_id(), $s ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Solicitação não encontrada.', 'eb-credito-rural' ), array( 'status' => 404 ) );
		}
		return Authorization::client_can_upload( get_current_user_id(), $s ) ? true : new \WP_Error( 'rest_locked', __( 'Esta solicitação não aceita documentos no momento.', 'eb-credito-rural' ), array( 'status' => 403 ) );
	}

	/**
	 * Pode ver (dono ou equipe com acesso).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function can_view( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'rest_forbidden', __( 'É necessário estar autenticado.', 'eb-credito-rural' ), array( 'status' => 401 ) );
		}
		$s = $this->submission( $request );
		return $s && Authorization::can_view_submission( get_current_user_id(), $s ) ? true : new \WP_Error( 'rest_forbidden', __( 'Solicitação não encontrada.', 'eb-credito-rural' ), array( 'status' => 404 ) );
	}

	/**
	 * Equipe pode editar.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function team_can_edit( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'rest_forbidden', __( 'É necessário estar autenticado.', 'eb-credito-rural' ), array( 'status' => 401 ) );
		}
		$s = $this->submission( $request );
		return $s && Authorization::team_can_edit( get_current_user_id(), $s ) ? true : new \WP_Error( 'rest_forbidden', __( 'Sem permissão.', 'eb-credito-rural' ), array( 'status' => 403 ) );
	}

	/**
	 * Equipe pode revisar documento.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function team_can_review( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'rest_forbidden', __( 'É necessário estar autenticado.', 'eb-credito-rural' ), array( 'status' => 401 ) );
		}
		$d = ( new DocumentRepository() )->find_by_public_id( (string) $request['id'] );
		if ( ! $d ) {
			return new \WP_Error( 'rest_forbidden', __( 'Documento não encontrado.', 'eb-credito-rural' ), array( 'status' => 404 ) );
		}
		$s = ( new SubmissionRepository() )->find( (int) $d['submission_id'] );
		return $s && Authorization::team_can_edit( get_current_user_id(), $s ) ? true : new \WP_Error( 'rest_forbidden', __( 'Sem permissão.', 'eb-credito-rural' ), array( 'status' => 403 ) );
	}

	/**
	 * Converte WP_Error com dados de status em resposta.
	 *
	 * @param mixed $result Resultado.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function respond( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * Novo captcha.
	 *
	 * @return \WP_REST_Response
	 */
	public function captcha() {
		return rest_ensure_response( MathCaptcha::provider()->issue() );
	}

	/**
	 * Salva etapa (draft=1 para autosave).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function step( $request ) {
		$s     = $this->submission( $request );
		$input = $request->get_json_params();
		if ( ! is_array( $input ) || ! $input ) {
			$input = $request->get_body_params();
		}
		$draft                          = ! empty( $request['draft'] ) || ! empty( $input['draft'] );
		list( $ok, $errors, $warnings ) = ( new Wizard() )->handle_step( get_current_user_id(), $s, (int) $request['step'], is_array( $input ) ? $input : array(), $draft );
		return rest_ensure_response(
			array(
				'ok'       => $ok,
				'errors'   => $errors,
				'warnings' => $warnings,
				'next'     => $ok ? min( 7, (int) $request['step'] + 1 ) : (int) $request['step'],
			)
		);
	}

	/**
	 * Upload multipart.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function upload( $request ) {
		if ( ! RateLimiter::hit( 'upload', get_current_user_id(), 120, HOUR_IN_SECONDS ) ) {
			return new \WP_Error( 'rest_too_many', __( 'Muitos envios em pouco tempo. Aguarde alguns minutos.', 'eb-credito-rural' ), array( 'status' => 429 ) );
		}
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new \WP_Error( 'no_file', __( 'Nenhum arquivo enviado.', 'eb-credito-rural' ), array( 'status' => 400 ) );
		}
		$s   = $this->submission( $request );
		$row = ( new UploadHandler() )->handle( get_current_user_id(), $s, $files['file'], (string) $request['doc_type'], (string) $request['ref_key'], (int) $request['request_id'] );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( ! empty( $row['request_id'] ) ) {
			\EBCR\Mail\Notifier::client_responded( $s, $row );
		}
		return rest_ensure_response( self::document_view( $row ) );
	}

	/**
	 * Lista documentos.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function documents( $request ) {
		$s    = $this->submission( $request );
		$docs = ( new DocumentRepository() )->for_submission( (int) $s['id'] );
		return rest_ensure_response( array_map( array( __CLASS__, 'document_view' ), $docs ) );
	}

	/**
	 * Representação segura de um documento.
	 *
	 * @param array $d Linha.
	 * @return array
	 */
	public static function document_view( array $d ) {
		return array(
			'id'            => $d['public_id'],
			'doc_type'      => $d['doc_type'],
			'ref_key'       => $d['ref_key'],
			'name'          => $d['original_name'],
			'size'          => (int) $d['size'],
			'mime'          => $d['mime'],
			'review_status' => $d['review_status'],
			'review_note'   => (string) $d['review_note'],
			'expires_at'    => $d['expires_at'],
			'uploaded_at'   => $d['uploaded_at'],
			'download_url'  => \EBCR\Files\DownloadController::url( $d ),
		);
	}

	/**
	 * Remove documento (cliente).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_document( $request ) {
		$d = ( new DocumentRepository() )->find_by_public_id( (string) $request['id'] );
		if ( ! $d ) {
			return new \WP_Error( 'not_found', __( 'Documento não encontrado.', 'eb-credito-rural' ), array( 'status' => 404 ) );
		}
		return $this->respond( SubmissionService::client_delete_document( get_current_user_id(), $d ) );
	}

	/**
	 * Envio final.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit( $request ) {
		$input = $request->get_body_params();
		$json  = $request->get_json_params();
		if ( is_array( $json ) && $json ) {
			$input = $json;
		}
		$hp = Honeypot::check( $input );
		if ( true !== $hp ) {
			return new \WP_Error( 'antispam', __( 'Não foi possível validar o envio. Recarregue a página e tente novamente.', 'eb-credito-rural' ), array( 'status' => 422 ) );
		}
		if ( ! MathCaptcha::verify_request( $input ) ) {
			return new \WP_Error( 'captcha', __( 'Resposta da verificação de segurança incorreta ou expirada.', 'eb-credito-rural' ), array( 'status' => 422 ) );
		}
		$s      = $this->submission( $request );
		$result = ( new Wizard() )->submit( get_current_user_id(), $s, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response(
			array(
				'ok'       => true,
				'protocol' => $result['protocol'],
				'redirect' => \EBCR\Support\Helpers::portal_url(
					array(
						'ebcr_view' => 'solicitacao',
						'id'        => $result['public_id'],
						'enviada'   => '1',
					)
				),
			)
		);
	}

	/**
	 * Mensagem.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function message( $request ) {
		$s = $this->submission( $request );
		return $this->respond( SubmissionService::send_message( get_current_user_id(), $s, (string) $request['body'], (string) $request['visibility'] ) );
	}

	/**
	 * Status (equipe).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function status( $request ) {
		$s = $this->submission( $request );
		return $this->respond( SubmissionService::change_status( get_current_user_id(), $s, (string) $request['status'], (string) $request['comment_internal'], (string) $request['comment_client'] ) );
	}

	/**
	 * Atribuição (equipe).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function assign( $request ) {
		$s = $this->submission( $request );
		return $this->respond( SubmissionService::assign( get_current_user_id(), $s, (int) $request['analyst_id'] ) );
	}

	/**
	 * Pedido de documento (equipe).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function request_document( $request ) {
		$s = $this->submission( $request );
		return $this->respond( SubmissionService::request_document( get_current_user_id(), $s, (string) $request['doc_type'], (string) $request['label'], (string) $request['note'], ! empty( $request['set_status'] ) ) );
	}

	/**
	 * Revisão de documento (equipe).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function review( $request ) {
		$d = ( new DocumentRepository() )->find_by_public_id( (string) $request['id'] );
		return $this->respond( SubmissionService::review_document( get_current_user_id(), $d, (string) $request['result'], (string) $request['note'] ) );
	}
}
