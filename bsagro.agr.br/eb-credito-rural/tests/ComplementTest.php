<?php
/**
 * 1.3.1 — Bens e garantias opcionais: orientação ao cliente, complemento depois do envio (status permitidos,
 * validação no servidor, pedidos de documento, histórico, auditoria e aviso à equipe), lembrete único e pasta privada.
 *
 * @package EBCR
 */

use EBCR\Abilities\Abilities;
use EBCR\Admin\Settings\Settings;
use EBCR\Database\Db;
use EBCR\Database\DocumentRequestRepository;
use EBCR\Database\GuaranteeRepository;
use EBCR\Database\PropertyRepository;
use EBCR\Database\StatusHistoryRepository;
use EBCR\Database\SubmissionDataRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Files\FileGuard;
use EBCR\Files\UploadHandler;
use EBCR\Forms\Complement;
use EBCR\Forms\Wizard;
use EBCR\Frontend\Portal;
use EBCR\Roles\Capabilities;
use EBCR\Support\Options;
use EBCR\Support\View;

/**
 * Complemento de bens e garantias.
 */
final class ComplementTest extends EBCR_TestCase {

	const CAR = 'GO-5218805-1A2B.3C4D.5E6F.7A8B.9C0D.1E2F.3A4B.5C6D';

	/**
	 * Pastas criadas nos testes.
	 *
	 * @var string[]
	 */
	private $dirs = array();

