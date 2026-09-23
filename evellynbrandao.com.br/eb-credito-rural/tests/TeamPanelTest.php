<?php
/**
 * Painel da equipe no portal: permissões, handlers (via run_*), modo "só no site" e CSV.
 *
 * @package EBCR
 */

use EBCR\Database\CheckRepository;
use EBCR\Database\DocumentRequestRepository;
use EBCR\Database\MessageRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Frontend\Team\Access;
use EBCR\Frontend\Team\Actions;
use EBCR\Frontend\Team\Export;
use EBCR\Frontend\Team\Panel;
use EBCR\Frontend\Team\Screens;
use EBCR\Roles\Capabilities;
use EBCR\Support\Options;

/**
 * Os handlers são exercitados pelos métodos run_*() com $_POST simulado (nonce real do usuário atual).
 */
final class TeamPanelTest extends EBCR_TestCase {

	/**
	 * IDs de solicitações criadas (limpeza).
	 *
	 * @var int[]
	 */
	private $created = array();

	/**
	 * Sem e-mails reais; opções limpas.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	/**
	 * Remove as solicitações criadas e dados relacionados.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->created as $id ) {
			$wpdb->delete( $wpdb->prefix . 'ebcr_submissions', array( 'id' => $id ) );
			foreach ( array( 'ebcr_submission_data', 'ebcr_status_history', 'ebcr_document_requests', 'ebcr_messages', 'ebcr_checks' ) as $t ) {
				$wpdb->delete( $wpdb->prefix . $t, array( 'submission_id' => $id ) );
			}
		}
		remove_filter( 'pre_wp_mail', '__return_true' );
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * Solicitação registrada para limpeza.
	 *
	 * @param int    $user_id Cliente.
	 * @param string $status  Status.
	 * @param array  $extra   Campos.
	 * @return array
	 */
	private function sub( $user_id, $status = Status::SUBMITTED, array $extra = array() ) {
		$s               = $this->make_submission( $user_id, $status, $extra );
		$this->created[] = (int) $s['id'];
		return $s;
	}

	/**
	 * $_POST simulado com nonce válido para o usuário atual.
	 *
	 * @param string $action Ação do nonce (sem prefixo team_).
	 * @param array  $fields Campos.
	 * @return array
	 */
	private function post( $action, array $fields ) {
		return array_merge( $fields, array( 'ebcr_nonce' => wp_create_nonce( 'ebcr_team_' . $action ) ) );
	}

	// ------------------------------------------------------------------ permissões

	public function test_client_cannot_open_team_panel(): void {
		$client = $this->make_user();
		$this->assertFalse( Panel::can_access( $client ) );
		$html = Panel::render( get_userdata( $client ) );
		$this->assertStringContainsString( 'Sem permissão', $html );
		$this->assertStringNotContainsString( 'ebcr-team', $html );
	}

	public function test_team_can_open_panel_and_nav_follows_capabilities(): void {
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$manager = $this->make_user( Capabilities::ROLE_MANAGER );
		$this->assertTrue( Panel::can_access( $analyst ) );
		$this->assertTrue( Panel::can_access( $manager ) );
		$nav_a = Panel::nav( get_userdata( $analyst ) );
		$nav_m = Panel::nav( get_userdata( $manager ) );
		$this->assertArrayHasKey( 'crm', $nav_a );
		$this->assertArrayNotHasKey( 'relatorios', $nav_a, 'analista não tem relatórios' );
		$this->assertArrayHasKey( 'relatorios', $nav_m );
		$this->assertStringContainsString( 'ebcr_view=equipe', $nav_m['solicitacoes']['url'] );
	}

