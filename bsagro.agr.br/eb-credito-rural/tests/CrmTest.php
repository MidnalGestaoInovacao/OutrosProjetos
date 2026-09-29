<?php
/**
 * CRM: fichas sob demanda, estágios, atividades/tarefas, lembretes, CSV e permissões.
 *
 * @package EBCR
 */

use EBCR\Crm\Service;
use EBCR\Database\AuditLogRepository;
use EBCR\Database\CrmActivityRepository;
use EBCR\Database\CrmContactRepository;
use EBCR\Database\Db;
use EBCR\Roles\Capabilities;
use EBCR\Support\Options;

/**
 * Regras de negócio do CRM (sem HTTP) + rota REST do Kanban.
 */
final class CrmTest extends EBCR_TestCase {

	/**
	 * Fichas criadas (limpeza).
	 *
	 * @var int[]
	 */
	private $contacts = array();

	protected function setUp(): void {
		parent::setUp();
		add_filter( 'pre_wp_mail', '__return_true' );
		update_option(
			Options::OPTION,
			array(
				'min_interval_days'    => 0,
				'block_if_in_progress' => false,
				'min_fill_seconds'     => 0,
				'crm_task_reminders'   => true,
			),
			false
		);
		Options::flush();
	}

	protected function tearDown(): void {
		$contacts   = new CrmContactRepository();
		$activities = new CrmActivityRepository();
		foreach ( $this->contacts as $id ) {
			$activities->delete_for_contact( $id );
			$contacts->delete( $id );
		}
		remove_filter( 'pre_wp_mail', '__return_true' );
		parent::tearDown();
	}

	/**
	 * Ficha de um cliente (registrada para limpeza).
	 *
	 * @param int $user_id Usuário.
	 * @return array
	 */
	private function contact( $user_id ) {
		$c = Service::contact_for_user( $user_id );
		$this->assertIsArray( $c );
		$this->contacts[] = (int) $c['id'];
		return $c;
	}

	public function test_get_or_create_is_idempotent_and_prefills_from_registration(): void {
		$client = $this->make_user();
		update_user_meta( $client, 'ebcr_phone', '(11) 99999-8888' );
		update_user_meta( $client, 'ebcr_whatsapp', '11 98888-7777' );

		$c = $this->contact( $client );
		$this->assertSame( $client, (int) $c['user_id'] );
		$this->assertSame( '11999998888', $c['phone'] );
		$this->assertSame( '11988887777', $c['whatsapp'] );
		$this->assertSame( array_keys( Service::stages() )[0], $c['stage'] );
		$this->assertSame( 'site', $c['lead_source'] );

		$again = Service::contact_for_user( $client );
		$this->assertSame( (int) $c['id'], (int) $again['id'], 'get_or_create deve devolver a mesma ficha' );
		$this->assertNull( Service::contact_for_user( 999999999 ) );

		// sync_clients cria fichas para clientes sem ficha e não duplica.
		$other = $this->make_user();
		$this->assertGreaterThanOrEqual( 1, Service::sync_clients() );
		$oc = ( new CrmContactRepository() )->find_by_user( $other );
		$this->assertIsArray( $oc );
		$this->contacts[] = (int) $oc['id'];
		$this->assertSame( array(), ( new CrmContactRepository() )->missing_user_ids( array( $other, $client ) ) );
		$this->assertSame( (int) $oc['id'], (int) Service::contact_for_user( $other )['id'], 'sync não duplica fichas' );
	}

	public function test_change_stage_valid_invalid_and_forbidden(): void {
		$client  = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$c       = $this->contact( $client );

		$r = Service::change_stage( $analyst, (int) $c['id'], 'contato' );
		$this->assertIsArray( $r );
		$this->assertSame( 'contato', $r['stage'] );

		$bad = Service::change_stage( $analyst, (int) $c['id'], 'estagio_inexistente' );
		$this->assertInstanceOf( WP_Error::class, $bad );
		$this->assertSame( 'invalid_stage', $bad->get_error_code() );

		$forbidden = Service::change_stage( $client, (int) $c['id'], 'proposta' );
		$this->assertInstanceOf( WP_Error::class, $forbidden );
		$this->assertSame( 'forbidden', $forbidden->get_error_code() );

		$missing = Service::change_stage( $analyst, 999999999, 'contato' );
		$this->assertSame( 'not_found', $missing->get_error_code() );

		$this->assertSame( 'contato', ( new CrmContactRepository() )->find( (int) $c['id'] )['stage'] );
		list( $log ) = ( new AuditLogRepository() )->query(
			array(
				'action'      => 'crm_stage_changed',
				'object_type' => 'crm_contact',
				'object_id'   => (int) $c['id'],
			)
		);
		$this->assertNotEmpty( $log, 'mudança de estágio deve ir para o log de auditoria' );
		$this->assertSame( $analyst, (int) $log[0]['actor_id'] );

		// Também via update_contact (fallback sem JS), com validação de responsável/origem.
		$r = Service::update_contact(
			$analyst,
			(int) $c['id'],
			array(
				'stage'    => 'proposta',
				'owner_id' => $analyst,
				'tags'     => ' soja , Soja,, prioridade ',
			)
		);
		$this->assertSame( 'proposta', $r['stage'] );
		$this->assertSame( $analyst, (int) $r['owner_id'] );
		$this->assertSame( 'soja,prioridade', $r['tags'] );
		$this->assertSame( 'invalid_owner', Service::update_contact( $analyst, (int) $c['id'], array( 'owner_id' => $client ) )->get_error_code() );
		$this->assertSame( 'invalid_source', Service::update_contact( $analyst, (int) $c['id'], array( 'lead_source' => 'nao_existe' ) )->get_error_code() );
		$this->assertSame( 'invalid_date', Service::update_contact( $analyst, (int) $c['id'], array( 'next_action_at' => '31/12/2026' ) )->get_error_code() );
	}

