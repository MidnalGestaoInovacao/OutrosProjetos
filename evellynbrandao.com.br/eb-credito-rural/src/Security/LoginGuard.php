<?php
/**
 * Limite de tentativas de login por IP e por usuário, com bloqueio progressivo.
 *
 * @package EBCR
 */

namespace EBCR\Security;

use EBCR\Support\Ip;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Integra com o fluxo nativo de autenticação (wp_signon / wp-login.php).
 */
final class LoginGuard {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'authenticate', array( $this, 'check_block' ), 99, 3 ); // depois dos handlers do core: um bloqueio vale mesmo com senha correta.
		add_action( 'wp_login_failed', array( $this, 'on_failed' ) );
		add_action( 'wp_login', array( $this, 'on_success' ), 10, 2 );
		add_filter( 'auth_cookie_expiration', array( $this, 'team_session' ), 10, 3 );
	}

	/**
	 * Bloqueia antes de validar a senha se IP ou usuário estiverem bloqueados.
	 *
	 * @param \WP_User|\WP_Error|null $user     Usuário.
	 * @param string                  $username Login.
	 * @param string                  $password Senha.
	 * @return \WP_User|\WP_Error|null
	 */
	public function check_block( $user, $username, $password ) {
		if ( empty( $username ) && empty( $password ) ) {
			return $user;
		}
		if ( $user instanceof \WP_Error && 'ebcr_too_many_attempts' === $user->get_error_code() ) {
			return $user;
		}
		$ip   = Ip::get();
		$wait = max( RateLimiter::blocked_for( 'login_ip', $ip ), RateLimiter::blocked_for( 'login_user', strtolower( (string) $username ) ) );
		if ( $wait > 0 ) {
			AuditLog::log(
				'login_blocked',
				'user',
				'',
				array(
					'login' => mb_substr( (string) $username, 0, 100 ),
					'wait'  => $wait,
				),
				0
			);
			return new \WP_Error(
				'ebcr_too_many_attempts',
				sprintf(
					/* translators: %d: minutos */
					__( 'Muitas tentativas de acesso. Tente novamente em %d minuto(s).', 'eb-credito-rural' ),
					max( 1, (int) ceil( $wait / 60 ) )
				)
			);
		}
		return $user;
	}

	/**
	 * Falha: incrementa contadores e aplica bloqueio ao exceder.
	 *
	 * @param string $username Login.
	 * @return void
	 */
	public function on_failed( $username ) {
		$max    = max( 1, Options::int( 'login_max_attempts' ) );
		$window = max( 60, Options::int( 'login_window_minutes' ) * 60 );
		$ip     = Ip::get();
		$user   = strtolower( (string) $username );
		if ( ! RateLimiter::hit( 'login_ip', $ip, $max, $window ) ) {
			RateLimiter::lock( 'login_ip', $ip, $window );
		}
		if ( $user && ! RateLimiter::hit( 'login_user', $user, $max, $window ) ) {
			RateLimiter::lock( 'login_user', $user, $window );
		}
	}

	/**
	 * Sucesso: limpa contadores.
	 *
	 * @param string   $login Login.
	 * @param \WP_User $user  Usuário.
	 * @return void
	 */
	public function on_success( $login, $user ) {
		RateLimiter::clear( 'login_ip', Ip::get() );
		RateLimiter::clear( 'login_user', strtolower( (string) $login ) );
		if ( $user instanceof \WP_User ) {
			RateLimiter::clear( 'login_user', strtolower( $user->user_email ) );
		}
	}

	/**
	 * Expiração de sessão configurável para a equipe.
	 *
	 * @param int  $expiration Segundos.
	 * @param int  $user_id    Usuário.
	 * @param bool $remember   Lembrar.
	 * @return int
	 */
	public function team_session( $expiration, $user_id, $remember ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- assinatura do filtro.
		$hours = Options::int( 'team_session_hours' );
		if ( $hours > 0 && user_can( $user_id, 'ebcr_view_submissions' ) ) {
			return min( $expiration, $hours * HOUR_IN_SECONDS );
		}
		return $expiration;
	}
}
