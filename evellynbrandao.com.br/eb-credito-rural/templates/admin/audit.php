<?php
/**
 * Log de auditoria. Variáveis: $items, $total, $args, $actions, $labels, $pages.
 *
 * @package EBCR
 */

use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ebcr-admin">
	<h1><?php esc_html_e( 'Log de auditoria', 'eb-credito-rural' ); ?></h1>
	<form method="get">
		<input type="hidden" name="page" value="ebcr-audit">
		<p>
			<select name="action"><option value=""><?php esc_html_e( 'Todas as ações', 'eb-credito-rural' ); ?></option>
			<?php
			foreach ( $actions as $ebcr_a ) :
				?>
				<option value="<?php echo esc_attr( $ebcr_a ); ?>" <?php selected( $args['action'], $ebcr_a ); ?>><?php echo esc_html( $labels[ $ebcr_a ] ?? $ebcr_a ); ?></option><?php endforeach; ?></select>
			<input type="number" name="actor_id" placeholder="<?php esc_attr_e( 'ID do usuário', 'eb-credito-rural' ); ?>" value="<?php echo esc_attr( $args['actor_id'] ? (string) $args['actor_id'] : '' ); ?>" style="width:120px">
			<input type="text" name="object_type" placeholder="<?php esc_attr_e( 'Tipo (submission, document…)', 'eb-credito-rural' ); ?>" value="<?php echo esc_attr( $args['object_type'] ); ?>">
			<input type="text" name="object_id" placeholder="<?php esc_attr_e( 'ID do objeto', 'eb-credito-rural' ); ?>" value="<?php echo esc_attr( $args['object_id'] ); ?>">
			<input type="date" name="date_from" value="<?php echo esc_attr( $args['date_from'] ); ?>"> <input type="date" name="date_to" value="<?php echo esc_attr( $args['date_to'] ); ?>">
			<button class="button"><?php esc_html_e( 'Filtrar', 'eb-credito-rural' ); ?></button>
			<?php
			if ( current_user_can( \EBCR\Roles\Capabilities::CAP_EXPORT ) ) :
				?>
				<a class="button" href="
				<?php
				echo esc_url(
					wp_nonce_url(
						add_query_arg(
							array_merge(
								array_filter( $args ),
								array(
									'page'   => 'ebcr-audit',
									'export' => 'csv',
								)
							),
							admin_url( 'admin.php' )
						),
						'ebcr_audit_export'
					)
				);
				?>
				"><?php esc_html_e( 'Exportar CSV', 'eb-credito-rural' ); ?></a><?php endif; ?>
		</p>
	</form>
	<p class="description"><?php /* translators: %d: total */ printf( esc_html__( '%d registro(s).', 'eb-credito-rural' ), (int) $total ); ?></p>
	<table class="widefat striped ebcr-t">
		<thead><tr><th><?php esc_html_e( 'Data', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Usuário', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Ação', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Objeto', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'IP', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Detalhes', 'eb-credito-rural' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $items as $ebcr_i ) : ?>
			<tr><td><?php echo esc_html( Helpers::date( $ebcr_i['created_at'] ) ); ?></td><td><?php echo esc_html( $ebcr_i['actor_id'] ? Helpers::user_name( (int) $ebcr_i['actor_id'] ) . ' (#' . (int) $ebcr_i['actor_id'] . ')' : '—' ); ?></td><td><?php echo esc_html( $labels[ $ebcr_i['action'] ] ?? $ebcr_i['action'] ); ?></td><td><?php echo esc_html( $ebcr_i['object_type'] . ' ' . $ebcr_i['object_id'] ); ?></td><td><?php echo esc_html( $ebcr_i['ip'] ); ?></td><td><code><?php echo esc_html( mb_substr( (string) $ebcr_i['meta'], 0, 200 ) ); ?></code></td></tr>
		<?php endforeach; ?>
		<?php
		if ( ! $items ) :
			?>
			<tr><td colspan="6"><?php esc_html_e( 'Nenhum registro.', 'eb-credito-rural' ); ?></td></tr><?php endif; ?>
		</tbody>
	</table>
	<?php if ( $pages > 1 ) : ?>
	<p>
		<?php $ebcr_max = min( $pages, 50 ); ?>
		<?php for ( $ebcr_p = 1; $ebcr_p <= $ebcr_max; $ebcr_p++ ) : ?>
		<a class="button<?php echo $ebcr_p === $args['page'] ? ' button-primary' : ''; ?>" href="
			<?php
			echo esc_url(
				add_query_arg(
					array_merge(
						array_filter( $args ),
						array(
							'page'  => 'ebcr-audit',
							'paged' => $ebcr_p,
						)
					),
					admin_url( 'admin.php' )
				)
			);
			?>
						"><?php echo (int) $ebcr_p; ?></a>
	<?php endfor; ?>
	</p>
	<?php endif; ?>
</div>
