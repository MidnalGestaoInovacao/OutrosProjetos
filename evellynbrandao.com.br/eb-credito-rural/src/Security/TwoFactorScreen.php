<?php
/**
 * Tela de verificação em duas etapas (front-end: home_url( '/?ebcr_2fa=1' )), processada em `init`.
 * Página HTML própria, mínima e acessível, sem o tema. Entregue pelo módulo "2FA".
 *
 * @package EBCR
 */

namespace EBCR\Security;

use EBCR\Support\Helpers;
use EBCR\Support\Options;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * GET renderiza; POST (action ebcr_2fa_verify | ebcr_2fa_resend, com nonce) processa e redireciona (PRG).
 */
final class TwoFactorScreen {

	/**
	 * Ponto de entrada (chamado em init quando ?ebcr_2fa está presente). Sempre termina a requisição.
	 *
	 * @return void
	 */
	public function handle() {
		nocache_headers();
		// phpcs:disable WordPress.Security.NonceVerification -- roteamento; cada ação verifica o próprio nonce abaixo.
		$do = isset( $_GET['ebcr_2fa_do'] ) ? sanitize_key( wp_unslash( $_GET['ebcr_2fa_do'] ) ) : '';
		// phpcs:enable
		if ( 'send_activation' === $do ) {
			$this->send_activation();
		}
		$user_id = get_current_user_id();
		if ( ! $user_id || ! TwoFactor::applies_to( $user_id ) ) {
			// Sem sessão (ou cliente, que não usa 2FA) não há nada a verificar nem a revelar: volta para o portal.
			wp_safe_redirect( Helpers::portal_url() );
			exit;
		}
		$action = TwoFactor::required_action( $user_id );
		if ( 'verify' !== $action ) {
			wp_safe_redirect( 'setup' === $action ? TwoFactor::setup_url() : $this->destination() );
			exit;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'POST' === $method ) {
			$this->post( $user_id );
		}
		$this->render( $user_id );
	}

	/**
	 * Destino após verificar (redirect_to validado ou painel do plugin).
	 *
	 * @return string
	 */
	private function destination() {
		// phpcs:disable WordPress.Security.NonceVerification -- apenas lê o destino, que é validado por wp_validate_redirect.
		$raw = '';
		if ( isset( $_POST['redirect_to'] ) ) {
			$raw = rawurldecode( sanitize_text_field( wp_unslash( $_POST['redirect_to'] ) ) );
		} elseif ( isset( $_GET['redirect_to'] ) ) {
			$raw = rawurldecode( sanitize_text_field( wp_unslash( $_GET['redirect_to'] ) ) );
		}
		// phpcs:enable
		$fallback = TwoFactor::default_destination();
		if ( '' === $raw ) {
			return $fallback;
		}
		$url = wp_validate_redirect( esc_url_raw( $raw ), $fallback );
		// Não volta para a própria tela nem para a saída.
		if ( false !== strpos( $url, TwoFactor::QUERY_VAR . '=' ) || false !== strpos( $url, 'action=logout' ) ) {
			return $fallback;
		}
		return $url;
	}

