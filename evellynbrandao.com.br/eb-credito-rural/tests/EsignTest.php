<?php
/**
 * Assinatura eletrônica: elegibilidade, código por e-mail, PDF assinado, matriz, substituição e helpers do admin.
 *
 * @package EBCR
 */

use EBCR\Database\DocumentRepository;
use EBCR\Database\SignatureRepository;
use EBCR\Database\SubmissionDataRepository;
use EBCR\Esign\Esign;
use EBCR\Esign\Service;
use EBCR\Files\Storage;
use EBCR\Forms\Wizard;
use EBCR\Security\RateLimiter;
use EBCR\Support\Options;

/**
 * Fluxo completo da assinatura eletrônica simples.
 */
final class EsignTest extends EBCR_TestCase {

	/**
	 * Módulo ligado com o tipo padrão; e-mails não saem de verdade.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		add_filter( 'pre_wp_mail', '__return_true' );
		Options::update(
			array(
				'esign_enabled'   => true,
				'esign_doc_types' => 'autorizacao_scr',
			)
		);
	}

	/**
	 * Limpeza.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		remove_filter( 'pre_wp_mail', '__return_true' );
		parent::tearDown();
	}

	/**
	 * Grava a etapa 1 (PF).
	 *
	 * @param array  $s    Solicitação.
	 * @param string $name Nome.
	 * @param string $cpf  CPF.
	 * @return void
	 */
	private function seed_step1( array $s, $name = 'Maria da Silva', $cpf = '52998224725' ) {
		( new SubmissionDataRepository() )->save(
			(int) $s['id'],
			'identificacao',
			array(
				'person_type' => 'PF',
				'nome'        => $name,
				'cpf'         => $cpf,
				'married'     => false,
			)
		);
	}

	/**
	 * Emite código e assina com nome confirmado.
	 *
	 * @param int    $uid  Usuário.
	 * @param array  $s    Solicitação.
	 * @param string $name Nome digitado.
	 * @return array|\WP_Error
	 */
	private function issue_and_sign( $uid, array $s, $name = 'maria da silva' ) {
		$issued = Service::issue_code( $uid, $s, 'autorizacao_scr' );
		$this->assertIsArray( $issued, is_wp_error( $issued ) ? $issued->get_error_message() : 'emissão falhou' );
		return Service::sign(
			$uid,
			$s,
			'autorizacao_scr',
			array(
				'codigo'           => $issued['code'],
				'nome_confirmacao' => $name,
				'aceite'           => '1',
			)
		);
	}

	public function test_eligibility_errors(): void {
		$uid = $this->make_user();
		$s   = $this->make_submission( $uid, 'rascunho' );
		$this->seed_step1( $s );

		Options::update( array( 'esign_enabled' => false ) );
		$r = Service::issue_code( $uid, $s, 'autorizacao_scr' );
		$this->assertInstanceOf( WP_Error::class, $r );
		$this->assertSame( 'disabled', $r->get_error_code() );
		$this->assertFalse( Esign::signable( 'autorizacao_scr', $s ) );
		$this->assertSame( '', Esign::sign_link( $s, 'autorizacao_scr' ) );
		Options::update( array( 'esign_enabled' => true ) );

		$r = Service::issue_code( $uid, $s, 'comprovante_residencia' );
		$this->assertSame( 'not_signable', $r->get_error_code(), 'tipo fora de esign_doc_types' );
		$r = Service::issue_code( $uid, $s, 'tipo_inexistente' );
		$this->assertSame( 'not_signable', $r->get_error_code() );

		$s2 = $this->make_submission( $uid, 'rascunho' );
		$r  = Service::issue_code( $uid, $s2, 'autorizacao_scr' );
		$this->assertSame( 'missing_identification', $r->get_error_code(), 'sem etapa 1' );
		$this->assertNull( Service::signer( $s2 ) );

		$other = $this->make_user();
		$r     = Service::issue_code( $other, $s, 'autorizacao_scr' );
		$this->assertSame( 'forbidden', $r->get_error_code(), 'outro usuário não assina' );
		$r = Service::sign( $other, $s, 'autorizacao_scr', array( 'codigo' => '123456' ) );
		$this->assertSame( 'forbidden', $r->get_error_code() );

		update_user_meta( $uid, 'ebcr_email_verified', 0 );
		$r = Service::issue_code( $uid, $s, 'autorizacao_scr' );
		$this->assertSame( 'email_unverified', $r->get_error_code() );
		update_user_meta( $uid, 'ebcr_email_verified', time() );

		$sent = $this->make_submission( $uid, 'enviada' );
		$this->seed_step1( $sent );
		$r = Service::issue_code( $uid, $sent, 'autorizacao_scr' );
		$this->assertSame( 'locked', $r->get_error_code(), 'enviada sem pedido aberto não aceita assinatura' );

		// Sem código emitido, assinar falha como expirado.
		$r = Service::sign( $uid, $s, 'autorizacao_scr', array( 'codigo' => '123456', 'nome_confirmacao' => 'Maria da Silva', 'aceite' => '1' ) );
		$this->assertSame( 'expired', $r->get_error_code() );
		$this->assertTrue( Esign::signable( 'autorizacao_scr', $s ) );
		$this->assertStringContainsString( 'ebcr_view=assinar', Esign::sign_link( $s, 'autorizacao_scr' ) );
	}

