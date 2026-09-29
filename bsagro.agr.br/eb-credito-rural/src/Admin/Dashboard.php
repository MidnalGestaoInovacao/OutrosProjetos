<?php
/**
 * Painel inicial: cartões, gráficos (Chart.js empacotado) e listas de acompanhamento.
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Database\CrmActivityRepository;
use EBCR\Database\DocumentRepository;
use EBCR\Database\DocumentRequestRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Reports\Metrics;
use EBCR\Roles\Capabilities;
use EBCR\Support\Helpers;
use EBCR\Support\Options;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Cartões: novas (7 dias), em análise, com pendência, aprovadas no mês, volume, tempo médio por etapa.
 * Gráficos: funil por status, submissões por mês, por UF, por atividade, por tipo de garantia.
 * Listas: pendências paradas, certidões vencendo, tarefas do CRM.
 */
final class Dashboard {

	/**
	 * Máximo de categorias exibidas por gráfico (o restante vai para "Outras").
	 *
	 * @var int
	 */
	const MAX_BARS = 8;

	/**
	 * Já renderizado nesta requisição? O menu registra o hook da página duas vezes (item de topo + subitem "Painel"
	 * com o mesmo slug), o que chamaria render() duas vezes e duplicaria a tela e os IDs dos gráficos.
	 *
	 * @var bool
	 */
	private static $rendered = false;

