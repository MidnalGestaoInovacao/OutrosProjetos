<?php
/**
 * 1.3.1 — Envio autenticado por SMTP: senha somente escrita e cifrada, configuração do PHPMailer (inclusive o
 * remetente do envelope), remetente das mensagens do WordPress, registro de falhas e run-tool test_smtp com
 * transcrição mascarada (servidor SMTP falso local em tests/fixtures/fake-smtp.php).
 *
 * @package EBCR
 */

use EBCR\Abilities\Abilities;
use EBCR\Admin\Settings\Settings;
use EBCR\Database\Db;
use EBCR\Database\MailQueueRepository;
use EBCR\Mail\Queue;
use EBCR\Mail\Smtp;
use EBCR\Support\Options;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP.
 */
final class SmtpTest extends EBCR_TestCase {

	const USER = 'contato@bsagro.agr.br';
	const PASS = 'Senha-Forte#123';

	/**
	 * Processo do servidor falso.
	 *
	 * @var resource|null
	 */
	private $proc = null;

	/**
	 * Arquivo de registro do servidor falso.
	 *
	 * @var string
	 */
	private $log = '';

	protected function setUp(): void {
		parent::setUp();
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		delete_option( Smtp::FAILURES_OPTION );
		require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
		require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
	}

	protected function tearDown(): void {
		if ( is_resource( $this->proc ) ) {
			proc_terminate( $this->proc );
			proc_close( $this->proc );
		}
		foreach ( array( $this->log, $this->log . '.ready' ) as $f ) {
			if ( $f && is_file( $f ) ) {
				unlink( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
		remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		global $phpmailer;
		if ( is_object( $phpmailer ) ) {
			$phpmailer->SMTPDebug = 0;
		}
		parent::tearDown();
	}

	/**
	 * Configuração SMTP de teste.
	 *
	 * @param array $over Sobrescritas.
	 * @return void
	 */
	private function configure( array $over = array() ) {
		list( $values ) = Settings::sanitize(
			array_merge(
				array(
					'smtp_enabled'    => true,
					'smtp_host'       => '127.0.0.1',
					'smtp_port'       => 465,
					'smtp_secure'     => 'none',
					'smtp_auth'       => true,
					'smtp_username'   => self::USER,
					'smtp_password'   => self::PASS,
					'smtp_from_email' => self::USER,
					'smtp_from_name'  => 'BS Agro Capital',
					'smtp_apply_all'  => false,
					'smtp_timeout'    => 5,
				),
				$over
			)
		);
		Options::update( $values );
	}

	/**
	 * Sobe o servidor SMTP falso e devolve a porta.
	 *
	 * @param int    $connections Conexões aceitas.
	 * @param string $expected    Senha esperada.
	 * @return int
	 */
	private function start_server( $connections = 1, $expected = self::PASS ) {
		$this->log = trailingslashit( sys_get_temp_dir() ) . 'ebcr-smtp-' . wp_generate_password( 8, false, false ) . '.json';
		for ( $try = 0; $try < 5; $try++ ) {
			$port       = wp_rand( 20000, 45000 );
			$cmd        = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/fake-smtp.php' ) . ' ' . (int) $port . ' ' . escapeshellarg( $this->log ) . ' ' . (int) $connections . ' ' . escapeshellarg( $expected );
			$this->proc = proc_open( $cmd, array( 2 => array( 'pipe', 'w' ) ), $pipes );
			for ( $i = 0; $i < 50 && ! is_file( $this->log . '.ready' ); $i++ ) {
				usleep( 100000 );
			}
			if ( is_file( $this->log . '.ready' ) ) {
				return $port;
			}
			proc_terminate( $this->proc );
		}
		$this->markTestSkipped( 'Não foi possível abrir o servidor SMTP falso.' );
		return 0;
	}

	/**
	 * Sessões registradas pelo servidor falso.
	 *
	 * @return array
	 */
	private function sessions() {
		for ( $i = 0; $i < 30 && ! is_file( $this->log ); $i++ ) {
			usleep( 100000 );
		}
		return is_file( $this->log ) ? (array) json_decode( (string) file_get_contents( $this->log ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	public function test_defaults_and_write_only_encrypted_password(): void {
		$d = Options::defaults();
		$this->assertFalse( $d['smtp_enabled'] );
		$this->assertSame( 465, $d['smtp_port'] );
		$this->assertSame( 'ssl', $d['smtp_secure'] );
		$this->assertTrue( $d['smtp_auth'] );
		$this->assertTrue( $d['smtp_apply_all'] );
		$this->assertTrue( $d['smtp_verify_peer'] );
		$this->assertSame( 15, $d['smtp_timeout'] );
		$this->assertSame( 'none', Smtp::password_source() );

		// Gravada cifrada (com EBCR_ENCRYPTION_KEY neste ambiente), nunca em claro.
		list( $v ) = Settings::sanitize( array( 'smtp_password' => self::PASS ) );
		$this->assertStringStartsWith( 'ebcr1:', $v['smtp_password'] );
		$this->assertStringNotContainsString( self::PASS, $v['smtp_password'] );
		$this->assertSame( self::PASS, Smtp::open( $v['smtp_password'] ) );
		Options::update( $v );
		$this->assertSame( 'option', Smtp::password_source() );
		$this->assertSame( 'key', Smtp::password_storage() );

		// Vazio mantém; "apagar" limpa.
		list( $v ) = Settings::sanitize( array( 'smtp_password' => '' ) );
		$this->assertArrayNotHasKey( 'smtp_password', $v );
		list( $v ) = Settings::sanitize( array( 'smtp_password_clear' => true ) );
		$this->assertSame( '', $v['smtp_password'] );

		// Sem EBCR_ENCRYPTION_KEY: chave derivada do sal (e aviso recomendando a constante).
		$salted = Smtp::seal( self::PASS, false );
		$this->assertStringStartsWith( Smtp::SALT_PREFIX, $salted );
		$this->assertSame( self::PASS, Smtp::open( $salted ) );
		$this->assertNull( Smtp::open( Smtp::SALT_PREFIX . 'lixo' ) );
		$w = Settings::smtp_warnings(
			array_merge(
				Options::all(),
				array(
					'smtp_enabled'     => true,
					'smtp_host'        => 'mail.bsagro.agr.br',
					'smtp_password'    => $salted,
					'smtp_verify_peer' => false,
					'smtp_secure'      => 'none',
					'smtp_username'    => self::USER,
					'smtp_from_email'  => 'outro@bsagro.agr.br',
				)
			)
		);
		$flat = implode( ' | ', $w );
		$this->assertStringContainsString( 'EBCR_SMTP_PASSWORD', $flat );
		$this->assertStringContainsString( 'certificado', $flat );
		$this->assertStringContainsString( 'sem proteção', $flat );
		$this->assertStringContainsString( 'diferente da caixa autenticada', $flat );

		// Abilities: get-settings nunca devolve a senha; update-settings aceita e mascara.
		wp_set_current_user( $this->make_user( 'administrator' ) );
		$r = wp_get_ability( 'ebcr/update-settings' )->execute(
			array(
				'settings' => array(
					'smtp_enabled'  => true,
					'smtp_host'     => 'mail.bsagro.agr.br',
					'smtp_username' => self::USER,
					'smtp_password' => self::PASS,
				),
			)
		);
		$this->assertSame( '(definida)', $r['values']['smtp_password'] );
		$this->assertStringNotContainsString( self::PASS, wp_json_encode( $r ) );
		$get  = wp_get_ability( 'ebcr/get-settings' )->execute( array( 'tab' => 'emails' ) );
		$json = wp_json_encode( $get );
		$this->assertNull( $get['settings']['smtp_password'] );
		$this->assertSame( 'option', $get['secrets']['smtp_password'] );
		$this->assertStringNotContainsString( self::PASS, $json );
		$this->assertStringNotContainsString( 'ebcr1:', $json );
		$this->assertSame( 'password', $get['fields']['smtp_password']['type'] );
		global $wpdb;
		$audit = (string) $wpdb->get_var( 'SELECT GROUP_CONCAT(meta) FROM ' . Db::table( 'audit_log' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$this->assertStringNotContainsString( self::PASS, $audit );
		$st = wp_get_ability( 'ebcr/get-status' )->execute( array() );
		$this->assertSame( 'option', $st['smtp']['password_source'] );
		$this->assertSame( 'mail.bsagro.agr.br', $st['smtp']['host'] );
		$this->assertStringNotContainsString( self::PASS, wp_json_encode( $st ) );
	}

	public function test_settings_screen_renders_smtp_section_without_secret(): void {
		$this->configure();
		wp_set_current_user( $this->make_user( 'administrator' ) );
		set_transient(
			'ebcr_smtp_test_' . get_current_user_id(),
			array(
				'sent'       => false,
				'error'      => 'SMTP Error: Could not authenticate.',
				'transport'  => 'smtp',
				'sender'     => self::USER,
				'transcript' => "CLIENT -> SERVER: AUTH LOGIN\nCLIENT -> SERVER: " . Smtp::MASK,
			),
			60
		);
		ob_start();
		( new Settings() )->render();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'Envio (SMTP)', $html );
		$this->assertStringContainsString( 'name="smtp_password" value=""', $html );
		$this->assertStringContainsString( 'type="password"', $html );
		$this->assertStringContainsString( 'Apagar a senha salva', $html );
		$this->assertStringContainsString( 'Testar SMTP (com diagnóstico)', $html );
		$this->assertStringContainsString( 'Could not authenticate', $html, 'resultado do último teste' );
		$this->assertStringNotContainsString( self::PASS, $html );
		$this->assertStringNotContainsString( (string) Options::get( 'smtp_password' ), $html, 'nem a cifra' );
		$this->assertFalse( get_transient( 'ebcr_smtp_test_' . get_current_user_id() ), 'exibido uma vez' );
	}

	public function test_phpmailer_configuration_and_envelope_sender(): void {
		$this->configure(
			array(
				'smtp_secure'      => 'ssl',
				'smtp_verify_peer' => false,
			)
		);
		// Mensagem do próprio plugin: SMTP completo + remetente e envelope.
		$m = new PHPMailer( true );
		Smtp::own(
			static function () use ( $m ) {
				Smtp::configure( $m );
			}
		);
		$this->assertSame( 'smtp', $m->Mailer );
		$this->assertSame( '127.0.0.1', $m->Host );
		$this->assertSame( 465, $m->Port );
		$this->assertSame( 'ssl', $m->SMTPSecure );
		$this->assertTrue( $m->SMTPAuth );
		$this->assertSame( self::USER, $m->Username );
		$this->assertSame( self::PASS, $m->Password );
		$this->assertSame( 5, $m->Timeout );
		$this->assertSame( self::USER, $m->From );
		$this->assertSame( 'BS Agro Capital', $m->FromName );
		$this->assertSame( self::USER, $m->Sender, 'Return-Path = remetente' );
		$this->assertFalse( $m->SMTPOptions['ssl']['verify_peer'] );

		// Mensagem de terceiros com "só o plugin": intocada (e sem herdar o envelope).
		$other         = new PHPMailer( true );
		$other->Sender = self::USER;
		Smtp::configure( $other );
		$this->assertSame( 'mail', $other->Mailer );
		$this->assertSame( '', $other->Sender );
		$this->assertSame( 'wordpress@localhost', apply_filters( 'wp_mail_from', 'wordpress@localhost' ) );

		// Todo o site: WordPress também sai pelo SMTP, com o remetente da caixa.
		Options::update( array( 'smtp_apply_all' => true ) );
		$core = new PHPMailer( true );
		Smtp::configure( $core );
		$this->assertSame( 'smtp', $core->Mailer );
		$this->assertSame( self::USER, $core->Sender );
		$this->assertSame( self::USER, apply_filters( 'wp_mail_from', 'wordpress@localhost' ) );
		$this->assertSame( 'BS Agro Capital', apply_filters( 'wp_mail_from_name', 'WordPress' ) );

		// TLS (STARTTLS) e "nenhuma".
		Options::update( array( 'smtp_secure' => 'tls' ) );
		$t = new PHPMailer( true );
		Smtp::configure( $t );
		$this->assertSame( 'tls', $t->SMTPSecure );
		$this->assertTrue( $t->SMTPAutoTLS );
		Options::update( array( 'smtp_secure' => 'none' ) );
		$n = new PHPMailer( true );
		Smtp::configure( $n );
		$this->assertSame( '', $n->SMTPSecure );
		$this->assertFalse( $n->SMTPAutoTLS );

		// SMTP desligado: mensagens do plugin continuam por mail(), mas com o envelope = from_email.
		Options::update(
			array(
				'smtp_enabled' => false,
				'from_email'   => 'credito@bsagro.agr.br',
			)
		);
		$plain = new PHPMailer( true );
		Smtp::own(
			static function () use ( $plain ) {
				Smtp::configure( $plain );
			}
		);
		$this->assertSame( 'mail', $plain->Mailer );
		$this->assertSame( 'credito@bsagro.agr.br', $plain->Sender );
		$this->assertSame( 'wordpress@localhost', apply_filters( 'wp_mail_from', 'wordpress@localhost' ) );
	}

	public function test_failures_are_recorded_without_credentials_and_queue_marks_error(): void {
		$this->configure( array( 'smtp_port' => 1 ) );
		do_action(
			'wp_mail_failed',
			new WP_Error(
				'wp_mail_failed',
				'SMTP Error: Could not authenticate (' . self::PASS . ').',
				array(
					'to'      => array( 'maria.silva@exemplo.com.br', 'outra@gmail.com' ),
					'subject' => 'Assunto com dado pessoal',
				)
			)
		);
		$f    = Smtp::failures();
		$last = end( $f );
		$this->assertSame( array( 'exemplo.com.br', 'gmail.com' ), $last['domains'] );
		$this->assertStringNotContainsString( self::PASS, $last['message'] );
		$this->assertStringContainsString( Smtp::MASK, $last['message'] );
		$this->assertStringNotContainsString( 'maria.silva', wp_json_encode( $f ) );
		$this->assertStringNotContainsString( 'Assunto', wp_json_encode( $f ) );
		for ( $i = 0; $i < 25; $i++ ) {
			do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'x' . $i, array( 'to' => array( 'a@b.com' ) ) ) );
		}
		$this->assertCount( Smtp::MAX_FAILURES, Smtp::failures() );

		// Fila: servidor inacessível → item volta a pendente com o erro do PHPMailer, e a falha fica registrada.
		delete_option( Smtp::FAILURES_OPTION );
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . Db::table( 'mail_queue' ) . " WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$id = Queue::enqueue( 'teste_smtp', 'destino@example.com', 'Teste', '<p>x</p>' );
		Queue::process( 5 );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Db::table( 'mail_queue' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$this->assertSame( 'pending', $row['status'] );
		$this->assertSame( '1', (string) $row['attempts'] );
		$this->assertStringContainsString( 'SMTP', $row['last_error'] );
		$recent = Smtp::failures();
		$this->assertSame( 'smtp', end( $recent )['transport'] );
		$this->assertSame( array( 'example.com' ), end( $recent )['domains'] );
		wp_set_current_user( $this->make_user( 'administrator' ) );
		$st = Abilities::get_status();
		$this->assertNotEmpty( $st['mail_failures']['recent'] );
		$this->assertArrayHasKey( 'queue', $st['mail_failures'] );
		$wpdb->delete( Db::table( 'mail_queue' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function test_test_smtp_tool_authenticates_masks_credentials_and_reports_transport(): void {
		$port = $this->start_server( 1 );
		$this->configure( array( 'smtp_port' => $port ) );
		wp_set_current_user( $this->make_user( 'administrator' ) );
		$r = wp_get_ability( 'ebcr/run-tool' )->execute(
			array(
				'tool' => 'test_smtp',
				'to'   => 'destino@example.com',
			)
		);
		$this->assertIsArray( $r, is_wp_error( $r ) ? $r->get_error_message() : '' );
		$this->assertTrue( $r['sent'], ( $r['smtp_error'] ?? '' ) . "\n" . $r['transcript'] );
		// Sem chave "error" via MCP: o Easy MCP AI a trataria como falha da ferramenta.
		$this->assertArrayNotHasKey( 'error', $r );
		$this->assertArrayNotHasKey( 'smtp_error', $r );
		$this->assertSame( 'smtp', $r['transport'] );
		$this->assertSame( self::USER, $r['sender'] );
		$this->assertSame( '127.0.0.1', $r['host'] );
		$this->assertStringContainsString( 'AUTH LOGIN', $r['transcript'] );
		$this->assertStringContainsString( Smtp::MASK, $r['transcript'] );
		foreach ( array( self::PASS, base64_encode( self::PASS ), base64_encode( self::USER ) ) as $secret ) { // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			$this->assertStringNotContainsString( $secret, $r['transcript'] );
		}
		$this->assertLessThanOrEqual( Smtp::TRANSCRIPT_MAX + 10, strlen( $r['transcript'] ) );
		$this->assertStringContainsString( 'MAIL FROM:<' . self::USER . '>', $r['transcript'] );
		$this->assertStringContainsString( '[corpo da mensagem omitido]', $r['transcript'] );
		$this->assertStringNotContainsString( '<!DOCTYPE', $r['transcript'] );
		$this->assertStringContainsString( '250 2.0.0 OK queued', $r['transcript'] );
		$s = $this->sessions();
		$this->assertCount( 1, $s );
		$this->assertSame( array( self::USER, self::PASS ), $s[0]['auth'], 'credenciais corretas chegaram ao servidor' );
		$this->assertStringContainsString( '<' . self::USER . '>', $s[0]['mail_from'], 'envelope = remetente' );
		$this->assertStringContainsString( 'From: BS Agro Capital <' . self::USER . '>', $s[0]['data'] );
		$this->assertStringContainsString( '<destino@example.com>', $s[0]['rcpt'][0] );
	}

	public function test_test_smtp_reports_auth_failure_and_mail_transport(): void {
		$port = $this->start_server( 1, 'outra-senha' );
		$this->configure( array( 'smtp_port' => $port ) );
		wp_set_current_user( $this->make_user( 'administrator' ) );
		$m = wp_get_ability( 'ebcr/run-tool' )->execute(
			array(
				'tool' => 'test_smtp',
				'to'   => 'destino@example.com',
			)
		);
		$this->assertFalse( $m['sent'] );
		$this->assertArrayNotHasKey( 'error', $m );
		$this->assertStringContainsString( 'authenticate', strtolower( $m['smtp_error'] ) );
		$this->assertStringNotContainsString( self::PASS, $m['smtp_error'] );

		$port = $this->start_server( 1, 'outra-senha' );
		$this->configure( array( 'smtp_port' => $port ) );
		$r = Smtp::test( 'destino@example.com' );
		$this->assertFalse( $r['sent'] );
		$this->assertSame( 'smtp', $r['transport'] );
		$this->assertStringContainsString( 'authenticate', strtolower( $r['error'] ) );
		$this->assertStringContainsString( '535', $r['transcript'] );
		$this->assertStringNotContainsString( self::PASS, $r['transcript'] . $r['error'] );
		$this->assertStringNotContainsString( self::PASS, wp_json_encode( Smtp::failures() ) );

		// SMTP desligado: informa o transporte mail() (sem transcrição SMTP) e o envelope das mensagens do plugin.
		Options::update(
			array(
				'smtp_enabled' => false,
				'from_email'   => 'credito@bsagro.agr.br',
			)
		);
		// Neste ambiente não há sendmail: troca o binário por /bin/true depois da configuração do plugin.
		$fake_sendmail = static function ( $m ) {
			$m->isSendmail();
			$m->Sendmail = '/bin/true';
		};
		add_action( 'phpmailer_init', $fake_sendmail, 99 );
		$r = Smtp::test( 'destino@example.com' );
		remove_action( 'phpmailer_init', $fake_sendmail, 99 );
		$this->assertTrue( $r['sent'] );
		$this->assertSame( 'mail', $r['transport'] );
		$this->assertSame( 'credito@bsagro.agr.br', $r['sender'] );
		$this->assertStringContainsString( 'mail()', $r['transcript'] );
		$this->assertInstanceOf( WP_Error::class, Smtp::test( 'invalido' ) );
	}
}
