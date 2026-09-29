<?php
/**
 * Registro de consentimento de cookies (POST ebcr/v1/cookie-consent): validação, gravação sem IP em claro,
 * limite por IP, listagem/CSV, retenção e integração com exportador/apagador de dados pessoais.
 *
 * @package EBCR
 */

use EBCR\Admin\CookieConsentsView;
use EBCR\Database\CookieConsentRepository;
use EBCR\Domain\CookieConsent;
use EBCR\Security\PrivacyIntegration;
use EBCR\Security\RateLimiter;
use EBCR\Security\Retention;
use EBCR\Support\Ip;
use EBCR\Support\Options;

/**
 * 1.3.0.
 */
final class CookieConsentTest extends EBCR_TestCase {

	const ID = '3f1c2b8e-9d4a-4c2b-8f1e-0a1b2c3d4e5f';

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . \EBCR\Database\Db::table( 'cookie_consents' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		RateLimiter::clear( 'cookie_consent', Ip::get() );
	}

	/**
	 * Payload válido.
	 *
	 * @param array $over Sobrescritas.
	 * @return array
	 */
	private function payload( array $over = array() ) {
		return array_merge(
			array(
				'consent_id' => self::ID,
				'categories' => array(
					'necessary'   => true,
					'functional'  => true,
					'analytics'   => false,
					'advertising' => false,
				),
				'action'     => 'custom',
				'version'    => 3,
				'path'       => '/credito-rural/?utm_source=x#topo',
			),
			$over
		);
	}

	/**
	 * Faz o POST pela API REST.
	 *
	 * @param mixed $body Corpo.
	 * @return WP_REST_Response
	 */
	private function post( $body ) {
		$req = new WP_REST_Request( 'POST', '/ebcr/v1/cookie-consent' );
		$req->set_header( 'Content-Type', 'application/json' );
		$req->set_body( wp_json_encode( $body ) );
		return rest_get_server()->dispatch( $req );
	}

	public function test_payload_validation(): void {
		$clean = CookieConsent::validate( $this->payload() );
		$this->assertIsArray( $clean );
		$this->assertSame( '/credito-rural/', $clean['path'], 'só o caminho, sem query/fragmento' );
		$this->assertSame( 3, $clean['version'] );
		$this->assertSame(
			array(
				'necessary'   => true,
				'functional'  => true,
				'analytics'   => false,
				'advertising' => false,
			),
			$clean['categories']
		);
		// Normalizações.
		$c = CookieConsent::validate( $this->payload( array( 'consent_id' => strtoupper( self::ID ), 'version' => '7', 'path' => 'https://bsagro.agr.br/contato?x=1', 'categories' => array( 'analytics' => 'true', 'extra' => true, 'necessary' => false ) ) ) );
		$this->assertSame( self::ID, $c['consent_id'] );
		$this->assertSame( 7, $c['version'] );
		$this->assertSame( '/contato', $c['path'] );
		$this->assertTrue( $c['categories']['necessary'], 'necessários sempre ligados' );
		$this->assertTrue( $c['categories']['analytics'] );
		$this->assertFalse( $c['categories']['functional'] );
		$this->assertArrayNotHasKey( 'extra', $c['categories'], 'chaves fora da lista são ignoradas' );
		$this->assertSame( array( true, true, true, true ), array_values( CookieConsent::validate( $this->payload( array( 'action' => 'accept_all' ) ) )['categories'] ) );
		$reject = CookieConsent::validate( $this->payload( array( 'action' => 'reject_all' ) ) )['categories'];
		$this->assertTrue( $reject['necessary'] );
		$this->assertFalse( $reject['functional'] || $reject['analytics'] || $reject['advertising'] );
		$this->assertSame( '/' . str_repeat( 'a', 199 ), CookieConsent::validate( $this->payload( array( 'path' => '/' . str_repeat( 'a', 500 ) ) ) )['path'], 'até 200 caracteres' );

		$bad = array(
			'consent_id' => array( 'consent_id' => 'nao-e-uuid' ),
			'uuid v1'    => array( 'consent_id' => '3f1c2b8e-9d4a-1c2b-8f1e-0a1b2c3d4e5f' ),
			'action'     => array( 'action' => 'hack' ),
			'categories' => array( 'categories' => 'tudo' ),
			'lista'      => array( 'categories' => array( true, false ) ),
			'bool'       => array( 'categories' => array( 'analytics' => 'talvez' ) ),
			'version'    => array( 'version' => -1 ),
			'version2'   => array( 'version' => '1.5' ),
			'path'       => array( 'path' => array( '/x' ) ),
		);
		foreach ( $bad as $label => $over ) {
			$r = CookieConsent::validate( $this->payload( $over ) );
			$this->assertInstanceOf( WP_Error::class, $r, $label );
			$this->assertSame( 400, $r->get_error_data()['status'], $label );
		}
		$missing = $this->payload();
		unset( $missing['version'] );
		$this->assertInstanceOf( WP_Error::class, CookieConsent::validate( $missing ) );
		$this->assertInstanceOf( WP_Error::class, CookieConsent::validate( 'x' ) );
	}