	public function test_analyst_sees_only_assigned_when_option_is_on(): void {
		Options::update( array( 'analyst_only_assigned' => true ) );
		$client  = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$manager = $this->make_user( Capabilities::ROLE_MANAGER );
		$mine    = $this->sub( $client, Status::SUBMITTED, array( 'assigned_to' => $analyst ) );
		$other   = $this->sub( $client, Status::SUBMITTED );

		$this->assertTrue( Screens::only_assigned( $analyst ) );
		$this->assertFalse( Screens::only_assigned( $manager ) );

		list( $items, $total ) = Screens::query_submissions( $analyst, Screens::list_args( array( 'busca' => $mine['protocol'] ) ) );
		$this->assertSame( 1, $total );
		$this->assertSame( $mine['public_id'], $items[0]['public_id'] );
		list( $items, $total ) = Screens::query_submissions( $analyst, Screens::list_args( array( 'busca' => $other['protocol'] ) ) );
		$this->assertSame( 0, $total, 'analista não lista a não atribuída' );

		list( $items ) = Screens::query_submissions( $manager, array_merge( Screens::list_args( array( 'busca' => $other['protocol'] ) ), array( 'per_page' => 200 ) ) );
		$this->assertContains( $other['public_id'], wp_list_pluck( $items, 'public_id' ), 'gestor vê tudo' );

		// Detalhe: analista não abre a não atribuída; gestor abre.
		wp_set_current_user( $analyst );
		$this->assertStringContainsString( 'não encontrada', ( new Screens( $analyst ) )->submission( $other['public_id'] ) );
		wp_set_current_user( $manager );
		$this->assertStringContainsString( $other['protocol'], ( new Screens( $manager ) )->submission( $other['public_id'] ) );
	}

	public function test_manager_sees_all_when_option_is_off(): void {
		Options::update( array( 'analyst_only_assigned' => false ) );
		$client  = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$other   = $this->sub( $client, Status::SUBMITTED );
		list( $items ) = Screens::query_submissions( $analyst, array_merge( Screens::list_args( array( 'busca' => $other['protocol'] ) ), array( 'per_page' => 200 ) ) );
		$this->assertContains( $other['public_id'], wp_list_pluck( $items, 'public_id' ) );
	}

	public function test_list_args_are_sanitized(): void {
		$args = Screens::list_args(
			array(
				'status'   => 'inexistente',
				'analista' => '12abc',
				'ordem'    => 'DROP TABLE',
				'dir'      => 'asc',
				'de'       => '2026-13-99x',
				'pg'       => 'abc',
			)
		);
		$this->assertSame( '', $args['status'] );
		$this->assertSame( 12, $args['assigned_to'] );
		$this->assertSame( 'submitted_at', $args['orderby'] );
		$this->assertSame( 'ASC', $args['order'] );
		$this->assertSame( '', $args['date_from'] );
		$this->assertSame( 1, $args['page'] );
	}

	public function test_transitions_respect_final_status_capability(): void {
		$client  = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$manager = $this->make_user( Capabilities::ROLE_MANAGER );
		$s       = $this->sub( $client, Status::COMMITTEE );
		$ta      = Screens::transitions( $analyst, $s );
		$tm      = Screens::transitions( $manager, $s );
		$this->assertArrayNotHasKey( Status::APPROVED, $ta );
		$this->assertArrayNotHasKey( Status::REJECTED, $ta );
		$this->assertArrayHasKey( Status::PENDING_DOCS, $ta );
		$this->assertArrayHasKey( Status::APPROVED, $tm );
		$this->assertArrayHasKey( Status::REJECTED, $tm );

		// O handler também recusa (mesmo com nonce válido e POST forjado).
		wp_set_current_user( $analyst );
		$r = Actions::run_status(
			$analyst,
			$this->post(
				'status',
				array(
					'id'               => $s['public_id'],
					'status'           => Status::APPROVED,
					'comment_internal' => 'Parecer',
				)
			)
		);
		$this->assertFalse( $r['ok'] );
		$this->assertSame( Status::COMMITTEE, ( new SubmissionRepository() )->find( (int) $s['id'] )['status'] );
	}

	// ------------------------------------------------------------------ handlers

