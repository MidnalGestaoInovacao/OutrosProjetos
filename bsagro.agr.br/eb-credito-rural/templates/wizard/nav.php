<?php
/**
 * Botões de navegação do formulário. Variáveis: $s, $step.
 *
 * @package EBCR
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ebcr-actions ebcr-wizard-nav">
	<?php
	if ( $step > 1 ) :
		?>
		<button type="submit" name="ebcr_back" value="1" class="ebcr-btn" formnovalidate><?php esc_html_e( 'Voltar', 'eb-credito-rural' ); ?></button><?php endif; ?>
	<button type="submit" name="ebcr_save" value="1" class="ebcr-btn" formnovalidate><?php esc_html_e( 'Salvar e sair', 'eb-credito-rural' ); ?></button>
	<button type="submit" class="ebcr-btn ebcr-btn--primary" data-next><?php esc_html_e( 'Salvar e continuar', 'eb-credito-rural' ); ?></button>
</div>
