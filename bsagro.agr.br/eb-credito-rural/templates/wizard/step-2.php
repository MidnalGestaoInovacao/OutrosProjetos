<?php
/**
 * Etapa 2 — imóveis (repetível). Variáveis: $s, $data, $errors, $add, $wizard, $saved, $context e, opcional, $mode.
 * Modo "Bens e garantias": obrigatório (padrão) mostra só a lista; opcional pergunta antes se há imóvel a informar
 * e exibe a orientação configurável (assets_optional_notice).
 * $mode = 'complement': mesmo formulário na área do cliente, depois do envio (Forms\Complement) — sem a pergunta,
 * itens já informados sem o botão "Remover" e envio para a ação ebcr_complement.
 *
 * @package EBCR
 */

use EBCR\Forms\Steps;
use EBCR\Forms\SubmissionRules;
use EBCR\Frontend\Fields;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_ctx        = isset( $context ) && is_array( $context ) ? $context : $wizard->rules_context( $s, $saved );
$ebcr_complement = isset( $mode ) && 'complement' === $mode;
$ebcr_required   = SubmissionRules::assets_required( $ebcr_ctx );
$ebcr_items      = isset( $data['imoveis'] ) && is_array( $data['imoveis'] ) ? array_values( $data['imoveis'] ) : array();
$ebcr_answer     = isset( $data['possui_imoveis'] ) && in_array( $data['possui_imoveis'], array( 'sim', 'nao' ), true ) ? $data['possui_imoveis'] : ( $ebcr_items ? 'sim' : '' );
if ( 'imoveis' === $add || ! $ebcr_items ) {
	$ebcr_items[] = array();
}
if ( 'imoveis' === $add ) {
	$ebcr_answer = 'sim';
}
$ebcr_e   = static function ( $k ) use ( $errors ) {
	return Fields::error( $errors, $k );
};
$ebcr_row = static function ( $i, array $it ) use ( $ebcr_e, $ebcr_complement ) {
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
	if ( ! $ebcr_complement || empty( $it['id'] ) ) {
		$out .= '<button type="button" class="ebcr-btn ebcr-btn--small ebcr-btn--ghost" data-remove-row>' . esc_html__( 'Remover imóvel', 'eb-credito-rural' ) . '</button>';
	}
	return $out . '</div>';
};
?>
<form class="ebcr-form" method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>" novalidate <?php echo $ebcr_complement ? 'data-ebcr-complement="imoveis"' : 'data-ebcr-step="2"'; ?>>
	<?php echo ebcr_error_summary( $errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php if ( $ebcr_complement ) : ?>
		<input type="hidden" name="action" value="ebcr_complement"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>"><input type="hidden" name="parte" value="imoveis">
		<?php echo ebcr_nonce_field( 'complement' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php else : ?>
		<input type="hidden" name="action" value="ebcr_wizard_step"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>"><input type="hidden" name="etapa" value="2">
		<?php echo ebcr_nonce_field( 'wizard' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>
	<?php if ( $ebcr_complement ) : ?>
		<?php echo ebcr_notice( \EBCR\Forms\Complement::notice( 'imoveis' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado no helper. ?>
		<p class="ebcr-muted"><?php esc_html_e( 'Inclua os imóveis que faltam ou corrija os já informados. Os documentos de cada imóvel novo (matrícula, CCIR/ITR, CAR…) serão pedidos em seguida, na página da solicitação. Para remover um imóvel já informado, fale com a equipe pelas mensagens.', 'eb-credito-rural' ); ?></p>
	<?php elseif ( $ebcr_required ) : ?>
		<p class="ebcr-muted"><?php esc_html_e( 'Informe todos os imóveis envolvidos na atividade e nas garantias. Os documentos de cada imóvel serão pedidos na etapa de documentos.', 'eb-credito-rural' ); ?></p>
	<?php else : ?>
		<?php echo ebcr_notice( \EBCR\Forms\Complement::notice( 'imoveis' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado no helper. ?>
		<p class="ebcr-muted"><?php esc_html_e( 'Se houver imóveis envolvidos na atividade ou nas garantias, informe-os: os documentos de cada imóvel serão pedidos na etapa de documentos.', 'eb-credito-rural' ); ?></p>
		<?php
		echo ebcr_radios( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado no helper.
			'possui_imoveis',
			__( 'Você tem imóvel rural para informar?', 'eb-credito-rural' ),
			array(
				'sim' => __( 'Sim, quero informar imóveis', 'eb-credito-rural' ),
				'nao' => __( 'Não tenho imóvel rural a informar', 'eb-credito-rural' ),
			),
			$ebcr_answer,
			Fields::error( $errors, 'possui_imoveis' ),
			'',
			true
		);
		?>
		<div data-show-if="possui_imoveis=sim">
	<?php endif; ?>
	<div class="ebcr-repeat" data-repeat="imoveis" data-min-rows="1" data-min-rows-message="<?php echo esc_attr( $ebcr_required || $ebcr_complement ? __( 'Informe pelo menos um imóvel rural.', 'eb-credito-rural' ) : __( 'Informe pelo menos um imóvel rural ou marque que não possui imóvel a informar.', 'eb-credito-rural' ) ); ?>" data-min-rows-key="imoveis">
		<?php
		if ( ! empty( $errors['imoveis'] ) ) :
			?>
			<p class="ebcr-error" role="alert"><?php echo esc_html( $errors['imoveis'] ); ?></p><?php endif; ?>
		<?php foreach ( $ebcr_items as $ebcr_i => $ebcr_it ) : ?>
			<?php echo $ebcr_row( $ebcr_i, (array) $ebcr_it ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endforeach; ?>
		<template data-repeat-template><?php echo str_replace( 'imoveis[' . count( $ebcr_items ) . ']', 'imoveis[__i__]', $ebcr_row( count( $ebcr_items ), array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<button type="submit" name="ebcr_add" value="imoveis" class="ebcr-btn ebcr-btn--small" data-add-row formnovalidate><?php esc_html_e( '+ Adicionar outro imóvel', 'eb-credito-rural' ); ?></button>
	</div>
	<?php if ( ! $ebcr_required && ! $ebcr_complement ) : ?>
		</div>
	<?php endif; ?>
	<?php
	\EBCR\Support\View::show(
		$ebcr_complement ? 'portal/complement-nav' : 'wizard/nav',
		array(
			's'    => $s,
			'step' => 2,
		)
	);
	?>
</form>
