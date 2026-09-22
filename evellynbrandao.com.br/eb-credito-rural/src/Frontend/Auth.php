<?php
/**
 * Cadastro, confirmação de e-mail, login e recuperação de senha do cliente.
 *
 * @package EBCR
 */

namespace EBCR\Frontend;

use EBCR\Database\CrmContactRepository;
use EBCR\Domain\Consent;
use EBCR\Forms\Validators\Rules;
use EBCR\Mail\Mailer;
use EBCR\Mail\Notifier;
use EBCR\Roles\Capabilities;
use EBCR\Security\AuditLog;
use EBCR\Security\Honeypot;
use EBCR\Security\MathCaptcha;
use EBCR\Security\Nonces;
use EBCR\Security\RateLimiter;
use EBCR\Support\Helpers;
use EBCR\Support\Ip;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Fluxos de autenticação com captcha, honeypot, tempo mínimo e limites.
 */
// phpcs:disable WordPress.Security.NonceVerification -- todos os handlers verificam o nonce (Nonces::verify/check_admin_referer) antes de ler a entrada.
final class Auth {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_nopriv_ebcr_register', array( $this, 'handle_register' ) );
		add_action( 'admin_post_ebcr_register', array( $this, 'handle_register' ) );
		add_action( 'admin_post_nopriv_ebcr_login', array( $this, 'handle_login' ) );
		add_action( 'admin_post_ebcr_login', array( $this, 'handle_login' ) );
		add_action( 'admin_post_ebcr_resend_confirm', array( $this, 'handle_resend' ) );
		add_action( 'admin_post_ebcr_reaccept', array( $this, 'handle_reaccept' ) );
		add_action( 'init', array( $this, 'maybe_confirm' ), 25 );
		add_filter( 'retrieve_password_title', array( $this, 'reset_title' ), 10, 3 );
		add_filter( 'retrieve_password_message', array( $this, 'reset_message' ), 10, 4 );
		add_filter( 'wp_mail_content_type', array( $this, 'reset_content_type' ) );
		add_action( 'login_form_lostpassword', array( $this, 'protect_lost_password' ) );
		add_filter( 'lostpassword_redirect', array( $this, 'lostpassword_redirect' ) );
	}

	/**
	 * Redireciona com mensagem para o portal.
	 *
	 * @param string $code  Código da mensagem.
	 * @param array  $extra Args extras.
	 * @return void
	 */
	private function back( $code, array $extra = array() ) {
		wp_safe_redirect( Helpers::portal_url( array_merge( array( 'ebcr_msg' => $code ), $extra ) ) );
		exit;
	}

	/**
	 * Guarda erros e valores para reexibir no formulário.
	 *
	 * @param string $form   Formulário.
	 * @param array  $errors Erros.
	 * @param array  $values Valores (sem senha).
	 * @return void
	 */
	private function remember( $form, array $errors, array $values ) {
		$key = 'ebcr_form_' . $form . '_' . md5( Ip::get() . '|' . ( isset( $_COOKIE['ebcr_fid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['ebcr_fid'] ) ) : '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- apenas identifica a sessão do formulário.
		set_transient(
			$key,
			array(
				'errors' => $errors,
				'values' => $values,
			),
			5 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Recupera erros/valores guardados (uso único).
	 *
	 * @param string $form Formulário.
	 * @return array{errors:array,values:array}
	 */
	public static function recall( $form ) {
		$key  = 'ebcr_form_' . $form . '_' . md5( Ip::get() . '|' . ( isset( $_COOKIE['ebcr_fid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['ebcr_fid'] ) ) : '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idem.
		$data = get_transient( $key );
		if ( $data ) {
			delete_transient( $key );
			return $data;
		}
		return array(
			'errors' => array(),
			'values' => array(),
		);
	}

	/**
	 * Verificações anti-spam comuns (honeypot, tempo, captcha, limite por IP).
	 *
	 * @param string $scope Escopo do limite.
	 * @param int    $max   Máximo por hora.
	 * @return string|null Mensagem de erro ou null.
	 */
	private function antispam( $scope, $max ) {
		if ( ! RateLimiter::hit( $scope, Ip::get(), $max, HOUR_IN_SECONDS ) ) {
			return __( 'Muitas tentativas. Aguarde alguns minutos e tente novamente.', 'eb-credito-rural' );
		}
		$hp = Honeypot::check( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verificado pelo chamador.
		if ( true !== $hp ) {
			return __( 'Não foi possível validar o envio. Recarregue a página e tente novamente.', 'eb-credito-rural' );
		}
		if ( ! MathCaptcha::verify_request( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- idem.
			return __( 'Resposta da verificação de segurança incorreta ou expirada.', 'eb-credito-rural' );
		}
		return null;
	}

	/**
	 * Cadastro.
	 *
	 * @return void
	 */
	public function handle_register() {
		if ( ! Nonces::verify( 'register' ) ) {
			$this->back( 'nonce', array( 'ebcr_view' => 'cadastro' ) );
		}
		if ( is_user_logged_in() ) {
			$this->back( 'already' );
		}
		$values = array(
			'nome'     => isset( $_POST['nome'] ) ? sanitize_text_field( wp_unslash( $_POST['nome'] ) ) : '',
			'email'    => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'telefone' => isset( $_POST['telefone'] ) ? sanitize_text_field( wp_unslash( $_POST['telefone'] ) ) : '',
		);
		$errors = array();
		$spam   = $this->antispam( 'register', 10 );
		if ( $spam ) {
			$errors['_'] = $spam;
		}
		if ( mb_strlen( $values['nome'] ) < 5 || mb_strlen( $values['nome'] ) > 120 || false === strpos( trim( $values['nome'] ), ' ' ) ) {
			$errors['nome'] = __( 'Informe seu nome completo.', 'eb-credito-rural' );
		}
		if ( ! is_email( $values['email'] ) ) {
			$errors['email'] = __( 'Informe um e-mail válido.', 'eb-credito-rural' );
		} elseif ( email_exists( $values['email'] ) ) {
			$errors['email'] = __( 'Já existe uma conta com este e-mail. Faça login ou recupere a senha.', 'eb-credito-rural' );
		}
		if ( ! Rules::phone( $values['telefone'] ) ) {
			$errors['telefone'] = __( 'Informe telefone com DDD.', 'eb-credito-rural' );
		}
		$password = isset( $_POST['senha'] ) ? (string) wp_unslash( $_POST['senha'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- senha não deve ser alterada.
		$confirm  = isset( $_POST['senha2'] ) ? (string) wp_unslash( $_POST['senha2'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- idem.
		$pw_err   = self::password_error( $password, array( $values['nome'], $values['email'] ) );
		if ( $pw_err ) {
			$errors['senha'] = $pw_err;
		} elseif ( $password !== $confirm ) {
			$errors['senha2'] = __( 'As senhas não conferem.', 'eb-credito-rural' );
		}
		$accepted = array();
		foreach ( Consent::for_moment( 'register' ) as $key => $policy ) {
			$checked = ! empty( $_POST[ 'consent_' . $key ] );
			if ( $policy['required'] && ! $checked ) {
				$errors[ 'consent_' . $key ] = sprintf( /* translators: %s: política */ __( 'É necessário aceitar: %s.', 'eb-credito-rural' ), $policy['title'] );
			}
			if ( $checked ) {
				$accepted[] = $key;
			}
		}
		if ( $errors ) {
			$this->remember( 'register', $errors, $values );
			$this->back( 'register_errors', array( 'ebcr_view' => 'cadastro' ) );
		}
		$login   = sanitize_user( strstr( $values['email'], '@', true ) . '_' . wp_generate_password( 6, false, false ), true );
		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $values['email'],
				'user_pass'    => $password,
				'display_name' => $values['nome'],
				'first_name'   => trim( strstr( $values['nome'] . ' ', ' ', true ) ),
				'last_name'    => trim( (string) strstr( $values['nome'], ' ' ) ),
				'role'         => Capabilities::ROLE_CLIENT,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			$errors['_'] = __( 'Não foi possível criar a conta. Tente novamente.', 'eb-credito-rural' );
			$this->remember( 'register', $errors, $values );
			$this->back( 'register_errors', array( 'ebcr_view' => 'cadastro' ) );
		}
		update_user_meta( $user_id, 'ebcr_phone', Helpers::digits( $values['telefone'] ) );
		update_user_meta( $user_id, 'ebcr_email_verified', 0 );
		( new CrmContactRepository() )->get_or_create(
			$user_id,
			array(
				'phone'       => Helpers::digits( $values['telefone'] ),
				'lead_source' => 'site',
			)
		);
		Consent::record( $user_id, $accepted );
		AuditLog::log( 'register', 'user', $user_id, array( 'email' => $values['email'] ), $user_id );
		$this->send_confirmation( get_userdata( $user_id ) );
		// Loga o usuário (poderá navegar, mas não enviar até confirmar o e-mail).
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, false, is_ssl() );
		$this->back( 'registered' );
	}

	/**
	 * Mensagem de erro de senha fraca (null se ok).
	 *
	 * @param string   $password Senha.
	 * @param string[] $personal Dados pessoais que não podem constar.
	 * @return string|null
	 */
	public static function password_error( $password, array $personal = array() ) {
		$min = max( 8, Options::int( 'password_min_length' ) );
		if ( strlen( $password ) < $min ) {
			return sprintf( /* translators: %d: mínimo */ __( 'A senha deve ter pelo menos %d caracteres.', 'eb-credito-rural' ), $min );
		}
		if ( strlen( $password ) > 128 ) {
			return __( 'A senha é longa demais.', 'eb-credito-rural' );
		}
		$classes = (int) (bool) preg_match( '/[a-z]/', $password ) + (int) (bool) preg_match( '/[A-Z]/', $password ) + (int) (bool) preg_match( '/\d/', $password ) + (int) (bool) preg_match( '/[^a-zA-Z\d]/', $password );
		if ( $classes < 3 ) {
			return __( 'Use pelo menos três tipos de caracteres: letras minúsculas, maiúsculas, números e símbolos.', 'eb-credito-rural' );
		}
		foreach ( $personal as $p ) {
			$p = strtolower( trim( (string) $p ) );
			if ( strlen( $p ) >= 4 && false !== strpos( strtolower( $password ), $p ) ) {
				return __( 'A senha não pode conter seu nome ou e-mail.', 'eb-credito-rural' );
			}
		}
		if ( preg_match( '/^(.)\1+$/', $password ) || in_array( strtolower( $password ), array( '1234567890', 'password123', 'senha12345' ), true ) ) {
			return __( 'Escolha uma senha menos previsível.', 'eb-credito-rural' );
		}
		return null;
	}

	/**
	 * Gera token de confirmação e envia e-mail.
	 *
	 * @param \WP_User $user Usuário.
	 * @return void
	 */
	public function send_confirmation( $user ) {
		$token = Helpers::random_hex( 24 );
		update_user_meta( $user->ID, 'ebcr_confirm_hash', wp_hash( $token ) );
		update_user_meta( $user->ID, 'ebcr_confirm_expires', time() + DAY_IN_SECONDS );
		$link = add_query_arg(
			array(
				'ebcr_confirm' => $token,
				'uid'          => $user->ID,
			),
			Helpers::portal_url()
		);
		Notifier::register_confirm( $user, $link );
	}

	/**
	 * Reenvio da confirmação (usuário logado, limite por usuário).
	 *
	 * @return void
	 */
	public function handle_resend() {
		if ( ! is_user_logged_in() || ! Nonces::verify( 'resend' ) ) {
			$this->back( 'nonce' );
		}
		$user = wp_get_current_user();
		if ( get_user_meta( $user->ID, 'ebcr_email_verified', true ) ) {
			$this->back( 'already_verified' );
		}
		if ( ! RateLimiter::hit( 'resend', $user->ID, 3, HOUR_IN_SECONDS ) ) {
			$this->back( 'too_many' );
		}
		$this->send_confirmation( $user );
		$this->back( 'resent' );
	}

	/**
	 * Confirma e-mail via link (token de uso único, 24 h).
	 *
	 * @return void
	 */
	public function maybe_confirm() {
		if ( empty( $_GET['ebcr_confirm'] ) || empty( $_GET['uid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- token de uso único verificado abaixo.
			return;
		}
		$token = preg_replace( '/[^a-f0-9]/', '', sanitize_text_field( wp_unslash( $_GET['ebcr_confirm'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idem.
		$uid   = absint( $_GET['uid'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idem.
		$hash  = (string) get_user_meta( $uid, 'ebcr_confirm_hash', true );
		$exp   = (int) get_user_meta( $uid, 'ebcr_confirm_expires', true );
		if ( ! $uid || ! $hash || 48 !== strlen( $token ) || ! hash_equals( $hash, wp_hash( $token ) ) || $exp < time() ) {
			$this->back( 'confirm_invalid' );
		}
		update_user_meta( $uid, 'ebcr_email_verified', time() );
		delete_user_meta( $uid, 'ebcr_confirm_hash' );
		delete_user_meta( $uid, 'ebcr_confirm_expires' );
		AuditLog::log( 'email_confirmed', 'user', $uid, array(), $uid );
		$this->back( 'confirmed' );
	}

	/**
	 * Login.
	 *
	 * @return void
	 */
	public function handle_login() {
		if ( ! Nonces::verify( 'login' ) ) {
			$this->back( 'nonce' );
		}
		if ( is_user_logged_in() ) {
			$this->back( 'already' );
		}
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$hp    = Honeypot::check( $_POST );
		if ( true !== $hp ) {
			$this->remember( 'login', array( '_' => __( 'Não foi possível validar o envio. Recarregue a página e tente novamente.', 'eb-credito-rural' ) ), array( 'email' => $email ) );
			$this->back( 'login_errors' );
		}
		if ( Options::bool( 'captcha_on_login' ) && ! MathCaptcha::verify_request( $_POST ) ) {
			$this->remember( 'login', array( '_' => __( 'Resposta da verificação de segurança incorreta ou expirada.', 'eb-credito-rural' ) ), array( 'email' => $email ) );
			$this->back( 'login_errors' );
		}
		$password = isset( $_POST['senha'] ) ? (string) wp_unslash( $_POST['senha'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- senha.
		$user     = wp_signon(
			array(
				'user_login'    => $email,
				'user_password' => $password,
				'remember'      => ! empty( $_POST['lembrar'] ),
			),
			is_ssl()
		);
		if ( is_wp_error( $user ) ) {
			$msg = 'ebcr_too_many_attempts' === $user->get_error_code() ? $user->get_error_message() : __( 'E-mail ou senha inválidos.', 'eb-credito-rural' );
			$this->remember( 'login', array( '_' => $msg ), array( 'email' => $email ) );
			$this->back( 'login_errors' );
		}
		$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
		if ( user_can( $user, Capabilities::CAP_VIEW ) && ! $redirect ) {
			wp_safe_redirect( admin_url( 'admin.php?page=ebcr-submissions' ) );
			exit;
		}
		wp_safe_redirect( $redirect ? $redirect : Helpers::portal_url() );
		exit;
	}

	/**
	 * Novo aceite de políticas com versão alterada.
	 *
	 * @return void
	 */
	public function handle_reaccept() {
		if ( ! is_user_logged_in() || ! Nonces::verify( 'reaccept' ) ) {
			$this->back( 'nonce' );
		}
		$user_id  = get_current_user_id();
		$pending  = Consent::pending_reaccept( $user_id );
		$accepted = array();
		foreach ( $pending as $key => $policy ) {
			if ( empty( $_POST[ 'consent_' . $key ] ) ) {
				$this->back( 'reaccept_missing' );
			}
			$accepted[] = $key;
		}
		Consent::record( $user_id, $accepted );
		$this->back( 'reaccepted' );
	}

	/**
	 * Título do e-mail de recuperação de senha.
	 *
	 * @param string $title      Título.
	 * @param string $user_login Login.
	 * @param \WP_User $user_data Usuário.
	 * @return string
	 */
	public function reset_title( $title, $user_login = '', $user_data = null ) {
		if ( $user_data instanceof \WP_User && user_can( $user_data, Capabilities::CAP_CLIENT ) ) {
			return wp_strip_all_tags( Mailer::fill( Mailer::template( 'password_reset' )['subject'], array( 'nome' => $user_data->display_name ), false ) );
		}
		return $title;
	}

	/**
	 * Corpo do e-mail de recuperação de senha (clientes).
	 *
	 * @param string   $message    Mensagem.
	 * @param string   $key        Chave.
	 * @param string   $user_login Login.
	 * @param \WP_User $user_data  Usuário.
	 * @return string
	 */
	public function reset_message( $message, $key, $user_login, $user_data ) {
		if ( ! ( $user_data instanceof \WP_User ) || ! user_can( $user_data, Capabilities::CAP_CLIENT ) ) {
			return $message;
		}
		$link            = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user_login ), 'login' );
		$tpl             = Mailer::template( 'password_reset' );
		$body            = wpautop(
			wp_kses(
				Mailer::fill(
					$tpl['body'],
					array(
						'nome'             => $user_data->display_name,
						'link_confirmacao' => $link,
					),
					true
				),
				array(
					'a'      => array( 'href' => true ),
					'p'      => array(),
					'br'     => array(),
					'strong' => array(),
				)
			)
		);
		$this->html_mail = true;
		return \EBCR\Support\View::render(
			'emails/layout',
			array(
				'subject'  => $this->reset_title( '', $user_login, $user_data ),
				'body'     => $body,
				'logo_url' => (string) Options::get( 'email_logo_url', '' ),
				'site'     => get_bloginfo( 'name' ),
				'home'     => home_url( '/' ),
			)
		);
	}

	/**
	 * Marca e-mail de recuperação como HTML.
	 *
	 * @var bool
	 */
	private $html_mail = false;

	/**
	 * Content-type HTML apenas para o e-mail de recuperação gerado acima.
	 *
	 * @param string $type Tipo.
	 * @return string
	 */
	public function reset_content_type( $type ) {
		if ( $this->html_mail ) {
			$this->html_mail = false;
			return 'text/html';
		}
		return $type;
	}

	/**
	 * Limite de pedidos de recuperação de senha por IP.
	 *
	 * @return void
	 */
	public function protect_lost_password() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' === $method && ! RateLimiter::hit( 'lostpassword', Ip::get(), 5, HOUR_IN_SECONDS ) ) {
			wp_die( esc_html__( 'Muitas tentativas de recuperação de senha. Aguarde uma hora.', 'eb-credito-rural' ), 429 );
		}
	}

	/**
	 * Após pedir a recuperação, volta ao portal com mensagem.
	 *
	 * @param string $redirect Redirecionamento.
	 * @return string
	 */
	public function lostpassword_redirect( $redirect ) {
		return $redirect ? $redirect : Helpers::portal_url( array( 'ebcr_msg' => 'reset_sent' ) );
	}

	/**
	 * Mensagens por código.
	 *
	 * @param string $code Código.
	 * @return array{type:string,text:string}|null
	 */
	public static function message( $code ) {
		$map = array(
			'registered'       => array( 'success', __( 'Cadastro realizado! Enviamos um link de confirmação para o seu e-mail. Confirme para poder enviar solicitações.', 'eb-credito-rural' ) ),
			'confirmed'        => array( 'success', __( 'E-mail confirmado com sucesso. Você já pode iniciar uma solicitação.', 'eb-credito-rural' ) ),
			'confirm_invalid'  => array( 'error', __( 'Link de confirmação inválido ou expirado. Solicite um novo pela sua área.', 'eb-credito-rural' ) ),
			'resent'           => array( 'success', __( 'Enviamos um novo link de confirmação.', 'eb-credito-rural' ) ),
			'already_verified' => array( 'info', __( 'Seu e-mail já está confirmado.', 'eb-credito-rural' ) ),
			'too_many'         => array( 'error', __( 'Muitas tentativas. Aguarde um pouco.', 'eb-credito-rural' ) ),
			'nonce'            => array( 'error', __( 'Sessão expirada. Recarregue a página e tente novamente.', 'eb-credito-rural' ) ),
			'already'          => array( 'info', __( 'Você já está conectado.', 'eb-credito-rural' ) ),
			'reset_sent'       => array( 'success', __( 'Se o e-mail estiver cadastrado, você receberá um link para redefinir a senha.', 'eb-credito-rural' ) ),
			'reaccepted'       => array( 'success', __( 'Obrigado. Seus aceites foram atualizados.', 'eb-credito-rural' ) ),
			'reaccept_missing' => array( 'error', __( 'É necessário aceitar todas as políticas atualizadas para continuar.', 'eb-credito-rural' ) ),
			'logged_out'       => array( 'info', __( 'Você saiu da sua conta.', 'eb-credito-rural' ) ),
		);
		return isset( $map[ $code ] ) ? array(
			'type' => $map[ $code ][0],
			'text' => $map[ $code ][1],
		) : null;
	}
}