	public function test_run_status_requires_valid_nonce(): void {
		$client  = $this->make_user();
		$manager = $this->make_user( Capabilities::ROLE_MANAGER );
		$s       = $this->sub( $client );
		wp_set_current_user( $manager );
		$r = Actions::run_status(
			$manager,
			array(
				'id'         => $s['public_id'],
				'status'     => Status::PRE_ANALYSIS,
				'ebcr_nonce' => 'invalido',
			)
		);
		$this->assertFalse( $r['ok'] );
		$this->assertStringContainsString( 'Sessão expirada', $r['msg'] );
		$this->assertSame( Status::SUBMITTED, ( new SubmissionRepository() )->find( (int) $s['id'] )['status'] );
	}

	public function test_run_status_changes_status_with_comments(): void {
		$client  = $this->make_user();
		$manager = $this->make_user( Capabilities::ROLE_MANAGER );
		$s       = $this->sub( $client );
		wp_set_current_user( $manager );
		$r = Actions::run_status(
			$manager,
			$this->post(
				'status',
				array(
					'id'               => $s['public_id'],
					'status'           => Status::PRE_ANALYSIS,
					'comment_internal' => 'Iniciando análise',
					'comment_client'   => 'Recebemos tudo.',
				)
			)
		);
		$this->assertTrue( $r['ok'], $r['msg'] );
		$this->assertSame( 'solicitacao', $r['to']['tela'] );
		$this->assertSame( $s['public_id'], $r['to']['id'] );
		$this->assertSame( Status::PRE_ANALYSIS, ( new SubmissionRepository() )->find( (int) $s['id'] )['status'] );
	}

	public function test_run_assign_sets_analyst_and_rejects_non_team(): void {
		$client  = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$manager = $this->make_user( Capabilities::ROLE_MANAGER );
		$s       = $this->sub( $client );
		wp_set_current_user( $manager );
		$r = Actions::run_assign(
			$manager,
			$this->post(
				'assign',
				array(
					'id'         => $s['public_id'],
					'analyst_id' => $analyst,
				)
			)
		);
		$this->assertTrue( $r['ok'], $r['msg'] );
		$this->assertSame( $analyst, (int) ( new SubmissionRepository() )->find( (int) $s['id'] )['assigned_to'] );

		$r = Actions::run_assign(
			$manager,
			$this->post(
				'assign',
				array(
					'id'         => $s['public_id'],
					'analyst_id' => $client,
				)
			)
		);
		$this->assertFalse( $r['ok'], 'cliente não pode ser analista' );
	}

	public function test_run_request_document_opens_pending_and_changes_status(): void {
		$client  = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$s       = $this->sub( $client, Status::PRE_ANALYSIS );
		wp_set_current_user( $analyst );
		$r = Actions::run_request_document(
			$analyst,
			$this->post(
				'request',
				array(
					'id'         => $s['public_id'],
					'doc_type'   => 'laudo_avaliacao',
					'label'      => 'Laudo com ART',
					'note'       => 'Enviar laudo assinado.',
					'set_status' => '1',
				)
			)
		);
		$this->assertTrue( $r['ok'], $r['msg'] );
		$open = ( new DocumentRequestRepository() )->for_submission( (int) $s['id'], true );
		$this->assertCount( 1, $open );
		$this->assertSame( 'Laudo com ART', $open[0]['label'] );
		$this->assertSame( Status::PENDING_DOCS, ( new SubmissionRepository() )->find( (int) $s['id'] )['status'] );
	}

	public function test_run_message_internal_and_client(): void {
		$client  = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$s       = $this->sub( $client, Status::PRE_ANALYSIS );
		wp_set_current_user( $analyst );
		$r = Actions::run_message(
			$analyst,
			$this->post(
				'message',
				array(
					'id'         => $s['public_id'],
					'body'       => 'Nota só para a equipe',
					'visibility' => 'interno',
				)
			)
		);
		$this->assertTrue( $r['ok'], $r['msg'] );
		$r = Actions::run_message(
			$analyst,
			$this->post(
				'message',
				array(
					'id'         => $s['public_id'],
					'body'       => 'Olá, precisamos do laudo.',
					'visibility' => 'cliente',
				)
			)
		);
		$this->assertTrue( $r['ok'], $r['msg'] );
		$all    = ( new MessageRepository() )->for_submission( (int) $s['id'] );
		$client_visible = ( new MessageRepository() )->for_submission( (int) $s['id'], true );
		$this->assertCount( 2, $all );
		$this->assertCount( 1, $client_visible );
		$this->assertSame( 'Olá, precisamos do laudo.', $client_visible[0]['body'] );

		$r = Actions::run_message( $analyst, $this->post( 'message', array( 'id' => $s['public_id'], 'body' => '   ' ) ) );
		$this->assertFalse( $r['ok'], 'mensagem vazia é recusada' );
	}

