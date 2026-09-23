<?php
/**
 * Relatórios por fundo/carteira, status, mês e analista, com filtros (período, carteira, status, analista)
 * e exportação CSV. Tela Crédito Rural → Relatórios.
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Domain\Status;
use EBCR\Reports\Metrics;
use EBCR\Roles\Capabilities;
use EBCR\Security\AuditLog;
use EBCR\Security\Nonces;
use EBCR\Support\Options;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Tela e exportação.
 */
final class Reports {

	/**
	 * Chaves de filtro aceitas (GET na tela, POST na exportação).
	 *
	 * @var string[]
	 */
	const FILTER_KEYS = array( 'date_from', 'date_to', 'fund', 'status', 'assigned_to' );

	/**
	 * Hooks (exportação CSV via admin-post).
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_ebcr_admin_reports_csv', array( $this, 'handle_csv' ) );
	}

	/**
	 * Lê os filtros de uma entrada (GET/POST), já sanitizados. A normalização final é feita por Metrics::normalize().
	 *
	 * @param array $input Entrada bruta.
	 * @return array
	 */
	public static function read_filters( array $input ) {
		$out = array();
		foreach ( self::FILTER_KEYS as $k ) {
			$out[ $k ] = isset( $input[ $k ] ) && is_scalar( $input[ $k ] ) ? sanitize_text_field( wp_unslash( (string) $input[ $k ] ) ) : '';
		}
		return $out;
	}

