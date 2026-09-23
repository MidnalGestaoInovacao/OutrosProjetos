<?php
/**
 * Integrações da Fase 3: consulta de CEP/CNPJ, simulador, Turnstile e WhatsApp. HTTP externo sempre simulado (pre_http_request).
 *
 * @package EBCR
 */

use EBCR\Frontend\Simulator;
use EBCR\Integrations\Lookup;
use EBCR\Integrations\WhatsApp;
use EBCR\Security\MathCaptcha;
use EBCR\Security\RateLimiter;
use EBCR\Security\Turnstile;
use EBCR\Support\Options;

/**
 * Lookup, Simulator, Turnstile e WhatsApp.
 */
final class IntegrationsTest extends EBCR_TestCase {

	/**
	 * Requisições HTTP capturadas pelo mock.
	 *
	 * @var array
	 */
	private $requests = array();

	/**
	 * Filtro ativo.
	 *
	 * @var callable|null
	 */
	private $mock = null;

	/**
	 * Limpa o mock.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->unmock();
		parent::tearDown();
	}

	/**
	 * Simula respostas HTTP: $responder( $url, $args ) devolve array( code, body ) ou WP_Error.
	 *
	 * @param callable $responder Função.
	 * @return void
	 */
	private function mock_http( callable $responder ) {
		$this->unmock();
		$this->requests = array();
		$self           = $this;
		$this->mock     = static function ( $pre, $args, $url ) use ( $responder, $self ) {
			$self->requests[] = array( 'url' => $url, 'args' => $args );
			$r                = $responder( $url, $args );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			return array(
				'response' => array( 'code' => $r[0], 'message' => '' ),
				'headers'  => array(),
				'body'     => is_string( $r[1] ) ? $r[1] : wp_json_encode( $r[1] ),
				'cookies'  => array(),
			);
		};
		add_filter( 'pre_http_request', $this->mock, 10, 3 );
	}

	/**
	 * Remove o mock.
	 *
	 * @return void
	 */
	private function unmock() {
		if ( $this->mock ) {
			remove_filter( 'pre_http_request', $this->mock, 10 );
			$this->mock = null;
		}
	}

	/**
	 * Request REST autenticado.
	 *
	 * @param string $path Caminho.
	 * @return WP_REST_Response
	 */
	private function rest_get( $path ) {
		rest_get_server();
		return rest_do_request( new WP_REST_Request( 'GET', $path ) );
	}

	// ----- CEP / CNPJ -----