	public function test_run_checks_saves_checklist_and_requires_edit(): void {
		$client  = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$s       = $this->sub( $client, Status::PRE_ANALYSIS );
		wp_set_current_user( $analyst );
		$r = Actions::run_checks(
			$analyst,
			$this->post(
				'checks',
				array(
					'id'    => $s['public_id'],
					'check' => array(
						'area'      => array(
							'result' => 'ok',
							'note'   => 'Matrícula confere com CAR',
						),
						'dividas'   => array(
							'result' => 'alerta',
							'note'   => 'SCR com atraso',
						),
						'invalido'  => array( 'result' => 'ok' ),
						'certidoes' => array( 'result' => 'qualquer' ),
					),
				)
			)
		);
		$this->assertTrue( $r['ok'], $r['msg'] );
		$checks = ( new CheckRepository() )->for_submission( (int) $s['id'] );
		$this->assertSame( 'ok', $checks['area']['result'] );
		$this->assertSame( 'Matrícula confere com CAR', $checks['area']['note'] );
		$this->assertSame( 'alerta', $checks['dividas']['result'] );
		$this->assertSame( 'na', $checks['certidoes']['result'], 'valor inválido vira n.a.' );
		$this->assertArrayNotHasKey( 'invalido', $checks );
		$this->assertSame( $analyst, (int) $checks['area']['checked_by'] );

		// Analista sem atribuição (opção ligada) não salva a conferência.
		Options::update( array( 'analyst_only_assigned' => true ) );
		$other = $this->make_user( Capabilities::ROLE_ANALYST );
		wp_set_current_user( $other );
		$r = Actions::run_checks( $other, $this->post( 'checks', array( 'id' => $s['public_id'], 'check' => array( 'area' => array( 'result' => 'reprovado' ) ) ) ) );
		$this->assertFalse( $r['ok'] );
		$this->assertSame( 'ok', ( new CheckRepository() )->for_submission( (int) $s['id'] )['area']['result'] );
	}

	public function test_client_cannot_run_team_actions(): void {
		$client = $this->make_user();
		$s      = $this->sub( $client );
		wp_set_current_user( $client );
		$r = Actions::run_status( $client, $this->post( 'status', array( 'id' => $s['public_id'], 'status' => Status::PRE_ANALYSIS ) ) );
		$this->assertFalse( $r['ok'] );
		$this->assertSame( Status::SUBMITTED, ( new SubmissionRepository() )->find( (int) $s['id'] )['status'] );
	}

	// ------------------------------------------------------------------ modo "só no site"

