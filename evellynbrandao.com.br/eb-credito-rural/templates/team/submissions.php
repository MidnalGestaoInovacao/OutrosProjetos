<?php
/**
 * Lista de solicitações (painel da equipe).
 * Variáveis: $items, $total, $args, $per_page, $pages, $base_args, $statuses, $funds, $analysts, $can_export, $restricted.
 *
 * @package EBCR
 */

use EBCR\Frontend\Team\Panel;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;

$ebcr_sort = static function ( $key, $label ) use ( $args, $base_args ) {
	$active = $args['orderby'] === $key;
	$dir    = $active && 'DESC' === $args['order'] ? 'ASC' : 'DESC';
	$url    = Panel::url(
		array_merge(
			$base_args,
			array(
				'ordem' => $key,
				'dir'   => $dir,
			)
		)
	);
	return sprintf( '<a class="ebcr-tsort%s" href="%s">%s%s</a>', $active ? ' is-active' : '', esc_url( $url ), esc_html( $label ), $active ? ( 'ASC' === $args['order'] ? ' &#9650;' : ' &#9660;' ) : '' );
};
?>
<section class="ebcr-card">
	<div class="ebcr-thead">
		<h2><?php esc_html_e( 'Solicitações', 'eb-credito-rural' ); ?> <span class="ebcr-pill"><?php echo (int) $total; ?></span></h2>
		<?php if ( $restricted ) : ?>
			<span class="ebcr-muted ebcr-small"><?php esc_html_e( 'Você vê apenas as solicitações atribuídas a você.', 'eb-credito-rural' ); ?></span>
		<?php endif; ?>
	</div>
	<form method="get" action="<?php echo esc_url( Helpers::portal_url() ); ?>" class="ebcr-tfilters">
		<input type="hidden" name="ebcr_view" value="equipe"><input type="hidden" name="tela" value="solicitacoes">
		<label><span><?php esc_html_e( 'Buscar', 'eb-credito-rural' ); ?></span><input type="search" name="busca" class="ebcr-input" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'nome, e-mail, protocolo', 'eb-credito-rural' ); ?>"></label>
		<label><span><?php esc_html_e( 'Status', 'eb-credito-rural' ); ?></span><select name="status" class="ebcr-input"><option value=""><?php esc_html_e( 'Todos', 'eb-credito-rural' ); ?></option>
			<?php foreach ( $statuses as $ebcr_k => $ebcr_def ) : ?>
				<option value="<?php echo esc_attr( $ebcr_k ); ?>" <?php selected( $args['status'], $ebcr_k ); ?>><?php echo esc_html( $ebcr_def['label'] ); ?></option>
			<?php endforeach; ?></select></label>
		<label><span><?php esc_html_e( 'Analista', 'eb-credito-rural' ); ?></span><select name="analista" class="ebcr-input"><option value=""><?php esc_html_e( 'Qualquer', 'eb-credito-rural' ); ?></option>
			<?php foreach ( $analysts as $ebcr_a ) : ?>
				<option value="<?php echo (int) $ebcr_a->ID; ?>" <?php selected( (int) $args['assigned_to'], (int) $ebcr_a->ID ); ?>><?php echo esc_html( $ebcr_a->display_name ); ?></option>
			<?php endforeach; ?></select></label>
		<label><span><?php esc_html_e( 'Carteira', 'eb-credito-rural' ); ?></span><select name="carteira" class="ebcr-input"><option value=""><?php esc_html_e( 'Todas', 'eb-credito-rural' ); ?></option>
			<?php foreach ( $funds as $ebcr_k => $ebcr_l ) : ?>
				<option value="<?php echo esc_attr( $ebcr_k ); ?>" <?php selected( $args['fund'], $ebcr_k ); ?>><?php echo esc_html( $ebcr_l ); ?></option>
			<?php endforeach; ?></select></label>
		<label><span><?php esc_html_e( 'De', 'eb-credito-rural' ); ?></span><input type="date" name="de" class="ebcr-input" value="<?php echo esc_attr( $args['date_from'] ); ?>"></label>
		<label><span><?php esc_html_e( 'Até', 'eb-credito-rural' ); ?></span><input type="date" name="ate" class="ebcr-input" value="<?php echo esc_attr( $args['date_to'] ); ?>"></label>
		<div class="ebcr-tfilters-actions">
			<button type="submit" class="ebcr-btn ebcr-btn--primary ebcr-btn--small"><?php esc_html_e( 'Filtrar', 'eb-credito-rural' ); ?></button>
			<a class="ebcr-btn ebcr-btn--ghost ebcr-btn--small" href="<?php echo esc_url( Panel::url( array( 'tela' => 'solicitacoes' ) ) ); ?>"><?php esc_html_e( 'Limpar', 'eb-credito-rural' ); ?></a>
		</div>
	</form>
	<?php if ( $can_export ) : ?>
	<form method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>" class="ebcr-inline-form ebcr-texport">
		<input type="hidden" name="action" value="ebcr_team_export_list">
		<?php echo ebcr_nonce_field( 'team_export_list' ); ?>
		<?php
		foreach ( array(
			'status'   => $args['status'],
			'analista' => $args['assigned_to'],
			'carteira' => $args['fund'],
			'de'       => $args['date_from'],
			'ate'      => $args['date_to'],
			'busca'    => $args['search'],
			'ordem'    => $args['orderby'],
			'dir'      => $args['order'],
		) as $ebcr_k => $ebcr_v ) :
			?>
			<input type="hidden" name="<?php echo esc_attr( $ebcr_k ); ?>" value="<?php echo esc_attr( (string) $ebcr_v ); ?>">
		<?php endforeach; ?>
		<button type="submit" class="ebcr-btn ebcr-btn--small"><?php esc_html_e( 'Exportar CSV (filtro atual)', 'eb-credito-rural' ); ?></button>
	</form>
	<?php endif; ?>

	<?php if ( ! $items ) : ?>
		<p class="ebcr-muted"><?php esc_html_e( 'Nenhuma solicitação encontrada.', 'eb-credito-rural' ); ?></p>
	<?php else : ?>
	<div class="ebcr-table-wrap"><table class="ebcr-table ebcr-tlist-table">
		<thead><tr>
			<th><?php echo $ebcr_sort( 'protocol', __( 'Protocolo', 'eb-credito-rural' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado na closure. ?></th>
			<th><?php esc_html_e( 'Cliente', 'eb-credito-rural' ); ?></th>
			<th><?php echo $ebcr_sort( 'status', __( 'Status', 'eb-credito-rural' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- idem. ?></th>
			<th><?php esc_html_e( 'Carteira', 'eb-credito-rural' ); ?></th>
			<th><?php echo $ebcr_sort( 'requested_amount', __( 'Valor', 'eb-credito-rural' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- idem. ?></th>
			<th><?php esc_html_e( 'Analista', 'eb-credito-rural' ); ?></th>
			<th><?php echo $ebcr_sort( 'submitted_at', __( 'Enviada em', 'eb-credito-rural' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- idem. ?></th>
			<th><?php echo $ebcr_sort( 'updated_at', __( 'Atualizada', 'eb-credito-rural' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- idem. ?></th>
			<th></th>
		</tr></thead>
		<tbody>
		<?php foreach ( $items as $ebcr_s ) : ?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Protocolo', 'eb-credito-rural' ); ?>"><strong><a href="<?php echo esc_url( Panel::submission_url( $ebcr_s ) ); ?>"><?php echo esc_html( $ebcr_s['protocol'] ? $ebcr_s['protocol'] : __( '(rascunho)', 'eb-credito-rural' ) ); ?></a></strong></td>
				<td data-label="<?php esc_attr_e( 'Cliente', 'eb-credito-rural' ); ?>"><?php echo esc_html( $ebcr_s['user_name'] ); ?><br><span class="ebcr-muted ebcr-small"><?php echo esc_html( $ebcr_s['user_email'] ); ?></span></td>
				<td data-label="<?php esc_attr_e( 'Status', 'eb-credito-rural' ); ?>"><?php echo ebcr_status_badge( $ebcr_s['status'] ); ?></td>
				<td data-label="<?php esc_attr_e( 'Carteira', 'eb-credito-rural' ); ?>"><?php echo esc_html( '' === (string) $ebcr_s['fund'] ? '—' : ( isset( $funds[ $ebcr_s['fund'] ] ) ? $funds[ $ebcr_s['fund'] ] : $ebcr_s['fund'] ) ); ?></td>
				<td data-label="<?php esc_attr_e( 'Valor', 'eb-credito-rural' ); ?>"><?php echo esc_html( Helpers::money( $ebcr_s['requested_amount'] ) ); ?></td>
				<td data-label="<?php esc_attr_e( 'Analista', 'eb-credito-rural' ); ?>"><?php echo esc_html( $ebcr_s['assigned_to'] ? Helpers::user_name( (int) $ebcr_s['assigned_to'] ) : '—' ); ?></td>
				<td data-label="<?php esc_attr_e( 'Enviada em', 'eb-credito-rural' ); ?>"><?php echo esc_html( Helpers::date( $ebcr_s['submitted_at'] ) ); ?></td>
				<td data-label="<?php esc_attr_e( 'Atualizada', 'eb-credito-rural' ); ?>"><?php echo esc_html( Helpers::date( $ebcr_s['updated_at'] ) ); ?></td>
				<td><a class="ebcr-btn ebcr-btn--small" href="<?php echo esc_url( Panel::submission_url( $ebcr_s ) ); ?>"><?php esc_html_e( 'Abrir', 'eb-credito-rural' ); ?></a></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table></div>
		<?php if ( $pages > 1 ) : ?>
	<nav class="ebcr-tpages" aria-label="<?php esc_attr_e( 'Paginação', 'eb-credito-rural' ); ?>">
			<?php if ( $args['page'] > 1 ) : ?>
			<a class="ebcr-btn ebcr-btn--small" href="<?php echo esc_url( Panel::url( array_merge( $base_args, array( 'pg' => $args['page'] - 1 ) ) ) ); ?>">&larr; <?php esc_html_e( 'Anterior', 'eb-credito-rural' ); ?></a>
		<?php endif; ?>
		<span class="ebcr-muted"><?php /* translators: 1: página atual, 2: total de páginas */ printf( esc_html__( 'Página %1$d de %2$d', 'eb-credito-rural' ), (int) $args['page'], (int) $pages ); ?></span>
			<?php if ( $args['page'] < $pages ) : ?>
			<a class="ebcr-btn ebcr-btn--small" href="<?php echo esc_url( Panel::url( array_merge( $base_args, array( 'pg' => $args['page'] + 1 ) ) ) ); ?>"><?php esc_html_e( 'Próxima', 'eb-credito-rural' ); ?> &rarr;</a>
		<?php endif; ?>
	</nav>
	<?php endif; ?>
	<?php endif; ?>
</section>
