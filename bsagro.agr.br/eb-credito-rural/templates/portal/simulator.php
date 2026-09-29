<?php
/**
 * Simulador de crédito ([ebcr_simulador]). Variáveis: $cfg (config), $result (cronograma inicial), $uid (id único).
 *
 * @package EBCR
 */

use EBCR\Frontend\Simulator;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_rows    = isset( $result['rows'] ) ? $result['rows'] : array();
$ebcr_preview = 12;
$ebcr_json    = wp_json_encode(
	array(
		'rate'    => $cfg['rate'],
		'system'  => $cfg['system'],
		'min'     => $cfg['min'],
		'max'     => $cfg['max'],
		'amount'  => $cfg['amount'],
		'maxTerm' => $cfg['max_term'],
		'term'    => $cfg['term'],
		'grace'   => $cfg['grace'],
		'preview' => $ebcr_preview,
	)
);
?>
<div class="ebcr-sim__wrap">
<div class="ebcr-sim<?php echo 'escuro' === $cfg['theme'] ? ' ebcr-sim--escuro' : ''; ?>" id="<?php echo esc_attr( $uid ); ?>" data-ebcr-simulator data-config="<?php echo esc_attr( $ebcr_json ); ?>">
	<div class="ebcr-sim__head">
		<h3 class="ebcr-sim__title"><?php echo esc_html( $cfg['title'] ); ?></h3>
		<p class="ebcr-sim__lead"><?php esc_html_e( 'Ajuste o valor, o prazo e a carência para estimar as parcelas. Os números são ilustrativos.', 'eb-credito-rural' ); ?></p>
	</div>
	<div class="ebcr-sim__grid">
		<form class="ebcr-sim__form" novalidate autocomplete="off" aria-label="<?php esc_attr_e( 'Parâmetros da simulação', 'eb-credito-rural' ); ?>">
			<div class="ebcr-sim__field">
				<label for="<?php echo esc_attr( $uid ); ?>-valor"><?php esc_html_e( 'Valor do crédito', 'eb-credito-rural' ); ?></label>
				<div class="ebcr-sim__amount">
					<span class="ebcr-sim__prefix" aria-hidden="true">R$</span>
					<input type="text" inputmode="numeric" id="<?php echo esc_attr( $uid ); ?>-valor" class="ebcr-sim__input" data-sim="amount" value="<?php echo esc_attr( number_format( $cfg['amount'], 2, ',', '.' ) ); ?>" aria-describedby="<?php echo esc_attr( $uid ); ?>-faixa">
				</div>
				<input type="range" class="ebcr-sim__range" data-sim="amount-range" min="<?php echo esc_attr( $cfg['min'] ); ?>" max="<?php echo esc_attr( $cfg['max'] ); ?>" step="1000" value="<?php echo esc_attr( $cfg['amount'] ); ?>" aria-label="<?php esc_attr_e( 'Valor do crédito (controle deslizante)', 'eb-credito-rural' ); ?>">
				<div class="ebcr-sim__range-labels" id="<?php echo esc_attr( $uid ); ?>-faixa"><span><?php echo esc_html( Helpers::money( $cfg['min'] ) ); ?></span><span><?php echo esc_html( Helpers::money( $cfg['max'] ) ); ?></span></div>
			</div>
			<div class="ebcr-sim__row">
				<div class="ebcr-sim__field">
					<label for="<?php echo esc_attr( $uid ); ?>-prazo"><?php esc_html_e( 'Prazo total (meses)', 'eb-credito-rural' ); ?></label>
					<input type="number" id="<?php echo esc_attr( $uid ); ?>-prazo" class="ebcr-sim__input" data-sim="months" min="1" max="<?php echo esc_attr( $cfg['max_term'] ); ?>" step="1" value="<?php echo esc_attr( $cfg['term'] ); ?>">
					<small class="ebcr-sim__help"><?php /* translators: %d: prazo máximo em meses */ printf( esc_html__( 'Até %d meses, incluindo a carência.', 'eb-credito-rural' ), (int) $cfg['max_term'] ); ?></small>
				</div>
				<div class="ebcr-sim__field">
					<label for="<?php echo esc_attr( $uid ); ?>-carencia"><?php esc_html_e( 'Carência (meses)', 'eb-credito-rural' ); ?></label>
					<input type="number" id="<?php echo esc_attr( $uid ); ?>-carencia" class="ebcr-sim__input" data-sim="grace" min="0" max="60" step="1" value="<?php echo esc_attr( $cfg['grace'] ); ?>">
					<small class="ebcr-sim__help"><?php esc_html_e( 'Meses iniciais sem pagamento; os juros são incorporados ao saldo.', 'eb-credito-rural' ); ?></small>
				</div>
			</div>
			<fieldset class="ebcr-sim__system">
				<legend><?php esc_html_e( 'Sistema de amortização', 'eb-credito-rural' ); ?></legend>
				<label class="ebcr-sim__radio"><input type="radio" name="<?php echo esc_attr( $uid ); ?>-sistema" value="price" data-sim="system" <?php checked( 'price', $cfg['system'] ); ?>> <span><strong>Price</strong> — <?php esc_html_e( 'parcelas iguais', 'eb-credito-rural' ); ?></span></label>
				<label class="ebcr-sim__radio"><input type="radio" name="<?php echo esc_attr( $uid ); ?>-sistema" value="sac" data-sim="system" <?php checked( 'sac', $cfg['system'] ); ?>> <span><strong>SAC</strong> — <?php esc_html_e( 'parcelas decrescentes', 'eb-credito-rural' ); ?></span></label>
			</fieldset>
			<p class="ebcr-sim__rate"><?php esc_html_e( 'Taxa de referência:', 'eb-credito-rural' ); ?> <strong><?php echo esc_html( Simulator::percent( $cfg['rate'] ) ); ?> <?php esc_html_e( 'ao ano', 'eb-credito-rural' ); ?></strong> <span class="ebcr-sim__muted">(<?php echo esc_html( Simulator::percent( $result['monthly_rate'] * 100 ) ); ?> <?php esc_html_e( 'ao mês', 'eb-credito-rural' ); ?>)</span></p>
		</form>
		<div class="ebcr-sim__results" aria-live="polite">
			<div class="ebcr-sim__stats">
				<div class="ebcr-sim__stat ebcr-sim__stat--main"><span><?php esc_html_e( 'Parcela inicial', 'eb-credito-rural' ); ?></span><strong data-sim="first"><?php echo esc_html( Helpers::money( $result['first'] ) ); ?></strong></div>
				<div class="ebcr-sim__stat"><span><?php esc_html_e( 'Parcela final', 'eb-credito-rural' ); ?></span><strong data-sim="last"><?php echo esc_html( Helpers::money( $result['last'] ) ); ?></strong></div>
				<div class="ebcr-sim__stat"><span><?php esc_html_e( 'Total de juros', 'eb-credito-rural' ); ?></span><strong data-sim="interest"><?php echo esc_html( Helpers::money( $result['total_interest'] ) ); ?></strong></div>
				<div class="ebcr-sim__stat"><span><?php esc_html_e( 'Total pago', 'eb-credito-rural' ); ?></span><strong data-sim="total"><?php echo esc_html( Helpers::money( $result['total_paid'] ) ); ?></strong></div>
				<div class="ebcr-sim__stat"><span><?php esc_html_e( 'CET aproximado', 'eb-credito-rural' ); ?></span><strong data-sim="cet"><?php echo esc_html( Simulator::percent( $result['cet'] ) ); ?> a.a.</strong><small><?php esc_html_e( 'apenas juros; sem tarifas ou seguros', 'eb-credito-rural' ); ?></small></div>
			</div>
			<div class="ebcr-sim__table-wrap">
				<table class="ebcr-sim__table">
					<caption class="ebcr-sim__sr"><?php esc_html_e( 'Parcelas da simulação', 'eb-credito-rural' ); ?></caption>
					<thead><tr><th scope="col"><?php esc_html_e( 'Mês', 'eb-credito-rural' ); ?></th><th scope="col"><?php esc_html_e( 'Parcela', 'eb-credito-rural' ); ?></th><th scope="col"><?php esc_html_e( 'Juros', 'eb-credito-rural' ); ?></th><th scope="col"><?php esc_html_e( 'Amortização', 'eb-credito-rural' ); ?></th><th scope="col"><?php esc_html_e( 'Saldo devedor', 'eb-credito-rural' ); ?></th></tr></thead>
					<tbody data-sim="rows">
					<?php foreach ( $ebcr_rows as $ebcr_i => $ebcr_r ) : ?>
						<tr<?php echo $ebcr_i >= $ebcr_preview ? ' hidden class="is-extra"' : ''; ?><?php echo ! empty( $ebcr_r['carencia'] ) ? ' data-grace="1"' : ''; ?>>
							<td data-label="<?php esc_attr_e( 'Mês', 'eb-credito-rural' ); ?>"><?php echo (int) $ebcr_r['n']; ?><?php echo ! empty( $ebcr_r['carencia'] ) ? ' <em>' . esc_html__( 'carência', 'eb-credito-rural' ) . '</em>' : ''; ?></td>
							<td data-label="<?php esc_attr_e( 'Parcela', 'eb-credito-rural' ); ?>"><?php echo esc_html( Helpers::money( $ebcr_r['parcela'] ) ); ?></td>
							<td data-label="<?php esc_attr_e( 'Juros', 'eb-credito-rural' ); ?>"><?php echo esc_html( Helpers::money( $ebcr_r['juros'] ) ); ?></td>
							<td data-label="<?php esc_attr_e( 'Amortização', 'eb-credito-rural' ); ?>"><?php echo esc_html( Helpers::money( $ebcr_r['amortizacao'] ) ); ?></td>
							<td data-label="<?php esc_attr_e( 'Saldo devedor', 'eb-credito-rural' ); ?>"><?php echo esc_html( Helpers::money( $ebcr_r['saldo'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<button type="button" class="ebcr-sim__more" data-sim="toggle" aria-expanded="false"<?php echo count( $ebcr_rows ) <= $ebcr_preview ? ' hidden' : ''; ?> data-label-more="<?php esc_attr_e( 'Ver todas as parcelas', 'eb-credito-rural' ); ?>" data-label-less="<?php esc_attr_e( 'Mostrar só as primeiras', 'eb-credito-rural' ); ?>"><span data-sim="toggle-label"><?php esc_html_e( 'Ver todas as parcelas', 'eb-credito-rural' ); ?></span> (<span data-sim="count"><?php echo count( $ebcr_rows ); ?></span>)</button>
			<p class="ebcr-sim__disclaimer"><?php esc_html_e( 'Simulação ilustrativa; as condições reais (taxa, prazo, carência e garantias) dependem da análise de crédito e da linha contratada.', 'eb-credito-rural' ); ?></p>
			<a class="ebcr-sim__cta" href="<?php echo esc_url( $cfg['url'] ); ?>"><?php echo esc_html( $cfg['button'] ); ?></a>
		</div>
	</div>
</div>
</div>
