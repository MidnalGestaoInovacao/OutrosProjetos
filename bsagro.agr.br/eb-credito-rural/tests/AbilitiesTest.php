<?php
/**
 * Abilities (WordPress Abilities API / Easy MCP AI): registro, permissões, configuração e operações.
 *
 * @package EBCR
 */

use EBCR\Abilities\Abilities;
use EBCR\Database\MessageRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Roles\Capabilities;
use EBCR\Support\Options;

/**
 * Configuração e operação por agentes de IA.
 */
final class AbilitiesTest extends EBCR_TestCase {

	/**
	 * Executa uma ability pelo registro do WordPress (passa por permission_callback e validação de entrada).
	 *
	 * @param string $name  Nome curto (ex.: get-settings).
	 * @param array  $input Entrada.
	 * @return mixed
	 */
	private function ability( $name, array $input = array() ) {
		$ability = wp_get_ability( 'ebcr/' . $name );
		$this->assertNotNull( $ability, "ability ebcr/$name não registrada" );
		return $ability->execute( $input );
	}

	protected function setUp(): void {
		parent::setUp();
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	protected function tearDown(): void {
		delete_option( Abilities::EMCP_OPT );
		remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		remove_filter( 'pre_wp_mail', '__return_true' );
		parent::tearDown();
	}

	public function test_all_abilities_registered_with_category_and_rest_meta(): void {
		if ( ! Abilities::is_available() ) {
			$this->markTestSkipped( 'Abilities API indisponível neste WordPress.' );
		}
		$names = array_keys( wp_get_abilities() );
		foreach ( Abilities::slugs() as $slug ) {
			$this->assertContains( $slug, $names );
			$ability = wp_get_ability( $slug );
			$this->assertSame( 'ebcr', $ability->get_category() );
			$this->assertTrue( (bool) $ability->get_meta_item( 'show_in_rest' ) );
			$this->assertSame( 'object', $ability->get_input_schema()['type'] );
		}
		$this->assertCount( 18, Abilities::slugs() );
		$this->assertTrue( wp_has_ability_category( 'ebcr' ) );
	}

	public function test_permissions_follow_capabilities(): void {
		$r = $this->ability( 'get-settings' );
		$this->assertInstanceOf( WP_Error::class, $r, 'anônimo não pode ler configurações' );

		wp_set_current_user( $this->make_user( Capabilities::ROLE_CLIENT ) );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'get-settings' ) );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'list-submissions' ) );

		wp_set_current_user( $this->make_user( Capabilities::ROLE_ANALYST ) );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'get-settings' ), 'analista não configura' );
		$this->assertIsArray( $this->ability( 'list-submissions' ), 'analista lista solicitações' );

		wp_set_current_user( $this->make_user( Capabilities::ROLE_MANAGER ) );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'get-settings' ), 'gestor não configura (só administrador)' );
		$this->assertIsArray( $this->ability( 'list-team' ), 'gestor vê a equipe' );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'set-team-member', array( 'email' => 'x@example.com', 'role' => 'ebcr_analista' ) ), 'gestor não altera papéis' );

		wp_set_current_user( $this->make_user( 'administrator' ) );
		$r = $this->ability( 'get-settings', array( 'tab' => 'geral' ) );
		$this->assertIsArray( $r );
		$this->assertArrayHasKey( 'protocol_prefix', $r['settings'] );
		$this->assertArrayHasKey( 'label', $r['fields']['protocol_prefix'] );
	}

	public function test_update_settings_sanitizes_clamps_and_reports_unknown_keys(): void {
		wp_set_current_user( $this->make_user( 'administrator' ) );
		$r = $this->ability(
			'update-settings',
			array(
				'settings' => array(
					'protocol_prefix'     => 'ZZ',
					'admin_emails'        => 'a@example.com, b@example.com',
					'password_min_length' => 2,
					'honeypot'            => 'sim',
					'inexistente'         => 1,
				),
			)
		);
		$this->assertIsArray( $r, is_wp_error( $r ) ? $r->get_error_message() : '' );
		$this->assertSame( 'ZZ', $r['values']['protocol_prefix'] );
		$this->assertSame( 8, $r['values']['password_min_length'], 'abaixo do mínimo é ajustado' );
		$this->assertTrue( $r['values']['honeypot'] );
		$this->assertNotEmpty( array_filter( $r['errors'], static fn( $e ) => false !== strpos( $e, 'inexistente' ) ) );
		Options::flush();
		$this->assertSame( 'ZZ', Options::get( 'protocol_prefix' ) );
		$this->assertSame( array( 'a@example.com', 'b@example.com' ), Options::admin_emails() );

		$this->assertInstanceOf( WP_Error::class, $this->ability( 'update-settings', array( 'settings' => 'x' ) ), 'entrada fora do schema é rejeitada' );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'update-settings', array( 'settings' => array() ) ) );

		$r = $this->ability( 'reset-settings', array( 'tab' => 'geral' ) );
		$this->assertContains( 'protocol_prefix', $r['reset'] );
		Options::flush();
		$this->assertSame( 'EB', Options::get( 'protocol_prefix' ) );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'reset-settings', array( 'tab' => 'nada' ) ) );
	}

	public function test_status_and_tools(): void {
		wp_set_current_user( $this->make_user( 'administrator' ) );
		$r = $this->ability( 'get-status' );
		$this->assertSame( EBCR_VERSION, $r['version'] );
		foreach ( array( 'environment', 'storage', 'mail_queue', 'submissions', 'mcp', 'todo' ) as $k ) {
			$this->assertArrayHasKey( $k, $r );
		}
		$this->assertContains( 'wp_ability_ebcr_update_settings', $r['mcp']['tool_names'] );

		$r = $this->ability( 'run-tool', array( 'tool' => 'test_protection' ) );
		$this->assertIsArray( $r );
		$this->assertArrayHasKey( 'result', $r );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'run-tool', array( 'tool' => 'rm_rf' ) ) );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'run-tool', array( 'tool' => 'test_email' ) ), 'test_email exige "to"' );
	}

	public function test_enable_in_easy_mcp_merges_without_removing_other_abilities(): void {
		update_option( Abilities::EMCP_OPT, array( 'core/get-site-info' ), false );
		$r = Abilities::enable_in_easy_mcp();
		$this->assertTrue( $r['enabled'] );
		$this->assertSame( 18, $r['added'] );
		$opt = get_option( Abilities::EMCP_OPT );
		$this->assertContains( 'core/get-site-info', $opt );
		$this->assertContains( 'ebcr/update-settings', $opt );
		$this->assertSame( 'already', Abilities::enable_in_easy_mcp()['reason'] );
		$st = Abilities::mcp_status();
		$this->assertSame( array(), $st['missing'] );
		$this->assertCount( 18, $st['enabled'] );

		delete_option( Abilities::EMCP_OPT );
		$this->assertSame( 'easy_mcp_ai_missing', Abilities::enable_in_easy_mcp()['reason'] );
		$this->assertFalse( get_option( Abilities::EMCP_OPT ), 'sem o conector, não cria a opção' );
	}

	public function test_team_management_and_operations_via_abilities(): void {
		$manager = $this->make_user( Capabilities::ROLE_MANAGER );
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$client  = $this->make_user( Capabilities::ROLE_CLIENT );
		$sub     = $this->make_submission( $client, 'enviada' );
		wp_set_current_user( $manager );

		$team = $this->ability( 'list-team' );
		$ids  = wp_list_pluck( $team['users'], 'id' );
		$this->assertContains( $analyst, $ids );
		$this->assertNotContains( $client, $ids, 'clientes não aparecem na equipe' );

		$r = $this->ability( 'change-status', array( 'id' => $sub['protocol'], 'status' => 'pre_analise', 'comment_internal' => 'ok' ) );
		$this->assertIsArray( $r, is_wp_error( $r ) ? $r->get_error_message() : '' );
		$this->assertSame( 'pre_analise', $r['status'] );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'change-status', array( 'id' => $sub['public_id'], 'status' => 'concluida' ) ), 'transição inválida' );

		$r = $this->ability( 'assign-submission', array( 'id' => $sub['public_id'], 'analyst' => get_userdata( $analyst )->user_email ) );
		$this->assertSame( $analyst, (int) $r['assigned_to'] );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'assign-submission', array( 'id' => $sub['public_id'], 'analyst' => 'ninguem@example.com' ) ) );

		$r = $this->ability( 'request-document', array( 'id' => $sub['public_id'], 'doc_type' => 'outro', 'label' => 'Matrícula atualizada', 'note' => 'Até 30 dias' ) );
		$this->assertIsArray( $r, is_wp_error( $r ) ? $r->get_error_message() : '' );
		$this->assertSame( 'pendencia_documental', $r['submission']['status'] );

		$r = $this->ability( 'send-message', array( 'id' => $sub['public_id'], 'body' => 'Mensagem interna', 'visibility' => 'interno' ) );
		$this->assertIsArray( $r, is_wp_error( $r ) ? $r->get_error_message() : '' );
		$msgs = ( new MessageRepository() )->for_submission( (int) $sub['id'] );
		$this->assertCount( 1, $msgs );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'send-message', array( 'id' => $sub['public_id'], 'body' => '' ) ) );

		$r = $this->ability( 'get-submission', array( 'id' => $sub['public_id'] ) );
		$this->assertSame( $sub['public_id'], $r['submission']['id'] );
		$this->assertArrayHasKey( 'history', $r );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'get-submission', array( 'id' => 'nao-existe' ) ) );

		// Analista só vê o que lhe cabe quando "analyst_only_assigned" está ligado.
		Options::update( array( 'analyst_only_assigned' => true ) );
		$other = $this->make_submission( $client, 'enviada' );
		wp_set_current_user( $analyst );
		$this->assertIsArray( $this->ability( 'get-submission', array( 'id' => $sub['public_id'] ) ) );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'get-submission', array( 'id' => $other['public_id'] ) ) );

		// Cliente nunca opera por abilities.
		wp_set_current_user( $client );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'get-submission', array( 'id' => $sub['public_id'] ) ) );

		$repo = new SubmissionRepository();
		$this->assertSame( 'pendencia_documental', $repo->find( (int) $sub['id'] )['status'] );
	}

	public function test_set_team_member_creates_and_changes_roles(): void {
		wp_set_current_user( $this->make_user( 'administrator' ) );
		$email = 'novo' . wp_generate_password( 6, false, false ) . '@example.com';
		$r     = $this->ability( 'set-team-member', array( 'email' => $email, 'role' => 'ebcr_analista', 'name' => 'Analista Novo' ) );
		$this->assertIsArray( $r, is_wp_error( $r ) ? $r->get_error_message() : '' );
		$u = get_user_by( 'email', $email );
		$this->assertNotFalse( $u );
		$this->users[] = $u->ID;
		$this->assertContains( 'ebcr_analista', $u->roles );

		$r = $this->ability( 'set-team-member', array( 'email' => $email, 'role' => 'ebcr_gestor' ) );
		$this->assertContains( 'ebcr_gestor', get_userdata( $u->ID )->roles );
		$this->assertNotContains( 'ebcr_analista', get_userdata( $u->ID )->roles );

		$r = $this->ability( 'set-team-member', array( 'email' => $email, 'role' => 'remover' ) );
		$this->assertIsArray( $r, is_wp_error( $r ) ? $r->get_error_message() : '' );
		$this->assertNotContains( 'ebcr_gestor', get_userdata( $u->ID )->roles );

		$this->assertInstanceOf( WP_Error::class, $this->ability( 'set-team-member', array( 'email' => 'x', 'role' => 'ebcr_analista' ) ) );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'set-team-member', array( 'email' => $email, 'role' => 'administrator' ) ), 'não promove a administrador' );
	}
	public function test_report_and_crm_abilities(): void {
		$manager = $this->make_user( Capabilities::ROLE_MANAGER );
		$client  = $this->make_user( Capabilities::ROLE_CLIENT );
		$this->make_submission( $client, 'enviada' );
		wp_set_current_user( $this->make_user( Capabilities::ROLE_CLIENT ) );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'get-report' ), 'cliente não vê relatórios' );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'list-contacts' ) );

		wp_set_current_user( $manager );
		$r = $this->ability( 'get-report', array( 'date_from' => '2000-01-01' ) );
		$this->assertIsArray( $r, is_wp_error( $r ) ? $r->get_error_message() : '' );
		foreach ( array( 'totals', 'by_fund', 'by_status', 'by_month', 'by_analyst', 'stage_durations' ) as $k ) {
			$this->assertArrayHasKey( $k, $r );
		}

		$list = $this->ability( 'list-contacts', array( 'search' => get_userdata( $client )->user_email ) );
		$this->assertIsArray( $list, is_wp_error( $list ) ? $list->get_error_message() : '' );
		$this->assertSame( 1, $list['total'] );
		$this->assertSame( 1, $list['items'][0]['open_submissions'] );
		$cid = $list['items'][0]['contact_id'];

		$stages = array_keys( \EBCR\Crm\Service::stages() );
		$u      = $this->ability( 'update-contact', array( 'contact_id' => $cid, 'stage' => $stages[1], 'tags' => 'soja, goiás', 'owner_id' => $manager, 'next_action' => 'Ligar' ) );
		$this->assertIsArray( $u, is_wp_error( $u ) ? $u->get_error_message() : '' );
		$this->assertSame( $stages[1], $u['stage'] );
		$this->assertSame( $manager, $u['owner_id'] );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'update-contact', array( 'contact_id' => $cid, 'stage' => 'inexistente' ) ) );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'update-contact', array( 'user_id' => 999999, 'stage' => $stages[0] ) ) );

		$a = $this->ability( 'add-activity', array( 'user_id' => $client, 'type' => 'tarefa', 'description' => 'Enviar proposta', 'due_at' => '2030-01-10 09:00' ) );
		$this->assertIsArray( $a, is_wp_error( $a ) ? $a->get_error_message() : '' );
		$this->assertInstanceOf( WP_Error::class, $this->ability( 'add-activity', array( 'contact_id' => $cid, 'type' => 'xyz', 'description' => 'x' ) ) );

		wp_set_current_user( $this->make_user( 'administrator' ) );
		$st = $this->ability( 'get-status' );
		$this->assertArrayHasKey( 'modules', $st );
		$this->assertArrayHasKey( 'team_2fa_mode', $st['modules'] );
		$r = $this->ability( 'run-tool', array( 'tool' => 'send_crm_reminders' ) );
		$this->assertFalse( is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_message() : '' );
	}
}