	public function test_activity_and_task_create_and_complete(): void {
		$client  = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$manager = $this->make_user( Capabilities::ROLE_MANAGER );
		$c       = $this->contact( $client );
		$s       = $this->make_submission( $client, 'enviada' );

		$call = Service::add_activity(
			$analyst,
			(int) $c['id'],
			array(
				'type'          => 'ligacao',
				'description'   => 'Ligação de apresentação',
				'submission_id' => (int) $s['id'],
			)
		);
		$this->assertIsArray( $call );
		$this->assertSame( 'ligacao', $call['type'] );
		$this->assertSame( (int) $s['id'], (int) $call['submission_id'] );
		$this->assertSame( $analyst, (int) $call['created_by'] );
		$this->assertNull( $call['due_at'] );

		$task = Service::add_activity(
			$analyst,
			(int) $c['id'],
			array(
				'type'        => Service::TYPE_TASK,
				'description' => 'Enviar proposta',
				'due_at'      => '2030-01-15T10:30',
				'assignee_id' => $manager,
			)
		);
		$this->assertIsArray( $task );
		$this->assertSame( Service::TYPE_TASK, $task['type'] );
		$this->assertSame( $manager, (int) $task['created_by'], 'responsável da tarefa fica em created_by' );
		$this->assertSame( get_gmt_from_date( '2030-01-15 10:30:00', 'Y-m-d H:i:s' ), $task['due_at'] );
		$this->assertNull( $task['done_at'] );

		$open = ( new CrmActivityRepository() )->open_tasks_for_contact( (int) $c['id'] );
		$this->assertCount( 1, $open );

		$done = Service::complete_task( $analyst, (int) $task['id'] );
		$this->assertNotEmpty( $done['done_at'] );
		$this->assertCount( 0, ( new CrmActivityRepository() )->open_tasks_for_contact( (int) $c['id'] ) );
		$this->assertCount( 2, ( new CrmActivityRepository() )->for_contact( (int) $c['id'] ) );

		// Validações.
		$this->assertSame( 'invalid_type', Service::add_activity( $analyst, (int) $c['id'], array( 'type' => 'x', 'description' => 'a' ) )->get_error_code() );
		$this->assertSame( 'empty_description', Service::add_activity( $analyst, (int) $c['id'], array( 'type' => 'nota', 'description' => '   ' ) )->get_error_code() );
		$this->assertSame( 'invalid_date', Service::add_activity( $analyst, (int) $c['id'], array( 'type' => Service::TYPE_TASK, 'description' => 'a', 'due_at' => 'amanhã' ) )->get_error_code() );
		$other_client = $this->make_user();
		$other_sub    = $this->make_submission( $other_client, 'enviada' );
		$this->assertSame( 'invalid_submission', Service::add_activity( $analyst, (int) $c['id'], array( 'type' => 'nota', 'description' => 'a', 'submission_id' => (int) $other_sub['id'] ) )->get_error_code() );
		$this->assertSame( 'forbidden', Service::add_activity( $client, (int) $c['id'], array( 'type' => 'nota', 'description' => 'a' ) )->get_error_code() );
		$this->assertSame( 'forbidden', Service::complete_task( $client, (int) $task['id'] )->get_error_code() );
	}

