<?php
/**
 * Etapa 5 — garantias (repetível). Variáveis: $s, $data, $errors, $add, $saved, $wizard, $context.
 * Exigência conforme Configurações → Bens e garantias (obrigatória, opcional — com "sem garantia a oferecer" —
 * ou exigida pela finalidade escolhida na etapa 4).
 *
 * @package EBCR
 */

use EBCR\Forms\Steps;
use EBCR\Forms\SubmissionRules;
use EBCR\Frontend\Fields;

defined( 'ABSPATH' ) || exit;
$ebcr_ctx      = isset( $context ) && is_array( $context ) ? $context : $wizard->rules_context( $s, $saved );
$ebcr_required = SubmissionRules::guarantees_required( $ebcr_ctx );
$ebcr_reason   = SubmissionRules::guarantees_reason( $ebcr_ctx );
$ebcr_items    = isset( $data['garantias'] ) && is_array( $data['garantias'] ) ? array_values( $data['garantias'] ) : array();
$ebcr_answer   = isset( $data['oferece_garantia'] ) && in_array( $data['oferece_garantia'], array( 'sim', 'nao' ), true ) ? $data['oferece_garantia'] : ( $ebcr_items ? 'sim' : '' );
if ( 'garantias' === $add || ! $ebcr_items ) {
	$ebcr_items[] = array();
}
if ( 'garantias' === $add ) {
	$ebcr_answer = 'sim';
}
$ebcr_props = array();
if ( SubmissionRules::assets_active( $ebcr_ctx ) ) {
	foreach ( $saved['imoveis']['imoveis'] as $ebcr_p ) {
		$ebcr_props[ (int) $ebcr_p['id'] ] = $ebcr_p['name'] . ' — ' . $ebcr_p['city'] . '/' . $ebcr_p['uf'];
	}
}
$ebcr_purposes = Steps::options( 'purposes' );
$ebcr_purpose  = isset( $ebcr_ctx['purpose'] ) ? (string) $ebcr_ctx['purpose'] : '';
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
		. ( $ebcr_props
			? '<div data-show-if="' . esc_attr( $p . '[type]=' . $ebcr_real ) . '">' . ebcr_select( $p . '[property_id]', __( 'Imóvel vinculado', 'eb-credito-rural' ), $ebcr_props, $v( 'property_id' ), array(), $ebcr_e( "garantias.{$i}.property_id" ), __( 'Para garantias reais, escolha o imóvel informado na etapa de imóveis rurais.', 'eb-credito-rural' ) ) . '</div>'
			: '<p class="ebcr-help" data-show-if="' . esc_attr( $p . '[type]=' . $ebcr_real ) . '">' . esc_html__( 'Para garantias reais, informe na descrição a matrícula, o cartório e o município do imóvel.', 'eb-credito-rural' ) . '</p>' )
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
	<?php if ( $ebcr_required ) : ?>
		<p class="ebcr-muted"><?php esc_html_e( 'Informe as garantias que pode oferecer. A equipe avaliará cada uma e poderá pedir laudo de avaliação.', 'eb-credito-rural' ); ?></p>
		<?php if ( 'modality' === $ebcr_reason ) : ?>
			<p class="ebcr-alert ebcr-alert--info" role="note"><?php /* translators: %s: finalidade */ printf( esc_html__( 'Para a finalidade "%s", é obrigatório informar ao menos uma garantia.', 'eb-credito-rural' ), esc_html( isset( $ebcr_purposes[ $ebcr_purpose ] ) ? $ebcr_purposes[ $ebcr_purpose ] : $ebcr_purpose ) ); ?></p>
		<?php else : ?>
			<p class="ebcr-muted ebcr-small"><?php esc_html_e( 'Obrigatório: informe ao menos uma garantia.', 'eb-credito-rural' ); ?></p>
		<?php endif; ?>
	<?php else : ?>
		<p class="ebcr-muted"><?php esc_html_e( 'Oferecer garantia é opcional nesta solicitação. Se tiver bens ou recebíveis para oferecer, informe-os: a equipe avaliará cada um e poderá pedir laudo de avaliação.', 'eb-credito-rural' ); ?></p>
		<?php
		echo ebcr_radios( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado no helper.
			'oferece_garantia',
			__( 'Você tem garantia a oferecer?', 'eb-credito-rural' ),
			array(
				'sim' => __( 'Sim, quero informar garantias', 'eb-credito-rural' ),
				'nao' => __( 'Não tenho garantia a oferecer', 'eb-credito-rural' ),
			),
			$ebcr_answer,
			Fields::error( $errors, 'oferece_garantia' ),
			'',
			true
		);
		?>
		<div data-show-if="oferece_garantia=sim">
	<?php endif; ?>
	<div class="ebcr-repeat" data-repeat="garantias" data-min-rows="1" data-min-rows-message="<?php echo esc_attr( $ebcr_required ? __( 'Informe pelo menos uma garantia.', 'eb-credito-rural' ) : __( 'Informe pelo menos uma garantia ou marque que não tem garantia a oferecer.', 'eb-credito-rural' ) ); ?>" data-min-rows-key="garantias">
		<?php
		if ( ! empty( $errors['garantias'] ) ) :
			?>
			<p class="ebcr-error" role="alert"><?php echo esc_html( $errors['garantias'] ); ?></p><?php endif; ?>
		<?php foreach ( $ebcr_items as $ebcr_i => $ebcr_it ) : ?>
			<?php echo $ebcr_row( $ebcr_i, (array) $ebcr_it ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endforeach; ?>
		<template data-repeat-template><?php echo str_replace( 'garantias[' . count( $ebcr_items ) . ']', 'garantias[__i__]', $ebcr_row( count( $ebcr_items ), array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<button type="submit" name="ebcr_add" value="garantias" class="ebcr-btn ebcr-btn--small" data-add-row formnovalidate><?php esc_html_e( '+ Adicionar garantia', 'eb-credito-rural' ); ?></button>
	</div>
	<?php if ( ! $ebcr_required ) : ?>
		</div>
	<?php endif; ?>
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
