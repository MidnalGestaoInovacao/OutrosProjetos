<?php
/**
 * Etapa 4 — situação financeira. Variáveis: $s, $data, $errors, $add.
 *
 * @package EBCR
 */

use EBCR\Forms\Steps;
use EBCR\Frontend\Fields;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;
$ebcr_v     = static function ( $k ) use ( $data ) {
	return Fields::value( $data, $k );
};
$ebcr_e     = static function ( $k ) use ( $errors ) {
	return Fields::error( $errors, $k );
};
$ebcr_debts = isset( $data['dividas'] ) && is_array( $data['dividas'] ) ? array_values( $data['dividas'] ) : array();
if ( 'dividas' === $add || ! $ebcr_debts ) {
	$ebcr_debts[] = array();
}
$ebcr_year = (int) wp_date( 'Y' );
$ebcr_per  = array(
	'mensal'    => __( 'Mensal', 'eb-credito-rural' ),
	'semestral' => __( 'Semestral', 'eb-credito-rural' ),
	'anual'     => __( 'Anual', 'eb-credito-rural' ),
);
?>
<form class="ebcr-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate data-ebcr-step="4">
	<?php echo ebcr_error_summary( $errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<input type="hidden" name="action" value="ebcr_wizard_step"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>"><input type="hidden" name="etapa" value="4">
	<?php echo ebcr_nonce_field( 'wizard' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<fieldset class="ebcr-group"><legend><?php esc_html_e( 'Receita bruta anual', 'eb-credito-rural' ); ?></legend>
		<div class="ebcr-row">
			<?php
			echo ebcr_input(
				'receita_ano1',
				sprintf( /* translators: %d: ano */ __( 'Receita %d (R$)', 'eb-credito-rural' ), $ebcr_year - 1 ),
				$ebcr_v( 'receita_ano1' ),
				array(
					'required'  => true,
					'inputmode' => 'decimal',
					'data-mask' => 'money',
				),
				$ebcr_e( 'receita_ano1' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<?php
			echo ebcr_input(
				'receita_ano2',
				sprintf( /* translators: %d: ano */ __( 'Receita %d (R$)', 'eb-credito-rural' ), $ebcr_year - 2 ),
				$ebcr_v( 'receita_ano2' ),
				array(
					'inputmode' => 'decimal',
					'data-mask' => 'money',
				),
				$ebcr_e( 'receita_ano2' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<?php
			echo ebcr_input(
				'receita_ano3',
				sprintf( /* translators: %d: ano */ __( 'Receita %d (R$)', 'eb-credito-rural' ), $ebcr_year - 3 ),
				$ebcr_v( 'receita_ano3' ),
				array(
					'inputmode' => 'decimal',
					'data-mask' => 'money',
				),
				$ebcr_e( 'receita_ano3' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
		</div>
	</fieldset>
	<fieldset class="ebcr-group"><legend><?php esc_html_e( 'Dívidas existentes', 'eb-credito-rural' ); ?></legend>
		<p class="ebcr-muted ebcr-small"><?php esc_html_e( 'Inclua financiamentos rurais, empréstimos bancários, dívidas com fornecedores e cooperativas. Deixe em branco se não houver.', 'eb-credito-rural' ); ?></p>
		<div class="ebcr-repeat" data-repeat="dividas">
		<?php foreach ( $ebcr_debts as $ebcr_i => $ebcr_d ) : ?>
			<div class="ebcr-repeat-row">
				<div class="ebcr-row">
				<?php echo ebcr_input( "dividas[{$ebcr_i}][credor]", __( 'Credor', 'eb-credito-rural' ), isset( $ebcr_d['credor'] ) ? $ebcr_d['credor'] : '', array( 'maxlength' => 190 ), $ebcr_e( "dividas.{$ebcr_i}.credor" ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php
				echo ebcr_input(
					"dividas[{$ebcr_i}][saldo]",
					__( 'Saldo devedor (R$)', 'eb-credito-rural' ),
					isset( $ebcr_d['saldo'] ) ? $ebcr_d['saldo'] : '',
					array(
						'inputmode' => 'decimal',
						'data-mask' => 'money',
					),
					$ebcr_e( "dividas.{$ebcr_i}.saldo" )
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
				?>
				<?php
				echo ebcr_input(
					"dividas[{$ebcr_i}][parcela]",
					__( 'Parcela (R$)', 'eb-credito-rural' ),
					isset( $ebcr_d['parcela'] ) ? $ebcr_d['parcela'] : '',
					array(
						'inputmode' => 'decimal',
						'data-mask' => 'money',
					),
					$ebcr_e( "dividas.{$ebcr_i}.parcela" )
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
				?>
				</div>
				<div class="ebcr-row">
				<?php echo ebcr_select( "dividas[{$ebcr_i}][periodicidade]", __( 'Periodicidade', 'eb-credito-rural' ), $ebcr_per, isset( $ebcr_d['periodicidade'] ) ? $ebcr_d['periodicidade'] : '', array(), $ebcr_e( "dividas.{$ebcr_i}.periodicidade" ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo ebcr_input( "dividas[{$ebcr_i}][vencimento]", __( 'Vencimento final', 'eb-credito-rural' ), isset( $ebcr_d['vencimento'] ) ? $ebcr_d['vencimento'] : '', array( 'type' => 'date' ), $ebcr_e( "dividas.{$ebcr_i}.vencimento" ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo ebcr_input( "dividas[{$ebcr_i}][garantia]", __( 'Garantia dada', 'eb-credito-rural' ), isset( $ebcr_d['garantia'] ) ? $ebcr_d['garantia'] : '', array( 'maxlength' => 190 ), $ebcr_e( "dividas.{$ebcr_i}.garantia" ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<button type="button" class="ebcr-btn ebcr-btn--small ebcr-btn--ghost" data-remove-row><?php esc_html_e( 'Remover dívida', 'eb-credito-rural' ); ?></button>
			</div>
		<?php endforeach; ?>
			<template data-repeat-template><div class="ebcr-repeat-row"><div class="ebcr-row">
			<?php
			echo ebcr_input(
				'dividas[__i__][credor]',
				__( 'Credor', 'eb-credito-rural' ),
				'',
				array(
					'maxlength' => 190,
					'id'        => 'ebcr-dv-__i__-c',
				)
			) . ebcr_input(
				'dividas[__i__][saldo]',
				__( 'Saldo devedor (R$)', 'eb-credito-rural' ),
				'',
				array(
					'inputmode' => 'decimal',
					'data-mask' => 'money',
					'id'        => 'ebcr-dv-__i__-s',
				)
			) . ebcr_input(
				'dividas[__i__][parcela]',
				__( 'Parcela (R$)', 'eb-credito-rural' ),
				'',
				array(
					'inputmode' => 'decimal',
					'data-mask' => 'money',
					'id'        => 'ebcr-dv-__i__-p',
				)
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			</div><div class="ebcr-row">
<?php
echo ebcr_select( 'dividas[__i__][periodicidade]', __( 'Periodicidade', 'eb-credito-rural' ), $ebcr_per, '' ) . ebcr_input(
	'dividas[__i__][vencimento]',
	__( 'Vencimento final', 'eb-credito-rural' ),
	'',
	array(
		'type' => 'date',
		'id'   => 'ebcr-dv-__i__-v',
	)
) . ebcr_input(
	'dividas[__i__][garantia]',
	__( 'Garantia dada', 'eb-credito-rural' ),
	'',
	array(
		'maxlength' => 190,
		'id'        => 'ebcr-dv-__i__-g',
	)
); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
?>
</div><button type="button" class="ebcr-btn ebcr-btn--small ebcr-btn--ghost" data-remove-row><?php esc_html_e( 'Remover dívida', 'eb-credito-rural' ); ?></button></div></template>
			<button type="submit" name="ebcr_add" value="dividas" class="ebcr-btn ebcr-btn--small" data-add-row formnovalidate><?php esc_html_e( '+ Adicionar dívida', 'eb-credito-rural' ); ?></button>
		</div>
	</fieldset>
	<fieldset class="ebcr-group"><legend><?php esc_html_e( 'Crédito solicitado', 'eb-credito-rural' ); ?></legend>
		<div class="ebcr-row">
			<?php
			echo ebcr_input(
				'valor_solicitado',
				__( 'Valor solicitado (R$)', 'eb-credito-rural' ),
				$ebcr_v( 'valor_solicitado' ),
				array(
					'required'  => true,
					'inputmode' => 'decimal',
					'data-mask' => 'money',
				),
				$ebcr_e( 'valor_solicitado' ),
				sprintf( /* translators: 1: mínimo, 2: máximo */ __( 'Entre %1$s e %2$s.', 'eb-credito-rural' ), \EBCR\Support\Helpers::money( Options::get( 'min_amount' ) ), \EBCR\Support\Helpers::money( Options::get( 'max_amount' ) ) )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<?php echo ebcr_select( 'finalidade', __( 'Finalidade', 'eb-credito-rural' ), Steps::options( 'purposes' ), $ebcr_v( 'finalidade' ), array( 'required' => true ), $ebcr_e( 'finalidade' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
			echo ebcr_input(
				'prazo_meses',
				__( 'Prazo desejado (meses)', 'eb-credito-rural' ),
				$ebcr_v( 'prazo_meses' ),
				array(
					'type'     => 'number',
					'required' => true,
					'min'      => Options::int( 'min_term_months' ),
					'max'      => Options::int( 'max_term_months' ),
					'step'     => 1,
				),
				$ebcr_e( 'prazo_meses' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
		</div>
		<div class="ebcr-row">
			<?php echo ebcr_select( 'epoca_pagamento', __( 'Época preferida de pagamento', 'eb-credito-rural' ), Steps::options( 'epoca_pagamento' ), $ebcr_v( 'epoca_pagamento' ), array( 'required' => true ), $ebcr_e( 'epoca_pagamento' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div data-show-if="epoca_pagamento=outro"><?php echo ebcr_input( 'epoca_detalhes', __( 'Detalhes', 'eb-credito-rural' ), $ebcr_v( 'epoca_detalhes' ), array( 'maxlength' => 500 ), $ebcr_e( 'epoca_detalhes' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
	</fieldset>
	<?php
	\EBCR\Support\View::show(
		'wizard/nav',
		array(
			's'    => $s,
			'step' => 4,
		)
	);
	?>
</form>