	public function test_rest_route_stores_hash_not_ip_and_never_echoes_data(): void {
		$uid = $this->make_user();
		wp_set_current_user( $uid );
		$r = $this->post( $this->payload() );
		$this->assertSame( 200, $r->get_status() );
		$this->assertSame( array( 'ok' => true ), $r->get_data(), 'resposta sem dados gravados' );
		list( $items, $total ) = ( new CookieConsentRepository() )->query( array( 'consent_id' => self::ID ) );
		$this->assertSame( 1, $total );
		$row = $items[0];
		$this->assertSame( 'custom', $row['action'] );
		$this->assertSame( '/credito-rural/', $row['path'] );
		$this->assertSame( 3, (int) $row['version'] );
		$this->assertSame( hash_hmac( 'sha256', Ip::get(), wp_salt( 'auth' ) ), $row['ip_hash'] );
		$this->assertStringNotContainsString( Ip::get(), implode( '|', $row ), 'IP nunca em claro' );
		$this->assertLessThanOrEqual( 180, strlen( $row['user_agent'] ) );
		$this->assertSame( $uid, (int) $row['user_id'] );
		$this->assertSame( array( 'necessary' => true, 'functional' => true, 'analytics' => false, 'advertising' => false ), json_decode( $row['categories'], true ) );

		wp_set_current_user( 0 );
		$r = $this->post( $this->payload( array( 'action' => 'nope' ) ) );
		$this->assertSame( 400, $r->get_status() );
		$this->assertStringNotContainsString( 'nope', wp_json_encode( $r->get_data() ), 'erro não ecoa o conteúdo' );
		$this->assertSame( 1, ( new CookieConsentRepository() )->query( array() )[1], 'inválido não grava' );
		$this->assertStringContainsString( 'cookie-consent', \EBCR\Frontend\ClientArea::js_data()['consentEndpoint'] );
	}

	public function test_rate_limit_20_per_hour_per_ip(): void {
		for ( $i = 0; $i < 20; $i++ ) {
			$this->assertSame( 200, $this->post( $this->payload( array( 'version' => $i ) ) )->get_status(), "requisição {$i}" );
		}
		$r = $this->post( $this->payload() );
		$this->assertSame( 429, $r->get_status() );
		$this->assertSame( 20, ( new CookieConsentRepository() )->query( array() )[1] );
		RateLimiter::clear( 'cookie_consent', Ip::get() );
	}

	public function test_retention_privacy_and_csv(): void {
		$uid  = $this->make_user();
		$repo = new CookieConsentRepository();
		wp_set_current_user( $uid );
		CookieConsent::record( CookieConsent::validate( $this->payload() ) );
		wp_set_current_user( 0 );
		CookieConsent::record( CookieConsent::validate( $this->payload( array( 'consent_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'action' => 'accept_all' ) ) ) );
		// Registro antigo, além do prazo.
		$repo->insert(
			array(
				'consent_id' => 'bbbbbbbb-bbbb-4bbb-9bbb-bbbbbbbbbbbb',
				'categories' => '{"necessary":true}',
				'action'     => 'reject_all',
				'version'    => 1,
				'path'       => '/',
				'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-800 days' ) ),
			)
		);
		$this->assertSame( 3, $repo->query( array() )[1] );

		// Exportador de dados pessoais: só os do usuário.
		$data = PrivacyIntegration::collect( $uid );
		$this->assertCount( 1, $data['cookies'] );
		$this->assertSame( self::ID, $data['cookies'][0]['id_consentimento'] );

		// CSV (neutraliza fórmulas).
		$this->assertContains( 'ip_hash', CookieConsentsView::csv_header() );
		list( $items ) = $repo->query( array( 'action' => 'accept_all' ) );
		$row           = CookieConsentsView::csv_row( $items[0] );
		$this->assertSame( array( '1', '1', '1', '1' ), array_slice( $row, 3, 4 ) );
		$this->assertSame( "'=HYPERLINK()", CookieConsentsView::csv_row( array_merge( $items[0], array( 'path' => '=HYPERLINK()' ) ) )[8] );

		// Retenção: apaga os mais antigos que o prazo (padrão 730 dias).
		$this->assertSame( 730, Options::int( 'cookie_consent_retention_days' ) );
		Retention::run();
		$this->assertSame( 1, Retention::last_cookie_purge() );
		$this->assertSame( 2, $repo->query( array() )[1] );

		// Apagador: desvincula o usuário, mantém o registro anônimo.
		$user = get_userdata( $uid );
		$r    = ( new PrivacyIntegration() )->erase( $user->user_email );
		$this->assertTrue( $r['items_removed'] );
		$this->assertSame( array(), $repo->for_user( $uid ) );
		list( $items ) = $repo->query( array( 'consent_id' => self::ID ) );
		$this->assertNull( $items[0]['user_id'] );
		$this->assertSame( '', $items[0]['ip_hash'] );
	}
}