	protected function setUp(): void {
		parent::setUp();
		Options::update(
			array(
				'guarantees_mode' => 'optional',
				'assets_mode'     => 'optional',
				'admin_emails'    => 'credito@example.com',
			)
		);
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . Db::table( 'mail_queue' ) . " WHERE event IN ('complement_added','complement_reminder')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	}

	protected function tearDown(): void {
		foreach ( array_reverse( $this->dirs ) as $d ) {
			foreach ( array( '.htaccess', 'index.php', 'web.config' ) as $f ) {
				if ( is_file( $d . '/' . $f ) ) {
					unlink( $d . '/' . $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				}
			}
			foreach ( (array) glob( $d . '/sentinel-*' ) as $f ) {
				if ( $f && is_file( $f ) ) {
					unlink( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				}
			}
			if ( is_dir( $d ) ) {
				rmdir( $d ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			}
		}
		parent::tearDown();
	}

	/**
	 * Solicitação enviada (há $hours horas) sem imóveis nem garantias.
	 *
	 * @param int    $uid    Cliente.
	 * @param string $status Status.
	 * @param int    $hours  Horas desde o envio.
	 * @param array  $extra  Campos extras.
	 * @return array
	 */
	private function sent( $uid, $status = 'pre_analise', $hours = 2, array $extra = array() ) {
		$s    = $this->make_submission(
			$uid,
			$status,
			array_merge(
				array(
					'submitted_at' => gmdate( 'Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS ),
					'protocol'     => 'T-' . wp_generate_password( 8, false, false ),
				),
				$extra
			)
		);
		$data = new SubmissionDataRepository();
		$data->save( (int) $s['id'], 'identificacao', array( 'person_type' => 'PF' ) );
		$data->save(
			(int) $s['id'],
			'imoveis',
			array(
				'_incomplete'    => false,
				'count'          => 0,
				'possui_imoveis' => 'nao',
			)
		);
		$data->save(
			(int) $s['id'],
			'garantias',
			array(
				'_incomplete'      => false,
				'count'            => 0,
				'oferece_garantia' => 'nao',
				'sem_garantia'     => true,
			)
		);
		return ( new SubmissionRepository() )->find( (int) $s['id'] );
	}

	/**
	 * Imóvel válido (entrada do formulário).
	 *
	 * @param array $over Sobrescritas.
	 * @return array
	 */
	private function prop( array $over = array() ) {
		return array_merge(
			array(
				'id'                  => '',
				'name'                => 'Fazenda Boa Vista',
				'city'                => 'Rio Verde',
				'uf'                  => 'GO',
				'registration_number' => '12345',
				'registry_office'     => 'CRI de Rio Verde',
				'total_area'          => '350',
				'usable_area'         => '300',
				'car_code'            => self::CAR,
				'ccir'                => '',
				'nirf'                => '',
				'sigef'               => '',
				'tenure'              => 'propria',
				'lease_end'           => '',
			),
			$over
		);
	}

	/**
	 * Garantia válida (entrada do formulário).
	 *
	 * @param array $over Sobrescritas.
	 * @return array
	 */
	private function gar( array $over = array() ) {
		return array_merge(
			array(
				'id'             => '',
				'type'           => 'penhor_agricola',
				'description'    => 'Penhor da safra de soja 2026/27',
				'declared_value' => '150000',
				'property_id'    => '',
			),
			$over
		);
	}

	/**
	 * Linhas da fila de e-mails de um evento.
	 *
	 * @param string $event Evento.
	 * @return array
	 */
	private function mails( $event ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Db::table( 'mail_queue' ) . ' WHERE event = %s ORDER BY id ASC', $event ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Chama um método privado do Portal (renderização).
	 *
	 * @param string $method Método.
	 * @param array  $args   Argumentos.
	 * @return string
	 */
	private function portal( $method, array $args ) {
		$m = new ReflectionMethod( Portal::class, $method );
		$m->setAccessible( true );
		return (string) $m->invokeArgs( new Portal(), $args );
	}

	public function test_settings_defaults_and_status_gate(): void {
		$this->assertSame( array( 'enviada', 'pre_analise', 'pendencia_documental', 'analise_credito', 'comite', 'formalizacao' ), Complement::statuses() );
		$this->assertArrayNotHasKey( 'rascunho', Complement::status_options() );
		$this->assertSame( 3, Options::int( 'complement_reminder_days' ) );
		$this->assertStringContainsString( 'não é obrigatório', Complement::notice( 'garantias' ) );
		$this->assertStringContainsString( 'Área do Cliente', Complement::notice( 'imoveis' ) );
		Options::update( array( 'guarantees_optional_notice' => 'Texto próprio.' ) );
		$this->assertSame( 'Texto próprio.', Complement::notice( 'garantias' ) );

		$uid = $this->make_user();
		foreach ( array( 'enviada', 'pre_analise', 'pendencia_documental', 'analise_credito', 'comite', 'formalizacao' ) as $st ) {
			$this->assertTrue( Complement::can_complement( $uid, $this->sent( $uid, $st ) ), $st );
		}
		foreach ( array( 'rascunho', 'aprovada', 'reprovada', 'cancelada', 'concluida' ) as $st ) {
			$this->assertFalse( Complement::can_complement( $uid, $this->make_submission( $uid, $st ) ), $st );
		}
		$s = $this->sent( $uid, 'analise_credito' );
		$this->assertFalse( Complement::can_complement( $this->make_user(), $s ), 'só o dono' );
		Options::update( array( 'complement_statuses' => array( 'enviada' ) ) );
		$this->assertFalse( Complement::can_complement( $uid, $s ), 'status fora da configuração' );
		Options::update( array( 'complement_statuses' => array( 'analise_credito' ) ) );
		$this->assertTrue( Complement::can_complement( $uid, $s, 'garantias' ) );
		Options::update( array( 'guarantees_mode' => 'required' ) );
		$this->assertFalse( Complement::can_complement( $uid, $s, 'garantias' ), 'só no modo opcional' );
		$this->assertTrue( Complement::can_complement( $uid, $s, 'imoveis' ) );

		// Bloqueado: nada é gravado e o acesso negado fica auditado.
		Options::update( array( 'complement_statuses' => array( 'enviada' ) ) );
		list( $ok, $errors ) = Complement::save( $uid, $s, 'imoveis', array( 'imoveis' => array( $this->prop() ) ) );
		$this->assertFalse( $ok );
		$this->assertArrayHasKey( '_', $errors );
		$this->assertSame( array(), ( new PropertyRepository() )->for_submission( (int) $s['id'] ) );
		list( $ok ) = Complement::save( $uid, $s, 'outra', array() );
		$this->assertFalse( $ok, 'parte inválida' );
	}

	public function test_server_side_validation_and_sanitization(): void {
		$uid   = $this->make_user();
		$s     = $this->sent( $uid );
		$other = $this->sent( $this->make_user() );
		list( $ok, $errors ) = Complement::save( $uid, $s, 'imoveis', array( 'imoveis' => array( $this->prop( array( 'car_code' => 'x', 'uf' => 'ZZ' ) ) ) ) );
		$this->assertFalse( $ok );
		$this->assertArrayHasKey( 'imoveis.0.car_code', $errors );
		$this->assertArrayHasKey( 'imoveis.0.uf', $errors );
		list( $ok, $errors ) = Complement::save( $uid, $s, 'imoveis', array( 'imoveis' => array( array( 'name' => '' ) ) ) );
		$this->assertFalse( $ok );
		$this->assertSame( 'Informe pelo menos um imóvel rural.', $errors['imoveis'] );
		$this->assertSame( array(), ( new PropertyRepository() )->for_submission( (int) $s['id'] ), 'nada gravado com erro' );

		// Garantia real vinculada a imóvel de OUTRA solicitação.
		( new PropertyRepository() )->replace_all( (int) $other['id'], array( $this->prop() ) );
		$foreign             = ( new PropertyRepository() )->for_submission( (int) $other['id'] )[0];
		list( $ok, $errors ) = Complement::save( $uid, $s, 'garantias', array( 'garantias' => array( $this->gar( array( 'type' => 'hipoteca', 'property_id' => (string) $foreign['id'] ) ) ) ) );
		$this->assertFalse( $ok );
		$this->assertArrayHasKey( 'garantias.0.property_id', $errors );

		// Sanitização igual à do formulário.
		list( $ok ) = Complement::save( $uid, $s, 'imoveis', array( 'imoveis' => array( $this->prop( array( 'name' => '<b>Fazenda</b> <script>x</script>Boa' ) ) ) ) );
		$this->assertTrue( $ok );
		$this->assertSame( 'Fazenda Boa', ( new PropertyRepository() )->for_submission( (int) $s['id'] )[0]['name'] );

		// Reenviar sem alteração: nada a salvar.
		$p                   = ( new PropertyRepository() )->for_submission( (int) $s['id'] )[0];
		list( $ok, $errors ) = Complement::save( $uid, $s, 'imoveis', array( 'imoveis' => array( $this->prop( array( 'id' => (string) $p['id'], 'name' => 'Fazenda Boa' ) ) ) ) );
		$this->assertFalse( $ok );
		$this->assertArrayHasKey( '_', $errors );
	}

	public function test_complement_flow_requests_history_audit_and_notification(): void {
		$uid     = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$s       = $this->sent( $uid, 'pre_analise', 2, array( 'assigned_to' => $analyst ) );
		$this->assertSame( array( 'imoveis', 'garantias' ), Complement::missing( $s ) );

		// 1) Imóvel arrendado: matrícula, CCIR/ITR, CAR e contrato de arrendamento passam a ser exigidos.
		list( $ok, $errors, $r ) = Complement::save(
			$uid,
			$s,
			'imoveis',
			array(
				'imoveis' => array(
					$this->prop(
						array(
							'tenure'    => 'arrendada',
							'lease_end' => gmdate( 'Y-m-d', strtotime( '+5 years' ) ),
						)
					),
				),
			)
		);
		$this->assertTrue( $ok, wp_json_encode( $errors ) );
		$this->assertSame( 1, $r['added'] );
		$this->assertSame( 'pre_analise', $r['submission']['status'], 'o status não muda' );
		$this->assertNotEmpty( $r['submission']['complemented_at'] );
		$prop  = ( new PropertyRepository() )->for_submission( (int) $s['id'] )[0];
		$types = wp_list_pluck( $r['requests'], 'doc_type' );
		sort( $types );
		$this->assertSame( array( 'ccir_itr', 'contrato_arrendamento', 'matricula', 'recibo_car' ), $types, 'SIGEF é condicional: não é pedido' );
		foreach ( $r['requests'] as $req ) {
			$this->assertSame( 'p:' . $prop['id'], $req['ref_key'] );
			$this->assertSame( Complement::ORIGIN, $req['origin'] );
			$this->assertSame( '0', (string) $req['requested_by'] );
		}
		$this->assertStringContainsString( 'Incluído pelo cliente', Complement::item_note( $prop, $r['submission'] ) );
		$this->assertTrue( Complement::item_info( $prop, $r['submission'] )['added_after_submission'] );

		// Histórico (sem mudar o status), auditoria e aviso à equipe (administração + analista atribuído).
		$hist = ( new StatusHistoryRepository() )->for_submission( (int) $s['id'] );
		$last = end( $hist );
		$this->assertSame( $last['from_status'], $last['to_status'] );
		$this->assertStringContainsString( 'Cliente complementou', $last['comment_internal'] );
		$this->assertSame( 'Atualização da solicitação', \EBCR\Domain\Status::history_title( $last ) );
		global $wpdb;
		$audit = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Db::table( 'audit_log' ) . ' WHERE action = %s AND object_id = %s ORDER BY id DESC LIMIT 1', 'complement_added', $s['public_id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$this->assertNotEmpty( $audit );
		$this->assertSame( 1, json_decode( $audit['meta'], true )['added'] );
		$mails = $this->mails( 'complement_added' );
		$to    = wp_list_pluck( $mails, 'recipient' );
		$this->assertContains( 'credito@example.com', $to );
		$this->assertContains( get_userdata( $analyst )->user_email, $to );
		$this->assertStringContainsString( 'Fazenda Boa Vista', $mails[0]['body'] );
		$this->assertStringContainsString( 'Matrícula atualizada do imóvel', $mails[0]['body'] );

		// Documento enviado para o pedido: fica com a referência do imóvel e satisfaz o slot.
		$req  = null;
		foreach ( $r['requests'] as $x ) {
			if ( 'matricula' === $x['doc_type'] ) {
				$req = $x;
			}
		}
		$path = $this->tmp_file( $this->pdf_bytes( 7 ) );
		$doc  = ( new UploadHandler() )->handle( $uid, $r['submission'], $this->files_item( $path, 'matricula.pdf' ), '', '', (int) $req['id'] );
		$this->assertIsArray( $doc, is_wp_error( $doc ) ? $doc->get_error_message() : '' );
		$this->assertSame( 'p:' . $prop['id'], $doc['ref_key'] );
		foreach ( ( new Wizard() )->document_slots( $r['submission'] )['slots'] as $slot ) {
			if ( 'matricula' === $slot['type'] ) {
				$this->assertTrue( $slot['satisfied'] );
			}
		}

		// 2) Garantia real vinculada ao imóvel: laudo (recomendado) pedido; nada duplicado.
		$s2                      = ( new SubmissionRepository() )->find( (int) $s['id'] );
		list( $ok, $errors, $r ) = Complement::save( $uid, $s2, 'garantias', array( 'garantias' => array( $this->gar( array( 'type' => 'hipoteca', 'property_id' => (string) $prop['id'] ) ) ) ) );
		$this->assertTrue( $ok, wp_json_encode( $errors ) );
		$this->assertSame( array( 'laudo_avaliacao' ), wp_list_pluck( $r['requests'], 'doc_type' ) );
		$this->assertStringContainsString( 'Recomendado', $r['requests'][0]['note'] );
		$this->assertSame( array(), Complement::missing( $r['submission'] ) );
		$open_before = count( ( new DocumentRequestRepository() )->for_submission( (int) $s['id'], true ) );

		// 3) Edição de uma garantia existente + inclusão de outra: mantém o ID, a data de inclusão e os campos da equipe;
		// linha existente omitida no envio não é removida.
		$g = ( new GuaranteeRepository() )->for_submission( (int) $s['id'] )[0];
		( new GuaranteeRepository() )->update(
			(int) $g['id'],
			array(
				'appraised_value' => 200000,
				'created_at'      => gmdate( 'Y-m-d H:i:s', time() - 600 ),
			)
		);
		$s3                      = ( new SubmissionRepository() )->find( (int) $s['id'] );
		list( $ok, $errors, $r ) = Complement::save(
			$uid,
			$s3,
			'garantias',
			array(
				'garantias' => array(
					$this->gar(
						array(
							'id'             => (string) $g['id'],
							'type'           => 'hipoteca',
							'property_id'    => (string) $prop['id'],
							'declared_value' => '180000',
						)
					),
					$this->gar(),
				),
			)
		);
		$this->assertTrue( $ok, wp_json_encode( $errors ) );
		$this->assertSame( 1, $r['added'] );
		$this->assertSame( 1, $r['updated'] );
		$after = ( new GuaranteeRepository() )->for_submission( (int) $s['id'] );
		$this->assertCount( 2, $after );
		$this->assertSame( (int) $g['id'], (int) $after[0]['id'] );
		$this->assertSame( '180000.00', $after[0]['declared_value'] );
		$this->assertSame( '200000.00', $after[0]['appraised_value'], 'campo da equipe preservado' );
		$this->assertNotEmpty( $after[0]['updated_at'] );
		$this->assertSame( $open_before, count( ( new DocumentRequestRepository() )->for_submission( (int) $s['id'], true ) ), 'sem pedidos duplicados' );
		list( $ok, , $r ) = Complement::save( $uid, $r['submission'], 'garantias', array( 'garantias' => array( $this->gar( array( 'type' => 'cpr' ) ) ) ) );
		$this->assertTrue( $ok );
		$this->assertCount( 3, ( new GuaranteeRepository() )->for_submission( (int) $s['id'] ), 'itens omitidos são mantidos' );

		// Abilities: get-submission traz complemented_at e as datas de cada item.
		wp_set_current_user( $this->make_user( 'administrator' ) );
		$sub = Abilities::get_submission( array( 'id' => $s['public_id'] ) );
		$this->assertNotEmpty( $sub['complement']['complemented_at'] );
		$this->assertNotEmpty( $sub['submission']['complemented_at'] );
		$this->assertCount( 1, $sub['properties']['items'] );
		$this->assertTrue( $sub['properties']['items'][0]['added_after_submission'] );
		$this->assertArrayHasKey( 'created_at', $sub['guarantees']['items'][0] );
		$this->assertArrayHasKey( 'updated_at', $sub['guarantees']['items'][0] );
	}

	public function test_reminder_is_sent_once_and_only_when_allowed(): void {
		$uid     = $this->make_user();
		$due     = $this->sent( $uid, 'enviada', 4 * 24 );
		$recent  = $this->sent( $uid, 'enviada', 24 );
		$old     = $this->sent( $uid, 'enviada', 40 * 24 );
		$blocked = $this->sent( $uid, 'aprovada', 4 * 24 );
		$full    = $this->sent( $uid, 'analise_credito', 5 * 24 );
		( new PropertyRepository() )->replace_all( (int) $full['id'], array( $this->prop() ) );
		( new GuaranteeRepository() )->replace_all( (int) $full['id'], array( $this->gar() ) );
		$half = $this->sent( $uid, 'comite', 5 * 24 );
		( new PropertyRepository() )->replace_all( (int) $half['id'], array( $this->prop() ) );

		$this->assertSame( 2, Complement::send_reminders(), 'devida + sem garantias' );
		$mails = $this->mails( 'complement_reminder' );
		$this->assertCount( 2, $mails );
		$this->assertSame( get_userdata( $uid )->user_email, $mails[0]['recipient'] );
		$subjects = implode( ' | ', wp_list_pluck( $mails, 'subject' ) );
		$this->assertStringContainsString( $due['protocol'], $subjects );
		$this->assertStringContainsString( $half['protocol'], $subjects );
		$this->assertStringContainsString( 'ebcr-bens', $mails[0]['body'] );
		$repo = new SubmissionRepository();
		$this->assertNotEmpty( $repo->find( (int) $due['id'] )['complement_reminded_at'] );
		$this->assertNull( $repo->find( (int) $recent['id'] )['complement_reminded_at'] );
		$this->assertNull( $repo->find( (int) $old['id'] )['complement_reminded_at'], 'fora da janela de 30 dias' );
		$this->assertNull( $repo->find( (int) $blocked['id'] )['complement_reminded_at'] );
		$this->assertNull( $repo->find( (int) $full['id'] )['complement_reminded_at'] );

		$this->assertSame( 0, Complement::send_reminders(), 'uma vez por solicitação' );
		$this->assertCount( 2, $this->mails( 'complement_reminder' ) );
		Options::update( array( 'complement_reminder_days' => 0 ) );
		$this->sent( $uid, 'enviada', 4 * 24 );
		$this->assertSame( 0, Complement::send_reminders(), '0 desliga' );
		$daily = \EBCR\Cron\Scheduler::daily();
		$this->assertArrayHasKey( 'complement', $daily );
	}

	public function test_portal_notices_card_banner_and_complement_form(): void {
		$uid = $this->make_user();
		wp_set_current_user( $uid );
		$s = $this->sent( $uid );

		// Painel: aviso de solicitação sem bens/garantias e chamada para continuar o rascunho.
		$draft = $this->make_submission( $uid );
		$html  = $this->portal( 'render_dashboard', array( $uid, true ) );
		$this->assertStringContainsString( 'está sem bens e garantias informados — completar agora', $html );
		$this->assertStringContainsString( 'role="note"', $html );
		$this->assertStringContainsString( 'Continuar preenchimento', $html );
		$this->assertStringContainsString( 'Você tem uma solicitação em preenchimento', $html );
		unset( $draft );

		// Tela da solicitação: quadro "Bens e garantias" com orientação e botões.
		$_GET = array( 'id' => $s['public_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$html = $this->portal( 'render_submission', array( $uid ) );
		$this->assertStringContainsString( 'id="ebcr-bens"', $html );
		$this->assertStringContainsString( 'Completar agora', $html );
		$this->assertStringContainsString( esc_html( Complement::notice( 'garantias' ) ), $html );

		// Formulário de complemento: mesmo template da etapa, sem a pergunta sim/não, com nonce próprio.
		$_GET = array( // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'id'    => $s['public_id'],
			'parte' => 'imoveis',
		);
		$html = $this->portal( 'render_complement', array( $uid ) );
		$this->assertStringContainsString( 'value="ebcr_complement"', $html );
		$this->assertStringContainsString( 'data-ebcr-complement="imoveis"', $html );
		$this->assertStringContainsString( 'name="ebcr_nonce"', $html );
		$this->assertStringNotContainsString( 'possui_imoveis', $html );
		$this->assertStringNotContainsString( 'data-ebcr-step', $html, 'sem autosave do formulário' );

		// Status que não aceita complemento: sem formulário.
		( new SubmissionRepository() )->update( (int) $s['id'], array( 'status' => 'aprovada' ) );
		$html = $this->portal( 'render_complement', array( $uid ) );
		$this->assertStringNotContainsString( 'ebcr_complement', $html );
		$_GET = array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Formulário (rascunho) no modo opcional: quadro de orientação nas etapas 2 e 5 e no resumo (etapa 7).
		$d      = $this->make_submission( $uid );
		$wizard = new Wizard();
		$saved  = $wizard->saved( $d );
		foreach ( array( 2 => 'imoveis', 5 => 'garantias' ) as $step => $part ) {
			$html = View::render(
				'wizard/step-' . $step,
				array(
					's'       => $d,
					'data'    => array(),
					'errors'  => array(),
					'saved'   => $saved,
					'add'     => '',
					'wizard'  => $wizard,
					'context' => $wizard->rules_context( $d, $saved ),
				)
			);
			$this->assertStringContainsString( 'class="ebcr-notice" role="note"', $html, $part );
			$this->assertStringContainsString( esc_html( Complement::notice( $part ) ), $html );
		}
		$html = View::render(
			'wizard/step-7',
			array(
				's'       => $d,
				'errors'  => array(),
				'wizard'  => $wizard,
				'captcha' => \EBCR\Security\MathCaptcha::provider(),
			)
		);
		$this->assertStringContainsString( 'role="note"', $html );
		$this->assertStringContainsString( 'Informar garantias agora', $html );
		// Modo obrigatório: sem o quadro.
		Options::update(
			array(
				'guarantees_mode' => 'required',
				'assets_mode'     => 'required',
			)
		);
		$html = View::render(
			'wizard/step-2',
			array(
				's'       => $d,
				'data'    => array(),
				'errors'  => array(),
				'saved'   => $saved,
				'add'     => '',
				'wizard'  => $wizard,
				'context' => $wizard->rules_context( $d, $saved ),
			)
		);
		$this->assertStringNotContainsString( 'ebcr-notice', $html );
	}

	public function test_admin_post_handler_saves_and_redirects(): void {
		$uid = $this->make_user();
		wp_set_current_user( $uid );
		$s        = $this->sent( $uid );
		$redirect = static function ( $location ) {
			throw new RuntimeException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- teste.
		};
		add_filter( 'wp_redirect', $redirect );
		$post = static function ( array $extra ) use ( $s ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST = array_merge(
				array(
					'action'     => 'ebcr_complement',
					'id'         => $s['public_id'],
					'parte'      => 'garantias',
					'ebcr_nonce' => wp_create_nonce( 'ebcr_complement' ),
				),
				$extra
			);
		};
		$run = function () {
			try {
				( new Portal() )->handle_complement();
				$this->fail( 'deveria redirecionar' );
			} catch ( RuntimeException $e ) {
				return $e->getMessage();
			}
			return '';
		};
		// Erro de validação: volta ao formulário com os valores guardados.
		$post( array( 'garantias' => array( $this->gar( array( 'declared_value' => '' ) ) ) ) );
		$url = $run();
		$this->assertStringContainsString( 'ebcr_view=complementar', $url );
		$this->assertStringContainsString( 'ebcr_msg=step_errors', $url );
		$flash = Portal::unflash( 'complement_' . $s['public_id'] . '_garantias' );
		$this->assertArrayHasKey( 'garantias.0.declared_value', $flash['errors'] );
		// Sucesso: volta à solicitação.
		$post( array( 'garantias' => array( $this->gar() ) ) );
		$url = $run();
		$this->assertStringContainsString( 'ebcr_view=solicitacao', $url );
		$this->assertStringContainsString( 'ebcr_msg=complemented', $url );
		$this->assertCount( 1, ( new GuaranteeRepository() )->for_submission( (int) $s['id'] ) );
		$done = Portal::unflash( 'complement_done_' . $s['public_id'] );
		$this->assertSame( 1, $done['values']['added'] );
		// Nonce inválido: nada é gravado.
		$post(
			array(
				'ebcr_nonce' => 'x',
				'garantias'  => array( $this->gar( array( 'type' => 'cpr' ) ) ),
			)
		);
		$url = $run();
		$this->assertStringContainsString( 'ebcr_msg=nonce', $url );
		$this->assertCount( 1, ( new GuaranteeRepository() )->for_submission( (int) $s['id'] ) );
		$_POST = array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		remove_filter( 'wp_redirect', $redirect );
	}

	public function test_storage_folder_is_created_and_protected(): void {
		$base = trailingslashit( wp_normalize_path( sys_get_temp_dir() ) ) . 'ebcr-store-' . wp_generate_password( 6, false, false );
		$path = $base . '/ebcr-private';
		$this->dirs[] = $base;
		$this->dirs[] = $path;
		$values   = array( 'storage_path' => $path );
		$warnings = Settings::guard( $values );
		$this->assertTrue( is_dir( $path ) );
		$this->assertSame( '0750', substr( sprintf( '%o', fileperms( $path ) ), -4 ) );
		$this->assertStringContainsString( 'Require all denied', (string) file_get_contents( $path . '/.htaccess' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertFileExists( $path . '/index.php' );
		$this->assertNotEmpty( preg_grep( '/Pasta privada criada/', $warnings ) );
		$this->assertEmpty( preg_grep( '/não existe ou não é gravável/', $warnings ) );
		$this->assertSame( wp_normalize_path( $path ), FileGuard::created_info()['path'] );
		$this->assertEmpty( preg_grep( '/criada/', Settings::guard( $values ) ), 'pasta existente: nada a criar' );

		// Caminho relativo e pai que não é pasta.
		$rel = array( 'storage_path' => 'relativo/ebcr' );
		$this->assertNotEmpty( preg_grep( '/caminho absoluto/', Settings::guard( $rel ) ) );
		$file = $this->tmp_file( 'x', 'txt' );
		$bad  = array( 'storage_path' => $file . '/sub' );
		$this->assertNotEmpty( preg_grep( '/Não foi possível criar a pasta/', Settings::guard( $bad ) ) );

		// Dentro da instalação do WordPress: cria, mas avisa.
		$inside       = wp_normalize_path( ABSPATH ) . 'ebcr-priv-' . wp_generate_password( 6, false, false );
		$this->dirs[] = $inside;
		$in           = array( 'storage_path' => $inside );
		$this->assertNotEmpty( preg_grep( '/dentro da instalação do WordPress/', Settings::guard( $in ) ) );

		// get-status: storage.created.
		wp_set_current_user( $this->make_user( 'administrator' ) );
		$st = Abilities::get_status();
		$this->assertArrayHasKey( 'created', $st['storage'] );
		$this->assertSame( wp_normalize_path( $inside ), $st['storage']['created']['path'] );
		$this->assertSame( array( 'imoveis' => true, 'garantias' => true ), $st['modules']['complement']['parts'] );
	}

	public function test_abilities_expose_new_settings(): void {
		wp_set_current_user( $this->make_user( 'administrator' ) );
		$get = Abilities::get_settings( array( 'tab' => 'garantias' ) );
		$flat = wp_json_encode( $get );
		foreach ( array( 'guarantees_optional_notice', 'assets_optional_notice', 'complement_statuses', 'complement_reminder_days' ) as $k ) {
			$this->assertStringContainsString( $k, $flat );
		}
		$r = Abilities::update_settings(
			array(
				'settings' => array(
					'complement_statuses'      => array( 'enviada', 'pre_analise', 'xyz' ),
					'complement_reminder_days' => 99,
				),
			)
		);
		$this->assertSame( array( 'enviada', 'pre_analise' ), Complement::statuses() );
		$this->assertSame( 60, Options::int( 'complement_reminder_days' ), 'limite 0–60' );
		$this->assertNotEmpty( $r['errors'] );
	}
}
