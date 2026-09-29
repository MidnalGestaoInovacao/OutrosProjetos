<?php
/**
 * Verificação em duas etapas da equipe: TOTP (vetores da RFC 6238), ativação, backup, sessão pendente, modos e limite de tentativas.
 *
 * @package EBCR
 */

use EBCR\Database\AuditLogRepository;
use EBCR\Roles\Capabilities;
use EBCR\Security\Crypto;
use EBCR\Security\RateLimiter;
use EBCR\Security\Totp;
use EBCR\Security\TwoFactor;
use EBCR\Support\Options;

/**
 * 2FA.
 */
final class TwoFactorTest extends EBCR_TestCase {

	/**
	 * Segredo dos vetores de teste da RFC 6238 ("12345678901234567890").
	 *
	 * @var string
	 */
	private $rfc_secret;

	protected function setUp(): void {
		parent::setUp();
		$this->rfc_secret = Totp::base32_encode( '12345678901234567890' );
		Options::update( array( 'team_2fa_mode' => 'optional' ) );
		add_filter( 'send_auth_cookies', '__return_false' );
		add_filter( 'pre_wp_mail', '__return_true' );
		unset( $_COOKIE[ LOGGED_IN_COOKIE ], $_COOKIE[ TwoFactor::COOKIE_TRUST ] );
	}

	protected function tearDown(): void {
		remove_filter( 'send_auth_cookies', '__return_false' );
		remove_filter( 'pre_wp_mail', '__return_true' );
		unset( $_COOKIE[ LOGGED_IN_COOKIE ], $_COOKIE[ TwoFactor::COOKIE_TRUST ] );
		Options::update( array( 'team_2fa_mode' => 'optional' ) );
		parent::tearDown();
	}

	/**
	 * Cria analista com TOTP ativo. Retorna [id, segredo].
	 *
	 * @return array
	 */
	private function analyst_with_totp() {
		$uid    = $this->make_user( Capabilities::ROLE_ANALYST );
		$secret = TwoFactor::pending_secret( $uid );
		$codes  = TwoFactor::activate_totp( $uid, Totp::code( $secret ) );
		$this->assertIsArray( $codes );
		RateLimiter::clear( '2fa_verify', $uid );
		return array( $uid, $secret, $codes );
	}

	/**
	 * Faz login por senha (wp_signon) e devolve o token da sessão criada.
	 *
	 * @param int $uid Usuário.
	 * @return string
	 */
	private function signon( $uid ) {
		$u = get_userdata( $uid );
		RateLimiter::clear( 'login_ip', '127.0.0.1' );
		RateLimiter::clear( 'login_user', strtolower( $u->user_login ) );
		$r = wp_signon(
			array(
				'user_login'    => $u->user_login,
				'user_password' => 'Senha-Forte-123!',
			)
		);
		$this->assertInstanceOf( WP_User::class, $r, is_wp_error( $r ) ? $r->get_error_message() : '' );
		$token = TwoFactor::login_token( $uid );
		$this->assertNotSame( '', $token, 'token da sessão deveria ter sido capturado em set_logged_in_cookie' );
		return $token;
	}