	public function test_reminders_select_due_tasks_and_send_once_per_day(): void {
		global $wpdb;
		$client  = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$c       = $this->contact( $client );
		$today   = wp_date( 'Y-m-d' );

		$due_today = Service::add_activity( $analyst, (int) $c['id'], array( 'type' => Service::TYPE_TASK, 'description' => 'Vence hoje', 'due_at' => $today . 'T08:00' ) );
		$overdue   = Service::add_activity( $analyst, (int) $c['id'], array( 'type' => Service::TYPE_TASK, 'description' => 'Já venceu', 'due_at' => '2020-01-01T09:00' ) );
		$future    = Service::add_activity( $analyst, (int) $c['id'], array( 'type' => Service::TYPE_TASK, 'description' => 'Futuro', 'due_at' => '2099-01-01T09:00' ) );
		$finished  = Service::add_activity( $analyst, (int) $c['id'], array( 'type' => Service::TYPE_TASK, 'description' => 'Concluída', 'due_at' => $today . 'T07:00' ) );
		Service::complete_task( $analyst, (int) $finished['id'] );
		// Tarefa sem responsável explícito: usa o responsável da ficha.
		$other   = $this->make_user();
		$oc      = $this->contact( $other );
		$manager = $this->make_user( Capabilities::ROLE_MANAGER );
		Service::update_contact( $manager, (int) $oc['id'], array( 'owner_id' => $manager ) );
		$orphan = Service::add_activity( $manager, (int) $oc['id'], array( 'type' => Service::TYPE_TASK, 'description' => 'Sem responsável', 'due_at' => '2020-02-02T09:00' ) );
		$wpdb->update( Db::table( 'crm_activities' ), array( 'created_by' => null ), array( 'id' => (int) $orphan['id'] ) );

		$groups = Service::tasks_to_remind( $today );
		$this->assertArrayHasKey( $analyst, $groups );
		$ids = array_map( 'intval', array_column( $groups[ $analyst ], 'id' ) );
		$this->assertContains( (int) $due_today['id'], $ids );
		$this->assertContains( (int) $overdue['id'], $ids );
		$this->assertNotContains( (int) $future['id'], $ids );
		$this->assertNotContains( (int) $finished['id'], $ids );
		$this->assertArrayHasKey( $manager, $groups, 'tarefa sem created_by cai para o responsável da ficha' );
		$this->assertContains( (int) $orphan['id'], array_map( 'intval', array_column( $groups[ $manager ], 'id' ) ) );

		$sent = Service::send_reminders( $today );
		$this->assertArrayHasKey( $analyst, $sent );
		$this->assertNotEmpty( $sent[ $analyst ] );
		$email = get_userdata( $analyst )->user_email;
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Db::table( 'mail_queue' ) . ' WHERE id = %d', (int) $sent[ $analyst ][0] ), ARRAY_A );
		$this->assertIsArray( $row );
		$this->assertSame( 'crm_task_reminder', $row['event'] );
		$this->assertSame( $email, $row['recipient'] );
		$this->assertStringContainsString( 'Vence hoje', $row['body'] );
		$this->assertStringContainsString( 'venceu', $row['body'] );
		$this->assertStringNotContainsString( 'Futuro', $row['body'] );
		$this->assertSame( $today, get_user_meta( $analyst, Service::REMINDER_META, true ) );

		$again = Service::send_reminders( $today );
		$this->assertArrayNotHasKey( $analyst, $again, 'só um lembrete por dia por responsável' );
		$this->assertArrayNotHasKey( $manager, $again );

		// Desligado nas configurações: nada é enviado.
		update_option( Options::OPTION, array( 'crm_task_reminders' => false ), false );
		Options::flush();
		delete_user_meta( $analyst, Service::REMINDER_META );
		$this->assertSame( array(), Service::send_reminders( $today ) );
	}

	public function test_csv_export_has_header_and_rows(): void {
		$client  = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$c       = $this->contact( $client );
		$tag     = 'csv' . wp_generate_password( 8, false, false );
		$this->make_submission( $client, 'enviada' );
		Service::update_contact(
			$analyst,
			(int) $c['id'],
			array(
				'tags'        => $tag,
				'owner_id'    => $analyst,
				'next_action' => '=SOMA(1;2)',
			)
		);
		$this->contact( $this->make_user() ); // fora do filtro.

		$rows = Service::export_rows( array( 'tag' => $tag ) );
		$this->assertCount( 2, $rows, 'cabeçalho + 1 linha' );
		$this->assertSame( 'id', $rows[0][0] );
		$this->assertSame( (int) $c['id'], $rows[1][0] );
		$this->assertSame( get_userdata( $client )->user_email, $rows[1][2] );
		$this->assertSame( 1, $rows[1][11], 'solicitações abertas' );

		$csv = Service::csv_string( $rows );
		$this->assertStringStartsWith( "\xEF\xBB\xBF", $csv );
		$lines = explode( "\n", trim( substr( $csv, 3 ) ) );
		$this->assertCount( 2, $lines );
		$this->assertSame( 'id;cliente;email;telefone;whatsapp;origem;tags;estagio;responsavel;proxima_acao;proxima_acao_em;solicitacoes_abertas;ultima_atividade;criado_em;atualizado_em', $lines[0] );
		$this->assertStringContainsString( get_userdata( $client )->user_email, $lines[1] );
		$this->assertStringContainsString( "'=SOMA(1;2)", $lines[1], 'proteção contra fórmulas' );
		$this->assertSame( $csv, Service::export_csv( array( 'tag' => $tag ) ) );
	}

	public function test_list_query_filters_and_bulk_assign(): void {
		$client  = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		update_user_meta( $client, 'ebcr_phone', '62911112222' );
		$c = $this->contact( $client );
		$this->make_submission( $client, 'enviada' );
		$this->make_submission( $client, 'reprovada' );
		$this->make_submission( $client, 'rascunho' );
		Service::add_activity( $analyst, (int) $c['id'], array( 'type' => 'nota', 'description' => 'x' ) );

		$repo = new CrmContactRepository();
		$open = Service::open_statuses();
		list( $items, $total ) = $repo->query( array( 'search' => get_userdata( $client )->user_email, 'open_statuses' => $open ) );
		$this->assertSame( 1, $total );
		$this->assertSame( (int) $c['id'], (int) $items[0]['id'] );
		$this->assertSame( 1, (int) $items[0]['open_submissions'], 'só a enviada conta como aberta' );
		$this->assertNotEmpty( $items[0]['last_activity_at'] );

		list( $items ) = $repo->query( array( 'search' => '(62) 91111-2222' ) );
		$this->assertContains( (int) $c['id'], array_map( 'intval', array_column( $items, 'id' ) ), 'busca por telefone ignora máscara' );

		$this->assertSame( 1, Service::bulk_assign( $analyst, array( (int) $c['id'] ), $analyst ) );
		list( $items, $total ) = $repo->query( array( 'owner_id' => $analyst ) );
		$this->assertSame( 1, $total );
		$this->assertSame( $analyst, (int) $items[0]['owner_id'] );
		$this->assertSame( 'invalid_owner', Service::bulk_assign( $analyst, array( (int) $c['id'] ), $client )->get_error_code() );
		$this->assertSame( 'forbidden', Service::bulk_assign( $client, array( (int) $c['id'] ), $analyst )->get_error_code() );
		$this->assertSame( 1, Service::bulk_assign( $analyst, array( (int) $c['id'] ), 0 ) );
		$this->assertNull( $repo->find( (int) $c['id'] )['owner_id'] );
	}

	public function test_permissions_service_and_rest_stage_route(): void {
		$client  = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$c       = $this->contact( $client );
		$route   = '/ebcr/v1/crm/contacts/' . (int) $c['id'] . '/stage';

		$this->assertFalse( Service::can_manage( $client ) );
		$this->assertTrue( Service::can_manage( $analyst ) );
		$this->assertFalse( Service::can_export( $analyst ) );
		$this->assertTrue( Service::can_export( $this->make_user( Capabilities::ROLE_MANAGER ) ) );
		$this->assertSame( 'forbidden', Service::update_contact( $client, (int) $c['id'], array( 'notes' => 'x' ) )->get_error_code() );

		wp_set_current_user( 0 );
		$req = new WP_REST_Request( 'POST', $route );
		$req->set_param( 'stage', 'contato' );
		$this->assertSame( 401, rest_do_request( $req )->get_status() );

		wp_set_current_user( $client );
		$req = new WP_REST_Request( 'POST', $route );
		$req->set_param( 'stage', 'contato' );
		$this->assertSame( 403, rest_do_request( $req )->get_status() );
		$this->assertSame( 'novo', ( new CrmContactRepository() )->find( (int) $c['id'] )['stage'] );

		wp_set_current_user( $analyst );
		$req = new WP_REST_Request( 'POST', $route );
		$req->set_param( 'stage', 'contato' );
		$res = rest_do_request( $req );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 'contato', $res->get_data()['stage'] );
		$this->assertSame( 'contato', ( new CrmContactRepository() )->find( (int) $c['id'] )['stage'] );

		$req = new WP_REST_Request( 'POST', $route );
		$req->set_param( 'stage', 'nao_existe' );
		$this->assertSame( 400, rest_do_request( $req )->get_status() );

		$req = new WP_REST_Request( 'POST', '/ebcr/v1/crm/contacts/999999999/stage' );
		$req->set_param( 'stage', 'contato' );
		$this->assertSame( 404, rest_do_request( $req )->get_status() );
	}
}