	public function test_cep_lookup_via_viacep_and_cache(): void {
		Options::update( array( 'cep_lookup' => true ) );
		$uid = $this->make_user();
		wp_set_current_user( $uid );
		RateLimiter::clear( 'lookup', $uid );
		delete_transient( Lookup::cache_key( 'cep', '74000001' ) );
		$this->mock_http(
			static function ( $url ) {
				if ( false !== strpos( $url, 'viacep.com.br/ws/74000001/json' ) ) {
					return array( 200, array( 'cep' => '74000-001', 'logradouro' => 'Rua das Flores', 'bairro' => 'Centro', 'localidade' => 'Goiânia', 'uf' => 'GO' ) );
				}
				return array( 500, 'unexpected' );
			}
		);
		$res = $this->rest_get( '/ebcr/v1/lookup/cep/74000001' );
		$this->assertSame( 200, $res->get_status() );
		$data = $res->get_data();
		$this->assertSame( 'Rua das Flores', $data['logradouro'] );
		$this->assertSame( 'Centro', $data['bairro'] );
		$this->assertSame( 'Goiânia', $data['cidade'] );
		$this->assertSame( 'GO', $data['uf'] );
		$this->assertSame( 'viacep', $data['fonte'] );
		$this->assertCount( 1, $this->requests );
		// Segunda consulta vem do cache (sem HTTP).
		$res = $this->rest_get( '/ebcr/v1/lookup/cep/74000001' );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 'Goiânia', $res->get_data()['cidade'] );
		$this->assertCount( 1, $this->requests, 'a segunda consulta não deve chamar a API' );
		delete_transient( Lookup::cache_key( 'cep', '74000001' ) );
	}

	public function test_cep_lookup_falls_back_to_brasilapi(): void {
		Options::update( array( 'cep_lookup' => true ) );
		delete_transient( Lookup::cache_key( 'cep', '01001000' ) );
		$this->mock_http(
			static function ( $url ) {
				if ( false !== strpos( $url, 'viacep.com.br' ) ) {
					return new WP_Error( 'http_request_failed', 'timeout' );
				}
				if ( false !== strpos( $url, 'brasilapi.com.br/api/cep/v2/01001000' ) ) {
					return array( 200, array( 'cep' => '01001000', 'state' => 'SP', 'city' => 'São Paulo', 'neighborhood' => 'Sé', 'street' => 'Praça da Sé', 'service' => 'open-cep' ) );
				}
				return array( 500, 'unexpected' );
			}
		);
		$r = Lookup::cep( '01001-000' );
		$this->assertIsArray( $r );
		$this->assertSame( 'brasilapi', $r['fonte'] );
		$this->assertSame( 'Praça da Sé', $r['logradouro'] );
		$this->assertSame( 'SP', $r['uf'] );
		$this->assertCount( 2, $this->requests );
		delete_transient( Lookup::cache_key( 'cep', '01001000' ) );
		// ViaCEP "erro" + BrasilAPI 404 → não encontrado.
		delete_transient( Lookup::cache_key( 'cep', '99999999' ) );
		$this->mock_http(
			static function ( $url ) {
				if ( false !== strpos( $url, 'viacep.com.br' ) ) {
					return array( 200, array( 'erro' => true ) );
				}
				return array( 404, array( 'name' => 'CepPromiseError', 'message' => 'Todos os serviços de CEP retornaram erro.' ) );
			}
		);
		$r = Lookup::cep( '99999999' );
		$this->assertInstanceOf( WP_Error::class, $r );
		$this->assertSame( 'lookup_not_found', $r->get_error_code() );
		$this->assertFalse( get_transient( Lookup::cache_key( 'cep', '99999999' ) ), 'não encontrado não entra no cache' );
	}

	public function test_cep_lookup_disabled_and_requires_login(): void {
		Options::update( array( 'cep_lookup' => false ) );
		$uid = $this->make_user();
		wp_set_current_user( $uid );
		RateLimiter::clear( 'lookup', $uid );
		$this->mock_http(
			static function () {
				return array( 200, array( 'localidade' => 'X', 'uf' => 'GO' ) );
			}
		);
		$res = $this->rest_get( '/ebcr/v1/lookup/cep/74000000' );
		$this->assertSame( 404, $res->get_status() );
		$this->assertSame( 'lookup_disabled', $res->get_data()['code'] );
		$this->assertCount( 0, $this->requests, 'desligado não chama a API' );
		// Visitante.
		Options::update( array( 'cep_lookup' => true ) );
		wp_set_current_user( 0 );
		$res = $this->rest_get( '/ebcr/v1/lookup/cep/74000000' );
		$this->assertSame( 401, $res->get_status() );
		$this->assertCount( 0, $this->requests );
	}

	public function test_cnpj_lookup_and_invalid_digits(): void {
		Options::update( array( 'cnpj_lookup' => true ) );
		$uid = $this->make_user();
		wp_set_current_user( $uid );
		RateLimiter::clear( 'lookup', $uid );
		delete_transient( Lookup::cache_key( 'cnpj', '19131243000197' ) );
		$this->mock_http(
			static function ( $url ) {
				if ( false !== strpos( $url, 'brasilapi.com.br/api/cnpj/v1/19131243000197' ) ) {
					return array(
						200,
						array(
							'cnpj'                         => '19131243000197',
							'razao_social'                 => 'OPEN KNOWLEDGE BRASIL',
							'nome_fantasia'                => 'REDE PELO CONHECIMENTO LIVRE',
							'descricao_tipo_de_logradouro' => 'ALAMEDA',
							'logradouro'                   => 'FRANCA',
							'numero'                       => '144',
							'complemento'                  => 'APT 22',
							'bairro'                       => 'JARDIM PAULISTA',
							'municipio'                    => 'SAO PAULO',
							'uf'                           => 'SP',
							'cep'                          => '01422000',
							'descricao_situacao_cadastral' => 'ATIVA',
						),
					);
				}
				return array( 500, 'unexpected' );
			}
		);
		$res = $this->rest_get( '/ebcr/v1/lookup/cnpj/19131243000197' );
		$this->assertSame( 200, $res->get_status() );
		$d = $res->get_data();
		$this->assertSame( 'OPEN KNOWLEDGE BRASIL', $d['razao_social'] );
		$this->assertSame( 'Alameda Franca', $d['logradouro'] );
		$this->assertSame( '144', $d['numero'] );
		$this->assertSame( 'Jardim Paulista', $d['bairro'] );
		$this->assertSame( 'Sao Paulo', $d['cidade'] );
		$this->assertSame( 'SP', $d['uf'] );
		$this->assertSame( '01422000', $d['cep'] );
		$this->assertSame( 'ATIVA', $d['situacao'] );
		$this->assertCount( 1, $this->requests );
		// Dígito verificador inválido → 400 sem chamar a API.
		$res = $this->rest_get( '/ebcr/v1/lookup/cnpj/19131243000198' );
		$this->assertSame( 400, $res->get_status() );
		$this->assertSame( 'lookup_invalid', $res->get_data()['code'] );
		$this->assertCount( 1, $this->requests );
		// Desligado.
		Options::update( array( 'cnpj_lookup' => false ) );
		$this->assertSame( 'lookup_disabled', Lookup::cnpj( '19131243000197' )->get_error_code() );
		delete_transient( Lookup::cache_key( 'cnpj', '19131243000197' ) );
	}

	// ----- Simulador -----

	public function test_simulator_schedule_price_and_sac(): void {
		$i = pow( 1.12, 1 / 12 ) - 1; // 0,9489% a.m.
		// Price: 100.000 em 12 meses a 12% a.a.
		$r = Simulator::schedule( 100000, 12, 12, 'price', 0 );
		$this->assertCount( 12, $r['rows'] );
		$pmt = 100000 * $i / ( 1 - pow( 1 + $i, -12 ) ); // ≈ 8.856,21
		$this->assertEqualsWithDelta( 8856.21, $pmt, 0.01 );
		$this->assertEqualsWithDelta( $pmt, $r['first'], 0.01 );
		$this->assertEqualsWithDelta( $pmt, $r['last'], 0.02 );
		$this->assertEqualsWithDelta( $pmt * 12, $r['total_paid'], 0.05 );
		$this->assertEqualsWithDelta( $pmt * 12 - 100000, $r['total_interest'], 0.05 );
		$this->assertEqualsWithDelta( 12.0, $r['cet'], 0.001 );
		$this->assertEqualsWithDelta( 0.0, $r['rows'][11]['saldo'], 0.001 );
		$this->assertEqualsWithDelta( 100000 * $i, $r['rows'][0]['juros'], 0.01 );
		// SAC: amortização constante de 8.333,33; primeira parcela 8.333,33 + juros do saldo; última menor.
		$r = Simulator::schedule( 100000, 12, 12, 'sac', 0 );
		$this->assertEqualsWithDelta( 100000 / 12, $r['rows'][0]['amortizacao'], 0.01 );
		$this->assertEqualsWithDelta( 100000 / 12 + 100000 * $i, $r['first'], 0.01 ); // ≈ 9.282,21
		$this->assertEqualsWithDelta( 100000 / 12 * ( 1 + $i ), $r['last'], 0.02 ); // ≈ 8.412,41
		$this->assertLessThan( $r['first'], $r['last'] );
		$this->assertEqualsWithDelta( 100000 * $i * 13 / 2, $r['total_interest'], 0.05 ); // soma de PA dos juros.
		$this->assertEqualsWithDelta( 0.0, $r['rows'][11]['saldo'], 0.001 );
		// Carência de 2 meses: juros capitalizados, sem parcela, amortização em 10 meses.
		$r = Simulator::schedule( 100000, 12, 12, 'price', 2 );
		$this->assertCount( 12, $r['rows'] );
		$this->assertTrue( $r['rows'][0]['carencia'] );
		$this->assertSame( 0.0, $r['rows'][1]['parcela'] );
		$this->assertEqualsWithDelta( 100000 * pow( 1 + $i, 2 ), $r['rows'][1]['saldo'], 0.01 );
		$this->assertFalse( $r['rows'][2]['carencia'] );
		$pv  = 100000 * pow( 1 + $i, 2 );
		$pmt = $pv * $i / ( 1 - pow( 1 + $i, -10 ) );
		$this->assertEqualsWithDelta( $pmt, $r['first'], 0.01 );
		$this->assertEqualsWithDelta( 0.0, $r['rows'][11]['saldo'], 0.001 );
		// Taxa zero: parcela = valor / n.
		$r = Simulator::schedule( 12000, 12, 0, 'price', 0 );
		$this->assertEqualsWithDelta( 1000.0, $r['first'], 0.001 );
		$this->assertEqualsWithDelta( 0.0, $r['total_interest'], 0.001 );
	}

	public function test_simulator_shortcode_respects_option_and_attributes(): void {
		Options::update( array( 'simulator_enabled' => true, 'simulator_min_amount' => 10000, 'simulator_max_amount' => 1000000, 'simulator_max_term' => 120, 'simulator_rate' => 10 ) );
		$html = do_shortcode( '[ebcr_simulador taxa="9,5" sistema="sac" valor="250000" prazo="24" carencia="3" titulo="Teste" botao="Quero" url="https://exemplo.com/x"]' );
		$this->assertStringContainsString( 'data-ebcr-simulator', $html );
		$this->assertStringContainsString( 'Teste', $html );
		$this->assertStringContainsString( 'href="https://exemplo.com/x"', $html );
		$this->assertStringContainsString( 'Quero', $html );
		$this->assertStringContainsString( '&quot;system&quot;:&quot;sac&quot;', $html );
		$this->assertStringContainsString( '&quot;rate&quot;:9.5', $html );
		$this->assertStringContainsString( '&quot;grace&quot;:3', $html );
		$this->assertStringContainsString( 'value="24"', $html );
		$this->assertTrue( wp_script_is( 'ebcr-simulator', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'ebcr-simulator', 'enqueued' ) );
		$cfg = Simulator::config( array( 'valor' => '5', 'prazo' => '999', 'carencia' => '999' ) );
		$this->assertSame( 10000.0, $cfg['amount'], 'valor abaixo do mínimo é limitado' );
		$this->assertSame( 120, $cfg['term'], 'prazo limitado ao máximo' );
		$this->assertSame( 60, $cfg['grace'], 'carência limitada' );
		Options::update( array( 'simulator_enabled' => false ) );
		$this->assertSame( '', do_shortcode( '[ebcr_simulador]' ) );
	}

	// ----- Turnstile -----

	public function test_turnstile_provider_only_with_keys(): void {
		delete_transient( 'ebcr_turnstile_warned' );
		Options::update( array( 'captcha_provider' => 'turnstile', 'turnstile_site_key' => '', 'turnstile_secret_key' => '' ) );
		$this->assertInstanceOf( MathCaptcha::class, MathCaptcha::provider(), 'sem chaves cai no matemático' );
		$this->assertNotFalse( get_transient( 'ebcr_turnstile_warned' ), 'aviso registrado' );
		Options::update( array( 'turnstile_site_key' => '1x00000000000000000000AA', 'turnstile_secret_key' => '1x0000000000000000000000000000000AA' ) );
		$p = MathCaptcha::provider();
		$this->assertInstanceOf( Turnstile::class, $p );
		$html = $p->field();
		$this->assertStringContainsString( 'class="cf-turnstile"', $html );
		$this->assertStringContainsString( 'data-sitekey="1x00000000000000000000AA"', $html );
		$this->assertStringNotContainsString( '1x0000000000000000000000000000000AA', $html, 'secret nunca vai ao HTML' );
		$this->assertTrue( wp_script_is( Turnstile::HANDLE, 'enqueued' ) );
		$tag = apply_filters( 'script_loader_tag', '<script src="' . Turnstile::SCRIPT_URL . '"></script>', Turnstile::HANDLE, Turnstile::SCRIPT_URL );
		$this->assertStringContainsString( '<script async defer src=', $tag );
		Options::update( array( 'captcha_provider' => 'math' ) );
		$this->assertInstanceOf( MathCaptcha::class, MathCaptcha::provider() );
		delete_transient( 'ebcr_turnstile_warned' );
	}

	public function test_turnstile_siteverify_success_and_failure(): void {
		Options::update( array( 'captcha_provider' => 'turnstile', 'turnstile_site_key' => 'site-key', 'turnstile_secret_key' => 'secret-key' ) );
		$this->mock_http(
			static function ( $url, $args ) {
				if ( false === strpos( $url, 'challenges.cloudflare.com/turnstile/v0/siteverify' ) ) {
					return array( 500, 'unexpected' );
				}
				$ok = isset( $args['body']['secret'], $args['body']['response'] ) && 'secret-key' === $args['body']['secret'] && 'token-bom' === $args['body']['response'];
				return array( 200, array( 'success' => $ok, 'error-codes' => $ok ? array() : array( 'invalid-input-response' ) ) );
			}
		);
		$this->assertTrue( MathCaptcha::verify_request( array( 'cf-turnstile-response' => 'token-bom' ) ) );
		$this->assertFalse( MathCaptcha::verify_request( array( 'cf-turnstile-response' => 'token-ruim' ) ) );
		$this->assertFalse( MathCaptcha::verify_request( array( 'ebcr_captcha_token' => 'x', 'ebcr_captcha_answer' => '1' ) ), 'sem o campo do widget falha sem chamar a API' );
		$this->assertCount( 2, $this->requests );
		$this->assertSame( 'POST', $this->requests[0]['args']['method'] );
		$this->assertArrayHasKey( 'remoteip', $this->requests[0]['args']['body'] );
		// Falha de rede → false, sem exceção.
		$this->mock_http(
			static function () {
				return new WP_Error( 'http_request_failed', 'timeout' );
			}
		);
		$this->assertFalse( MathCaptcha::verify_request( array( 'cf-turnstile-response' => 'token-bom' ) ) );
		Options::update( array( 'captcha_provider' => 'math', 'turnstile_site_key' => '', 'turnstile_secret_key' => '' ) );
		// Provedor matemático continua funcionando pelo mesmo caminho.
		$c = ( new MathCaptcha() )->issue();
		$this->assertTrue( MathCaptcha::verify_request( array( 'ebcr_captcha_token' => $c['token'], 'ebcr_captcha_answer' => get_transient( 'ebcr_captcha_' . $c['token'] ) ) ) );
	}

	// ----- WhatsApp -----

	public function test_whatsapp_normalize(): void {
		$this->assertSame( '5562999998888', WhatsApp::normalize( '(62) 99999-8888' ) );
		$this->assertSame( '5562999998888', WhatsApp::normalize( '+55 62 99999-8888' ) );
		$this->assertSame( '556233334444', WhatsApp::normalize( '062 3333-4444' ) );
		$this->assertSame( '5555999998888', WhatsApp::normalize( '55 99999-8888' ), 'DDD 55 sem DDI' );
		$this->assertSame( '', WhatsApp::normalize( '1234' ) );
		$this->assertSame( '', WhatsApp::normalize( '' ) );
	}

	public function test_whatsapp_send_text_uses_template_or_text(): void {
		Options::update( array( 'whatsapp_enabled' => true, 'whatsapp_token' => 'TOKEN-SECRETO', 'whatsapp_phone_id' => '123456789', 'whatsapp_template' => '' ) );
		$this->mock_http(
			static function ( $url ) {
				if ( false === strpos( $url, 'graph.facebook.com/v20.0/123456789/messages' ) ) {
					return array( 500, 'unexpected' );
				}
				return array( 200, array( 'messages' => array( array( 'id' => 'wamid.1' ) ) ) );
			}
		);
		$this->assertTrue( WhatsApp::send_text( '(62) 99999-8888', "Olá,\n\nteste   de   texto." ) );
		$this->assertCount( 1, $this->requests );
		$req  = $this->requests[0];
		$body = json_decode( $req['args']['body'], true );
		$this->assertSame( 'Bearer TOKEN-SECRETO', $req['args']['headers']['Authorization'] );
		$this->assertSame( 'whatsapp', $body['messaging_product'] );
		$this->assertSame( '5562999998888', $body['to'] );
		$this->assertSame( 'text', $body['type'] );
		$this->assertSame( 'Olá, teste de texto.', $body['text']['body'] );
		// Com template aprovado.
		Options::update( array( 'whatsapp_template' => 'ebcr_aviso' ) );
		$this->assertTrue( WhatsApp::send_text( '5562999998888', 'Crédito Rural: sua solicitação EB-2026-000001 mudou.' ) );
		$body = json_decode( $this->requests[1]['args']['body'], true );
		$this->assertSame( 'template', $body['type'] );
		$this->assertSame( 'ebcr_aviso', $body['template']['name'] );
		$this->assertSame( 'pt_BR', $body['template']['language']['code'] );
		$this->assertSame( 'Crédito Rural: sua solicitação EB-2026-000001 mudou.', $body['template']['components'][0]['parameters'][0]['text'] );
		// Erro da API → WP_Error com código, sem exceção e sem token na auditoria.
		$this->mock_http(
			static function () {
				return array( 400, array( 'error' => array( 'message' => 'Invalid parameter', 'code' => 100 ) ) );
			}
		);
		$r = WhatsApp::send_text( '5562999998888', 'x' );
		$this->assertInstanceOf( WP_Error::class, $r );
		$this->assertSame( 'whatsapp_http', $r->get_error_code() );
		$this->assertSame( 400, $r->get_error_data()['status'] );
		global $wpdb;
		$row = $wpdb->get_row( "SELECT object_id, meta FROM {$wpdb->prefix}ebcr_audit_log WHERE action = 'whatsapp_failed' ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertNotEmpty( $row );
		$this->assertStringNotContainsString( 'TOKEN-SECRETO', $row['meta'] );
		$this->assertStringContainsString( 'Invalid parameter', $row['meta'] );
		$this->assertStringContainsString( '"code":400', $row['meta'] );
		$this->assertStringNotContainsString( '5562999998888', $row['meta'] . $row['object_id'], 'número mascarado' );
		$this->assertStringContainsString( '*********8888', $row['meta'] );
		// Número inválido não chama a API.
		$n = count( $this->requests );
		$this->assertInstanceOf( WP_Error::class, WhatsApp::send_text( '123', 'x' ) );
		$this->assertCount( $n, $this->requests );
	}

	public function test_whatsapp_notification_hook_client_team_and_disabled(): void {
		Options::update( array( 'whatsapp_enabled' => true, 'whatsapp_token' => 'TOKEN', 'whatsapp_phone_id' => '999', 'whatsapp_template' => '', 'whatsapp_notify_client' => true, 'whatsapp_notify_team' => true, 'whatsapp_team_number' => '5562988887777' ) );
		$uid = $this->make_user();
		update_user_meta( $uid, 'ebcr_whatsapp', '62977776666' );
		$u = get_userdata( $uid );
		$this->mock_http(
			static function () {
				return array( 200, array( 'messages' => array( array( 'id' => 'wamid.2' ) ) ) );
			}
		);
		$vars = array( 'nome' => 'Maria', 'protocolo' => 'EB-2026-000042', 'status' => 'Em análise', 'link_portal' => 'https://exemplo.com/area/?id=1', 'valor' => 'R$ 100.000,00' );
		do_action( 'ebcr_notification_sent', 'status_client', $u->user_email, $vars );
		$this->assertCount( 1, $this->requests );
		$body = json_decode( $this->requests[0]['args']['body'], true );
		$this->assertSame( '5562977776666', $body['to'] );
		$this->assertStringContainsString( 'EB-2026-000042', $body['text']['body'] );
		$this->assertStringContainsString( 'Em análise', $body['text']['body'] );
		$this->assertStringContainsString( 'https://exemplo.com/area/?id=1', $body['text']['body'] );
		// Equipe: nova solicitação enviada a dois e-mails administrativos → um único WhatsApp ao número da equipe.
		do_action( 'ebcr_notification_sent', 'submission_admin', 'a@example.com', $vars );
		do_action( 'ebcr_notification_sent', 'submission_admin', 'b@example.com', $vars );
		$this->assertCount( 2, $this->requests );
		$body = json_decode( $this->requests[1]['args']['body'], true );
		$this->assertSame( '5562988887777', $body['to'] );
		$this->assertStringContainsString( 'Maria', $body['text']['body'] );
		$this->assertStringContainsString( 'R$ 100.000,00', $body['text']['body'] );
		// Evento sensível (confirmação de e-mail) não é espelhado; evento admin fora da lista da equipe também não.
		do_action( 'ebcr_notification_sent', 'register_confirm', $u->user_email, array( 'nome' => 'Maria', 'link_confirmacao' => 'https://x/y' ) );
		do_action( 'ebcr_notification_sent', 'status_admin', 'a@example.com', $vars );
		$this->assertCount( 2, $this->requests );
		// Número da etapa 1 quando o perfil não tem telefone.
		delete_user_meta( $uid, 'ebcr_whatsapp' );
		$s = $this->make_submission( $uid, 'enviada' );
		$this->assertNotEmpty( $s['protocol'] );
		( new \EBCR\Database\SubmissionDataRepository() )->save( (int) $s['id'], 'identificacao', array( 'telefone' => '62955554444' ) );
		do_action( 'ebcr_notification_sent', 'document_requested', $u->user_email, array( 'nome' => 'Maria', 'protocolo' => $s['protocol'], 'documento' => 'Matrícula', 'comentario' => '', 'link_portal' => 'https://x/y' ) );
		$this->assertCount( 3, $this->requests );
		$this->assertSame( '5562955554444', json_decode( $this->requests[2]['args']['body'], true )['to'] );
		// Desligado → não envia.
		Options::update( array( 'whatsapp_enabled' => false ) );
		do_action( 'ebcr_notification_sent', 'status_client', $u->user_email, $vars );
		$this->assertCount( 3, $this->requests );
		Options::update( array( 'whatsapp_enabled' => true, 'whatsapp_notify_client' => false ) );
		do_action( 'ebcr_notification_sent', 'status_client', $u->user_email, $vars );
		$this->assertCount( 3, $this->requests, 'cliente desligado não envia' );
	}
}