	public function test_portal_only_redirects_team_but_never_admins(): void {
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$manager = $this->make_user( Capabilities::ROLE_MANAGER );
		$client  = $this->make_user();
		$admin   = $this->make_user( 'administrator' );
		$panel   = Panel::url();

		$this->assertSame( $panel, Access::admin_redirect_url( $analyst, Access::MODE_PORTAL_ONLY, array( 'pagenow' => 'index.php' ) ) );
		$this->assertSame( $panel, Access::admin_redirect_url( $manager, Access::MODE_PORTAL_ONLY, array( 'pagenow' => 'admin.php' ) ) );
		$this->assertSame( '', Access::admin_redirect_url( $admin, Access::MODE_PORTAL_ONLY, array( 'pagenow' => 'index.php' ) ), 'administrador nunca é bloqueado' );
		$this->assertSame( '', Access::admin_redirect_url( $client, Access::MODE_PORTAL_ONLY, array( 'pagenow' => 'index.php' ) ), 'cliente é tratado por Capabilities::restrict_clients' );
		$this->assertSame( '', Access::admin_redirect_url( $analyst, Access::MODE_BOTH, array( 'pagenow' => 'index.php' ) ), 'em both nada muda' );
		$this->assertSame( '', Access::admin_redirect_url( $analyst, Access::MODE_PORTAL_ONLY, array( 'ajax' => true ) ), 'admin-ajax passa' );
		$this->assertSame( '', Access::admin_redirect_url( $analyst, Access::MODE_PORTAL_ONLY, array( 'rest' => true ) ), 'REST passa' );
		$this->assertSame( '', Access::admin_redirect_url( $analyst, Access::MODE_PORTAL_ONLY, array( 'pagenow' => 'admin-post.php' ) ), 'admin-post passa' );
		$this->assertSame( '', Access::admin_redirect_url( 0, Access::MODE_PORTAL_ONLY, array() ) );

		$this->assertSame( $panel, Access::login_destination( admin_url(), $analyst, Access::MODE_PORTAL_ONLY ) );
		$this->assertSame( admin_url(), Access::login_destination( admin_url(), $admin, Access::MODE_PORTAL_ONLY ) );
		$this->assertSame( admin_url(), Access::login_destination( admin_url(), $analyst, Access::MODE_BOTH ) );

		$this->assertFalse( Access::show_admin_bar( true, $analyst, Access::MODE_PORTAL_ONLY ) );
		$this->assertTrue( Access::show_admin_bar( true, $admin, Access::MODE_PORTAL_ONLY ) );
		$this->assertTrue( Access::show_admin_bar( true, $analyst, Access::MODE_BOTH ) );
	}

	public function test_mode_reads_option_with_safe_default(): void {
		Options::update( array( 'team_portal_mode' => 'portal_only' ) );
		$this->assertTrue( Access::portal_only() );
		Options::update( array( 'team_portal_mode' => 'qualquer coisa' ) );
		$this->assertSame( Access::MODE_BOTH, Access::mode() );
		Options::update( array( 'team_portal_mode' => 'both' ) );
		$this->assertFalse( Access::portal_only() );
	}

	// ------------------------------------------------------------------ CSV

	public function test_submissions_csv_lists_only_visible_rows_and_masks_document(): void {
		Options::update( array( 'analyst_only_assigned' => true ) );
		$client  = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$manager = $this->make_user( Capabilities::ROLE_MANAGER );
		$mine    = $this->sub( $client, Status::SUBMITTED, array( 'assigned_to' => $analyst, 'requested_amount' => 123456.78 ) );
		$other   = $this->sub( $client, Status::SUBMITTED );
		( new \EBCR\Database\SubmissionDataRepository() )->save( (int) $mine['id'], 'identificacao', array( 'cpf' => '52998224725', 'uf' => 'GO' ) );

		list( $items ) = Screens::query_submissions( $manager, array( 'search' => 'T-', 'per_page' => 500 ) );
		$csv_manager   = Export::submissions_csv( $manager, $items );
		$this->assertStringStartsWith( "\xEF\xBB\xBF", $csv_manager, 'BOM para Excel' );
		$this->assertStringContainsString( 'protocolo;status;cliente;email', $csv_manager );
		$this->assertStringContainsString( $mine['protocol'], $csv_manager );
		$this->assertStringContainsString( $other['protocol'], $csv_manager );
		$this->assertStringContainsString( '***.***.247-25', $csv_manager, 'CPF mascarado' );
		$this->assertStringNotContainsString( '52998224725', $csv_manager );

		$csv_analyst = Export::submissions_csv( $analyst, $items );
		$this->assertStringContainsString( $mine['protocol'], $csv_analyst );
		$this->assertStringNotContainsString( $other['protocol'], $csv_analyst, 'analista só exporta as atribuídas' );

		$csv_reports = \EBCR\Admin\Reports::csv( \EBCR\Reports\Metrics::normalize( array(), $manager ) );
		$this->assertStringContainsString( 'Por fundo/carteira', $csv_reports );
	}
}
