<?php
/**
 * Etapa 5 — garantias (repetível). Variáveis: $s, $data, $errors, $add, $saved.
 *
 * @package EBCR
 */

use EBCR\Forms\Steps;
use EBCR\Frontend\Fields;

defined( 'ABSPATH' ) || exit;
$ebcr_items = isset( $data['garantias'] ) && is_array( $data['garantias'] ) ? array_values( $data['garantias'] ) : array();
if ( 'garantias' === $add || ! $ebcr_items ) {
	$ebcr_items[] = array();
}
$ebcr_props = array();
foreach ( $saved['imoveis']['imoveis'] as $ebcr_p ) {
	$ebcr_props[ (int) $ebcr_p['id'] ] = $ebcr_p['name'] . ' — ' . $ebcr_p['city'] . '/' . $ebcr_p['uf'];
}
$ebcr_e    = static function ( $k ) use ( $errors ) {
	return Fields::error( $errors, $k );
};
$ebcr_real = implode( ',', Steps::options( 'real_guarantees' ) );
$ebcr_row  = static function ( $i, array $it ) use ( $ebcr_e, $ebcr_props, $ebcr_real ) {
	$v = static function ( $k ) use ( $it ) {
		return isset( $it[ $k ] ) && null !== $it[ $k ] ? $it[ $k ] : '';
	};
	$p = "garantias[{$i}]";
	return '<div class="ebcr-repeat-row ebcr-guarantee"><h4>' . esc_html( sprintf( /* translators: %d: número */ __( 'Garantia %d', 'eb-credito-rural' ), (int) $i + 1 ) ) . '</h4>'
		. '<div class="ebcr-row">' . ebcr_select(
			$p . '[type]',
			__( 'Tipo', 'eb-credito-rural' ),
			Steps::options( 'guarantee_types' ),
			$v( 'type' ),
			array(
				'required'    => true,
				'data-toggle' => 'gtype',
			),
			$ebcr_e( "garantias.{$i}.type" )
		)
		. ebcr_input(
			$p . '[declared_value]',
			__( 'Valor declarado (R$)', 'eb-credito-rural' ),
			$v( 'declared_value' ),
			array(
				'required'  => true,
				'inputmode' => 'decimal',
				'data-mask' => 'money',
			),
			$ebcr_e( "garantias.{$i}.declared_value" )
		) . '</div>'
		. '<div data-show-if="' . esc_attr( $p . '[type]=' . $ebcr_real ) . '">' . ebcr_select( $p . '[property_id]', __( 'Imóvel vinculado', 'eb-credito-rural' ), $ebcr_props, $v( 'property_id' ), array(), $ebcr_e( "garantias.{$i}.property_id" ), __( 'Para garantias reais, escolha o imóvel informado na etapa 2.', 'eb-credito-rural' ) ) . '</div>'
		. ebcr_textarea(
			$p . '[description]',
			__( 'Descrição (bem, localização, situação, ônus existentes)', 'eb-credito-rural' ),
			$v( 'description' ),
			array(
				'required'  => true,
				'rows'      => 3,
				'maxlength' => 2000,
			),
			$ebcr_e( "garantias.{$i}.description" )
		)
		. '<button type="button" class="ebcr-btn ebcr-btn--small ebcr-btn--ghost" data-remove-row>' . esc_html__( 'Remover garantia', 'eb-credito-rural' ) . '</button></div>';
};
?>
<form class="ebcr-form" method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>" novalidate data-ebcr-step="5">
	<?php echo ebcr_error_summary( $errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<input type="hidden" name="action" value="ebcr_wizard_step"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>"><input type="hidden" name="etapa" value="5">
	<?php echo ebcr_nonce_field( 'wizard' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<p class="ebcr-muted"><?php esc_html_e( 'Informe as garantias que pode oferecer. A equipe avaliará cada uma e poderá pedir laudo de avaliação.', 'eb-credito-rural' ); ?></p>
	<div class="ebcr-repeat" data-repeat="garantias">
		<?php foreach ( $ebcr_items as $ebcr_i => $ebcr_it ) : ?>
			<?php echo $ebcr_row( $ebcr_i, (array) $ebcr_it ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endforeach; ?>
		<template data-repeat-template><?php echo str_replace( 'garantias[' . count( $ebcr_items ) . ']', 'garantias[__i__]', $ebcr_row( count( $ebcr_items ), array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<button type="submit" name="ebcr_add" value="garantias" class="ebcr-btn ebcr-btn--small" data-add-row formnovalidate><?php esc_html_e( '+ Adicionar garantia', 'eb-credito-rural' ); ?></button>
	</div>
	<?php
	\EBCR\Support\View::show(
		'wizard/nav',
		array(
			's'    => $s,
			'step' => 5,
		)
	);
	?>
</form>