	/**
	 * Simula a requisição seguinte com o cookie de sessão.
	 *
	 * @param int    $uid   Usuário.
	 * @param string $token Token.
	 * @return void
	 */
	private function as_session( $uid, $token ) {
		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $uid, time() + DAY_IN_SECONDS, 'logged_in', $token );
		wp_set_current_user( $uid );
		$this->assertSame( $token, wp_get_session_token() );
	}

	public function test_totp_rfc6238_vectors_and_base32(): void {
		$this->assertSame( 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $this->rfc_secret );
		$this->assertSame( '12345678901234567890', Totp::base32_decode( $this->rfc_secret ) );
		$this->assertSame( '12345678901234567890', Totp::base32_decode( 'gezd gnbv-gy3t qojq gezd gnbv gy3t qojq==' ), 'decode tolera minúsculas, espaços, hífens e padding' );
		$this->assertNull( Totp::base32_decode( 'ABC1' ), 'caractere fora do alfabeto' );
		foreach ( array( 59 => '94287082', 1111111109 => '07081804', 1234567890 => '89005924' ) as $t => $expected ) {
			$this->assertSame( $expected, Totp::code( $this->rfc_secret, $t, 30, 8 ), "T={$t}" );
			$this->assertSame( substr( $expected, -6 ), Totp::code( $this->rfc_secret, $t ), "T={$t} (6 dígitos)" );
		}
		// Janela ±1 passo (30 s): aceita o código do passo anterior e do seguinte, rejeita dois passos de distância.
		$this->assertSame( 1, Totp::verify( $this->rfc_secret, '287082', 1, 59 ) );
		$this->assertSame( 1, Totp::verify( $this->rfc_secret, '287082', 1, 89 ) );
		$this->assertFalse( Totp::verify( $this->rfc_secret, '287082', 1, 120 ) );
		$this->assertFalse( Totp::verify( $this->rfc_secret, '000000', 1, 59 ) );
		$this->assertFalse( Totp::verify( $this->rfc_secret, '28708', 1, 59 ), 'tamanho errado' );
		$secret = Totp::generate_secret();
		$this->assertMatchesRegularExpression( '/^[A-Z2-7]{32}$/', $secret, '20 bytes → 32 caracteres base32' );
		$this->assertSame( 20, strlen( Totp::base32_decode( $secret ) ) );
		$uri = Totp::uri( $secret, 'gestor@exemplo.com', 'EB Crédito' );
		$this->assertStringStartsWith( 'otpauth://totp/EB%20Cr%C3%A9dito:gestor%40exemplo.com?secret=' . $secret, $uri );
		$this->assertStringContainsString( '&issuer=EB%20Cr%C3%A9dito&algorithm=SHA1&digits=6&period=30', $uri );
		$this->assertSame( 'ABCD EFGH IJ', Totp::format_secret( 'ABCDEFGHIJ' ) );
	}

	public function test_activation_requires_valid_code(): void {
		$uid    = $this->make_user( Capabilities::ROLE_ANALYST );
		$secret = TwoFactor::pending_secret( $uid );
		$this->assertSame( $secret, TwoFactor::pending_secret( $uid ), 'segredo provisório é estável enquanto não expira' );
		$this->assertFalse( TwoFactor::is_enabled( $uid ) );

		$r = TwoFactor::activate_totp( $uid, '000000' === Totp::code( $secret ) ? '111111' : '000000' );
		$this->assertInstanceOf( WP_Error::class, $r );
		$this->assertSame( 'invalid', $r->get_error_code() );
		$this->assertFalse( TwoFactor::is_enabled( $uid ), 'código inválido não ativa' );

		$codes = TwoFactor::activate_totp( $uid, Totp::code( $secret ) );
		$this->assertIsArray( $codes );
		$this->assertCount( 10, $codes );
		$this->assertTrue( TwoFactor::is_enabled( $uid ) );
		$this->assertSame( 'totp', TwoFactor::method( $uid ) );
		$this->assertSame( $secret, TwoFactor::secret( $uid ) );
		$this->assertSame( '', TwoFactor::pending_secret( $uid, false ), 'segredo provisório descartado após ativar' );
		if ( Crypto::is_available() ) {
			$this->assertTrue( TwoFactor::secret_is_encrypted( $uid ), 'segredo guardado cifrado' );
			$this->assertStringStartsWith( Crypto::PREFIX, (string) get_user_meta( $uid, TwoFactor::META_SECRET, true ) );
		}
		foreach ( (array) get_user_meta( $uid, TwoFactor::META_BACKUP, true ) as $hash ) {
			$this->assertStringNotContainsString( str_replace( '-', '', $codes[0] ), $hash, 'backup guardado com hash' );
		}
		// Auditoria.
		list( $rows ) = ( new AuditLogRepository() )->query(
			array(
				'action'    => '2fa_enabled',
				'object_id' => (string) $uid,
			)
		);
		$this->assertNotEmpty( $rows );
	}

	public function test_backup_code_is_single_use(): void {
		list( $uid, , $codes ) = $this->analyst_with_totp();
		$this->assertSame( 10, TwoFactor::backup_codes_left( $uid ) );
		$this->assertTrue( TwoFactor::verify_code( $uid, strtoupper( ' ' . $codes[3] . ' ' ), true ), 'aceita com espaços/maiúsculas' );
		$this->assertSame( 9, TwoFactor::backup_codes_left( $uid ) );
		$again = TwoFactor::verify_code( $uid, $codes[3], true );
		$this->assertInstanceOf( WP_Error::class, $again, 'segundo uso deve falhar' );
		$this->assertSame( 'invalid', $again->get_error_code() );
		$this->assertSame( 9, TwoFactor::backup_codes_left( $uid ) );
		$this->assertTrue( TwoFactor::verify_code( $uid, $codes[7], true ) );
		$this->assertSame( 8, TwoFactor::backup_codes_left( $uid ) );
	}

	public function test_pending_session_blocks_until_verified(): void {
		list( $uid, $secret ) = $this->analyst_with_totp();
		$token = $this->signon( $uid );
		$this->assertSame( 'pending', TwoFactor::session_flag( $uid, $token ) );
		$this->assertSame( 'verify', TwoFactor::required_action( $uid, $token ) );

		$this->as_session( $uid, $token );
		$this->assertSame( 'verify', TwoFactor::required_action() );
		$rest = TwoFactor::rest_gate( null );
		$this->assertInstanceOf( WP_Error::class, $rest );
		$this->assertSame( 'ebcr_2fa_verify', $rest->get_error_code() );
		$this->assertSame( 403, $rest->get_error_data()['status'] );
		$this->assertStringContainsString( 'ebcr_2fa=1', TwoFactor::login_redirect( admin_url(), admin_url(), get_userdata( $uid ) ) );

		// Código de um passo já consumido é recusado (anti-replay); o de um passo posterior (dentro da janela) é aceito.
		$last   = (int) get_user_meta( $uid, 'ebcr_2fa_last_step', true );
		$replay = TwoFactor::verify_code( $uid, Totp::code( $secret, $last * 30 ) );
		$this->assertInstanceOf( WP_Error::class, $replay, 'reuso do mesmo passo de tempo' );
		$this->assertTrue( TwoFactor::verify_code( $uid, Totp::code( $secret, ( $last + 1 ) * 30 ) ) );
		TwoFactor::complete_verification( $uid, $token, 'totp', false );
		$this->assertSame( 'verified', TwoFactor::session_flag( $uid, $token ) );
		$this->assertSame( '', TwoFactor::required_action() );
		$this->assertNull( TwoFactor::rest_gate( null ) );
		$this->assertSame( admin_url(), TwoFactor::login_redirect( admin_url(), admin_url(), get_userdata( $uid ) ) );

		// Um novo login volta a exigir a verificação.
		$token2 = $this->signon( $uid );
		$this->assertNotSame( $token, $token2 );
		$this->assertSame( 'verify', TwoFactor::required_action( $uid, $token2 ) );

		// Cliente nunca é cobrado, mesmo com o mesmo fluxo.
		$client = $this->make_user( Capabilities::ROLE_CLIENT );
		$ctoken = $this->signon( $client );
		$this->assertSame( '', TwoFactor::required_action( $client, $ctoken ) );
	}

	public function test_mode_off_does_not_block(): void {
		list( $uid ) = $this->analyst_with_totp();
		Options::update( array( 'team_2fa_mode' => 'off' ) );
		$token = $this->signon( $uid );
		$this->assertSame( '', TwoFactor::session_flag( $uid, $token ), 'com o modo desligado a sessão não é marcada' );
		$this->assertSame( '', TwoFactor::required_action( $uid, $token ) );
		// Mesmo uma sessão marcada como pendente (modo mudou depois do login) não bloqueia com o modo desligado.
		TwoFactor::set_session_flag( $uid, $token, 'pending' );
		$this->assertSame( '', TwoFactor::required_action( $uid, $token ) );
		$this->as_session( $uid, $token );
		$this->assertNull( TwoFactor::rest_gate( null ) );
	}

	public function test_required_mode_demands_setup(): void {
		$uid   = $this->make_user( Capabilities::ROLE_MANAGER );
		$token = $this->signon( $uid );
		$this->assertSame( '', TwoFactor::required_action( $uid, $token ), 'opcional: sem 2FA não bloqueia' );

		Options::update( array( 'team_2fa_mode' => 'required' ) );
		$this->assertSame( 'setup', TwoFactor::required_action( $uid, $token ) );
		$this->as_session( $uid, $token );
		$rest = TwoFactor::rest_gate( null );
		$this->assertInstanceOf( WP_Error::class, $rest );
		$this->assertSame( 'ebcr_2fa_setup', $rest->get_error_code() );
		$this->assertStringContainsString( 'profile.php', TwoFactor::setup_url() );

		// XML-RPC (só senha) é recusado para quem está sujeito ao 2FA.
		$x = TwoFactor::block_xmlrpc( get_userdata( $uid ), 'x', 'y', true );
		$this->assertInstanceOf( WP_Error::class, $x );
		$this->assertSame( 'ebcr_2fa_xmlrpc', $x->get_error_code() );
		$this->assertInstanceOf( WP_User::class, TwoFactor::block_xmlrpc( get_userdata( $uid ), 'x', 'y', false ) );

		// Cliente fica fora do modo obrigatório.
		$client = $this->make_user( Capabilities::ROLE_CLIENT );
		$this->assertSame( '', TwoFactor::required_action( $client, 'qualquer' ) );

		// Depois de ativar, a sessão que provou posse do fator é liberada.
		$secret = TwoFactor::pending_secret( $uid );
		$this->assertIsArray( TwoFactor::activate_totp( $uid, Totp::code( $secret ) ) );
		TwoFactor::set_session_flag( $uid, $token, 'verified' );
		$this->assertSame( '', TwoFactor::required_action( $uid, $token ) );
	}

	public function test_rate_limit_locks_after_five_failures(): void {
		list( $uid, $secret ) = $this->analyst_with_totp();
		$wrong = '000000' === Totp::code( $secret, time() + 30 ) ? '111111' : '000000';
		for ( $i = 1; $i <= 4; $i++ ) {
			$r = TwoFactor::verify_code( $uid, $wrong );
			$this->assertInstanceOf( WP_Error::class, $r );
			$this->assertSame( 'invalid', $r->get_error_code(), "tentativa {$i}" );
		}
		$r = TwoFactor::verify_code( $uid, $wrong );
		$this->assertSame( 'locked', $r->get_error_code(), '5ª falha bloqueia' );
		$this->assertGreaterThan( 0, RateLimiter::blocked_for( '2fa_verify', $uid ) );
		// Bloqueado: nem o código certo passa.
		$r = TwoFactor::verify_code( $uid, Totp::code( $secret, time() + 30 ) );
		$this->assertSame( 'locked', $r->get_error_code() );
		list( $rows ) = ( new AuditLogRepository() )->query(
			array(
				'action'    => '2fa_failed',
				'object_id' => (string) $uid,
			)
		);
		$this->assertGreaterThanOrEqual( 6, count( $rows ) );
		RateLimiter::clear( '2fa_verify', $uid );
		$this->assertTrue( TwoFactor::verify_code( $uid, Totp::code( $secret, time() + 30 ) ) );
	}

	public function test_email_code_flow_and_activation(): void {
		$uid  = $this->make_user( Capabilities::ROLE_ANALYST );
		$sent = array();
		$grab = static function ( $event, $recipient, $vars ) use ( &$sent ) {
			if ( 'two_factor_code' === $event ) {
				$sent[] = array( $recipient, $vars['codigo'] );
			}
		};
		add_action( 'ebcr_notification_sent', $grab, 10, 3 );
		RateLimiter::clear( '2fa_send', $uid . ':activation' );
		RateLimiter::clear( '2fa_send', $uid . ':login' );

		$this->assertTrue( TwoFactor::send_email_code( $uid, 'activation' ) );
		$this->assertCount( 1, $sent );
		$this->assertSame( get_userdata( $uid )->user_email, $sent[0][0] );
		$this->assertMatchesRegularExpression( '/^\d{6}$/', $sent[0][1] );
		$this->assertTrue( TwoFactor::has_email_code( $uid, 'activation' ) );
		$this->assertInstanceOf( WP_Error::class, TwoFactor::activate_email( $uid, '999999' === $sent[0][1] ? '000000' : '999999' ) );
		$this->assertFalse( TwoFactor::is_enabled( $uid ) );
		$codes = TwoFactor::activate_email( $uid, $sent[0][1] );
		$this->assertIsArray( $codes );
		$this->assertSame( 'email', TwoFactor::method( $uid ) );
		$this->assertFalse( TwoFactor::check_email_code( $uid, $sent[0][1], 'activation' ), 'código consumido' );

		// Login: código enviado, uso único, reenvio limitado a 3 por 10 minutos.
		$this->assertTrue( TwoFactor::send_email_code( $uid, 'login' ) );
		$code = $sent[1][1];
		$this->assertTrue( TwoFactor::verify_code( $uid, $code ) );
		$this->assertInstanceOf( WP_Error::class, TwoFactor::verify_code( $uid, $code ), 'código de e-mail é de uso único' );
		$this->assertTrue( TwoFactor::send_email_code( $uid, 'login' ) );
		$this->assertTrue( TwoFactor::send_email_code( $uid, 'login' ) );
		$limited = TwoFactor::send_email_code( $uid, 'login' );
		$this->assertInstanceOf( WP_Error::class, $limited );
		$this->assertSame( 'too_many', $limited->get_error_code() );
		remove_action( 'ebcr_notification_sent', $grab, 10 );
		$this->assertSame( 'e***@example.com', substr( TwoFactor::mask_email( 'e@example.com' ), 0 ) );
	}

	public function test_trusted_device_skips_verification_and_can_be_revoked(): void {
		list( $uid ) = $this->analyst_with_totp();
		$token = TwoFactor::trust_device( $uid );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{40}$/', $token );
		$this->assertCount( 1, TwoFactor::trusted_devices( $uid ) );
		$this->assertStringNotContainsString( $token, wp_json_encode( get_user_meta( $uid, TwoFactor::META_TRUSTED, true ) ), 'só o hash fica no meta' );

		$_COOKIE[ TwoFactor::COOKIE_TRUST ] = $uid . ':' . $token;
		$session                            = $this->signon( $uid );
		$this->assertSame( 'verified', TwoFactor::session_flag( $uid, $session ), 'dispositivo confiável dispensa o código' );
		$this->assertSame( '', TwoFactor::required_action( $uid, $session ) );

		$_COOKIE[ TwoFactor::COOKIE_TRUST ] = $uid . ':' . str_repeat( 'a', 40 );
		$this->assertFalse( TwoFactor::trusted_cookie_valid( $uid ) );
		$_COOKIE[ TwoFactor::COOKIE_TRUST ] = ( $uid + 1 ) . ':' . $token;
		$this->assertFalse( TwoFactor::trusted_cookie_valid( $uid ), 'cookie de outro usuário' );

		$_COOKIE[ TwoFactor::COOKIE_TRUST ] = $uid . ':' . $token;
		$this->assertSame( 1, TwoFactor::revoke_trusted( $uid ) );
		$this->assertFalse( TwoFactor::trusted_cookie_valid( $uid ) );
		$session2 = $this->signon( $uid );
		$this->assertSame( 'pending', TwoFactor::session_flag( $uid, $session2 ) );
	}

	public function test_disable_by_user_and_by_admin(): void {
		list( $uid, $secret, $codes ) = $this->analyst_with_totp();
		$this->assertFalse( TwoFactor::confirm_identity( $uid, 'senha-errada' ) );
		$this->assertTrue( TwoFactor::confirm_identity( $uid, 'Senha-Forte-123!' ) );
		$this->assertTrue( TwoFactor::confirm_identity( $uid, $codes[0] ), 'código de backup confirma' );
		$this->assertTrue( TwoFactor::confirm_identity( $uid, Totp::code( $secret, time() + 30 ) ), 'código do aplicativo confirma' );
		TwoFactor::disable( $uid, false );
		$this->assertFalse( TwoFactor::is_enabled( $uid ) );
		$this->assertSame( '', TwoFactor::secret( $uid ) );
		$this->assertSame( 0, TwoFactor::backup_codes_left( $uid ) );

		// Administrador desativa o 2FA de outro usuário: auditoria com by_admin.
		list( $other ) = $this->analyst_with_totp();
		$admin         = $this->make_user( 'administrator' );
		wp_set_current_user( $admin );
		TwoFactor::disable( $other, true );
		$this->assertFalse( TwoFactor::is_enabled( $other ) );
		list( $rows ) = ( new AuditLogRepository() )->query(
			array(
				'action'    => '2fa_disabled',
				'object_id' => (string) $other,
			)
		);
		$this->assertNotEmpty( $rows );
		$this->assertSame( $admin, (int) $rows[0]['actor_id'] );
		$this->assertTrue( json_decode( $rows[0]['meta'], true )['by_admin'] );
	}
}
