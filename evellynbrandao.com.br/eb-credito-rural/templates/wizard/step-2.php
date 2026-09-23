<?php
/**
 * Etapa 2 — imóveis (repetível). Variáveis: $s, $data, $errors, $add.
 *
 * @package EBCR
 */

use EBCR\Forms\Steps;
use EBCR\Frontend\Fields;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_items = isset( $data['imoveis'] ) && is_array( $data['imoveis'] ) ? array_values( $data['imoveis'] ) : array();
if ( 'imoveis' === $add || ! $ebcr_items ) {
	$ebcr_items[] = array();
}
$ebcr_e   = static function ( $k ) use ( $errors ) {
	return Fields::error( $errors, $k );
};
$ebcr_row = static function ( $i, array $it ) use ( $ebcr_e ) {
	$v    = static function ( $k ) use ( $it ) {
		return isset( $it[ $k ] ) && null !== $it[ $k ] ? $it[ $k ] : '';
	};
	$p    = "imoveis[{$i}]";
	$out  = '<div class="ebcr-repeat-row ebcr-property"><h4>' . esc_html( sprintf( /* translators: %d: número */ __( 'Imóvel %d', 'eb-credito-rural' ), (int) $i + 1 ) ) . '</h4>';
	$out .= '<input type="hidden" name="' . esc_attr( $p . '[id]' ) . '" value="' . esc_attr( (string) $v( 'id' ) ) . '">';
	$out .= '<div class="ebcr-row">' . ebcr_input(
		$p . '[name]',
		__( 'Nome do imóvel / fazenda', 'eb-credito-rural' ),
		$v( 'name' ),
		array(
			'required'  => true,
			'maxlength' => 190,
		),
		$ebcr_e( "imoveis.{$i}.name" )
	)
		. ebcr_input(
			$p . '[city]',
			__( 'Município', 'eb-credito-rural' ),
			$v( 'city' ),
			array(
				'required'  => true,
				'maxlength' => 120,
			),
			$ebcr_e( "imoveis.{$i}.city" )
		)
		. ebcr_select( $p . '[uf]', __( 'UF', 'eb-credito-rural' ), Helpers::ufs(), $v( 'uf' ), array( 'required' => true ), $ebcr_e( "imoveis.{$i}.uf" ) ) . '</div>';
	$out .= '<div class="ebcr-row">' . ebcr_input(
		$p . '[registration_number]',
		__( 'Matrícula nº', 'eb-credito-rural' ),
		$v( 'registration_number' ),
		array(
			'required'  => true,
			'maxlength' => 60,
		),
		$ebcr_e( "imoveis.{$i}.registration_number" )
	)
		. ebcr_input(
			$p . '[registry_office]',
			__( 'Cartório de registro de imóveis', 'eb-credito-rural' ),
			$v( 'registry_office' ),
			array(
				'required'  => true,
				'maxlength' => 190,
			),
			$ebcr_e( "imoveis.{$i}.registry_office" )
		) . '</div>';
	$out .= '<div class="ebcr-row">' . ebcr_input(
		$p . '[total_area]',
		__( 'Área total (ha)', 'eb-credito-rural' ),
		$v( 'total_area' ),
		array(
			'required'  => true,
			'inputmode' => 'decimal',
			'data-mask' => 'decimal',
		),
		$ebcr_e( "imoveis.{$i}.total_area" )
	)
		. ebcr_input(
			$p . '[usable_area]',
			__( 'Área útil / agricultável (ha)', 'eb-credito-rural' ),
			$v( 'usable_area' ),
			array(
				'required'  => true,
				'inputmode' => 'decimal',
				'data-mask' => 'decimal',
			),
			$ebcr_e( "imoveis.{$i}.usable_area" )
		) . '</div>';
	$out .= '<div class="ebcr-row">' . ebcr_input(
		$p . '[car_code]',
		__( 'Código do CAR', 'eb-credito-rural' ),
		$v( 'car_code' ),
		array(
			'required'      => true,
			'maxlength'     => 60,
			'data-validate' => 'car',
			'placeholder'   => 'GO-5201405-1A2B.3C4D.5E6F.7A8B.9C0D.1E2F.3A4B.5C6D',
		),
		$ebcr_e( "imoveis.{$i}.car_code" ),
		__( 'Formato UF-XXXXXXX-XXXX.XXXX.XXXX.XXXX.XXXX.XXXX.XXXX.XXXX (recibo do CAR).', 'eb-credito-rural' )
	) . '</div>';
	$out .= '<div class="ebcr-row">' . ebcr_input( $p . '[ccir]', __( 'CCIR', 'eb-credito-rural' ), $v( 'ccir' ), array( 'maxlength' => 60 ), $ebcr_e( "imoveis.{$i}.ccir" ) )
		. ebcr_input( $p . '[nirf]', __( 'NIRF / CIB', 'eb-credito-rural' ), $v( 'nirf' ), array( 'maxlength' => 60 ), $ebcr_e( "imoveis.{$i}.nirf" ) )
		. ebcr_input( $p . '[sigef]', __( 'Certificação SIGEF', 'eb-credito-rural' ), $v( 'sigef' ), array( 'maxlength' => 80 ), $ebcr_e( "imoveis.{$i}.sigef" ), __( 'Se georreferenciado.', 'eb-credito-rural' ) ) . '</div>';
	$out .= '<div class="ebcr-row">' . ebcr_select(
		$p . '[tenure]',
		__( 'Condição', 'eb-credito-rural' ),
		Steps::options( 'tenure' ),
		$v( 'tenure' ) ? $v( 'tenure' ) : 'propria',
		array(
			'required'    => true,
			'data-toggle' => 'lease',
		),
		$ebcr_e( "imoveis.{$i}.tenure" )
	)
		. '<div data-show-if="' . esc_attr( $p . '[tenure]=arrendada,parceria' ) . '">' . ebcr_input( $p . '[lease_end]', __( 'Término do contrato', 'eb-credito-rural' ), $v( 'lease_end' ), array( 'type' => 'date' ), $ebcr_e( "imoveis.{$i}.lease_end" ), __( 'Alertaremos se terminar antes do prazo do financiamento.', 'eb-credito-rural' ) ) . '</div></div>';
	$out .= '<button type="button" class="ebcr-btn ebcr-btn--small ebcr-btn--ghost" data-remove-row>' . esc_html__( 'Remover imóvel', 'eb-credito-rural' ) . '</button></div>';
	return $out;
};
?>
<form class="ebcr-form" method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>" novalidate data-ebcr-step="2">
	<?php echo ebcr_error_summary( $errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<input type="hidden" name="action" value="ebcr_wizard_step"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>"><input type="hidden" name="etapa" value="2">
	<?php echo ebcr_nonce_field( 'wizard' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<p class="ebcr-muted"><?php esc_html_e( 'Informe todos os imóveis envolvidos na atividade e nas garantias. Os documentos de cada imóvel serão pedidos na etapa 6.', 'eb-credito-rural' ); ?></p>
	<div class="ebcr-repeat" data-repeat="imoveis">
		<?php foreach ( $ebcr_items as $ebcr_i => $ebcr_it ) : ?>
			<?php echo $ebcr_row( $ebcr_i, (array) $ebcr_it ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endforeach; ?>
		<template data-repeat-template><?php echo str_replace( 'imoveis[' . count( $ebcr_items ) . ']', 'imoveis[__i__]', $ebcr_row( count( $ebcr_items ), array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<button type="submit" name="ebcr_add" value="imoveis" class="ebcr-btn ebcr-btn--small" data-add-row formnovalidate><?php esc_html_e( '+ Adicionar outro imóvel', 'eb-credito-rural' ); ?></button>
	</div>
	<?php
	\EBCR\Support\View::show(
		'wizard/nav',
		array(
			's'    => $s,
			'step' => 2,
		)
	);
	?>
</form>
