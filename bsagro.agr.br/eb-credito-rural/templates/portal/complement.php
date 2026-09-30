<?php
/**
 * Complemento de bens e garantias depois do envio (área do cliente).
 * Variáveis: $s, $part (imoveis|garantias), $content (formulário da etapa em modo "complement").
 *
 * @package EBCR
 */

use EBCR\Forms\Complement;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
?>
<p><a class="ebcr-link" href="
<?php
echo esc_url(
	Helpers::portal_url(
		array(
			'ebcr_view' => 'solicitacao',
			'id'        => $s['public_id'],
		)
	) . '#ebcr-bens'
);
?>
">&larr; <?php /* translators: %s: protocolo */ printf( esc_html__( 'Voltar para a solicitação %s', 'eb-credito-rural' ), esc_html( $s['protocol'] ) ); ?></a></p>
<div class="ebcr-wizard ebcr-complement" data-ebcr-wizard data-part="<?php echo esc_attr( $part ); ?>">
	<div class="ebcr-card">
		<h2><?php /* translators: %s: Imóveis rurais / bens | Garantias */ printf( esc_html__( 'Completar: %s', 'eb-credito-rural' ), esc_html( Complement::part_label( $part ) ) ); ?></h2>
		<noscript><p class="ebcr-alert ebcr-alert--info"><?php esc_html_e( 'Seu navegador está sem JavaScript: o formulário funciona, mas sem validação instantânea.', 'eb-credito-rural' ); ?></p></noscript>
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template já escapado. ?>
	</div>
</div>
