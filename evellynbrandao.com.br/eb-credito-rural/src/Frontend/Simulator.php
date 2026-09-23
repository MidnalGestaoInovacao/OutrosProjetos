<?php
/**
 * Shortcode [ebcr_simulador]: simulador de crédito ilustrativo (Price/SAC, carência) para a página de captação.
 * Entregue pelo módulo "Integrações".
 *
 * @package EBCR
 */

namespace EBCR\Frontend;

use EBCR\Support\Helpers;
use EBCR\Support\Options;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode e assets do simulador.
 *
 * Atributos (todos opcionais; sobrepõem as configurações): taxa (% a.a.), sistema (price|sac), valor, prazo (meses),
 * carencia (meses), titulo, botao, url, tema (claro|escuro).
 *
 * Convenções de cálculo (iguais no PHP e no JS):
 * - Taxa mensal equivalente: i = (1 + a)^(1/12) − 1, com a = taxa anual.
 * - Prazo = número total de meses da operação, incluindo a carência.
 * - Carência: meses iniciais SEM pagamento, com juros CAPITALIZADOS no saldo (o saldo cresce a cada mês);
 *   a amortização começa no mês seguinte ao fim da carência e dura (prazo − carência) meses.
 * - Price: parcela = PV·i/(1 − (1+i)^−n); SAC: amortização constante PV/n + juros do saldo.
 * - "CET aproximado" = taxa efetiva anual dos juros (sem tarifas, seguros ou IOF; crédito rural é isento de IOF).
 */
