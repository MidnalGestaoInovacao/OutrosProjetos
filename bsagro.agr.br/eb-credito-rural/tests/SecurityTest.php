<?php
/**
 * Captcha, honeypot, rate limit, criptografia e proteção da pasta.
 *
 * @package EBCR
 */

use EBCR\Files\FileGuard;
use EBCR\Security\Crypto;
use EBCR\Security\Honeypot;
use EBCR\Security\MathCaptcha;
use EBCR\Security\RateLimiter;
use EBCR\Support\Options;

/**
 * Camadas anti-abuso.
 */
final class SecurityTest extends EBCR_TestCase {

	public function test_captcha_is_single_use_and_rejects_wrong_or_expired(): void {
		$c = new MathCaptcha();
		$i = $c->issue();
		$this->assertMatchesRegularExpression( '/^\d+ [+\-x] \d+$/', $i['question'] );
		$answer = get_transient( 'ebcr_captcha_' . $i['token'] );
		$this->assertTrue( $c->verify( $i['token'], $answer ) );
		$this->assertFalse( $c->verify( $i['token'], $answer ), 'reuso deve falhar' );
		$i2 = $c->issue();
		$this->assertFalse( $c->verify( $i2['token'], '999999' ) );
		$i3 = $c->issue();
		delete_transient( 'ebcr_captcha_' . $i3['token'] ); // expirado
		$this->assertFalse( $c->verify( $i3['token'], '1' ) );
		$this->assertFalse( $c->verify( 'zz', '1' ) );
	}

	public function test_honeypot_and_min_time(): void {
		Options::update( array( 'min_fill_seconds' => 3, 'honeypot' => true ) );
		$html = Honeypot::fields();
		preg_match( '/name="ebcr_ts" value="([^"]+)"/', $html, $m );
		$this->assertSame( 'too_fast', Honeypot::check( array( 'ebcr_ts' => $m[1] ) ) );
		$this->assertSame( 'honeypot', Honeypot::check( array( 'ebcr_ts' => $m[1], 'ebcr_website' => 'x' ) ) );
		$this->assertSame( 'bad_ts', Honeypot::check( array( 'ebcr_ts' => '1234.abcd' ) ) );
		Options::update( array( 'min_fill_seconds' => 0 ) );
		$this->assertTrue( Honeypot::check( array( 'ebcr_ts' => $m[1] ) ) );
	}

	public function test_rate_limiter_and_progressive_lock(): void {
		RateLimiter::clear( 't', 'k' );
		$this->assertTrue( RateLimiter::hit( 't', 'k', 2, 60 ) );
		$this->assertTrue( RateLimiter::hit( 't', 'k', 2, 60 ) );
		$this->assertFalse( RateLimiter::hit( 't', 'k', 2, 60 ) );
		$this->assertSame( 60, RateLimiter::lock( 't', 'k', 60 ) );
		$this->assertSame( 120, RateLimiter::lock( 't', 'k', 60 ) );
		$this->assertGreaterThan( 0, RateLimiter::blocked_for( 't', 'k' ) );
		RateLimiter::clear( 't', 'k' );
		$this->assertSame( 0, RateLimiter::blocked_for( 't', 'k' ) );
	}

	public function test_login_blocked_after_attempts(): void {
		Options::update( array( 'login_max_attempts' => 2, 'login_window_minutes' => 15 ) );
		$uid = $this->make_user();
		$u   = get_userdata( $uid );
		RateLimiter::clear( 'login_ip', '127.0.0.1' );
		RateLimiter::clear( 'login_user', strtolower( $u->user_email ) );
		for ( $i = 0; $i < 3; $i++ ) {
			wp_authenticate( $u->user_email, 'senha-errada' );
		}
		$r = wp_authenticate( $u->user_email, 'Senha-Forte-123!' );
		$this->assertInstanceOf( WP_Error::class, $r );
		$this->assertSame( 'ebcr_too_many_attempts', $r->get_error_code() );
		RateLimiter::clear( 'login_ip', '127.0.0.1' );
		RateLimiter::clear( 'login_user', strtolower( $u->user_email ) );
	}

	public function test_crypto_roundtrip(): void {
		if ( ! Crypto::is_available() ) {
			$this->markTestSkipped( 'EBCR_ENCRYPTION_KEY não definida no wp-config de teste.' );
		}
		$enc = Crypto::encrypt( 'segredo' );
		$this->assertStringStartsWith( 'ebcr1:', $enc );
		$this->assertSame( 'segredo', Crypto::decrypt( $enc ) );
		$this->assertNull( Crypto::decrypt( 'ebcr1:invalido' ) );
		$bytes = random_bytes( 5000 );
		$this->assertSame( $bytes, Crypto::decrypt_bytes( Crypto::encrypt_bytes( $bytes ) ) );
	}

	public function test_file_guard_writes_protection_and_detects_exposure(): void {
		$g = new FileGuard();
		$this->assertTrue( $g->ensure_base_dir() );
		$this->assertFileExists( $g->base_dir() . '.htaccess' );
		$this->assertStringContainsString( 'Require all denied', file_get_contents( $g->base_dir() . '.htaccess' ) );
		$this->assertFileExists( $g->base_dir() . 'index.php' );
		$sentinel = $g->ensure_sentinel( $g->base_dir() );
		$this->assertFileExists( $g->base_dir() . $sentinel );
		// Simula servidor que expõe o arquivo.
		$exposed = static function () use ( $g, $sentinel ) { return array( 'response' => array( 'code' => 200 ), 'body' => file_get_contents( $g->base_dir() . $sentinel ) ); };
		add_filter( 'pre_http_request', $exposed );
		$r = $g->test_protection();
		remove_filter( 'pre_http_request', $exposed );
		$this->assertSame( 'exposed', $r['result'] );
		// Simula servidor protegido.
		$denied = static function () { return array( 'response' => array( 'code' => 403 ), 'body' => 'Forbidden' ); };
		add_filter( 'pre_http_request', $denied );
		$r = $g->test_protection();
		remove_filter( 'pre_http_request', $denied );
		$this->assertSame( 'protected', $r['result'] );
	}
}
