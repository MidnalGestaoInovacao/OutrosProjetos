<?php
/**
 * Testes de autorização (critério de aceite): IDOR, download, REST, status final, regras de submissão, uploads.
 *
 * @package EBCR
 */

use EBCR\Database\DocumentRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Files\DownloadController;
use EBCR\Files\DownloadDenied;
use EBCR\Files\Storage;
use EBCR\Files\UploadHandler;
use EBCR\Forms\SubmissionRules;
use EBCR\Forms\SubmissionService;
use EBCR\Forms\Wizard;
use EBCR\Roles\Capabilities;
use EBCR\Security\Authorization;
use EBCR\Support\Options;

/**
 * Cliente A × cliente B × analista × gestor × visitante.
 */
final class AuthorizationTest extends EBCR_TestCase {

	public function test_client_cannot_see_edit_or_list_other_clients_submission(): void {
		$a = $this->make_user();
		$b = $this->make_user();
		$s = $this->make_submission( $a, Status::SUBMITTED );
		$this->assertTrue( Authorization::can_view_submission( $a, $s ) );
		$this->assertFalse( Authorization::can_view_submission( $b, $s ) );
		$this->assertFalse( Authorization::can_view_submission( 0, $s ) );
		$this->assertFalse( Authorization::client_can_edit( $b, $s ) );
		$this->assertFalse( Authorization::client_can_cancel( $b, $s ) );
		// Listagem: só as próprias.
		$ids = wp_list_pluck( ( new SubmissionRepository() )->for_user( $b ), 'id' );
		$this->assertNotContains( (int) $s['id'], array_map( 'intval', $ids ) );
		// Serviços rejeitam B mesmo com o UUID correto.
		$this->assertInstanceOf( WP_Error::class, SubmissionService::client_cancel( $b, $s ) );
		$this->assertInstanceOf( WP_Error::class, SubmissionService::send_message( $b, $s, 'oi' ) );
		list( $ok, $errors ) = ( new Wizard() )->handle_step( $b, $this->make_submission( $a ), 1, array( 'person_type' => 'PF' ) );
		$this->assertFalse( $ok );
		$this->assertArrayHasKey( '_', $errors );
	}

	public function test_client_cannot_download_other_clients_document(): void {
		$a   = $this->make_user();
		$b   = $this->make_user();
		$s   = $this->make_submission( $a );
		$doc = $this->upload_pdf( $a, $s );
		$this->assertTrue( Authorization::can_access_document( $a, $doc ) );
		$this->assertFalse( Authorization::can_access_document( $b, $doc ) );
		$this->assertFalse( Authorization::can_access_document( 0, $doc ) );
		// Controlador: B logado com nonce válido → 403.
		wp_set_current_user( $b );
		$_GET['ebcr_nonce'] = wp_create_nonce( 'ebcr_download_' . $doc['public_id'] );
		try {
			( new DownloadController() )->serve( $doc['public_id'] );
			$this->fail( 'deveria negar' );
		} catch ( DownloadDenied $e ) {
			$this->assertSame( 403, $e->getCode() );
		}
		// Visitante → 401.
		wp_set_current_user( 0 );
		try {
			( new DownloadController() )->serve( $doc['public_id'] );
			$this->fail( 'deveria negar' );
		} catch ( DownloadDenied $e ) {
			$this->assertSame( 401, $e->getCode() );
		}
		// Dono sem nonce → 403 (link expirado).
		wp_set_current_user( $a );
		$_GET['ebcr_nonce'] = 'invalido';
		try {
			( new DownloadController() )->serve( $doc['public_id'] );
			$this->fail( 'deveria negar' );
		} catch ( DownloadDenied $e ) {
			$this->assertSame( 403, $e->getCode() );
		}
		unset( $_GET['ebcr_nonce'] );
		// Arquivo armazenado sem extensão executável e legível pelo Storage.
		$storage = new Storage();
		$this->assertStringEndsWith( '.dat', $doc['stored_name'] );
		$this->assertStringStartsWith( '%PDF', (string) $storage->read( $doc ) );
		$this->assertSame( 64, strlen( $doc['sha256'] ) );
	}