final class Simulator {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'ebcr_simulador', array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Registra os assets e os enfileira quando a página contém o shortcode.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style( 'ebcr-simulator', EBCR_URL . 'assets/css/simulator.css', array(), EBCR_VERSION );
		wp_register_script( 'ebcr-simulator', EBCR_URL . 'assets/js/simulator.js', array(), EBCR_VERSION, true );
		global $post;
		if ( $post instanceof \WP_Post && has_shortcode( $post->post_content, 'ebcr_simulador' ) ) {
			self::enqueue();
		}
	}

	/**
	 * Enfileira CSS/JS do simulador.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! wp_style_is( 'ebcr-simulator', 'registered' ) ) {
			wp_register_style( 'ebcr-simulator', EBCR_URL . 'assets/css/simulator.css', array(), EBCR_VERSION );
			wp_register_script( 'ebcr-simulator', EBCR_URL . 'assets/js/simulator.js', array(), EBCR_VERSION, true );
		}
		wp_enqueue_style( 'ebcr-simulator' );
		wp_enqueue_script( 'ebcr-simulator' );
	}

	/**
	 * [ebcr_simulador taxa="" sistema="" valor="" prazo="" carencia="" titulo="" botao="" url="" tema=""].
	 *
	 * @param array|string $atts Atributos.
	 * @return string
	 */
	public function shortcode( $atts ) {
		if ( ! Options::bool( 'simulator_enabled' ) ) {
			return '';
		}
		$cfg = self::config( is_array( $atts ) ? $atts : array() );
		self::enqueue();
		return View::render(
			'portal/simulator',
			array(
				'cfg'    => $cfg,
				'result' => self::schedule( $cfg['amount'], $cfg['term'], $cfg['rate'], $cfg['system'], $cfg['grace'] ),
				'uid'    => 'ebcr-sim-' . substr( md5( wp_json_encode( $cfg ) . wp_rand() ), 0, 8 ),
			)
		);
	}

	/**
	 * Configuração efetiva (opções + atributos do shortcode), já saneada.
	 *
	 * @param array $atts Atributos do shortcode.
	 * @return array{rate:float,system:string,min:float,max:float,amount:float,max_term:int,term:int,grace:int,title:string,button:string,url:string,theme:string}
	 */
	public static function config( array $atts = array() ) {
		$a        = shortcode_atts(
			array(
				'taxa'     => Options::get( 'simulator_rate', 12 ),
				'sistema'  => Options::get( 'simulator_system', 'price' ),
				'valor'    => '',
				'prazo'    => '',
				'carencia' => Options::int( 'simulator_grace_months' ),
				'titulo'   => __( 'Simule seu crédito rural', 'eb-credito-rural' ),
				'botao'    => __( 'Solicitar crédito', 'eb-credito-rural' ),
				'url'      => (string) Options::get( 'simulator_cta_url', '' ),
				'tema'     => 'claro',
			),
			$atts,
			'ebcr_simulador'
		);
		$rate     = Helpers::parse_decimal( $a['taxa'] );
		$rate     = null === $rate ? 12.0 : min( 200.0, max( 0.0, $rate ) );
		$system   = 'sac' === strtolower( trim( (string) $a['sistema'] ) ) ? 'sac' : 'price';
		$min      = max( 0.0, (float) Options::get( 'simulator_min_amount', 50000 ) );
		$max      = max( $min + 1000.0, (float) Options::get( 'simulator_max_amount', 50000000 ) );
		$max_term = max( 1, min( 600, Options::int( 'simulator_max_term' ) ? Options::int( 'simulator_max_term' ) : 180 ) );
		$amount   = Helpers::parse_decimal( $a['valor'] );
		if ( null === $amount || $amount <= 0 ) {
			// Valor inicial "redondo" dentro da faixa: o mínimo mais 10% do intervalo, arredondado ao milhar.
			$amount = round( ( $min + 0.1 * ( $max - $min ) ) / 1000 ) * 1000;
		}
		$amount = min( $max, max( $min, (float) $amount ) );
		$term   = (int) $a['prazo'];
		if ( $term <= 0 ) {
			$term = min( 60, $max_term );
		}
		$term  = min( $max_term, max( 1, $term ) );
		$grace = max( 0, min( 60, (int) $a['carencia'] ) );
		$grace = min( $grace, max( 0, $term - 1 ) );
		$url   = trim( (string) $a['url'] );
		$url   = $url ? esc_url_raw( $url ) : Helpers::portal_url( array( 'ebcr_view' => 'cadastro' ) );
		return array(
			'rate'     => (float) $rate,
			'system'   => $system,
			'min'      => (float) $min,
			'max'      => (float) $max,
			'amount'   => (float) $amount,
			'max_term' => (int) $max_term,
			'term'     => (int) $term,
			'grace'    => (int) $grace,
			'title'    => sanitize_text_field( (string) $a['titulo'] ),
			'button'   => sanitize_text_field( (string) $a['botao'] ),
			'url'      => $url,
			'theme'    => 'escuro' === strtolower( trim( (string) $a['tema'] ) ) ? 'escuro' : 'claro',
		);
	}

	/**
	 * Taxa mensal equivalente à taxa anual (juros compostos).
	 *
	 * @param float $annual_rate Taxa anual em % (ex.: 12 para 12% a.a.).
	 * @return float
	 */
	public static function monthly_rate( $annual_rate ) {
		return pow( 1 + (float) $annual_rate / 100, 1 / 12 ) - 1;
	}

	/**
	 * Cronograma de parcelas.
	 *
	 * Carência = meses iniciais sem pagamento com juros capitalizados; prazo inclui a carência (ver docblock da classe).
	 *
	 * @param float  $amount      Valor financiado.
	 * @param int    $months      Prazo total em meses (inclui a carência).
	 * @param float  $annual_rate Taxa anual em %.
	 * @param string $system      price|sac.
	 * @param int    $grace       Meses de carência (0 = nenhuma; máximo prazo − 1).
	 * @return array{amount:float,months:int,grace:int,system:string,annual_rate:float,monthly_rate:float,first:float,last:float,total_interest:float,total_paid:float,cet:float,rows:array}
	 */
	public static function schedule( $amount, $months, $annual_rate, $system = 'price', $grace = 0 ) {
		$amount = max( 0.0, (float) $amount );
		$months = max( 1, (int) $months );
		$grace  = max( 0, min( (int) $grace, $months - 1 ) );
		$system = 'sac' === strtolower( (string) $system ) ? 'sac' : 'price';
		$i      = self::monthly_rate( (float) $annual_rate );
		$n      = $months - $grace;
		$rows   = array();
		$bal    = $amount;
		$paid   = 0.0;
		for ( $k = 1; $k <= $grace; $k++ ) {
			$interest = $bal * $i;
			$bal     += $interest;
			$rows[]   = array(
				'n'           => $k,
				'carencia'    => true,
				'juros'       => round( $interest, 2 ),
				'amortizacao' => 0.0,
				'parcela'     => 0.0,
				'saldo'       => round( $bal, 2 ),
			);
		}
		$pv    = $bal;
		$amort = $pv / $n;
		$pmt   = $i > 0 ? $pv * $i / ( 1 - pow( 1 + $i, -$n ) ) : $pv / $n;
		for ( $k = 1; $k <= $n; $k++ ) {
			$interest = $bal * $i;
			if ( 'sac' === $system ) {
				$payment = $amort + $interest;
				$a_k     = $amort;
			} else {
				$payment = $pmt;
				$a_k     = $pmt - $interest;
			}
			if ( $k === $n ) {
				// Última parcela absorve resíduos de arredondamento e zera o saldo.
				$a_k     = $bal;
				$payment = $a_k + $interest;
			}
			$bal   -= $a_k;
			$paid  += $payment;
			$rows[] = array(
				'n'           => $grace + $k,
				'carencia'    => false,
				'juros'       => round( $interest, 2 ),
				'amortizacao' => round( $a_k, 2 ),
				'parcela'     => round( $payment, 2 ),
				'saldo'       => round( max( 0.0, $bal ), 2 ),
			);
		}
		$first = $rows[ $grace ]['parcela'];
		$last  = $rows[ count( $rows ) - 1 ]['parcela'];
		return array(
			'amount'         => round( $amount, 2 ),
			'months'         => $months,
			'grace'          => $grace,
			'system'         => $system,
			'annual_rate'    => (float) $annual_rate,
			'monthly_rate'   => $i,
			'first'          => $first,
			'last'           => $last,
			'total_interest' => round( $paid - $amount, 2 ),
			'total_paid'     => round( $paid, 2 ),
			'cet'            => round( ( pow( 1 + $i, 12 ) - 1 ) * 100, 2 ),
			'rows'           => $rows,
		);
	}

	/**
	 * Percentual em pt-BR (12,50%).
	 *
	 * @param float $value Valor.
	 * @return string
	 */
	public static function percent( $value ) {
		return number_format( (float) $value, 2, ',', '.' ) . '%';
	}
}