	/**
	 * URL desta tela preservando o destino e o modo (código normal ou backup).
	 *
	 * @param bool $backup Modo backup.
	 * @return string
	 */
	private function self_url( $backup = false ) {
		$args = array( TwoFactor::QUERY_VAR => '1' );
		$dest = $this->destination();
		if ( TwoFactor::default_destination() !== $dest ) {
			$args['redirect_to'] = rawurlencode( $dest );
		}
		if ( $backup ) {
			$args['backup'] = '1';
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Mensagem de uma requisição para a próxima (PRG), por usuário.
	 *
	 * @param int    $user_id Usuário.
	 * @param string $type    error | success | info.
	 * @param string $text    Texto.
	 * @return void
	 */
	private function flash( $user_id, $type, $text ) {
		set_transient(
			'ebcr_2fa_flash_' . (int) $user_id,
			array(
				'type' => $type,
				'text' => $text,
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Lê e apaga a mensagem pendente.
	 *
	 * @param int $user_id Usuário.
	 * @return array|null
	 */
	private function unflash( $user_id ) {
		$key = 'ebcr_2fa_flash_' . (int) $user_id;
		$msg = get_transient( $key );
		if ( $msg ) {
			delete_transient( $key );
			return is_array( $msg ) ? $msg : null;
		}
		return null;
	}

	/**
	 * Processa os formulários da tela e redireciona.
	 *
	 * @param int $user_id Usuário.
	 * @return void
	 */
	private function post( $user_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verificado por ação logo abaixo.
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		$backup = ! empty( $_POST['backup'] );
		// phpcs:enable
		if ( 'ebcr_2fa_resend' === $action ) {
			if ( ! Nonces::verify( '2fa_resend' ) || 'email' !== TwoFactor::method( $user_id ) ) {
				$this->flash( $user_id, 'error', __( 'Sessão expirada. Recarregue a página e tente novamente.', 'eb-credito-rural' ) );
			} else {
				$sent = TwoFactor::send_email_code( $user_id, 'login' );
				if ( is_wp_error( $sent ) ) {
					$this->flash( $user_id, 'error', $sent->get_error_message() );
				} else {
					$this->flash( $user_id, 'success', __( 'Enviamos um novo código para o seu e-mail.', 'eb-credito-rural' ) );
				}
			}
			wp_safe_redirect( $this->self_url( false ) );
			exit;
		}
		if ( 'ebcr_2fa_verify' !== $action ) {
			return;
		}
		if ( ! Nonces::verify( '2fa_verify' ) ) {
			$this->flash( $user_id, 'error', __( 'Sessão expirada. Recarregue a página e tente novamente.', 'eb-credito-rural' ) );
			wp_safe_redirect( $this->self_url( $backup ) );
			exit;
		}
		$code   = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verificado acima (Nonces::verify).
		$trust  = ! empty( $_POST['trust'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- idem.
		$result = TwoFactor::verify_code( $user_id, $code, $backup );
		if ( is_wp_error( $result ) ) {
			$this->flash( $user_id, 'error', $result->get_error_message() );
			wp_safe_redirect( $this->self_url( $backup ) );
			exit;
		}
		$via = $backup ? 'backup' : TwoFactor::method( $user_id );
		TwoFactor::complete_verification( $user_id, wp_get_session_token(), $via, $trust );
		wp_safe_redirect( $this->destination() );
		exit;
	}

	/**
	 * Link do perfil: envia o código de ativação do método por e-mail (GET com nonce, limitado) e volta ao perfil.
	 *
	 * @return void
	 */
	private function send_activation() {
		$user_id = get_current_user_id();
		if ( ! $user_id || ! Nonces::verify_get( '2fa_send_activation' ) || ! TwoFactor::applies_to( $user_id ) || TwoFactor::is_enabled( $user_id ) ) {
			wp_safe_redirect( $user_id ? admin_url( 'profile.php#ebcr-2fa' ) : Helpers::portal_url() );
			exit;
		}
		$sent = TwoFactor::send_email_code( $user_id, 'activation' );
		$user = wp_get_current_user();
		TwoFactorProfile::notice(
			$user_id,
			is_wp_error( $sent ) ? 'error' : 'success',
			is_wp_error( $sent ) ? $sent->get_error_message() : sprintf(
				/* translators: %s: e-mail mascarado */
				__( 'Enviamos um código de ativação para %s. Digite-o no campo "Código recebido" e clique em "Atualizar perfil".', 'eb-credito-rural' ),
				TwoFactor::mask_email( $user->user_email )
			)
		);
		wp_safe_redirect( admin_url( 'profile.php#ebcr-2fa' ) );
		exit;
	}

	/**
	 * Renderiza a página e encerra.
	 *
	 * @param int $user_id Usuário.
	 * @return void
	 */
	private function render( $user_id ) {
		$user   = get_userdata( $user_id );
		$method = TwoFactor::method( $user_id );
		$backup = ! empty( $_GET['backup'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- só alterna a variante do formulário.
		$flash  = $this->unflash( $user_id );
		$wait   = RateLimiter::blocked_for( '2fa_verify', $user_id );
		$msg    = $flash;
		if ( $wait > 0 && ( ! $msg || 'error' !== $msg['type'] ) ) {
			$msg = array(
				'type' => 'error',
				'text' => sprintf(
					/* translators: %d: minutos */
					__( 'Muitas tentativas incorretas. Aguarde %d minuto(s) e tente novamente.', 'eb-credito-rural' ),
					max( 1, (int) ceil( $wait / 60 ) )
				),
			);
		}
		// Método e-mail: envia o código automaticamente na primeira exibição.
		if ( 'email' === $method && ! $backup && $wait <= 0 && ! TwoFactor::has_email_code( $user_id, 'login' ) ) {
			$sent = TwoFactor::send_email_code( $user_id, 'login' );
			if ( is_wp_error( $sent ) && ! $msg ) {
				$msg = array(
					'type' => 'error',
					'text' => $sent->get_error_message(),
				);
			}
		}
		status_header( 200 );
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		header( 'X-Frame-Options: DENY' );
		header( 'Referrer-Policy: no-referrer' );
		wp_register_style( 'ebcr-2fa', EBCR_URL . 'assets/css/2fa.css', array(), EBCR_VERSION );
		View::show(
			'two-factor/verify',
			array(
				'site'         => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'logo_url'     => (string) Options::get( 'email_logo_url', '' ),
				'user'         => $user,
				'method'       => $method,
				'backup'       => $backup,
				'email_masked' => $user ? TwoFactor::mask_email( $user->user_email ) : '',
				'message'      => $msg,
				'locked'       => $wait > 0,
				'backup_left'  => TwoFactor::backup_codes_left( $user_id ),
				'redirect_to'  => $this->destination(),
				'form_url'     => $this->self_url( $backup ),
				'switch_url'   => $this->self_url( ! $backup ),
				'logout_url'   => wp_logout_url( Helpers::portal_url( array( 'ebcr_msg' => 'logged_out' ) ) ),
			)
		);
		exit;
	}
}
