<?php
/**
 * Etapa 3 — atividade produtiva. Variáveis: $s, $data, $errors, $add, $saved.
 *
 * @package EBCR
 */

use EBCR\Forms\Steps;
use EBCR\Frontend\Fields;

defined( 'ABSPATH' ) || exit;
$ebcr_v      = static function ( $k ) use ( $data ) {
	return Fields::value( $data, $k );
};
$ebcr_e      = static function ( $k ) use ( $errors ) {
	return Fields::error( $errors, $k );
};
$ebcr_rows   = static function ( $group, $min = 1 ) use ( $data, $add ) {
	$items = isset( $data[ $group ] ) && is_array( $data[ $group ] ) ? array_values( $data[ $group ] ) : array();
	if ( $group === $add || count( $items ) < $min ) {
		$items[] = array();
	}
	return $items;
};
$ebcr_usable = 0;
foreach ( $saved['imoveis']['imoveis'] as $ebcr_p ) {
	$ebcr_usable += (float) $ebcr_p['usable_area'];
}
?>
<form class="ebcr-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate data-ebcr-step="3" data-usable-area="<?php echo esc_attr( (string) $ebcr_usable ); ?>">
	<?php echo ebcr_error_summary( $errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<input type="hidden" name="action" value="ebcr_wizard_step"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>"><input type="hidden" name="etapa" value="3">
	<?php echo ebcr_nonce_field( 'wizard' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php echo ebcr_checkboxes( 'atividades', __( 'Atividades exercidas', 'eb-credito-rural' ), Steps::options( 'activities' ), isset( $data['atividades'] ) ? (array) $data['atividades'] : array(), $ebcr_e( 'atividades' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div data-show-if="atividades=outras"><?php echo ebcr_input( 'atividade_outra', __( 'Qual outra atividade?', 'eb-credito-rural' ), $ebcr_v( 'atividade_outra' ), array( 'maxlength' => 190 ), $ebcr_e( 'atividade_outra' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>

	<fieldset class="ebcr-group"><legend><?php esc_html_e( 'Área plantada por cultura', 'eb-credito-rural' ); ?></legend>
		<p class="ebcr-muted ebcr-small"><?php /* translators: %s: hectares */ printf( esc_html__( 'Área útil declarada nos imóveis: %s ha. A soma das áreas por cultura não deve ultrapassá-la (aviso, não bloqueio).', 'eb-credito-rural' ), esc_html( number_format( $ebcr_usable, 2, ',', '.' ) ) ); ?></p>
		<div class="ebcr-repeat" data-repeat="area_cultura">
		<?php foreach ( $ebcr_rows( 'area_cultura' ) as $ebcr_i => $ebcr_it ) : ?>
			<div class="ebcr-repeat-row ebcr-row">
				<?php echo ebcr_input( "area_cultura[{$ebcr_i}][cultura]", __( 'Cultura', 'eb-credito-rural' ), isset( $ebcr_it['cultura'] ) ? $ebcr_it['cultura'] : '', array( 'maxlength' => 100 ), $ebcr_e( "area_cultura.{$ebcr_i}.cultura" ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php
				echo ebcr_input(
					"area_cultura[{$ebcr_i}][area_ha]",
					__( 'Área (ha)', 'eb-credito-rural' ),
					isset( $ebcr_it['area_ha'] ) ? $ebcr_it['area_ha'] : '',
					array(
						'inputmode' => 'decimal',
						'data-mask' => 'decimal',
						'data-sum'  => 'area',
					),
					$ebcr_e( "area_cultura.{$ebcr_i}.area_ha" )
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
				?>
				<button type="button" class="ebcr-btn ebcr-btn--small ebcr-btn--ghost" data-remove-row><?php esc_html_e( 'Remover', 'eb-credito-rural' ); ?></button>
			</div>
		<?php endforeach; ?>
			<template data-repeat-template><div class="ebcr-repeat-row ebcr-row">
			<?php
			echo ebcr_input(
				'area_cultura[__i__][cultura]',
				__( 'Cultura', 'eb-credito-rural' ),
				'',
				array(
					'maxlength' => 100,
					'id'        => 'ebcr-ac-__i__-c',
				)
			) . ebcr_input(
				'area_cultura[__i__][area_ha]',
				__( 'Área (ha)', 'eb-credito-rural' ),
				'',
				array(
					'inputmode' => 'decimal',
					'data-mask' => 'decimal',
					'data-sum'  => 'area',
					'id'        => 'ebcr-ac-__i__-a',
				)
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<button type="button" class="ebcr-btn ebcr-btn--small ebcr-btn--ghost" data-remove-row><?php esc_html_e( 'Remover', 'eb-credito-rural' ); ?></button></div></template>
			<button type="submit" name="ebcr_add" value="area_cultura" class="ebcr-btn ebcr-btn--small" data-add-row formnovalidate><?php esc_html_e( '+ Adicionar cultura', 'eb-credito-rural' ); ?></button>
			<p class="ebcr-warning" data-area-warning hidden><?php esc_html_e( 'Atenção: a soma das áreas por cultura ultrapassa a área útil declarada.', 'eb-credito-rural' ); ?></p>
		</div>
	</fieldset>

	<fieldset class="ebcr-group"><legend><?php esc_html_e( 'Produtividade das últimas safras (3 a 5)', 'eb-credito-rural' ); ?></legend>
		<div class="ebcr-repeat" data-repeat="produtividade">
		<?php foreach ( $ebcr_rows( 'produtividade' ) as $ebcr_i => $ebcr_it ) : ?>
			<div class="ebcr-repeat-row ebcr-row">
				<?php
				echo ebcr_input(
					"produtividade[{$ebcr_i}][safra]",
					__( 'Safra', 'eb-credito-rural' ),
					isset( $ebcr_it['safra'] ) ? $ebcr_it['safra'] : '',
					array(
						'maxlength'   => 20,
						'placeholder' => '2024/25',
					),
					$ebcr_e( "produtividade.{$ebcr_i}.safra" )
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
				?>
				<?php echo ebcr_input( "produtividade[{$ebcr_i}][cultura]", __( 'Cultura', 'eb-credito-rural' ), isset( $ebcr_it['cultura'] ) ? $ebcr_it['cultura'] : '', array( 'maxlength' => 100 ), $ebcr_e( "produtividade.{$ebcr_i}.cultura" ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php
				echo ebcr_input(
					"produtividade[{$ebcr_i}][valor]",
					__( 'Produtividade', 'eb-credito-rural' ),
					isset( $ebcr_it['valor'] ) ? $ebcr_it['valor'] : '',
					array(
						'inputmode' => 'decimal',
						'data-mask' => 'decimal',
					),
					$ebcr_e( "produtividade.{$ebcr_i}.valor" )
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
				?>
				<?php
				echo ebcr_input(
					"produtividade[{$ebcr_i}][unidade]",
					__( 'Unidade', 'eb-credito-rural' ),
					isset( $ebcr_it['unidade'] ) ? $ebcr_it['unidade'] : '',
					array(
						'maxlength'   => 20,
						'placeholder' => 'sc/ha',
					),
					$ebcr_e( "produtividade.{$ebcr_i}.unidade" )
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
				?>
				<button type="button" class="ebcr-btn ebcr-btn--small ebcr-btn--ghost" data-remove-row><?php esc_html_e( 'Remover', 'eb-credito-rural' ); ?></button>
			</div>
		<?php endforeach; ?>
			<template data-repeat-template><div class="ebcr-repeat-row ebcr-row">
			<?php
			echo ebcr_input(
				'produtividade[__i__][safra]',
				__( 'Safra', 'eb-credito-rural' ),
				'',
				array(
					'maxlength' => 20,
					'id'        => 'ebcr-pr-__i__-s',
				)
			) . ebcr_input(
				'produtividade[__i__][cultura]',
				__( 'Cultura', 'eb-credito-rural' ),
				'',
				array(
					'maxlength' => 100,
					'id'        => 'ebcr-pr-__i__-c',
				)
			) . ebcr_input(
				'produtividade[__i__][valor]',
				__( 'Produtividade', 'eb-credito-rural' ),
				'',
				array(
					'inputmode' => 'decimal',
					'data-mask' => 'decimal',
					'id'        => 'ebcr-pr-__i__-v',
				)
			) . ebcr_input(
				'produtividade[__i__][unidade]',
				__( 'Unidade', 'eb-credito-rural' ),
				'',
				array(
					'maxlength' => 20,
					'id'        => 'ebcr-pr-__i__-u',
				)
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<button type="button" class="ebcr-btn ebcr-btn--small ebcr-btn--ghost" data-remove-row><?php esc_html_e( 'Remover', 'eb-credito-rural' ); ?></button></div></template>
			<button type="submit" name="ebcr_add" value="produtividade" class="ebcr-btn ebcr-btn--small" data-add-row formnovalidate><?php esc_html_e( '+ Adicionar safra', 'eb-credito-rural' ); ?></button>
		</div>
	</fieldset>

	<?php
	echo ebcr_textarea(
		'plano_safra',
		__( 'Plano da safra e orçamento de custeio', 'eb-credito-rural' ),
		$ebcr_v( 'plano_safra' ),
		array(
			'required'  => true,
			'rows'      => 5,
			'maxlength' => 4000,
		),
		$ebcr_e( 'plano_safra' ),
		__( 'O que será plantado/criado, em quantos hectares, principais custos e cronograma.', 'eb-credito-rural' )
	); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
	?>
	<?php
	echo ebcr_input(
		'orcamento_custeio',
		__( 'Orçamento de custeio da safra (R$)', 'eb-credito-rural' ),
		$ebcr_v( 'orcamento_custeio' ),
		array(
			'inputmode' => 'decimal',
			'data-mask' => 'money',
		),
		$ebcr_e( 'orcamento_custeio' )
	); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
	?>

	<fieldset class="ebcr-group"><legend><?php esc_html_e( 'Comercialização', 'eb-credito-rural' ); ?></legend>
		<?php
		echo ebcr_textarea(
			'compradores',
			__( 'Principais compradores', 'eb-credito-rural' ),
			$ebcr_v( 'compradores' ),
			array(
				'rows'      => 2,
				'maxlength' => 2000,
			),
			$ebcr_e( 'compradores' )
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<?php
		echo ebcr_radios(
			'contratos_venda',
			__( 'Possui contratos de venda futura ou barter?', 'eb-credito-rural' ),
			array(
				'nao' => __( 'Não', 'eb-credito-rural' ),
				'sim' => __( 'Sim', 'eb-credito-rural' ),
			),
			$ebcr_v( 'contratos_venda' ),
			$ebcr_e( 'contratos_venda' )
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<div data-show-if="contratos_venda=sim">
		<?php
		echo ebcr_textarea(
			'contratos_detalhes',
			__( 'Descreva (comprador, produto, volume, vencimento)', 'eb-credito-rural' ),
			$ebcr_v( 'contratos_detalhes' ),
			array(
				'rows'      => 3,
				'maxlength' => 2000,
			),
			$ebcr_e( 'contratos_detalhes' )
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		</div>
	</fieldset>

	<fieldset class="ebcr-group" data-show-if="atividades=pecuaria_corte,pecuaria_leite"><legend><?php esc_html_e( 'Rebanho por categoria', 'eb-credito-rural' ); ?></legend>
		<?php
		if ( $ebcr_e( 'rebanho' ) ) :
			?>
			<p class="ebcr-error" role="alert"><?php echo esc_html( $ebcr_e( 'rebanho' ) ); ?></p><?php endif; ?>
		<div class="ebcr-repeat" data-repeat="rebanho">
		<?php foreach ( $ebcr_rows( 'rebanho' ) as $ebcr_i => $ebcr_it ) : ?>
			<div class="ebcr-repeat-row ebcr-row">
				<?php echo ebcr_select( "rebanho[{$ebcr_i}][categoria]", __( 'Categoria', 'eb-credito-rural' ), Steps::options( 'rebanho_categorias' ), isset( $ebcr_it['categoria'] ) ? $ebcr_it['categoria'] : '', array(), $ebcr_e( "rebanho.{$ebcr_i}.categoria" ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php
				echo ebcr_input(
					"rebanho[{$ebcr_i}][quantidade]",
					__( 'Quantidade (cabeças)', 'eb-credito-rural' ),
					isset( $ebcr_it['quantidade'] ) ? $ebcr_it['quantidade'] : '',
					array(
						'type' => 'number',
						'min'  => 1,
						'step' => 1,
					),
					$ebcr_e( "rebanho.{$ebcr_i}.quantidade" )
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
				?>
				<button type="button" class="ebcr-btn ebcr-btn--small ebcr-btn--ghost" data-remove-row><?php esc_html_e( 'Remover', 'eb-credito-rural' ); ?></button>
			</div>
		<?php endforeach; ?>
			<template data-repeat-template><div class="ebcr-repeat-row ebcr-row">
			<?php
			echo ebcr_select( 'rebanho[__i__][categoria]', __( 'Categoria', 'eb-credito-rural' ), Steps::options( 'rebanho_categorias' ), '' ) . ebcr_input(
				'rebanho[__i__][quantidade]',
				__( 'Quantidade (cabeças)', 'eb-credito-rural' ),
				'',
				array(
					'type' => 'number',
					'min'  => 1,
					'step' => 1,
					'id'   => 'ebcr-rb-__i__-q',
				)
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<button type="button" class="ebcr-btn ebcr-btn--small ebcr-btn--ghost" data-remove-row><?php esc_html_e( 'Remover', 'eb-credito-rural' ); ?></button></div></template>
			<button type="submit" name="ebcr_add" value="rebanho" class="ebcr-btn ebcr-btn--small" data-add-row formnovalidate><?php esc_html_e( '+ Adicionar categoria', 'eb-credito-rural' ); ?></button>
		</div>
	</fieldset>

	<fieldset class="ebcr-group"><legend><?php esc_html_e( 'Seguro, irrigação, maquinário e licenças', 'eb-credito-rural' ); ?></legend>
		<?php
		echo ebcr_radios(
			'seguro_rural',
			__( 'Possui seguro rural?', 'eb-credito-rural' ),
			array(
				'nao' => __( 'Não', 'eb-credito-rural' ),
				'sim' => __( 'Sim', 'eb-credito-rural' ),
			),
			$ebcr_v( 'seguro_rural' ),
			$ebcr_e( 'seguro_rural' )
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<div data-show-if="seguro_rural=sim"><?php echo ebcr_input( 'seguro_tipo', __( 'Tipo de seguro (ex.: multirrisco, Proagro, faturamento)', 'eb-credito-rural' ), $ebcr_v( 'seguro_tipo' ), array( 'maxlength' => 190 ), $ebcr_e( 'seguro_tipo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php
		echo ebcr_radios(
			'irrigacao',
			__( 'Utiliza irrigação?', 'eb-credito-rural' ),
			array(
				'nao' => __( 'Não', 'eb-credito-rural' ),
				'sim' => __( 'Sim', 'eb-credito-rural' ),
			),
			$ebcr_v( 'irrigacao' ),
			$ebcr_e( 'irrigacao' )
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<?php
		echo ebcr_textarea(
			'maquinario',
			__( 'Maquinário principal (marca, modelo, ano)', 'eb-credito-rural' ),
			$ebcr_v( 'maquinario' ),
			array(
				'rows'      => 2,
				'maxlength' => 2000,
			),
			$ebcr_e( 'maquinario' )
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<?php
		echo ebcr_radios(
			'licenca_ambiental',
			__( 'A atividade exige licença ambiental ou outorga de água?', 'eb-credito-rural' ),
			array(
				'na'  => __( 'Não se aplica', 'eb-credito-rural' ),
				'sim' => __( 'Sim, possuo', 'eb-credito-rural' ),
				'nao' => __( 'Sim, mas ainda não possuo', 'eb-credito-rural' ),
			),
			$ebcr_v( 'licenca_ambiental' ),
			$ebcr_e( 'licenca_ambiental' )
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
	</fieldset>
	<?php
	\EBCR\Support\View::show(
		'wizard/nav',
		array(
			's'    => $s,
			'step' => 3,
		)
	);
	?>
</form>
