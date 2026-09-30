<?php
/**
 * Mensagens dos canais (POST ebcr/v1/channel-message): validação por canal, relato anônimo, protocolo,
 * contrato da resposta, limite por IP, e-mails (destinatário, Reply-To, recibo), CSV, retenção,
 * exportador/apagador de dados pessoais, capacidade, endpoint no front-end e remoção dos emojis do WordPress.
 *
 * @package EBCR
 */

use EBCR\Admin\Branding;
use EBCR\Admin\ChannelMessagesView;
use EBCR\Database\ChannelMessageRepository;
use EBCR\Database\Db;
use EBCR\Domain\ChannelMessage;
use EBCR\Domain\CookieConsent;
use EBCR\Frontend\ClientArea;
use EBCR\Frontend\Frontend;
use EBCR\Roles\Capabilities;
use EBCR\Security\PrivacyIntegration;
use EBCR\Security\RateLimiter;
use EBCR\Security\Retention;
use EBCR\Support\Ip;
use EBCR\Support\Options;

/**
 * 1.3.0 (banco 1.3.1).
 */
final class ChannelMessageTest extends EBCR_TestCase {

	/**
	 * User agent anterior (definido no bootstrap).
	 *
	 * @var string|null
	 */
	private $ua = null;

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( 'DELETE FROM ' . Db::table( 'channel_messages' ) );
		$wpdb->query( 'DELETE FROM ' . Db::table( 'mail_queue' ) . " WHERE event IN ('channel_message','channel_receipt')" );
		// phpcs:enable
		RateLimiter::clear( 'channel_message', Ip::get() );
		$this->ua                   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- teste.
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Teste)';
	}

	protected function tearDown(): void {
		RateLimiter::clear( 'channel_message', Ip::get() );
		if ( null === $this->ua ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->ua;
		}
		parent::tearDown();
	}

	/**
	 * Payload válido por canal.
	 *
	 * @param string $channel Canal.
	 * @param array  $fields  Campos a sobrescrever.
	 * @param array  $over    Chaves de topo a sobrescrever.
	 * @return array
	 */
	private function payload( $channel = 'contato', array $fields = array(), array $over = array() ) {
		$base = array(
			'contato'   => array(
				'nome'          => 'Maria Souza',
				'email'         => 'Maria@Example.com',
				'telefone'      => '(62) 99999-0000',
				'empresa'       => 'Fazenda Boa Vista',
				'assunto'       => 'Custeio de soja',
				'mensagem'      => "Gostaria de informações.\nObrigada.",
				'consentimento' => true,
			),
			'dpo'       => array(
				'nome'               => 'Maria Souza',
				'email'              => 'maria@example.com',
				'relacao'            => 'cliente',
				'direito'            => 'acesso',
				'descricao'          => 'Quero uma cópia dos meus dados.',
				'declaracao_titular' => 'on',
				'consentimento'      => '1',
			),
			'ouvidoria' => array(
				'tipo'   => 'fraude',
				'relato' => 'Relato detalhado do ocorrido.',
				'quando' => '09/2026',
				'boa_fe' => 'sim',
			),
		);
		return array_merge(
			array(
				'channel' => $channel,
				'page'    => 'https://exemplo.com.br/contato/?utm_source=x#form',
				'fields'  => array_merge( $base[ $channel ], $fields ),
				'_honey'  => '',
			),
			$over
		);
	}

	/**
	 * POST pela API REST (JSON ou formulário).
	 *
	 * @param array $body Corpo.
	 * @param bool  $form Enviar como formulário (x-www-form-urlencoded).
	 * @return WP_REST_Response
	 */
	private function post( array $body, $form = false ) {
		$req = new WP_REST_Request( 'POST', '/ebcr/v1/channel-message' );
		if ( $form ) {
			$req->set_header( 'Content-Type', 'application/x-www-form-urlencoded' );
			$req->set_body_params( $body );
		} else {
			$req->set_header( 'Content-Type', 'application/json' );
			$req->set_body( wp_json_encode( $body ) );
		}
		return rest_get_server()->dispatch( $req );
	}

	/**
	 * E-mails enfileirados de um evento.
	 *
	 * @param string $event Evento.
	 * @return array
	 */
	private function queued( $event ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Db::table( 'mail_queue' ) . ' WHERE event = %s ORDER BY id ASC', $event ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Campos com erro de um WP_Error.
	 *
	 * @param mixed $r Resultado de validate().
	 * @return array
	 */
	private function error_fields( $r ) {
		$this->assertInstanceOf( WP_Error::class, $r );
		$d = (array) $r->get_error_data();
		$this->assertSame( 400, $d['status'] );
		return isset( $d['fields'] ) ? $d['fields'] : array();
	}

	public function test_valid_payloads_are_normalized_per_channel(): void {
		$c = ChannelMessage::validate( $this->payload( 'contato', array( 'cpf' => '123', 'mensagem' => '<b>Olá</b> mundo' ) ) );
		$this->assertIsArray( $c );
		$this->assertSame( 'contato', $c['channel'] );
		$this->assertSame( '/contato/', $c['page'], 'só o caminho, sem domínio/query/fragmento' );
		$this->assertSame( 'maria@example.com', $c['fields']['email'], 'e-mail validado e em minúsculas' );
		$this->assertSame( 'sim', $c['fields']['consentimento'] );
		$this->assertSame( 'Olá mundo', $c['fields']['mensagem'], 'tags removidas' );
		$this->assertArrayNotHasKey( 'cpf', $c['fields'], 'chave fora da lista é ignorada' );
		$this->assertFalse( $c['anonymous'] );
		$this->assertSame( '', $c['protocol'], 'sem protocolo: será gerado no servidor' );

		$d = ChannelMessage::validate( $this->payload( 'dpo' ) );
		$this->assertIsArray( $d );
		$this->assertSame( 'sim', $d['fields']['declaracao_titular'] );

		$o = ChannelMessage::validate( $this->payload( 'ouvidoria' ) );
		$this->assertIsArray( $o );
		$this->assertSame( array( 'tipo', 'quando', 'relato', 'boa_fe' ), array_keys( $o['fields'] ) );

		// Caminho longo é cortado em 200.
		$long = ChannelMessage::validate( $this->payload( 'contato', array(), array( 'page' => '/' . str_repeat( 'a', 300 ) ) ) );
		$this->assertSame( 200, strlen( $long['page'] ) );
	}

	public function test_required_fields_per_channel(): void {
		$required = array(
			'contato'   => array( 'nome', 'email', 'assunto', 'mensagem', 'consentimento' ),
			'dpo'       => array( 'nome', 'email', 'relacao', 'direito', 'descricao', 'declaracao_titular', 'consentimento' ),
			'ouvidoria' => array( 'tipo', 'relato', 'boa_fe' ),
		);
		foreach ( $required as $channel => $keys ) {
			$this->assertSame( $keys, ChannelMessage::channels()[ $channel ]['required'] );
			foreach ( $keys as $k ) {
				$p = $this->payload( $channel );
				unset( $p['fields'][ $k ] );
				$this->assertContains( $k, $this->error_fields( ChannelMessage::validate( $p ) ), "{$channel}: {$k} obrigatório" );
			}
		}
		// Caixa desmarcada conta como ausente.
		$this->assertContains( 'consentimento', $this->error_fields( ChannelMessage::validate( $this->payload( 'contato', array( 'consentimento' => false ) ) ) ) );
		$this->assertContains( 'boa_fe', $this->error_fields( ChannelMessage::validate( $this->payload( 'ouvidoria', array( 'boa_fe' => '0' ) ) ) ) );
	}

	public function test_invalid_payloads_are_rejected_with_generic_message(): void {
		$generic = 'Não foi possível enviar: verifique os campos obrigatórios e tente novamente.';
		$r       = ChannelMessage::validate( $this->payload( 'contato', array( 'email' => 'nao-e-email' ) ) );
		$this->assertSame( array( 'email' ), $this->error_fields( $r ) );
		$this->assertSame( $generic, $r->get_error_message() );
		$this->assertContains( 'mensagem', $this->error_fields( ChannelMessage::validate( $this->payload( 'contato', array( 'mensagem' => str_repeat( 'x', 5001 ) ) ) ) ), 'máximo de 5000 caracteres' );
		$this->assertIsArray( ChannelMessage::validate( $this->payload( 'contato', array( 'mensagem' => str_repeat( 'x', 5000 ) ) ) ) );
		$this->assertContains( 'assunto', $this->error_fields( ChannelMessage::validate( $this->payload( 'contato', array( 'assunto' => array( 'a' ) ) ) ) ), 'valor não textual' );
		$this->assertSame( array( 'channel' ), $this->error_fields( ChannelMessage::validate( $this->payload( 'contato', array(), array( 'channel' => 'suporte' ) ) ) ) );

		// Mais de 20 chaves ou lista em vez de objeto.
		$many = array();
		for ( $i = 0; $i < 21; $i++ ) {
			$many[ 'k' . $i ] = 'v';
		}
		$this->assertSame( array( 'fields' ), $this->error_fields( ChannelMessage::validate( $this->payload( 'contato', array(), array( 'fields' => $many ) ) ) ) );
		$this->assertSame( array( 'fields' ), $this->error_fields( ChannelMessage::validate( $this->payload( 'contato', array(), array( 'fields' => array( 'a', 'b' ) ) ) ) ) );
		$this->assertSame( array( 'fields' ), $this->error_fields( ChannelMessage::validate( $this->payload( 'contato', array(), array( 'fields' => 'texto' ) ) ) ) );
		$this->assertInstanceOf( WP_Error::class, ChannelMessage::validate( 'x' ) );

		// Honeypot (no topo ou dentro de fields).
		$h = ChannelMessage::validate( $this->payload( 'contato', array(), array( '_honey' => 'http://spam' ) ) );
		$this->assertInstanceOf( WP_Error::class, $h );
		$this->assertSame( 'Não foi possível enviar. Tente novamente.', $h->get_error_message() );
		$this->assertInstanceOf( WP_Error::class, ChannelMessage::validate( $this->payload( 'contato', array( '_honey' => 'x' ) ) ) );
	}

	public function test_protocol_is_kept_only_when_valid_for_the_channel(): void {
		$ok = ChannelMessage::validate( $this->payload( 'contato', array(), array( 'protocol' => 'ct-20260929-ab12' ) ) );
		$this->assertSame( 'CT-20260929-AB12', $ok['protocol'] );
		$this->assertSame( 'LGPD-20260929-ZZ99', ChannelMessage::validate( $this->payload( 'dpo', array(), array( 'protocol' => 'LGPD-20260929-ZZ99' ) ) )['protocol'] );
		$this->assertSame( '', ChannelMessage::validate( $this->payload( 'contato', array(), array( 'protocol' => 'OUV-20260929-AB12' ) ) )['protocol'], 'prefixo de outro canal' );
		$this->assertSame( '', ChannelMessage::validate( $this->payload( 'contato', array(), array( 'protocol' => 'CT-2026-AB12' ) ) )['protocol'] );
		$this->assertSame( '', ChannelMessage::validate( $this->payload( 'contato', array(), array( 'protocol' => array( 'x' ) ) ) )['protocol'] );
		foreach ( array_keys( ChannelMessage::channels() ) as $ch ) {
			$p = ChannelMessage::generate_protocol( $ch );
			$this->assertMatchesRegularExpression( ChannelMessage::PROTOCOL_RE, $p );
			$this->assertStringStartsWith( ChannelMessage::channels()[ $ch ]['prefix'] . '-' . wp_date( 'Ymd' ) . '-', $p );
		}
	}

	public function test_anonymous_only_for_ouvidoria(): void {
		$identity = array(
			'nome'     => 'Fulano',
			'email'    => 'fulano@example.com',
			'telefone' => '62 3333-0000',
		);
		$a = ChannelMessage::validate( $this->payload( 'ouvidoria', $identity, array( 'anonymous' => true ) ) );
		$this->assertTrue( $a['anonymous'] );
		foreach ( array_keys( $identity ) as $k ) {
			$this->assertArrayNotHasKey( $k, $a['fields'], "anônimo descarta {$k}" );
		}
		// Identificado no Canal de Integridade: mantém os dados.
		$i = ChannelMessage::validate( $this->payload( 'ouvidoria', $identity, array( 'anonymous' => false ) ) );
		$this->assertFalse( $i['anonymous'] );
		$this->assertSame( 'fulano@example.com', $i['fields']['email'] );
		// Em outros canais, "anonymous" é ignorado.
		$c = ChannelMessage::validate( $this->payload( 'contato', array(), array( 'anonymous' => true ) ) );
		$this->assertFalse( $c['anonymous'] );
		$this->assertSame( 'Maria Souza', $c['fields']['nome'] );
		// E-mail inválido em relato anônimo não bloqueia (é descartado).
		$this->assertIsArray( ChannelMessage::validate( $this->payload( 'ouvidoria', array( 'email' => 'invalido' ), array( 'anonymous' => '1' ) ) ) );
	}

	public function test_rest_contract_and_storage(): void {
		$repo = new ChannelMessageRepository();

		// Protocolo do cliente válido e livre: mantido.
		$r = $this->post( $this->payload( 'contato', array(), array( 'protocol' => 'CT-20260929-AB12' ) ) );
		$this->assertSame( 200, $r->get_status() );
		$this->assertSame(
			array(
				'ok'       => true,
				'protocol' => 'CT-20260929-AB12',
			),
			$r->get_data()
		);
		$row = $repo->find_by_protocol( 'CT-20260929-AB12' );
		$this->assertSame( 'contato', $row['channel'] );
		$this->assertSame( 'novo', $row['status'] );
		$this->assertSame( '/contato/', $row['page'] );
		$this->assertSame( 'maria@example.com', $row['email'] );
		$this->assertSame( CookieConsent::ip_hash( Ip::get() ), $row['ip_hash'], 'somente o hash do IP' );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $row['ip_hash'] );
		$this->assertStringNotContainsString( Ip::get(), $row['fields'] );
		$this->assertSame( 'Mozilla/5.0 (Teste)', $row['user_agent'] );

		// Mesmo protocolo de novo: o servidor gera outro e devolve o gravado.
		$r2 = $this->post( $this->payload( 'contato', array(), array( 'protocol' => 'CT-20260929-AB12' ) ) );
		$this->assertTrue( $r2->get_data()['ok'] );
		$this->assertNotSame( 'CT-20260929-AB12', $r2->get_data()['protocol'] );
		$this->assertMatchesRegularExpression( '/^CT-' . wp_date( 'Ymd' ) . '-[A-Z0-9]{4}$/', $r2->get_data()['protocol'] );
		$this->assertNotNull( $repo->find_by_protocol( $r2->get_data()['protocol'] ) );

		// Sem protocolo (e como formulário): gerado no servidor.
		$r3 = $this->post( $this->payload( 'dpo' ), true );
		$this->assertSame( 200, $r3->get_status() );
		$this->assertMatchesRegularExpression( '/^LGPD-\d{8}-[A-Z0-9]{4}$/', $r3->get_data()['protocol'] );

		// Relato anônimo: sem nome/e-mail/telefone, sem hash de IP, user agent nem usuário.
		wp_set_current_user( $this->make_user() );
		$r4 = $this->post( $this->payload( 'ouvidoria', array( 'nome' => 'X', 'email' => 'x@example.com', 'telefone' => '1' ), array( 'anonymous' => true ) ) );
		$this->assertSame( 200, $r4->get_status() );
		$anon = $repo->find_by_protocol( $r4->get_data()['protocol'] );
		$this->assertStringStartsWith( 'OUV-', $anon['protocol'] );
		$this->assertSame( '1', (string) $anon['anonymous'] );
		$this->assertNull( $anon['ip_hash'] );
		$this->assertNull( $anon['user_agent'] );
		$this->assertNull( $anon['user_id'] );
		$this->assertSame( '', $anon['email'] );
		$this->assertSame( array( 'tipo', 'quando', 'relato', 'boa_fe' ), array_keys( ChannelMessage::fields_of( $anon ) ) );

		// Identificado com usuário conectado: vínculo gravado.
		$r5 = $this->post( $this->payload( 'contato' ) );
		$this->assertSame( get_current_user_id(), (int) $repo->find_by_protocol( $r5->get_data()['protocol'] )['user_id'] );
		wp_set_current_user( 0 );

		// Erro: 400 com mensagem genérica em pt-BR, nada gravado.
		$before = $repo->query( array() )[1];
		$bad    = $this->post( $this->payload( 'contato', array( 'email' => '' ) ) );
		$this->assertSame( 400, $bad->get_status() );
		$this->assertFalse( $bad->get_data()['ok'] );
		$this->assertSame( 'Não foi possível enviar: verifique os campos obrigatórios e tente novamente.', $bad->get_data()['message'] );
		$this->assertSame( array( 'email' ), $bad->get_data()['fields'] );
		$spam = $this->post( $this->payload( 'contato', array(), array( '_honey' => 'bot' ) ) );
		$this->assertSame( 400, $spam->get_status() );
		$this->assertSame(
			array(
				'ok'      => false,
				'message' => 'Não foi possível enviar. Tente novamente.',
			),
			$spam->get_data()
		);
		$this->assertSame( $before, $repo->query( array() )[1] );
	}

	public function test_rate_limit_10_per_hour_per_ip(): void {
		for ( $i = 0; $i < 10; $i++ ) {
			$this->assertSame( 400, $this->post( array( 'channel' => 'contato' ) )->get_status() );
		}
		$r = $this->post( $this->payload() );
		$this->assertSame( 429, $r->get_status() );
		$this->assertFalse( $r->get_data()['ok'] );
		$this->assertNotEmpty( $r->get_data()['message'] );
		$this->assertSame( 0, ( new ChannelMessageRepository() )->query( array() )[1] );
	}

	public function test_notification_emails(): void {
		$hash = CookieConsent::ip_hash( Ip::get() );
		$site = Branding::brand_name();

		// Contato sem e-mail configurado: vai para o administrador, com Reply-To do remetente e recibo.
		$p   = $this->post( $this->payload( 'contato', array( 'mensagem' => '<script>alert(1)</script>Olá & tchau' ) ) )->get_data()['protocol'];
		$msg = $this->queued( 'channel_message' );
		$this->assertCount( 1, $msg );
		$this->assertSame( get_option( 'admin_email' ), $msg[0]['recipient'] );
		$this->assertSame( '[Canal de contato] ' . $p . ' — ' . $site, $msg[0]['subject'] );
		$this->assertStringContainsString( $p, $msg[0]['body'] );
		$this->assertStringContainsString( 'Custeio de soja', $msg[0]['body'] );
		$this->assertStringNotContainsString( '<script', $msg[0]['body'] );
		$this->assertStringContainsString( 'Olá &amp; tchau', $msg[0]['body'], 'valores escapados' );
		$this->assertStringNotContainsString( $hash, $msg[0]['body'], 'nunca inclui o hash do IP' );
		$this->assertStringNotContainsString( $hash, (string) $msg[0]['headers'] );
		$this->assertContains( 'Reply-To: "Maria Souza" <maria@example.com>', json_decode( $msg[0]['headers'], true ) );
		$rec = $this->queued( 'channel_receipt' );
		$this->assertCount( 1, $rec );
		$this->assertSame( 'maria@example.com', $rec[0]['recipient'] );
		$this->assertStringContainsString( $p, $rec[0]['subject'] );
		$this->assertStringContainsString( $p, $rec[0]['body'] );
		$this->assertStringNotContainsString( 'Custeio de soja', $rec[0]['body'], 'o recibo não repete o conteúdo' );

		// DPO com e-mail próprio e recibo desligado.
		Options::update(
			array(
				'channel_email_dpo'    => 'dpo@example.com',
				'channel_send_receipt' => false,
			)
		);
		$p2  = $this->post( $this->payload( 'dpo' ) )->get_data()['protocol'];
		$msg = $this->queued( 'channel_message' );
		$this->assertSame( 'dpo@example.com', $msg[1]['recipient'] );
		$this->assertSame( '[Portal do Titular (LGPD)] ' . $p2 . ' — ' . $site, $msg[1]['subject'] );
		$this->assertCount( 1, $this->queued( 'channel_receipt' ), 'recibo desligado' );

		// Anônimo: sem Reply-To e sem recibo.
		Options::update( array( 'channel_send_receipt' => true ) );
		$this->post( $this->payload( 'ouvidoria', array( 'email' => 'x@example.com' ), array( 'anonymous' => true ) ) );
		$msg = $this->queued( 'channel_message' );
		$this->assertCount( 3, $msg );
		$this->assertSame( array(), preg_grep( '/^Reply-To:/i', json_decode( $msg[2]['headers'], true ) ) );
		$this->assertStringNotContainsString( 'x@example.com', $msg[2]['body'] );
		$this->assertStringContainsString( 'Relato anônimo', $msg[2]['body'] );
		$this->assertCount( 1, $this->queued( 'channel_receipt' ) );

		$this->assertSame( get_option( 'admin_email' ), ChannelMessage::recipient( 'ouvidoria' ) );
		Options::update( array( 'channel_email_ouvidoria' => 'invalido' ) );
		$this->assertSame( get_option( 'admin_email' ), ChannelMessage::recipient( 'ouvidoria' ), 'e-mail inválido = administrador' );
	}

	public function test_admin_list_status_csv_retention_and_privacy(): void {
		$repo = new ChannelMessageRepository();
		$c1   = ChannelMessage::record( ChannelMessage::validate( $this->payload( 'contato', array( 'assunto' => '=HYPERLINK("http://x")' ) ) ) );
		$c2   = ChannelMessage::record( ChannelMessage::validate( $this->payload( 'dpo' ) ) );
		$c3   = ChannelMessage::record( ChannelMessage::validate( $this->payload( 'ouvidoria', array( 'email' => 'maria@example.com' ), array( 'anonymous' => true ) ) ) );
		$repo->insert(
			array(
				'channel'    => 'contato',
				'protocol'   => 'CT-20200101-OLD1',
				'fields'     => '{"nome":"Antigo"}',
				'email'      => 'antigo@example.com',
				'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-1900 days' ) ),
			)
		);
		$this->assertSame( 4, $repo->query( array() )[1] );

		// Filtros e status.
		$this->assertSame( 2, $repo->query( array( 'channel' => 'contato' ) )[1] );
		$this->assertTrue( $repo->set_status( (int) $c2['id'], 'em_andamento' ) );
		$this->assertSame( 1, $repo->query( array( 'status' => 'em_andamento' ) )[1] );
		$this->assertSame( 3, $repo->query( array( 'date_from' => gmdate( 'Y-m-d', strtotime( '-1 day' ) ) ) )[1] );
		$this->assertSame( 1, $repo->query( array( 'date_to' => gmdate( 'Y-m-d', strtotime( '-1000 days' ) ) ) )[1] );
		$this->assertSame( array( 'novo', 'em_andamento', 'concluido' ), array_keys( ChannelMessage::status_labels() ) );

		// CSV: sem hash de IP/user agent; células neutralizadas contra fórmulas.
		$header = ChannelMessagesView::csv_header();
		$this->assertNotContains( 'ip_hash', $header );
		$this->assertNotContains( 'user_agent', $header );
		$this->assertContains( 'protocolo', $header );
		$row = array_combine( $header, ChannelMessagesView::csv_row( $repo->find( (int) $c1['id'] ) ) );
		$this->assertSame( '\'=HYPERLINK("http://x")', $row['assunto'] );
		$this->assertSame( $c1['protocol'], $row['protocolo'] );
		$this->assertSame( "'+55 62", ChannelMessagesView::csv_row( array_merge( $c1, array( 'page' => '+55 62' ) ) )[5] );
		$this->assertSame( "'@SUM(1)", ChannelMessagesView::csv_row( array_merge( $c1, array( 'page' => '@SUM(1)' ) ) )[5] );
		$anon_row = array_combine( $header, ChannelMessagesView::csv_row( $repo->find( (int) $c3['id'] ) ) );
		$this->assertSame( '1', $anon_row['anonimo'] );
		$this->assertSame( '', $anon_row['email'] );

		// Retenção (padrão 1825 dias).
		$this->assertSame( 1825, Options::int( 'channel_message_retention_days' ) );
		Retention::run();
		$this->assertSame( 1, Retention::last_channel_purge() );
		$this->assertNull( $repo->find_by_protocol( 'CT-20200101-OLD1' ) );

		// Exportador por e-mail (sem conta): só as identificadas; anônimas nunca entram.
		$exp = ( new PrivacyIntegration() )->export( 'MARIA@example.com' );
		$this->assertCount( 2, $exp['data'] );
		$this->assertSame( 'ebcr_canais', $exp['data'][0]['group_id'] );
		$flat = wp_json_encode( $exp['data'] );
		$this->assertStringContainsString( $c1['protocol'], $flat );
		$this->assertStringContainsString( $c2['protocol'], $flat );
		$this->assertStringNotContainsString( $c3['protocol'], $flat );
		$this->assertStringNotContainsString( 'ip_hash', $flat );
		$this->assertCount( 2, PrivacyIntegration::channel_items( 'maria@example.com' ) );

		// Apagador: mantém o registro do atendimento sem os dados de identificação.
		$er = ( new PrivacyIntegration() )->erase( 'maria@example.com' );
		$this->assertTrue( $er['items_removed'] );
		$this->assertCount( 2, $er['messages'] );
		$this->assertSame( array(), $repo->for_email( 'maria@example.com' ) );
		$left = $repo->find( (int) $c1['id'] );
		$this->assertSame( '', $left['email'] );
		$this->assertNull( $left['ip_hash'] );
		$this->assertNull( $left['user_agent'] );
		$f = ChannelMessage::fields_of( $left );
		foreach ( ChannelMessage::IDENTIFYING as $k ) {
			$this->assertArrayNotHasKey( $k, $f );
		}
		$this->assertSame( '=HYPERLINK("http://x")', $f['assunto'] );
		$this->assertSame( 'sim', $f['_anonimizado'] );
		$this->assertSame( $c3['fields'], $repo->find( (int) $c3['id'] )['fields'], 'relato anônimo intocado' );
	}

	public function test_admin_screens_render_escaped_and_audited(): void {
		$row = ChannelMessage::record( ChannelMessage::validate( $this->payload( 'contato', array( 'assunto' => 'Teste <img src=x onerror=alert(1)> "aspas"' ) ) ) );
		ChannelMessage::record( ChannelMessage::validate( $this->payload( 'ouvidoria', array(), array( 'anonymous' => true ) ) ) );
		wp_set_current_user( $this->make_user( Capabilities::ROLE_MANAGER ) );
		$view = new ChannelMessagesView();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$_GET = array(
			'page'    => ChannelMessagesView::PAGE,
			'channel' => 'contato',
		);
		ob_start();
		$view->render();
		$list = ob_get_clean();
		$this->assertStringContainsString( 'Mensagens dos canais', $list );
		$this->assertStringContainsString( esc_html( $row['protocol'] ), $list );
		$this->assertStringContainsString( 'view=' . (int) $row['id'], $list );
		$this->assertStringContainsString( 'ebcr_channel_messages_csv', $list, 'gestor tem ebcr_export' );
		$this->assertStringNotContainsString( 'OUV-', $list, 'filtro por canal' );
		$this->assertStringNotContainsString( '<img', $list );

		$_GET = array(
			'page' => ChannelMessagesView::PAGE,
			'view' => (string) $row['id'],
		);
		ob_start();
		$view->render();
		$detail = ob_get_clean();
		$_GET   = array();
		// phpcs:enable
		$this->assertStringContainsString( $row['protocol'], $detail );
		$this->assertStringContainsString( 'name="action" value="ebcr_channel_status"', $detail );
		$this->assertStringContainsString( 'name="_wpnonce"', $detail );
		$this->assertStringContainsString( 'mailto:maria@example.com', $detail );
		$this->assertStringNotContainsString( '<img', $detail );
		$this->assertStringNotContainsString( (string) $row['ip_hash'], $detail, 'hash do IP não aparece na tela' );
		global $wpdb;
		$logged = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Db::table( 'audit_log' ) . ' WHERE action = %s AND object_id = %s', 'channel_message_viewed', $row['protocol'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$this->assertSame( 1, $logged, 'visualização registrada no log de auditoria' );

		// Mudança de status (admin-post com nonce): grava e audita de/para.
		$redirect = static function ( $location ) {
			throw new RuntimeException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- teste.
		};
		add_filter( 'wp_redirect', $redirect );
		// phpcs:disable WordPress.Security.NonceVerification
		$_POST    = array(
			'id'     => (string) $row['id'],
			'status' => 'concluido',
		);
		$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'ebcr_channel_status_' . $row['id'] ) );
		try {
			$view->change_status();
			$this->fail( 'deveria redirecionar' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'view=' . (int) $row['id'], $e->getMessage() );
		}
		$_POST    = array();
		$_REQUEST = array();
		// phpcs:enable
		remove_filter( 'wp_redirect', $redirect );
		$this->assertSame( 'concluido', ( new ChannelMessageRepository() )->find( (int) $row['id'] )['status'] );
		$meta = $wpdb->get_var( $wpdb->prepare( 'SELECT meta FROM ' . Db::table( 'audit_log' ) . ' WHERE action = %s AND object_id = %s', 'channel_status_changed', $row['protocol'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$this->assertSame(
			array(
				'from' => 'novo',
				'to'   => 'concluido',
			),
			json_decode( (string) $meta, true )
		);
	}

	public function test_capability_endpoint_exposure_and_emoji(): void {
		// Capacidade: gestores e administradores; analistas não.
		$manager = get_role( Capabilities::ROLE_MANAGER );
		$manager->remove_cap( Capabilities::CAP_CHANNELS );
		Capabilities::add_missing_caps();
		$this->assertTrue( get_role( Capabilities::ROLE_MANAGER )->has_cap( Capabilities::CAP_CHANNELS ) );
		$this->assertTrue( get_role( 'administrator' )->has_cap( Capabilities::CAP_CHANNELS ) );
		$this->assertFalse( get_role( Capabilities::ROLE_ANALYST )->has_cap( Capabilities::CAP_CHANNELS ) );
		$this->assertFalse( user_can( $this->make_user( Capabilities::ROLE_ANALYST ), Capabilities::CAP_CHANNELS ) );

		// Endpoint no front-end.
		$data = ClientArea::js_data();
		$this->assertSame( esc_url_raw( rest_url( 'ebcr/v1/channel-message' ) ), $data['channelEndpoint'] );
		$this->assertArrayHasKey( 'consentEndpoint', $data );

		// Emojis do WordPress: removidos no front-end quando ligado (padrão). O "init" do carregamento já os removeu;
		// os ganchos do núcleo são recolocados para testar os dois estados.
		$this->assertTrue( Options::bool( 'disable_wp_emoji' ) );
		$this->restore_core_emoji_hooks();
		Options::update( array( 'disable_wp_emoji' => false ) );
		$this->assertFalse( Frontend::disable_emoji() );
		$this->assertSame( 7, has_action( 'wp_head', 'print_emoji_detection_script' ) );
		Options::update( array( 'disable_wp_emoji' => true ) );
		$this->assertTrue( Frontend::disable_emoji() );
		$this->assertFalse( has_action( 'wp_head', 'print_emoji_detection_script' ) );
		$this->assertFalse( has_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' ) );
		$this->assertFalse( has_action( 'wp_print_styles', 'print_emoji_styles' ) );
		$this->assertFalse( has_filter( 'the_content_feed', 'wp_staticize_emoji' ) );
		$this->assertFalse( apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/' ) );
	}

	/**
	 * Recoloca os ganchos de emoji do núcleo (como em wp-includes/default-filters.php).
	 *
	 * @return void
	 */
	private function restore_core_emoji_hooks() {
		add_action( 'wp_head', 'print_emoji_detection_script', 7 );
		add_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		add_action( 'wp_print_styles', 'print_emoji_styles' );
		add_action( 'embed_head', 'print_emoji_detection_script' );
		add_action( 'enqueue_embed_scripts', 'wp_enqueue_emoji_styles' );
		add_filter( 'the_content_feed', 'wp_staticize_emoji' );
		add_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'emoji_svg_url', '__return_false' );
	}
}
