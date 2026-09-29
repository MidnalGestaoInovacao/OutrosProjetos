<?php
/**
 * Consentimentos de cookies (somente leitura). Variáveis: $items, $total, $args, $pages, $actions.
 *
 * @package EBCR
 */

use EBCR\Domain\CookieConsent;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_filters = array_filter(
	array(
		'consent_id'     => $args['consent_id'],
		'consent_action' => $args['action'],
		'date_from'      => $args['date_from'],
		'date_to'        => $args['date_to'],
	)
);
?>
<div class="wrap ebcr-admin">
	<h1><?php esc_html_e( 'Consentimentos de cookies', 'eb-credito-rural' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Registros enviados pelo banner de cookies do site (POST ebcr/v1/cookie-consent) para comprovar o consentimento (LGPD, art. 8º, § 2º). O IP não é guardado: apenas um hash irreversível. Os registros são apagados pela rotina de retenção após o prazo definido em Configurações → Privacidade e compliance.', 'eb-credito-rural' ); ?></p>
	<p class="description"><?php esc_html_e( 'Endpoint para o banner:', 'eb-credito-rural' ); ?> <code><?php echo esc_html( rest_url( \EBCR\Rest\Routes::NS . '/cookie-consent' ) ); ?></code> — <?php esc_html_e( 'também disponível no front-end em window.EBCR_CLIENT_AREA.consentEndpoint.', 'eb-credito-rural' ); ?></p>
	<form method="get">
		<input type="hidden" name="page" value="ebcr-cookie-consents">
		<p>
			<input type="text" name="consent_id" placeholder="<?php esc_attr_e( 'ID do consentimento (UUID)', 'eb-credito-rural' ); ?>" value="<?php echo esc_attr( $args['consent_id'] ); ?>" style="width:320px">
			<select name="consent_action"><option value=""><?php esc_html_e( 'Todas as ações', 'eb-credito-rural' ); ?></option>
			<?php
			foreach ( $actions as $ebcr_a => $ebcr_l ) :
				?>
				<option value="<?php echo esc_attr( $ebcr_a ); ?>" <?php selected( $args['action'], $ebcr_a ); ?>><?php echo esc_html( $ebcr_l ); ?></option><?php endforeach; ?></select>
			<input type="date" name="date_from" value="<?php echo esc_attr( $args['date_from'] ); ?>" aria-label="<?php esc_attr_e( 'De', 'eb-credito-rural' ); ?>"> <input type="date" name="date_to" value="<?php echo esc_attr( $args['date_to'] ); ?>" aria-label="<?php esc_attr_e( 'Até', 'eb-credito-rural' ); ?>">
			<button class="button"><?php esc_html_e( 'Filtrar', 'eb-credito-rural' ); ?></button>
			<?php if ( current_user_can( \EBCR\Roles\Capabilities::CAP_EXPORT ) ) : ?>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( $ebcr_filters, array( 'action' => 'ebcr_cookie_consents_csv' ) ), admin_url( 'admin-post.php' ) ), 'ebcr_cookie_consents_csv' ) ); ?>"><?php esc_html_e( 'Exportar CSV', 'eb-credito-rural' ); ?></a>
			<?php endif; ?>
		</p>
	</form>
	<p class="description"><?php /* translators: %d: total */ printf( esc_html__( '%d registro(s).', 'eb-credito-rural' ), (int) $total ); ?></p>
	<table class="widefat striped ebcr-t">
		<thead><tr><th><?php esc_html_e( 'Data', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'ID do consentimento', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Ação', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Categorias aceitas', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Versão', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Página', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Usuário', 'eb-credito-rural' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $items as $ebcr_i ) : ?>
			<tr>
				<td><?php echo esc_html( Helpers::date( $ebcr_i['created_at'] ) ); ?></td>
				<td><code><?php echo esc_html( $ebcr_i['consent_id'] ); ?></code></td>
				<td><?php echo esc_html( isset( $actions[ $ebcr_i['action'] ] ) ? $actions[ $ebcr_i['action'] ] : $ebcr_i['action'] ); ?></td>
				<td><?php echo esc_html( CookieConsent::categories_text( $ebcr_i['categories'] ) ); ?></td>
				<td><?php echo (int) $ebcr_i['version']; ?></td>
				<td><code><?php echo esc_html( $ebcr_i['path'] ); ?></code></td>
				<td><?php echo $ebcr_i['user_id'] ? esc_html( Helpers::user_name( (int) $ebcr_i['user_id'] ) . ' (#' . (int) $ebcr_i['user_id'] . ')' ) : '—'; ?></td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! $items ) : ?>
			<tr><td colspan="7"><?php esc_html_e( 'Nenhum registro.', 'eb-credito-rural' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>
	<?php if ( $pages > 1 ) : ?>
		<p class="tablenav-pages">
		<?php
		echo wp_kses_post(
			paginate_links(
				array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => (int) $args['page'],
					'total'   => (int) $pages,
				)
			)
		);
		?>
		</p>
	<?php endif; ?>
</div>
