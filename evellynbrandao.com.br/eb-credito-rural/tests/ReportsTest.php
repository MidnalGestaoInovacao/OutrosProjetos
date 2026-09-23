<?php
/**
 * Testes das métricas do painel/relatórios (EBCR\Reports\Metrics) e da exportação CSV (EBCR\Admin\Reports::csv).
 *
 * @package EBCR
 */

use EBCR\Admin\Reports;
use EBCR\Database\GuaranteeRepository;
use EBCR\Database\SubmissionDataRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Reports\Metrics;
use EBCR\Roles\Capabilities;
use EBCR\Support\Options;

/**
 * Cada teste cria carteiras com chaves únicas e filtra por elas, para não depender de dados de outros testes.
 */
final class ReportsTest extends EBCR_TestCase {

	/**
	 * Chave da carteira A.
	 *
	 * @var string
	 */
	private $fund_a;

	/**
	 * Chave da carteira B.
	 *
	 * @var string
	 */
	private $fund_b;

	/**
	 * IDs de solicitações criadas (removidas no tearDown para não poluir o banco compartilhado).
	 *
	 * @var int[]
	 */
	private $created = array();

	/**
	 * Carteiras únicas por execução.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$suffix       = strtolower( wp_generate_password( 6, false, false ) );
		$this->fund_a = 'fa' . $suffix;
		$this->fund_b = 'fb' . $suffix;
		Options::update( array( 'funds' => "{$this->fund_a}|Fundo A\n{$this->fund_b}|Fundo B" ) );
	}

	/**
	 * Remove as solicitações criadas e seus dados relacionados.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->created as $id ) {
			$wpdb->delete( $wpdb->prefix . 'ebcr_submissions', array( 'id' => $id ) );
			foreach ( array( 'ebcr_submission_data', 'ebcr_guarantees', 'ebcr_status_history' ) as $t ) {
				$wpdb->delete( $wpdb->prefix . $t, array( 'submission_id' => $id ) );
			}
		}
		parent::tearDown();
	}

	/**
	 * Insere uma linha no histórico de status com data controlada.
	 *
	 * @param int         $submission_id Solicitação.
	 * @param string|null $from          Origem.
	 * @param string      $to            Destino.
	 * @param string      $when          Data UTC (Y-m-d H:i:s).
	 * @return void
	 */
	private function history( $submission_id, $from, $to, $when ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'ebcr_status_history',
			array(
				'submission_id'    => (int) $submission_id,
				'from_status'      => $from,
				'to_status'        => $to,
				'changed_by'       => null,
				'comment_internal' => '',
				'comment_client'   => '',
				'created_at'       => $when,
			)
		);
	}

	/**
	 * Conjunto de dados: 3 solicitações na carteira A (aprovada, reprovada, em pré-análise), 1 na B (enviada),
	 * 1 rascunho e 1 excluída na A (ambas devem ser ignoradas).
	 *
	 * @return array client, analyst, ids
	 */
	private function fixture() {
		$client  = $this->make_user();
		$analyst = $this->make_user( Capabilities::ROLE_ANALYST );
		$repo    = new SubmissionRepository();
		$data    = new SubmissionDataRepository();
		$gar     = new GuaranteeRepository();

		// s1: aprovada, GO, grãos, hipoteca, atribuída ao analista. Enviada 01/03 → pré 03/03 (+2) → análise 06/03 (+3) → aprovada 07/03 (+1).
		$s1 = $this->make_submission(
			$client,
			Status::APPROVED,
			array(
				'requested_amount' => 100000,
				'submitted_at'     => '2026-03-01 12:00:00',
				'protocol'         => 'R1-' . wp_generate_password( 6, false, false ),
				'assigned_to'      => $analyst,
			)
		);
		$repo->update( (int) $s1['id'], array( 'fund' => $this->fund_a ) );
		$data->save( (int) $s1['id'], 'identificacao', array( 'uf' => 'GO', 'cidade' => 'Goiânia' ) );
		$data->save( (int) $s1['id'], 'producao', array( 'atividades' => array( 'graos' ) ) );
		$gar->replace_all( (int) $s1['id'], array( array( 'type' => 'hipoteca', 'description' => 'Fazenda', 'declared_value' => 500000 ) ) );
		$this->history( $s1['id'], Status::DRAFT, Status::SUBMITTED, '2026-03-01 12:00:00' );
		$this->history( $s1['id'], Status::SUBMITTED, Status::PRE_ANALYSIS, '2026-03-03 12:00:00' );
		$this->history( $s1['id'], Status::PRE_ANALYSIS, Status::CREDIT, '2026-03-06 12:00:00' );
		$this->history( $s1['id'], Status::CREDIT, Status::COMMITTEE, '2026-03-06 18:00:00' );
		$this->history( $s1['id'], Status::COMMITTEE, Status::APPROVED, '2026-03-07 12:00:00' );

		// s2: reprovada, GO, grãos + café, penhor agrícola. Enviada 05/03 → pré 09/03 (+4) → análise 10/03 (+1) → reprovada 13/03 (+3).
		$s2 = $this->make_submission(
			$client,
			Status::REJECTED,
			array(
				'requested_amount' => 50000,
				'submitted_at'     => '2026-03-05 12:00:00',
				'protocol'         => 'R2-' . wp_generate_password( 6, false, false ),
				'fund'             => $this->fund_a,
			)
		);
		$data->save( (int) $s2['id'], 'identificacao', array( 'uf' => 'GO', 'cidade' => 'Rio Verde' ) );
		$data->save( (int) $s2['id'], 'producao', array( 'atividades' => array( 'graos', 'cafe' ) ) );
		$gar->replace_all( (int) $s2['id'], array( array( 'type' => 'penhor_agricola', 'description' => 'Safra', 'declared_value' => 80000 ) ) );
		$this->history( $s2['id'], Status::DRAFT, Status::SUBMITTED, '2026-03-05 12:00:00' );
		$this->history( $s2['id'], Status::SUBMITTED, Status::PRE_ANALYSIS, '2026-03-09 12:00:00' );
		$this->history( $s2['id'], Status::PRE_ANALYSIS, Status::CREDIT, '2026-03-10 12:00:00' );
		$this->history( $s2['id'], Status::CREDIT, Status::REJECTED, '2026-03-13 12:00:00' );

		// s3: em pré-análise, MT, café, sem garantias. Enviada 20/03 → pré 22/03 (+2).
		$s3 = $this->make_submission(
			$client,
			Status::PRE_ANALYSIS,
			array(
				'requested_amount' => 30000,
				'submitted_at'     => '2026-03-20 12:00:00',
				'protocol'         => 'R3-' . wp_generate_password( 6, false, false ),
				'fund'             => $this->fund_a,
			)
		);
		$data->save( (int) $s3['id'], 'identificacao', array( 'uf' => 'MT', 'cidade' => 'Sorriso' ) );
		$data->save( (int) $s3['id'], 'producao', array( 'atividades' => array( 'cafe' ) ) );
		$this->history( $s3['id'], Status::DRAFT, Status::SUBMITTED, '2026-03-20 12:00:00' );
		$this->history( $s3['id'], Status::SUBMITTED, Status::PRE_ANALYSIS, '2026-03-22 12:00:00' );

		// s4: carteira B, enviada em abril, sem dados de etapa.
		$s4 = $this->make_submission(
			$client,
			Status::SUBMITTED,
			array(
				'requested_amount' => 200000,
				'submitted_at'     => '2026-04-02 12:00:00',
				'protocol'         => 'R4-' . wp_generate_password( 6, false, false ),
				'fund'             => $this->fund_b,
			)
		);

		// Ignoradas: rascunho e excluída (ambas na carteira A).
		$s5 = $this->make_submission( $client, Status::DRAFT, array( 'fund' => $this->fund_a ) );
		$s6 = $this->make_submission(
			$client,
			Status::SUBMITTED,
			array(
				'requested_amount' => 999999,
				'submitted_at'     => '2026-03-15 12:00:00',
				'fund'             => $this->fund_a,
			)
		);
		$repo->soft_delete( (int) $s6['id'] );
		$this->created = array_merge( $this->created, array( (int) $s1['id'], (int) $s2['id'], (int) $s3['id'], (int) $s4['id'], (int) $s5['id'], (int) $s6['id'] ) );

		return array(
			'client'  => $client,
			'analyst' => $analyst,
			'ids'     => array( (int) $s1['id'], (int) $s2['id'], (int) $s3['id'], (int) $s4['id'] ),
		);
	}

	/**
	 * Linha de by_fund() da carteira informada.
	 *
	 * @param array  $rows Saída de by_fund().
	 * @param string $key  Carteira.
	 * @return array
	 */
	private function fund_row( array $rows, $key ) {
		foreach ( $rows as $r ) {
			if ( $r['key'] === $key ) {
				return $r;
			}
		}
		$this->fail( 'carteira não encontrada: ' . $key );
		return array();
	}

	public function test_normalize_sanitizes_and_validates_filters(): void {
		$f = Metrics::normalize(
			array(
				'date_from'   => '2026-02-30',
				'date_to'     => '2026-03-31',
				'fund'        => 'Fundo A!',
				'status'      => 'inexistente',
				'assigned_to' => '7abc',
			)
		);
		$this->assertSame( '', $f['date_from'], 'data inválida é descartada' );
		$this->assertSame( '2026-03-31', $f['date_to'] );
		$this->assertSame( 'fundoa', $f['fund'] );
		$this->assertSame( '', $f['status'], 'status desconhecido é descartado' );
		$this->assertSame( 7, $f['assigned_to'] );
		$this->assertSame( 0, $f['only_assigned_to'] );
		$this->assertSame( Status::APPROVED, Metrics::normalize( array( 'status' => Status::APPROVED ) )['status'] );
	}

	public function test_rows_funnel_and_by_status_ignore_drafts_and_deleted(): void {
		$this->fixture();
		$f    = array( 'fund' => $this->fund_a );
		$rows = Metrics::rows( $f );
		$this->assertCount( 3, $rows );
		$funnel = Metrics::funnel( $f );
		$this->assertSame( 1, $funnel[ Status::APPROVED ] );
		$this->assertSame( 1, $funnel[ Status::REJECTED ] );
		$this->assertSame( 1, $funnel[ Status::PRE_ANALYSIS ] );
		$this->assertSame( 0, $funnel[ Status::SUBMITTED ] );
		$this->assertArrayNotHasKey( Status::DRAFT, $funnel );
		$this->assertSame( 3, array_sum( $funnel ) );
		$by_status = array();
		foreach ( Metrics::by_status( $f ) as $r ) {
			$by_status[ $r['key'] ] = $r;
		}
		$this->assertSame( 1, $by_status[ Status::APPROVED ]['count'] );
		$this->assertSame( 100000.0, $by_status[ Status::APPROVED ]['requested'] );
		$this->assertSame( 'Aprovada', $by_status[ Status::APPROVED ]['label'] );
	}

	public function test_totals_by_fund(): void {
		$this->fixture();
		$rows = Metrics::by_fund( array() );
		$a    = $this->fund_row( $rows, $this->fund_a );
		$this->assertSame( 'Fundo A', $a['label'] );
		$this->assertSame( 3, $a['count'] );
		$this->assertSame( 180000.0, $a['requested'] );
		$this->assertSame( 1, $a['approved_count'] );
		$this->assertSame( 100000.0, $a['approved_volume'] );
		$this->assertSame( 1, $a['rejected_count'] );
		$this->assertSame( 1, $a['in_progress'] );
		$this->assertSame( 60000.0, $a['ticket'] );
		$this->assertSame( 50.0, $a['approval_rate'] );
		$b = $this->fund_row( $rows, $this->fund_b );
		$this->assertSame( 1, $b['count'] );
		$this->assertSame( 200000.0, $b['requested'] );
		$this->assertSame( 0.0, $b['approved_volume'] );
		$this->assertNull( $b['approval_rate'], 'sem decisão não há taxa' );
		// Carteiras configuradas aparecem mesmo sem solicitações; filtro por carteira isola.
		$only_a = Metrics::by_fund( array( 'fund' => $this->fund_a ) );
		$this->assertSame( 0, $this->fund_row( $only_a, $this->fund_b )['count'] );
		$totals = Metrics::totals( array( 'fund' => $this->fund_a ) );
		$this->assertSame( 3, $totals['count'] );
		$this->assertSame( 60000.0, $totals['ticket'] );
	}

	public function test_distribution_by_uf_activity_and_guarantee(): void {
		$this->fixture();
		$f  = array( 'fund' => $this->fund_a );
		$uf = wp_list_pluck( Metrics::by_uf( $f ), 'count', 'key' );
		$this->assertSame( array( 'GO' => 2, 'MT' => 1 ), $uf );
		$names = wp_list_pluck( Metrics::by_uf( $f ), 'name', 'key' );
		$this->assertSame( 'Goiás', $names['GO'] );
		$act = wp_list_pluck( Metrics::by_activity( $f ), 'count', 'key' );
		$this->assertSame( 2, $act['graos'] );
		$this->assertSame( 2, $act['cafe'] );
		$this->assertCount( 2, $act );
		$labels = wp_list_pluck( Metrics::by_activity( $f ), 'label', 'key' );
		$this->assertSame( 'Café', $labels['cafe'] );
		$g = wp_list_pluck( Metrics::by_guarantee_type( $f ), 'count', 'key' );
		$this->assertSame( array( 'hipoteca' => 1, 'penhor_agricola' => 1 ), $g );
		$this->assertSame( 'Hipoteca', wp_list_pluck( Metrics::by_guarantee_type( $f ), 'label', 'key' )['hipoteca'] );
		// Sem dados de etapa: bucket "Não informada".
		$b_uf = Metrics::by_uf( array( 'fund' => $this->fund_b ) );
		$this->assertSame( '', $b_uf[0]['key'] );
		$this->assertSame( 1, $b_uf[0]['count'] );
		$this->assertSame( array(), Metrics::by_guarantee_type( array( 'fund' => $this->fund_b ) ) );
	}

	public function test_stage_durations_from_status_history(): void {
		$this->fixture();
		$stages = array();
		foreach ( Metrics::stage_durations( array( 'fund' => $this->fund_a ) ) as $s ) {
			$stages[ $s['key'] ] = $s;
		}
		$this->assertSame( 3, $stages['submitted_to_pre']['n'] );
		$this->assertSame( 2.7, $stages['submitted_to_pre']['days'], '(2+4+2)/3' );
		$this->assertSame( 2, $stages['pre_to_credit']['n'] );
		$this->assertSame( 2.0, $stages['pre_to_credit']['days'], '(3+1)/2' );
		$this->assertSame( 2, $stages['credit_to_decision']['n'] );
		$this->assertSame( 2.0, $stages['credit_to_decision']['days'], '(1+3)/2' );
		$this->assertSame( 2, $stages['submitted_to_final']['n'] );
		$this->assertSame( 7.0, $stages['submitted_to_final']['days'], '(6+8)/2' );
		// Sem histórico: sem médias.
		foreach ( Metrics::stage_durations( array( 'fund' => $this->fund_b ) ) as $s ) {
			$this->assertNull( $s['days'] );
			$this->assertSame( 0, $s['n'] );
		}
	}

	public function test_filters_period_status_and_analyst(): void {
		$fx = $this->fixture();
		$a  = array( 'fund' => $this->fund_a );
		$this->assertCount( 3, Metrics::rows( $a + array( 'date_from' => '2026-03-01', 'date_to' => '2026-03-31' ) ) );
		$this->assertCount( 2, Metrics::rows( $a + array( 'date_from' => '2026-03-01', 'date_to' => '2026-03-06' ) ) );
		$this->assertCount( 1, Metrics::rows( $a + array( 'date_from' => '2026-03-19' ) ) );
		$this->assertCount( 0, Metrics::rows( $a + array( 'date_to' => '2026-02-28' ) ) );
		$this->assertCount( 1, Metrics::rows( $a + array( 'status' => Status::APPROVED ) ) );
		$this->assertCount( 1, Metrics::rows( $a + array( 'assigned_to' => $fx['analyst'] ) ) );
		$this->assertCount( 0, Metrics::rows( array( 'fund' => $this->fund_b, 'assigned_to' => $fx['analyst'] ) ) );
	}

	public function test_by_month_buckets_and_range(): void {
		$this->fixture();
		$months = Metrics::by_month(
			array(
				'fund'      => $this->fund_a,
				'date_from' => '2026-03-01',
				'date_to'   => '2026-04-30',
			)
		);
		$this->assertCount( 2, $months );
		$this->assertSame( '2026-03', $months[0]['month'] );
		$this->assertSame( 3, $months[0]['count'] );
		$this->assertSame( 180000.0, $months[0]['volume'] );
		$this->assertSame( 1, $months[0]['approved_count'] );
		$this->assertSame( 100000.0, $months[0]['approved_volume'] );
		$this->assertSame( '2026-04', $months[1]['month'] );
		$this->assertSame( 0, $months[1]['count'] );
		$this->assertNotSame( '', $months[0]['label'] );
		$b = Metrics::by_month(
			array(
				'fund'      => $this->fund_b,
				'date_from' => '2026-04-01',
				'date_to'   => '2026-04-30',
			)
		);
		$this->assertCount( 1, $b );
		$this->assertSame( 1, $b[0]['count'] );
		// Sem período: últimos N meses até o mês atual.
		$last = Metrics::by_month( array( 'fund' => $this->fund_a ), 6 );
		$this->assertCount( 6, $last );
		$this->assertSame( wp_date( 'Y-m' ), $last[5]['month'] );
	}

	public function test_by_analyst_groups_and_orders(): void {
		$fx   = $this->fixture();
		$rows = Metrics::by_analyst( array( 'fund' => $this->fund_a ) );
		$this->assertCount( 2, $rows );
		$this->assertSame( $fx['analyst'], $rows[0]['key'] );
		$this->assertSame( 1, $rows[0]['count'] );
		$this->assertSame( 100.0, $rows[0]['approval_rate'] );
		$this->assertSame( 0, $rows[1]['key'], '"Sem responsável" por último' );
		$this->assertSame( 2, $rows[1]['count'] );
		$this->assertSame( 80000.0, $rows[1]['requested'] );
	}

	public function test_analyst_only_assigned_restricts_metrics(): void {
		$fx      = $this->fixture();
		$manager = $this->make_user( Capabilities::ROLE_MANAGER );
		Options::update( array( 'analyst_only_assigned' => true ) );
		$f_analyst = Metrics::normalize( array( 'fund' => $this->fund_a ), $fx['analyst'] );
		$this->assertSame( $fx['analyst'], $f_analyst['only_assigned_to'] );
		$this->assertCount( 1, Metrics::rows( $f_analyst ) );
		$this->assertSame( 1, Metrics::totals( $f_analyst )['count'] );
		$f_manager = Metrics::normalize( array( 'fund' => $this->fund_a ), $manager );
		$this->assertSame( 0, $f_manager['only_assigned_to'] );
		$this->assertCount( 3, Metrics::rows( $f_manager ) );
		Options::update( array( 'analyst_only_assigned' => false ) );
		$this->assertSame( 0, Metrics::normalize( array( 'fund' => $this->fund_a ), $fx['analyst'] )['only_assigned_to'] );
	}

	public function test_csv_export_string(): void {
		$this->fixture();
		$csv = Reports::csv( array( 'fund' => $this->fund_a ) );
		$this->assertStringStartsWith( "\xEF\xBB\xBF", $csv, 'BOM UTF-8' );
		$this->assertStringContainsString( 'Por fundo/carteira', $csv );
		$this->assertStringContainsString( '"Fundo A";3;1;1;1;180000,00;100000,00;60000,00;50,0', $csv, 'campos com espaço vão entre aspas' );
		$this->assertStringContainsString( '"Fundo B";0;0;0;0;0,00;0,00;0,00;', $csv );
		$this->assertStringContainsString( 'Aprovada;1;100000,00', $csv );
		$this->assertStringContainsString( '"Enviada → Pré-análise";2,7;3', $csv );
		$this->assertStringContainsString( '"Sem responsável";2;', $csv );
		$this->assertStringContainsString( 'Carteira;"Fundo A"', $csv, 'cabeçalho com o filtro aplicado' );
		$this->assertStringNotContainsString( "\t", $csv );
		// Sem exportação de dados pessoais: nenhum e-mail ou protocolo.
		$this->assertStringNotContainsString( '@example.com', $csv );
		// Fórmulas neutralizadas.
		Options::update( array( 'funds' => "{$this->fund_a}|=SOMA(A1)" ) );
		$this->assertStringContainsString( "'=SOMA(A1)", Reports::csv( array( 'fund' => $this->fund_a ) ) );
	}

	public function test_read_filters_only_known_keys(): void {
		$f = Reports::read_filters(
			array(
				'date_from' => '2026-01-01',
				'fund'      => 'geral',
				'evil'      => 'x',
				'status'    => array( 'a' ),
			)
		);
		$this->assertSame( array( 'date_from', 'date_to', 'fund', 'status', 'assigned_to' ), array_keys( $f ) );
		$this->assertSame( '2026-01-01', $f['date_from'] );
		$this->assertSame( '', $f['status'], 'array é ignorado' );
	}
}