	public function test_team_access_respects_assignment_option(): void {
		$a       = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$manager = $this->make_user( Capabilities::ROLE_MANAGER );
		$s       = $this->make_submission( $a, Status::PRE_ANALYSIS );
		$doc     = $this->upload_pdf( $a, $this->make_submission( $a ) );
		$this->assertTrue( Authorization::can_view_submission( $analyst, $s ) );
		$this->assertTrue( Authorization::can_access_document( $analyst, $doc ) );
		Options::update( array( 'analyst_only_assigned' => true ) );
		$this->assertFalse( Authorization::can_view_submission( $analyst, $s ) );
		$this->assertFalse( Authorization::can_access_document( $analyst, $doc ) );
		$this->assertTrue( Authorization::can_view_submission( $manager, $s ) );
		( new SubmissionRepository() )->update( (int) $s['id'], array( 'assigned_to' => $analyst ) );
		$s = ( new SubmissionRepository() )->find( (int) $s['id'] );
		$this->assertTrue( Authorization::can_view_submission( $analyst, $s ) );
	}

	public function test_rest_endpoints_enforce_ownership(): void {
		$a = $this->make_user();
		$b = $this->make_user();
		$s = $this->make_submission( $a );
		$this->upload_pdf( $a, $s );
		rest_get_server();
		// B tenta listar documentos de A.
		wp_set_current_user( $b );
		$req = new WP_REST_Request( 'GET', '/ebcr/v1/submissions/' . $s['public_id'] . '/documents' );
		$res = rest_do_request( $req );
		$this->assertContains( $res->get_status(), array( 403, 404 ) );
		// B tenta salvar etapa de A.
		$req = new WP_REST_Request( 'POST', '/ebcr/v1/submissions/' . $s['public_id'] . '/step/1' );
		$req->set_body_params( array( 'person_type' => 'PF' ) );
		$this->assertContains( rest_do_request( $req )->get_status(), array( 403, 404 ) );
		// B tenta mudar status (sem capacidade).
		$req = new WP_REST_Request( 'POST', '/ebcr/v1/submissions/' . $s['public_id'] . '/status' );
		$req->set_body_params( array( 'status' => Status::APPROVED ) );
		$this->assertSame( 403, rest_do_request( $req )->get_status() );
		// Visitante.
		wp_set_current_user( 0 );
		$req = new WP_REST_Request( 'GET', '/ebcr/v1/submissions/' . $s['public_id'] . '/documents' );
		$this->assertSame( 401, rest_do_request( $req )->get_status() );
		// A consegue.
		wp_set_current_user( $a );
		$req = new WP_REST_Request( 'GET', '/ebcr/v1/submissions/' . $s['public_id'] . '/documents' );
		$res = rest_do_request( $req );
		$this->assertSame( 200, $res->get_status() );
		$this->assertCount( 1, $res->get_data() );
		$this->assertArrayNotHasKey( 'stored_name', $res->get_data()[0] );
	}

	public function test_analyst_cannot_set_final_status_but_manager_can(): void {
		$a       = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$manager = $this->make_user( Capabilities::ROLE_MANAGER );
		$s       = $this->make_submission( $a, Status::COMMITTEE );
		$this->assertFalse( Authorization::can_change_status( $analyst, $s, Status::APPROVED ) );
		$this->assertFalse( Authorization::can_change_status( $analyst, $s, Status::REJECTED ) );
		$this->assertTrue( Authorization::can_change_status( $analyst, $s, Status::PENDING_DOCS ) );
		$r = SubmissionService::change_status( $analyst, $s, Status::APPROVED, 'parecer' );
		$this->assertInstanceOf( WP_Error::class, $r );
		$this->assertSame( 'forbidden', $r->get_error_code() );
		// Gestor: exige comentário interno.
		$r = SubmissionService::change_status( $manager, $s, Status::APPROVED, '' );
		$this->assertSame( 'comment_required', $r->get_error_code() );
		$r = SubmissionService::change_status( $manager, $s, Status::APPROVED, 'Aprovado em comitê.' );
		$this->assertIsArray( $r );
		$this->assertSame( Status::APPROVED, $r['status'] );
		// Transição inválida (aprovada → comitê).
		$this->assertFalse( Authorization::can_change_status( $manager, $r, Status::COMMITTEE ) );
		// Cliente nunca muda status.
		$this->assertFalse( Authorization::can_change_status( $a, $s, Status::PENDING_DOCS ) );
	}