	/**
	 * Renderiza a tela.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Capabilities::CAP_DASHBOARD ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'eb-credito-rural' ), 403 );
		}
		$filters = Metrics::normalize( self::read_filters( $_GET ), get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtros de leitura de uma tela de consulta.
		View::show(
			'admin/reports',
			array(
				'filters'    => $filters,
				'funds'      => Options::pairs( 'funds' ),
				'statuses'   => Status::all(),
				'analysts'   => get_users(
					array(
						'capability' => Capabilities::CAP_VIEW,
						'fields'     => array( 'ID', 'display_name' ),
						'orderby'    => 'display_name',
					)
				),
				'totals'     => Metrics::totals( $filters ),
				'by_fund'    => Metrics::by_fund( $filters ),
				'by_status'  => Metrics::by_status( $filters ),
				'by_month'   => Metrics::by_month( $filters, 12 ),
				'by_analyst' => Metrics::by_analyst( $filters ),
				'stages'     => Metrics::stage_durations( $filters ),
				'can_export' => current_user_can( Capabilities::CAP_EXPORT ),
			)
		);
	}

	/**
	 * Exportação CSV (admin-post ebcr_admin_reports_csv): capacidade de relatórios + exportação, nonce e auditoria.
	 *
	 * @return void
	 */
	public function handle_csv() {
		if ( ! current_user_can( Capabilities::CAP_DASHBOARD ) || ! current_user_can( Capabilities::CAP_EXPORT ) || ! Nonces::verify( 'reports_csv' ) ) {
			wp_die( esc_html__( 'Sem permissão ou sessão expirada.', 'eb-credito-rural' ), 403 );
		}
		$filters = Metrics::normalize( self::read_filters( $_POST ), get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verificado acima.
		$csv     = self::csv( $filters );
		AuditLog::log(
			'export',
			'reports',
			'',
			array(
				'format'  => 'csv',
				'filters' => array_filter( $filters ),
			)
		);
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="relatorios-' . gmdate( 'Ymd-His' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV gerado pelo plugin.
		exit;
	}

	/**
	 * Gera o CSV (UTF-8 com BOM, separador ";", decimal com vírgula) com os mesmos blocos da tela.
	 *
	 * @param array $filters Filtros já normalizados (com a restrição por analista aplicada pelo chamador).
	 * @return string
	 */
	public static function csv( array $filters ) {
		$filters  = Metrics::normalize( $filters );
		$funds    = Options::pairs( 'funds' );
		$totals   = Metrics::totals( $filters );
		$analysts = array();
		if ( $filters['assigned_to'] ) {
			$analysts[] = \EBCR\Support\Helpers::user_name( $filters['assigned_to'] );
		}
		$blocks = array();

		$blocks[] = array(
			'title' => __( 'Relatórios — EB Crédito Rural', 'eb-credito-rural' ),
			'head'  => array( __( 'Filtro', 'eb-credito-rural' ), __( 'Valor', 'eb-credito-rural' ) ),
			'rows'  => array(
				array( __( 'Gerado em (UTC)', 'eb-credito-rural' ), gmdate( 'Y-m-d H:i' ) ),
				array( __( 'Período (envio) — de', 'eb-credito-rural' ), $filters['date_from'] ? $filters['date_from'] : __( 'todos', 'eb-credito-rural' ) ),
				array( __( 'Período (envio) — até', 'eb-credito-rural' ), $filters['date_to'] ? $filters['date_to'] : __( 'todos', 'eb-credito-rural' ) ),
				array( __( 'Carteira', 'eb-credito-rural' ), $filters['fund'] ? ( isset( $funds[ $filters['fund'] ] ) ? $funds[ $filters['fund'] ] : $filters['fund'] ) : __( 'todas', 'eb-credito-rural' ) ),
				array( __( 'Status', 'eb-credito-rural' ), $filters['status'] ? Status::label( $filters['status'] ) : __( 'todos', 'eb-credito-rural' ) ),
				array( __( 'Analista', 'eb-credito-rural' ), $analysts ? $analysts[0] : __( 'todos', 'eb-credito-rural' ) ),
			),
		);

		$agg_head = array( __( 'Solicitações', 'eb-credito-rural' ), __( 'Em andamento', 'eb-credito-rural' ), __( 'Aprovadas', 'eb-credito-rural' ), __( 'Não aprovadas', 'eb-credito-rural' ), __( 'Volume solicitado (R$)', 'eb-credito-rural' ), __( 'Volume aprovado (R$)', 'eb-credito-rural' ), __( 'Ticket médio (R$)', 'eb-credito-rural' ), __( 'Taxa de aprovação (%)', 'eb-credito-rural' ) );
		$agg_row  = static function ( array $a ) {
			return array( $a['count'], $a['in_progress'], $a['approved_count'], $a['rejected_count'], self::num( $a['requested'] ), self::num( $a['approved_volume'] ), self::num( $a['ticket'] ), null === $a['approval_rate'] ? '' : self::num( $a['approval_rate'], 1 ) );
		};

		$blocks[] = array(
			'title' => __( 'Resumo', 'eb-credito-rural' ),
			'head'  => $agg_head,
			'rows'  => array( $agg_row( $totals ) ),
		);

		$rows = array();
		foreach ( Metrics::by_fund( $filters ) as $r ) {
			$rows[] = array_merge( array( $r['label'] ), $agg_row( $r ) );
		}
		$blocks[] = array(
			'title' => __( 'Por fundo/carteira', 'eb-credito-rural' ),
			'head'  => array_merge( array( __( 'Carteira', 'eb-credito-rural' ) ), $agg_head ),
			'rows'  => $rows,
		);

		$rows = array();
		foreach ( Metrics::by_status( $filters ) as $r ) {
			$rows[] = array( $r['label'], $r['count'], self::num( $r['requested'] ) );
		}
		$blocks[] = array(
			'title' => __( 'Por status', 'eb-credito-rural' ),
			'head'  => array( __( 'Status', 'eb-credito-rural' ), __( 'Quantidade', 'eb-credito-rural' ), __( 'Volume solicitado (R$)', 'eb-credito-rural' ) ),
			'rows'  => $rows,
		);

		$rows = array();
		foreach ( Metrics::by_month( $filters, 12 ) as $r ) {
			$rows[] = array( $r['month'], $r['count'], self::num( $r['volume'] ), $r['approved_count'], self::num( $r['approved_volume'] ) );
		}
		$blocks[] = array(
			'title' => __( 'Por mês (envio)', 'eb-credito-rural' ),
			'head'  => array( __( 'Mês', 'eb-credito-rural' ), __( 'Quantidade', 'eb-credito-rural' ), __( 'Volume solicitado (R$)', 'eb-credito-rural' ), __( 'Aprovadas', 'eb-credito-rural' ), __( 'Volume aprovado (R$)', 'eb-credito-rural' ) ),
			'rows'  => $rows,
		);

		$rows = array();
		foreach ( Metrics::by_analyst( $filters ) as $r ) {
			$rows[] = array_merge( array( $r['label'] ), $agg_row( $r ) );
		}
		$blocks[] = array(
			'title' => __( 'Por analista', 'eb-credito-rural' ),
			'head'  => array_merge( array( __( 'Analista', 'eb-credito-rural' ) ), $agg_head ),
			'rows'  => $rows,
		);

		$rows = array();
		foreach ( Metrics::stage_durations( $filters ) as $r ) {
			$rows[] = array( $r['label'], null === $r['days'] ? '' : self::num( $r['days'], 1 ), $r['n'] );
		}
		$blocks[] = array(
			'title' => __( 'Tempo médio por etapa', 'eb-credito-rural' ),
			'head'  => array( __( 'Etapa', 'eb-credito-rural' ), __( 'Média (dias)', 'eb-credito-rural' ), __( 'Base (solicitações)', 'eb-credito-rural' ) ),
			'rows'  => $rows,
		);

		$h = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- buffer em memória.
		fwrite( $h, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- BOM para Excel.
		foreach ( $blocks as $i => $block ) {
			if ( $i > 0 ) {
				fputcsv( $h, array( '' ), ';', '"', '' );
			}
			fputcsv( $h, array( self::cell( $block['title'] ) ), ';', '"', '' );
			fputcsv( $h, array_map( array( __CLASS__, 'cell' ), $block['head'] ), ';', '"', '' );
			foreach ( $block['rows'] as $row ) {
				fputcsv( $h, array_map( array( __CLASS__, 'cell' ), $row ), ';', '"', '' );
			}
		}
		rewind( $h );
		$csv = (string) stream_get_contents( $h );
		fclose( $h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- idem.
		return $csv;
	}

	/**
	 * Número com vírgula decimal (sem separador de milhar), como o Excel em pt-BR espera.
	 *
	 * @param mixed $value    Valor.
	 * @param int   $decimals Casas.
	 * @return string
	 */
	private static function num( $value, $decimals = 2 ) {
		return number_format( (float) $value, $decimals, ',', '' );
	}

	/**
	 * Neutraliza fórmulas em células de texto (=, +, -, @) para planilhas.
	 *
	 * @param mixed $v Valor.
	 * @return mixed
	 */
	private static function cell( $v ) {
		return is_string( $v ) && preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v;
	}
}
