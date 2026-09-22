<?php
/**
 * Etapa 1 — identificação. Variáveis: $s, $data, $errors, $add.
 *
 * @package EBCR
 */

use EBCR\Forms\Steps;
use EBCR\Frontend\Fields;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_type = isset( $data['person_type'] ) && 'PJ' === $data['person_type'] ? 'PJ' : 'PF';
$ebcr_v    = static function ( $k ) use ( $data ) {
	return Fields::value( $data, $k );
};
$ebcr_e    = static function ( $k ) use ( $errors ) {
	return Fields::error( $errors, $k );
};
$ebcr_reps = isset( $data['representantes'] ) && is_array( $data['representantes'] ) ? array_values( $data['representantes'] ) : array();
if ( 'representantes' === $add || ! $ebcr_reps ) {
	$ebcr_reps[] = array(
		'nome' => '',
		'cpf'  => '',
	);
}
?>
<form class="ebcr-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate data-ebcr-step="1">
	<?php echo ebcr_error_summary( $errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<input type="hidden" name="action" value="ebcr_wizard_step"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>"><input type="hidden" name="etapa" value="1">
	<?php echo ebcr_nonce_field( 'wizard' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php
	echo ebcr_radios(
		'person_type',
		__( 'Tipo de tomador', 'eb-credito-rural' ),
		array(
			'PF' => __( 'Pessoa física (produtor rural)', 'eb-credito-rural' ),
			'PJ' => __( 'Pessoa jurídica', 'eb-credito-rural' ),
		),
		$ebcr_type,
		$ebcr_e( 'person_type' )
	); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
	?>

	<fieldset class="ebcr-group" data-show-if="person_type=PF" <?php echo 'PF' === $ebcr_type ? '' : 'hidden'; ?>><legend><?php esc_html_e( 'Dados pessoais', 'eb-credito-rural' ); ?></legend>
		<div class="ebcr-row">
			<?php
			echo ebcr_input(
				'nome',
				__( 'Nome completo', 'eb-credito-rural' ),
				$ebcr_v( 'nome' ),
				array(
					'required'     => true,
					'maxlength'    => 190,
					'autocomplete' => 'name',
				),
				$ebcr_e( 'nome' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<?php
			echo ebcr_input(
				'cpf',
				__( 'CPF', 'eb-credito-rural' ),
				Helpers::format_document( $ebcr_v( 'cpf' ) ),
				array(
					'required'      => true,
					'inputmode'     => 'numeric',
					'data-mask'     => 'cpf',
					'data-validate' => 'cpf',
				),
				$ebcr_e( 'cpf' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
		</div>
		<div class="ebcr-row">
			<?php
			echo ebcr_input(
				'rg',
				__( 'RG', 'eb-credito-rural' ),
				$ebcr_v( 'rg' ),
				array(
					'required'  => true,
					'maxlength' => 30,
				),
				$ebcr_e( 'rg' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<?php
			echo ebcr_input(
				'rg_orgao',
				__( 'Órgão emissor / UF', 'eb-credito-rural' ),
				$ebcr_v( 'rg_orgao' ),
				array(
					'required'  => true,
					'maxlength' => 30,
				),
				$ebcr_e( 'rg_orgao' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<?php
			echo ebcr_input(
				'nascimento',
				__( 'Data de nascimento', 'eb-credito-rural' ),
				$ebcr_v( 'nascimento' ),
				array(
					'type'     => 'date',
					'required' => true,
					'max'      => wp_date( 'Y-m-d', strtotime( '-18 years' ) ),
				),
				$ebcr_e( 'nascimento' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
		</div>
		<div class="ebcr-row">
			<?php
			echo ebcr_select(
				'estado_civil',
				__( 'Estado civil', 'eb-credito-rural' ),
				Steps::options( 'estado_civil' ),
				$ebcr_v( 'estado_civil' ),
				array(
					'required'    => true,
					'data-toggle' => 'married',
				),
				$ebcr_e( 'estado_civil' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<?php echo ebcr_select( 'regime_bens', __( 'Regime de bens', 'eb-credito-rural' ), Steps::options( 'regime_bens' ), $ebcr_v( 'regime_bens' ), array(), $ebcr_e( 'regime_bens' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<div class="ebcr-row" data-show-if="estado_civil=casado,uniao_estavel">
			<?php echo ebcr_input( 'conjuge_nome', __( 'Nome do cônjuge / companheiro(a)', 'eb-credito-rural' ), $ebcr_v( 'conjuge_nome' ), array( 'maxlength' => 190 ), $ebcr_e( 'conjuge_nome' ), __( 'Necessário para a outorga nas garantias reais.', 'eb-credito-rural' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
			echo ebcr_input(
				'conjuge_cpf',
				__( 'CPF do cônjuge / companheiro(a)', 'eb-credito-rural' ),
				Helpers::format_document( $ebcr_v( 'conjuge_cpf' ) ),
				array(
					'inputmode'     => 'numeric',
					'data-mask'     => 'cpf',
					'data-validate' => 'cpf',
				),
				$ebcr_e( 'conjuge_cpf' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
		</div>
	</fieldset>

	<fieldset class="ebcr-group" data-show-if="person_type=PJ" <?php echo 'PJ' === $ebcr_type ? '' : 'hidden'; ?>><legend><?php esc_html_e( 'Dados da empresa', 'eb-credito-rural' ); ?></legend>
		<div class="ebcr-row">
			<?php
			echo ebcr_input(
				'razao_social',
				__( 'Razão social', 'eb-credito-rural' ),
				$ebcr_v( 'razao_social' ),
				array(
					'required'  => true,
					'maxlength' => 190,
				),
				$ebcr_e( 'razao_social' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<?php
			echo ebcr_input(
				'cnpj',
				__( 'CNPJ', 'eb-credito-rural' ),
				Helpers::format_document( $ebcr_v( 'cnpj' ) ),
				array(
					'required'      => true,
					'inputmode'     => 'numeric',
					'data-mask'     => 'cnpj',
					'data-validate' => 'cnpj',
				),
				$ebcr_e( 'cnpj' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
		</div>
		<div class="ebcr-repeat" data-repeat="representantes">
			<h4><?php esc_html_e( 'Representantes legais', 'eb-credito-rural' ); ?></h4>
			<?php
			if ( $ebcr_e( 'representantes' ) ) :
				?>
				<p class="ebcr-error" role="alert"><?php echo esc_html( $ebcr_e( 'representantes' ) ); ?></p><?php endif; ?>
			<?php foreach ( $ebcr_reps as $ebcr_i => $ebcr_rep ) : ?>
			<div class="ebcr-repeat-row">
				<?php echo ebcr_input( "representantes[{$ebcr_i}][nome]", __( 'Nome', 'eb-credito-rural' ), isset( $ebcr_rep['nome'] ) ? $ebcr_rep['nome'] : '', array( 'maxlength' => 190 ), $ebcr_e( "representantes.{$ebcr_i}.nome" ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php
				echo ebcr_input(
					"representantes[{$ebcr_i}][cpf]",
					__( 'CPF', 'eb-credito-rural' ),
					Helpers::format_document( isset( $ebcr_rep['cpf'] ) ? $ebcr_rep['cpf'] : '' ),
					array(
						'inputmode'     => 'numeric',
						'data-mask'     => 'cpf',
						'data-validate' => 'cpf',
					),
					$ebcr_e( "representantes.{$ebcr_i}.cpf" )
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
				?>
				<button type="button" class="ebcr-btn ebcr-btn--small ebcr-btn--ghost" data-remove-row><?php esc_html_e( 'Remover', 'eb-credito-rural' ); ?></button>
			</div>
			<?php endforeach; ?>
			<template data-repeat-template>
				<div class="ebcr-repeat-row">
					<?php
					echo ebcr_input(
						'representantes[__i__][nome]',
						__( 'Nome', 'eb-credito-rural' ),
						'',
						array(
							'maxlength' => 190,
							'id'        => 'ebcr-rep-__i__-nome',
						)
					); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
					?>
					<?php
					echo ebcr_input(
						'representantes[__i__][cpf]',
						__( 'CPF', 'eb-credito-rural' ),
						'',
						array(
							'inputmode'     => 'numeric',
							'data-mask'     => 'cpf',
							'data-validate' => 'cpf',
							'id'            => 'ebcr-rep-__i__-cpf',
						)
					); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
					?>
					<button type="button" class="ebcr-btn ebcr-btn--small ebcr-btn--ghost" data-remove-row><?php esc_html_e( 'Remover', 'eb-credito-rural' ); ?></button>
				</div>
			</template>
			<button type="submit" name="ebcr_add" value="representantes" class="ebcr-btn ebcr-btn--small" data-add-row formnovalidate><?php esc_html_e( '+ Adicionar representante', 'eb-credito-rural' ); ?></button>
		</div>
	</fieldset>

	<fieldset class="ebcr-group"><legend><?php esc_html_e( 'Cadastros e endereço', 'eb-credito-rural' ); ?></legend>
		<div class="ebcr-row">
			<?php echo ebcr_input( 'inscricao_estadual', __( 'Inscrição estadual de produtor rural', 'eb-credito-rural' ), $ebcr_v( 'inscricao_estadual' ), array( 'maxlength' => 30 ), $ebcr_e( 'inscricao_estadual' ), __( 'Opcional.', 'eb-credito-rural' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo ebcr_input( 'caf', __( 'CAF (Pronaf)', 'eb-credito-rural' ), $ebcr_v( 'caf' ), array( 'maxlength' => 40 ), $ebcr_e( 'caf' ), __( 'Opcional. Cadastro Nacional da Agricultura Familiar.', 'eb-credito-rural' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<div class="ebcr-row">
			<?php
			echo ebcr_input(
				'cep',
				__( 'CEP', 'eb-credito-rural' ),
				$ebcr_v( 'cep' ),
				array(
					'required'     => true,
					'inputmode'    => 'numeric',
					'data-mask'    => 'cep',
					'autocomplete' => 'postal-code',
				),
				$ebcr_e( 'cep' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<?php
			echo ebcr_input(
				'logradouro',
				__( 'Endereço', 'eb-credito-rural' ),
				$ebcr_v( 'logradouro' ),
				array(
					'required'     => true,
					'maxlength'    => 190,
					'autocomplete' => 'street-address',
				),
				$ebcr_e( 'logradouro' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<?php
			echo ebcr_input(
				'numero',
				__( 'Número', 'eb-credito-rural' ),
				$ebcr_v( 'numero' ),
				array(
					'required'  => true,
					'maxlength' => 20,
				),
				$ebcr_e( 'numero' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
		</div>
		<div class="ebcr-row">
			<?php echo ebcr_input( 'complemento', __( 'Complemento', 'eb-credito-rural' ), $ebcr_v( 'complemento' ), array( 'maxlength' => 100 ), $ebcr_e( 'complemento' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo ebcr_input( 'bairro', __( 'Bairro / zona rural', 'eb-credito-rural' ), $ebcr_v( 'bairro' ), array( 'maxlength' => 100 ), $ebcr_e( 'bairro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
			echo ebcr_input(
				'cidade',
				__( 'Cidade', 'eb-credito-rural' ),
				$ebcr_v( 'cidade' ),
				array(
					'required'  => true,
					'maxlength' => 120,
				),
				$ebcr_e( 'cidade' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<?php echo ebcr_select( 'uf', __( 'UF', 'eb-credito-rural' ), Helpers::ufs(), $ebcr_v( 'uf' ), array( 'required' => true ), $ebcr_e( 'uf' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<div class="ebcr-row">
			<?php
			echo ebcr_input(
				'telefone',
				__( 'Telefone (com DDD)', 'eb-credito-rural' ),
				$ebcr_v( 'telefone' ),
				array(
					'type'         => 'tel',
					'required'     => true,
					'data-mask'    => 'phone',
					'autocomplete' => 'tel',
				),
				$ebcr_e( 'telefone' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
			<?php
			echo ebcr_input(
				'whatsapp',
				__( 'WhatsApp', 'eb-credito-rural' ),
				$ebcr_v( 'whatsapp' ),
				array(
					'type'      => 'tel',
					'data-mask' => 'phone',
				),
				$ebcr_e( 'whatsapp' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
		</div>
	</fieldset>

	<fieldset class="ebcr-group"><legend><?php esc_html_e( 'Pessoa politicamente exposta (PEP)', 'eb-credito-rural' ); ?></legend>
		<?php
		echo ebcr_radios(
			'pep',
			__( 'Você (ou o representante) exerce ou exerceu, nos últimos 5 anos, cargo público relevante, ou é familiar/estreito colaborador de quem exerce?', 'eb-credito-rural' ),
			array(
				'nao' => __( 'Não', 'eb-credito-rural' ),
				'sim' => __( 'Sim', 'eb-credito-rural' ),
			),
			$ebcr_v( 'pep' ),
			$ebcr_e( 'pep' )
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
		?>
		<div data-show-if="pep=sim">
			<?php
			echo ebcr_textarea(
				'pep_detalhes',
				__( 'Detalhes (cargo, órgão, período, vínculo)', 'eb-credito-rural' ),
				$ebcr_v( 'pep_detalhes' ),
				array(
					'rows'      => 3,
					'maxlength' => 1000,
				),
				$ebcr_e( 'pep_detalhes' )
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
			?>
		</div>
	</fieldset>
	<?php
	\EBCR\Support\View::show(
		'wizard/nav',
		array(
			's'    => $s,
			'step' => 1,
		)
	);
	?>
</form>