	public function test_wrong_code_counts_attempt_and_right_code_generates_signed_pdf(): void {
		global $wpdb;
		$uid = $this->make_user();
		$s   = $this->make_submission( $uid, 'rascunho' );
		$this->seed_step1( $s, 'José Antônio Souza', '52998224725' );

		$issued = Service::issue_code( $uid, $s, 'autorizacao_scr' );
		$this->assertIsArray( $issued, is_wp_error( $issued ) ? $issued->get_error_message() : '' );
		$this->assertMatchesRegularExpression( '/^\d{6}$/', $issued['code'] );
		$this->assertSame( 'pending', $issued['signature']['status'] );
		$this->assertSame( 'email_otp', $issued['signature']['method'] );
		$queue = \EBCR\Database\Db::table( 'mail_queue' );
		$user  = get_userdata( $uid );
		$this->assertGreaterThan( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$queue}` WHERE event = 'esign_code' AND recipient = %s", $user->user_email ) ), 'e-mail com o código enfileirado' ); // phpcs:ignore WordPress.DB
		$pending = Service::pending( $uid, $s, 'autorizacao_scr' );
		$this->assertIsArray( $pending );
		$this->assertSame( 0, $pending['attempts'] );

		// Código errado: falha e conta tentativa.
		$wrong = '000000' === $issued['code'] ? '111111' : '000000';
		$r     = Service::sign( $uid, $s, 'autorizacao_scr', array( 'codigo' => $wrong, 'nome_confirmacao' => 'Jose Antonio Souza', 'aceite' => '1' ) );
		$this->assertInstanceOf( WP_Error::class, $r );
		$this->assertSame( 'bad_code', $r->get_error_code() );
		$this->assertArrayHasKey( 'codigo', $r->get_error_data()['errors'] );
		$this->assertSame( 1, Service::pending( $uid, $s, 'autorizacao_scr' )['attempts'] );

		// Sem aceite / nome diferente: erros de campo, sem consumir tentativa.
		$r = Service::sign( $uid, $s, 'autorizacao_scr', array( 'codigo' => $issued['code'], 'nome_confirmacao' => 'Outro Nome', 'aceite' => '' ) );
		$this->assertSame( 'validation', $r->get_error_code() );
		$this->assertArrayHasKey( 'aceite', $r->get_error_data()['errors'] );
		$this->assertArrayHasKey( 'nome_confirmacao', $r->get_error_data()['errors'] );
		$this->assertSame( 1, Service::pending( $uid, $s, 'autorizacao_scr' )['attempts'] );

		// Código certo + nome sem acentos/caixa: gera o documento e conclui a assinatura.
		$r = Service::sign( $uid, $s, 'autorizacao_scr', array( 'codigo' => $issued['code'], 'nome_confirmacao' => '  jose antonio SOUZA ', 'aceite' => '1' ) );
		$this->assertIsArray( $r, is_wp_error( $r ) ? $r->get_error_message() : '' );
		$doc = $r['document'];
		$sig = $r['signature'];
		$this->assertSame( 'autorizacao_scr', $doc['doc_type'] );
		$this->assertSame( 'application/pdf', $doc['mime'] );
		$this->assertSame( 'pendente', $doc['review_status'] );
		$this->assertStringStartsWith( 'autorizacao-scr-assinada-', $doc['original_name'] );
		$this->assertStringEndsWith( '.pdf', $doc['original_name'] );
		$bytes = ( new Storage() )->read( $doc );
		$this->assertNotNull( $bytes );
		$this->assertStringStartsWith( '%PDF', $bytes );
		$this->assertSame( hash( 'sha256', $bytes ), $doc['sha256'] );
		$this->assertSame( strlen( $bytes ), (int) $doc['size'] );

		$this->assertSame( 'signed', $sig['status'] );
		$this->assertSame( (int) $doc['id'], (int) $sig['document_id'] );
		$this->assertSame( 'José Antônio Souza', $sig['signer_name'] );
		$this->assertSame( '52998224725', $sig['signer_document'] );
		$this->assertSame( $user->user_email, $sig['signer_email'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $sig['text_hash'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $sig['evidence_hash'] );
		$this->assertSame( Service::text_hash( Service::text( 'autorizacao_scr', $s ) ), $sig['text_hash'] );
		$this->assertSame( '127.0.0.1', $sig['ip'] );
		$this->assertSame( 'PHPUnit', $sig['user_agent'] );
		$this->assertNotEmpty( $sig['signed_at'] );
		$this->assertNull( Service::pending( $uid, $s, 'autorizacao_scr' ), 'código consumido' );
		$this->assertSame( array( $sig ), array_column( Service::signed_documents( $s, 'autorizacao_scr' ), 'signature' ) );

		// Slot da matriz satisfeito.
		$slots = ( new Wizard() )->document_slots( $s );
		$slot  = null;
		foreach ( $slots['slots'] as $sl ) {
			if ( 'autorizacao_scr' === $sl['type'] ) {
				$slot = $sl;
			}
		}
		$this->assertNotNull( $slot );
		$this->assertTrue( $slot['satisfied'] );
		$this->assertCount( 1, $slot['documents'] );

		// Reuso do mesmo código falha (uso único).
		$r = Service::sign( $uid, $s, 'autorizacao_scr', array( 'codigo' => $issued['code'], 'nome_confirmacao' => 'Jose Antonio Souza', 'aceite' => '1' ) );
		$this->assertSame( 'expired', $r->get_error_code() );

		// Helpers do admin.
		$badge = Esign::badge_for_document( $doc );
		$this->assertStringContainsString( 'Assinado eletronicamente', $badge );
		$this->assertStringContainsString( '#ebcr-esign-' . $sig['public_id'], $badge );
		$html = Esign::evidence_html( $sig );
		$this->assertStringContainsString( 'id="ebcr-esign-' . $sig['public_id'] . '"', $html );
		$this->assertStringContainsString( $sig['text_hash'], $html );
		$this->assertStringContainsString( $sig['evidence_hash'], $html );
		$this->assertStringContainsString( '127.0.0.1', $html );
		$this->assertStringContainsString( 'Lei 14.063/2020', $html );
		$this->assertSame( array( $sig['public_id'] ), array_column( Esign::signatures( $s ), 'public_id' ) );
		$manual = $this->upload_pdf( $uid, $s, 'comprovante_residencia' );
		$this->assertSame( '', Esign::badge_for_document( $manual ) );
	}

	public function test_too_many_wrong_codes_lock_and_invalidate(): void {
		$uid = $this->make_user();
		$s   = $this->make_submission( $uid, 'rascunho' );
		$this->seed_step1( $s );
		$issued = Service::issue_code( $uid, $s, 'autorizacao_scr' );
		$this->assertIsArray( $issued );
		$wrong = '000000' === $issued['code'] ? '111111' : '000000';
		$last  = null;
		for ( $i = 1; $i <= Service::MAX_ATTEMPTS; $i++ ) {
			$last = Service::sign( $uid, $s, 'autorizacao_scr', array( 'codigo' => $wrong, 'nome_confirmacao' => 'Maria da Silva', 'aceite' => '1' ) );
			$this->assertInstanceOf( WP_Error::class, $last );
		}
		$this->assertSame( 'too_many_attempts', $last->get_error_code() );
		$this->assertNull( Service::pending( $uid, $s, 'autorizacao_scr' ), 'código invalidado' );
		$this->assertSame( 'cancelled', ( new SignatureRepository() )->find( (int) $issued['signature']['id'] )['status'] );
		// Bloqueado até para pedir um novo código; o código antigo não serve mais.
		$r = Service::issue_code( $uid, $s, 'autorizacao_scr' );
		$this->assertSame( 'locked_attempts', $r->get_error_code() );
		$r = Service::sign( $uid, $s, 'autorizacao_scr', array( 'codigo' => $issued['code'], 'nome_confirmacao' => 'Maria da Silva', 'aceite' => '1' ) );
		$this->assertSame( 'locked_attempts', $r->get_error_code() );
		RateLimiter::clear( 'esign_verify', $uid . ':' . $s['id'] . ':autorizacao_scr' );
		$this->assertIsArray( Service::issue_code( $uid, $s, 'autorizacao_scr' ) );
	}

	public function test_resend_is_limited_and_cancel_clears_pending(): void {
		$uid = $this->make_user();
		$s   = $this->make_submission( $uid, 'rascunho' );
		$this->seed_step1( $s );
		$first = Service::issue_code( $uid, $s, 'autorizacao_scr' );
		$this->assertIsArray( $first );
		$second = Service::issue_code( $uid, $s, 'autorizacao_scr' );
		$this->assertIsArray( $second, 'reenvio permitido' );
		$this->assertSame( 'cancelled', ( new SignatureRepository() )->find( (int) $first['signature']['id'] )['status'], 'reenvio cancela a emissão anterior' );
		$this->assertIsArray( Service::issue_code( $uid, $s, 'autorizacao_scr' ) );
		$r = Service::issue_code( $uid, $s, 'autorizacao_scr' );
		$this->assertInstanceOf( WP_Error::class, $r );
		$this->assertSame( 'too_many_sends', $r->get_error_code() );
		$this->assertIsArray( Service::pending( $uid, $s, 'autorizacao_scr' ) );
		$this->assertTrue( Service::cancel( $uid, $s, 'autorizacao_scr' ) );
		$this->assertNull( Service::pending( $uid, $s, 'autorizacao_scr' ) );
		$this->assertFalse( Service::cancel( $this->make_user(), $s, 'autorizacao_scr' ), 'outro usuário não cancela' );
	}

	public function test_second_signature_replaces_generated_pdf_but_keeps_manual_uploads(): void {
		$uid = $this->make_user();
		$s   = $this->make_submission( $uid, 'rascunho' );
		$this->seed_step1( $s );
		$docs = new DocumentRepository();

		$first = $this->issue_and_sign( $uid, $s );
		$this->assertIsArray( $first, is_wp_error( $first ) ? $first->get_error_message() : '' );
		$manual = $this->upload_pdf( $uid, $s, 'autorizacao_scr' );

		$second = $this->issue_and_sign( $uid, $s );
		$this->assertIsArray( $second, is_wp_error( $second ) ? $second->get_error_message() : '' );
		$this->assertNotSame( (int) $first['document']['id'], (int) $second['document']['id'] );
		$old = $docs->find( (int) $first['document']['id'] );
		$this->assertNotEmpty( $old['deleted_at'], 'PDF anterior gerado pela assinatura é substituído (soft delete)' );
		$this->assertFalse( ( new Storage() )->exists( $old ), 'arquivo físico anterior removido' );
		$this->assertEmpty( $docs->find( (int) $manual['id'] )['deleted_at'], 'upload manual do mesmo tipo é mantido' );
		$this->assertSame( 'signed', ( new SignatureRepository() )->find( (int) $first['signature']['id'] )['status'], 'linha antiga fica como histórico' );
		$active = Service::signed_documents( $s, 'autorizacao_scr' );
		$this->assertCount( 1, $active );
		$this->assertSame( (int) $second['document']['id'], (int) $active[0]['id'] );
		$this->assertStringContainsString( 'substituído', Esign::evidence_html( $first['signature'] ) );

		// Aceito pela equipe: não permite assinar de novo.
		$docs->update( (int) $second['document']['id'], array( 'review_status' => 'aceito' ) );
		$r = $this->issue_and_sign( $uid, $s );
		$this->assertInstanceOf( WP_Error::class, $r );
		$this->assertSame( 'already_accepted', $r->get_error_code() );
		$this->assertEmpty( $docs->find( (int) $second['document']['id'] )['deleted_at'] );
	}

	public function test_pj_signer_placeholders_and_text_filter_for_other_types(): void {
		$uid = $this->make_user();
		$s   = $this->make_submission( $uid, 'rascunho' );
		( new SubmissionDataRepository() )->save(
			(int) $s['id'],
			'identificacao',
			array(
				'person_type'    => 'PJ',
				'razao_social'   => 'Agro Boa Vista LTDA',
				'cnpj'           => '11.222.333/0001-81',
				'representantes' => array(
					array(
						'nome' => 'João Pereira',
						'cpf'  => '111.444.777-35',
					),
				),
			)
		);
		Options::update( array( 'esign_scr_text' => 'Autorizo em nome de {nome} ({cpf}) a consulta — protocolo {protocolo}, em {data}.' ) );
		$signer = Service::signer( $s );
		$this->assertSame( 'PJ', $signer['person_type'] );
		$this->assertSame( 'João Pereira', $signer['signer_name'] );
		$this->assertSame( '11144477735', $signer['signer_document'] );
		$this->assertSame( '11222333000181', $signer['company_document'] );
		$text = Service::text( 'autorizacao_scr', $s );
		$this->assertStringContainsString( 'Agro Boa Vista LTDA (11.222.333/0001-81)', $text );
		$this->assertStringContainsString( $s['public_id'], $text, 'rascunho sem protocolo usa o identificador' );
		$this->assertStringContainsString( wp_date( 'd/m/Y', time(), new DateTimeZone( 'America/Sao_Paulo' ) ), $text );

		// O representante assina (nome confirmado = representante).
		$r = $this->issue_and_sign( $uid, $s, 'joao pereira' );
		$this->assertIsArray( $r, is_wp_error( $r ) ? $r->get_error_message() : '' );
		$this->assertSame( 'João Pereira', $r['signature']['signer_name'] );

		// Outro tipo passa a ser assinável via filtro de texto.
		Options::update( array( 'esign_doc_types' => 'autorizacao_scr, comprovante_residencia' ) );
		$this->assertFalse( Esign::signable( 'comprovante_residencia', $s ), 'sem texto não é assinável' );
		$filter = static function ( $text, $doc_type ) {
			return 'comprovante_residencia' === $doc_type ? 'Declaro residir no endereço informado.' : $text;
		};
		add_filter( 'ebcr_esign_text', $filter, 10, 2 );
		$this->assertTrue( Esign::signable( 'comprovante_residencia', $s ) );
		$r = $this->issue_and_sign_type( $uid, $s, 'comprovante_residencia', 'Joao Pereira' );
		remove_filter( 'ebcr_esign_text', $filter, 10 );
		$this->assertIsArray( $r, is_wp_error( $r ) ? $r->get_error_message() : '' );
		$this->assertSame( 'comprovante_residencia', $r['document']['doc_type'] );
		$this->assertStringStartsWith( 'comprovante-residencia-assinada-', $r['document']['original_name'] );
		$this->assertNotNull( $r['document']['expires_at'], 'validade da matriz aplicada (90 dias)' );
	}

	/**
	 * Emite código e assina para um tipo qualquer.
	 *
	 * @param int    $uid      Usuário.
	 * @param array  $s        Solicitação.
	 * @param string $doc_type Tipo.
	 * @param string $name     Nome digitado.
	 * @return array|\WP_Error
	 */
	private function issue_and_sign_type( $uid, array $s, $doc_type, $name ) {
		$issued = Service::issue_code( $uid, $s, $doc_type );
		if ( is_wp_error( $issued ) ) {
			return $issued;
		}
		return Service::sign(
			$uid,
			$s,
			$doc_type,
			array(
				'codigo'           => $issued['code'],
				'nome_confirmacao' => $name,
				'aceite'           => '1',
			)
		);
	}
}