	/**
	 * Renderiza.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Capabilities::CAP_VIEW ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'eb-credito-rural' ), 403 );
		}
		if ( self::$rendered ) {
			return;
		}
		self::$rendered = true;
		global $wpdb;
		$t     = \EBCR\Database\Db::table( 'submissions' );
		$week  = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$month = gmdate( 'Y-m-01 00:00:00' );
		$cards = array(
			array( __( 'Novas (7 dias)', 'eb-credito-rural' ), (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE deleted_at IS NULL AND submitted_at >= %s", $week ) ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
			array( __( 'Em análise', 'eb-credito-rural' ), (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE deleted_at IS NULL AND status IN (%s,%s,%s)", Status::PRE_ANALYSIS, Status::CREDIT, Status::COMMITTEE ) ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- idem.
			array( __( 'Com pendência', 'eb-credito-rural' ), (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE deleted_at IS NULL AND status = %s", Status::PENDING_DOCS ) ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- idem.
			array( __( 'Aprovadas no mês', 'eb-credito-rural' ), (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE deleted_at IS NULL AND status IN (%s,%s,%s) AND updated_at >= %s", Status::APPROVED, Status::FORMALIZATION, Status::DONE, $month ) ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- idem.
			array( __( 'Volume solicitado (em andamento)', 'eb-credito-rural' ), Helpers::money( (float) $wpdb->get_var( "SELECT COALESCE(SUM(requested_amount),0) FROM `{$t}` WHERE deleted_at IS NULL AND status NOT IN ('rascunho','reprovada','cancelada','concluida')" ) ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- idem.
			array( __( 'Volume aprovado (mês)', 'eb-credito-rural' ), Helpers::money( (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(requested_amount),0) FROM `{$t}` WHERE deleted_at IS NULL AND status IN (%s,%s,%s) AND updated_at >= %s", Status::APPROVED, Status::FORMALIZATION, Status::DONE, $month ) ) ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- idem.
		);

		// Métricas (respeitam "analista vê só as atribuídas").
		$filters   = Metrics::normalize( array(), get_current_user_id() );
		$funnel    = Metrics::funnel( $filters );
		$months    = Metrics::by_month( $filters, 12 );
		$uf        = Metrics::by_uf( $filters );
		$activity  = Metrics::by_activity( $filters );
		$guarantee = Metrics::by_guarantee_type( $filters );
		$stages    = Metrics::stage_durations( $filters );
		$by_key    = array();
		foreach ( $stages as $st ) {
			$by_key[ $st['key'] ] = $st;
		}
		$days    = static function ( $key ) use ( $by_key ) {
			return isset( $by_key[ $key ] ) && null !== $by_key[ $key ]['days'] ? number_format_i18n( $by_key[ $key ]['days'], 1 ) . ' d' : '—';
		};
		$total   = isset( $by_key['submitted_to_final'] ) ? $by_key['submitted_to_final']['days'] : null;
		$cards[] = array(
			__( 'Tempo médio por etapa (envio → decisão)', 'eb-credito-rural' ),
			null === $total ? '—' : sprintf( /* translators: %s: dias */ __( '%s dias', 'eb-credito-rural' ), number_format_i18n( $total, 1 ) ),
			sprintf( /* translators: 1: dias até a pré-análise, 2: dias até a análise de crédito, 3: dias até a decisão */ __( 'Pré-análise %1$s · Análise %2$s · Decisão %3$s', 'eb-credito-rural' ), $days( 'submitted_to_pre' ), $days( 'pre_to_credit' ), $days( 'credit_to_decision' ) ),
		);

		$chart_data = array(
			'funnel'    => array(
				'labels' => array_map( array( Status::class, 'label' ), array_keys( $funnel ) ),
				'values' => array_values( $funnel ),
			),
			'months'    => array(
				'labels'  => wp_list_pluck( $months, 'label' ),
				'counts'  => array_map( 'intval', wp_list_pluck( $months, 'count' ) ),
				'volumes' => array_map( 'floatval', wp_list_pluck( $months, 'volume' ) ),
			),
			'uf'        => self::series( $uf ),
			'activity'  => self::series( $activity ),
			'guarantee' => self::series( $guarantee ),
			'i18n'      => array(
				'submissions' => __( 'Solicitações', 'eb-credito-rural' ),
				'guarantees'  => __( 'Garantias', 'eb-credito-rural' ),
				'volume'      => __( 'Volume solicitado', 'eb-credito-rural' ),
				'empty'       => __( 'Sem dados para exibir.', 'eb-credito-rural' ),
			),
			'locale'    => str_replace( '_', '-', get_locale() ),
		);
		wp_add_inline_script( 'ebcr-dashboard', 'window.ebcrDashboardData = ' . wp_json_encode( $chart_data ) . ';', 'before' );

		$days_limit = max( 1, Options::int( 'pending_reminder_days' ) );
		$stale      = ( new DocumentRequestRepository() )->stale_open( $days_limit );
		$subs       = new SubmissionRepository();
		$stale_rows = array();
		foreach ( $stale as $r ) {
			$s = $subs->find( (int) $r['submission_id'] );
			if ( $s && ! Status::is_final( $s['status'] ) ) {
				$stale_rows[ $s['public_id'] ] = array(
					's'     => $s,
					'label' => $r['label'],
					'since' => $r['requested_at'],
				);
			}
		}
		View::show(
			'admin/dashboard',
			array(
				'cards'     => $cards,
				'by_status' => $funnel,
				'months'    => $months,
				'uf'        => $uf,
				'activity'  => $activity,
				'guarantee' => $guarantee,
				'stages'    => $stages,
				'tasks'     => current_user_can( Capabilities::CAP_CRM ) ? ( new CrmActivityRepository() )->open_tasks( 10 ) : array(),
				'stale'     => $stale_rows,
				'expiring'  => ( new DocumentRepository() )->expiring_until( gmdate( 'Y-m-d', strtotime( '+15 days' ) ), Status::in_progress() ),
				'days'      => $days_limit,
			)
		);
	}

	/**
	 * Série para gráfico de barras: até MAX_BARS categorias, o restante agrupado em "Outras".
	 *
	 * @param array $items Itens { label, count } já ordenados.
	 * @return array labels, values.
	 */
	private static function series( array $items ) {
		$labels = array();
		$values = array();
		$other  = 0;
		foreach ( $items as $i => $item ) {
			if ( $i < self::MAX_BARS ) {
				$labels[] = $item['label'];
				$values[] = (int) $item['count'];
			} else {
				$other += (int) $item['count'];
			}
		}
		if ( $other > 0 ) {
			$labels[] = __( 'Outras', 'eb-credito-rural' );
			$values[] = $other;
		}
		return array(
			'labels' => $labels,
			'values' => $values,
		);
	}
}