	public function test_submission_rules_enforced_server_side(): void {
		$a = $this->make_user();
		Options::update( array( 'min_interval_days' => 30, 'block_if_in_progress' => false ) );
		$this->make_submission( $a, Status::DONE, array( 'submitted_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-5 days' ) ) ) );
		$rules = SubmissionRules::can_submit( $a );
		$this->assertFalse( $rules['allowed'] );
		$this->assertSame( 'interval', $rules['reason'] );
		$this->assertGreaterThan( 0, $rules['wait_seconds'] );
		$this->assertInstanceOf( WP_Error::class, SubmissionService::start( $a ) );
		// Requisição forjada de envio de um rascunho é rejeitada mesmo antes de validar campos.
		$draft = $this->make_submission( $a );
		$r     = ( new Wizard() )->submit( $a, $draft, array( 'consent_scr' => 1 ) );
		$this->assertInstanceOf( WP_Error::class, $r );
		$this->assertSame( 'rules', $r->get_error_code() );
		// Em andamento bloqueia.
		Options::update( array( 'min_interval_days' => 0, 'block_if_in_progress' => true ) );
		$this->make_submission( $a, Status::PRE_ANALYSIS );
		$this->assertSame( 'in_progress', SubmissionRules::can_submit( $a )['reason'] );
		// E-mail não confirmado bloqueia.
		$c = $this->make_user( Capabilities::ROLE_CLIENT, false );
		$this->assertSame( 'unverified', SubmissionRules::can_submit( $c )['reason'] );
	}

	public function test_upload_rejects_php_disguised_as_pdf_and_bad_extensions(): void {
		$a = $this->make_user();
		$s = $this->make_submission( $a );
		$h = new UploadHandler();
		$php = $this->tmp_file( "<?php echo 'x'; ?>", 'pdf' );
		$r   = $h->handle( $a, $s, $this->files_item( $php, 'documento.pdf' ), 'comprovante_residencia' );
		$this->assertInstanceOf( WP_Error::class, $r );
		$this->assertSame( 'mime_mismatch', $r->get_error_code() );
		$this->assertFileExists( $php, 'arquivo rejeitado não deve ser movido' );
		$exe = $this->tmp_file( $this->pdf_bytes(), 'php' );
		$r   = $h->handle( $a, $s, $this->files_item( $exe, 'shell.php' ), 'comprovante_residencia' );
		$this->assertSame( 'bad_extension', $r->get_error_code() );
		$svg = $this->tmp_file( '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'svg' );
		Options::update( array( 'allowed_extensions' => 'pdf,svg' ) ); // svg nunca é aceito.
		$r = $h->handle( $a, $s, $this->files_item( $svg, 'img.svg' ), 'comprovante_residencia' );
		$this->assertSame( 'bad_extension', $r->get_error_code() );
		$r = $h->handle( $a, $s, $this->files_item( $this->tmp_file( $this->pdf_bytes() ), 'ok.pdf' ), 'tipo_inexistente' );
		$this->assertSame( 'bad_type', $r->get_error_code() );
	}

	public function test_upload_size_quota_and_duplicates(): void {
		$a = $this->make_user();
		$s = $this->make_submission( $a );
		$h = new UploadHandler();
		Options::update( array( 'max_file_size_mb' => 1, 'user_quota_mb' => 2 ) );
		$big = $this->tmp_file( $this->pdf_bytes( 1100 * 1024 ) );
		$r   = $h->handle( $a, $s, $this->files_item( $big, 'grande.pdf' ), 'comprovante_residencia' );
		$this->assertSame( 'too_large', $r->get_error_code() );
		$ok1 = $h->handle( $a, $s, $this->files_item( $this->tmp_file( $this->pdf_bytes( 900 * 1024 ) ), 'a.pdf' ), 'comprovante_residencia' );
		$this->assertIsArray( $ok1 );
		$ok2 = $h->handle( $a, $s, $this->files_item( $this->tmp_file( $this->pdf_bytes( 900 * 1024 + 7 ) ), 'b.pdf' ), 'certidoes_negativas' );
		$this->assertIsArray( $ok2 );
		$r = $h->handle( $a, $s, $this->files_item( $this->tmp_file( $this->pdf_bytes( 400 * 1024 ) ), 'c.pdf' ), 'certidoes_negativas' );
		$this->assertSame( 'quota', $r->get_error_code() );
		$this->assertSame( 100, UploadHandler::quota_usage( $a )['percent'] > 80 ? 100 : 0 );
		// Duplicado (mesmo hash).
		Options::update( array( 'user_quota_mb' => 200 ) );
		$content = $this->pdf_bytes( 33 );
		$d1 = $h->handle( $a, $s, $this->files_item( $this->tmp_file( $content ), 'd.pdf' ), 'irpf_lcdpr' );
		$this->assertIsArray( $d1 );
		$d2 = $h->handle( $a, $s, $this->files_item( $this->tmp_file( $content ), 'd-copia.pdf' ), 'irpf_lcdpr' );
		$this->assertSame( 'duplicate', $d2->get_error_code() );
		// Cliente não pode enviar em status final; equipe pode pedir documento e aí o cliente atende.
		$repo = new SubmissionRepository();
		$repo->update( (int) $s['id'], array( 'status' => Status::REJECTED ) );
		$s = $repo->find( (int) $s['id'] );
		$r = $h->handle( $a, $s, $this->files_item( $this->tmp_file( $this->pdf_bytes( 5 ) ), 'e.pdf' ), 'outro' );
		$this->assertInstanceOf( WP_Error::class, $r );
		// Documento aceito não pode ser removido pelo cliente.
		( new DocumentRepository() )->update( (int) $d1['id'], array( 'review_status' => 'aceito' ) );
		$repo->update( (int) $s['id'], array( 'status' => Status::DRAFT ) );
		$this->assertInstanceOf( WP_Error::class, SubmissionService::client_delete_document( $a, ( new DocumentRepository() )->find( (int) $d1['id'] ) ) );
	}

	public function test_document_request_flow_allows_upload_outside_draft(): void {
		$a       = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$s       = $this->make_submission( $a, Status::PRE_ANALYSIS );
		$h       = new UploadHandler();
		$r       = $h->handle( $a, $s, $this->files_item( $this->tmp_file( $this->pdf_bytes( 9 ) ), 'x.pdf' ), 'outro' );
		$this->assertSame( 'locked', $r->get_error_code() );
		$req = SubmissionService::request_document( $analyst, $s, 'laudo_avaliacao', 'Laudo', 'Com ART', true );
		$this->assertIsArray( $req );
		$this->assertSame( Status::PENDING_DOCS, $req['submission']['status'] );
		$doc = $h->handle( $a, $req['submission'], $this->files_item( $this->tmp_file( $this->pdf_bytes( 11 ) ), 'laudo.pdf' ), 'outro', '', (int) $req['request']['id'] );
		$this->assertIsArray( $doc );
		$this->assertSame( 'laudo_avaliacao', $doc['doc_type'] );
		$this->assertNotNull( $doc['expires_at'] );
		// Analista recusa → pedido reaberto; cliente B não pode revisar.
		$this->assertInstanceOf( WP_Error::class, SubmissionService::review_document( $this->make_user(), $doc, 'aceito' ) );
		$rev = SubmissionService::review_document( $analyst, $doc, 'recusado', 'Ilegível' );
		$this->assertSame( 'recusado', $rev['review_status'] );
	}
}
