<?php
/**
 * CRM — lista de contatos (painel da equipe).
 * Variáveis: $items, $total, $args, $pages, $base_args, $stages, $sources, $team, $can_export.
 *
 * @package EBCR
 */

use EBCR\Crm\Service;
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
		<h2><?php esc_html_e( 'CRM — contatos', 'eb-credito-rural' ); ?> <span class="ebcr-pill"><?php echo (int) $total; ?></span></h2>
		<nav class="ebcr-tsubnav" aria-label="<?php esc_attr_e( 'Visões do CRM', 'eb-credito-rural' ); ?>">
			<a class="ebcr-btn ebcr-btn--small ebcr-btn--primary" aria-current="page" href="<?php echo esc_url( Panel::url( array( 'tela' => 'crm' ) ) ); ?>"><?php esc_html_e( 'Lista', 'eb-credito-rural' ); ?></a>
			<a class="ebcr-btn ebcr-btn--small" href="<?php echo esc_url( Panel::url( array( 'tela' => 'crm', 'sub' => 'quadro' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- curto. ?>"><?php esc_html_e( 'Quadro', 'eb-credito-rural' ); ?></a>
		</nav>
	</div>
	<form method="get" action="<?php echo esc_url( Helpers::portal_url() ); ?>" class="ebcr-tfilters">
		<input type="hidden" name="ebcr_view" value="equipe"><input type="hidden" name="tela" value="crm"><input type="hidden" name="sub" value="lista">
		<label><span><?php esc_html_e( 'Buscar', 'eb-credito-rural' ); ?></span><input type="search" name="busca" class="ebcr-input" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'nome, e-mail, telefone', 'eb-credito-rural' ); ?>"></label>
		<label><span><?php esc_html_e( 'Estágio', 'eb-credito-rural' ); ?></span><select name="estagio" class="ebcr-input"><option value=""><?php esc_html_e( 'Todos', 'eb-credito-rural' ); ?></option>
			<?php foreach ( $stages as $ebcr_k => $ebcr_l ) : ?>
				<option value="<?php echo esc_attr( $ebcr_k ); ?>" <?php selected( $args['stage'], $ebcr_k ); ?>><?php echo esc_html( $ebcr_l ); ?></option>
			<?php endforeach; ?></select></label>
		<label><span><?php esc_html_e( 'Responsável', 'eb-credito-rural' ); ?></span><select name="resp" class="ebcr-input"><option value=""><?php esc_html_e( 'Qualquer', 'eb-credito-rural' ); ?></option><option value="none" <?php selected( $args['owner_id'], 'none' ); ?>><?php esc_html_e( 'Sem responsável', 'eb-credito-rural' ); ?></option>
			<?php foreach ( $team as $ebcr_u ) : ?>
				<option value="<?php echo (int) $ebcr_u->ID; ?>" <?php selected( (string) $args['owner_id'], (string) $ebcr_u->ID ); ?>><?php echo esc_html( $ebcr_u->display_name ); ?></option>
			<?php endforeach; ?></select></label>
		<label><span><?php esc_html_e( 'Origem', 'eb-credito-rural' ); ?></span><select name="origem" class="ebcr-input"><option value=""><?php esc_html_e( 'Todas', 'eb-credito-rural' ); ?></option>
			<?php foreach ( $sources as $ebcr_k => $ebcr_l ) : ?>
				<option value="<?php echo esc_attr( $ebcr_k ); ?>" <?php selected( $args['lead_source'], $ebcr_k ); ?>><?php echo esc_html( $ebcr_l ); ?></option>
			<?php endforeach; ?></select></label>
		<label><span><?php esc_html_e( 'Tag', 'eb-credito-rural' ); ?></span><input type="text" name="etiqueta" class="ebcr-input" value="<?php echo esc_attr( $args['tag'] ); ?>"></label>
		<div class="ebcr-tfilters-actions">
			<button type="submit" class="ebcr-btn ebcr-btn--primary ebcr-btn--small"><?php esc_html_e( 'Filtrar', 'eb-credito-rural' ); ?></button>
			<a class="ebcr-btn ebcr-btn--ghost ebcr-btn--small" href="<?php echo esc_url( Panel::url( array( 'tela' => 'crm' ) ) ); ?>"><?php esc_html_e( 'Limpar', 'eb-credito-rural' ); ?></a>
		</div>
	</form>
	<?php if ( $can_export ) : ?>
	<form method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>" class="ebcr-inline-form ebcr-texport">
		<input type="hidden" name="action" value="ebcr_team_crm_export">
		<?php echo ebcr_nonce_field( 'team_crm_export' ); ?>
		<?php
		foreach ( array(
			'busca'    => $args['search'],
			'estagio'  => $args['stage'],
			'resp'     => $args['owner_id'],
			'origem'   => $args['lead_source'],
			'etiqueta' => $args['tag'],
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
		<p class="ebcr-muted"><?php esc_html_e( 'Nenhum contato encontrado.', 'eb-credito-rural' ); ?></p>
	<?php else : ?>
	<div class="ebcr-table-wrap"><table class="ebcr-table ebcr-tlist-table">
		<thead><tr>
			<th><?php echo $ebcr_sort( 'user_name', __( 'Cliente', 'eb-credito-rural' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado na closure. ?></th>
			<th><?php esc_html_e( 'Contato', 'eb-credito-rural' ); ?></th>
			<th><?php echo $ebcr_sort( 'stage', __( 'Estágio', 'eb-credito-rural' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- idem. ?></th>
			<th><?php esc_html_e( 'Responsável', 'eb-credito-rural' ); ?></th>
			<th><?php echo $ebcr_sort( 'next_action_at', __( 'Próxima ação', 'eb-credito-rural' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- idem. ?></th>
			<th><?php echo $ebcr_sort( 'open_submissions', __( 'Abertas', 'eb-credito-rural' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- idem. ?></th>
			<th><?php echo $ebcr_sort( 'last_activity_at', __( 'Última atividade', 'eb-credito-rural' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- idem. ?></th>
		</tr></thead>
		<tbody>
		<?php
		foreach ( $items as $ebcr_c ) :
			$ebcr_late = $ebcr_c['next_action_at'] && strtotime( $ebcr_c['next_action_at'] . ' UTC' ) < time();
			$ebcr_wa   = Service::whatsapp_link( $ebcr_c['whatsapp'] );
			?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Cliente', 'eb-credito-rural' ); ?>"><strong><a href="<?php echo esc_url( Panel::contact_url( (int) $ebcr_c['id'] ) ); ?>"><?php echo esc_html( $ebcr_c['user_name'] ? $ebcr_c['user_name'] : __( '(usuário removido)', 'eb-credito-rural' ) ); ?></a></strong><br><span class="ebcr-muted ebcr-small"><?php echo esc_html( (string) $ebcr_c['user_email'] ); ?></span></td>
				<td data-label="<?php esc_attr_e( 'Contato', 'eb-credito-rural' ); ?>">
					<?php if ( $ebcr_c['phone'] ) : ?>
						<?php echo esc_html( Service::format_phone( $ebcr_c['phone'] ) ); ?><br>
					<?php endif; ?>
					<?php if ( $ebcr_c['whatsapp'] ) : ?>
						<?php if ( $ebcr_wa ) : ?>
							<a href="<?php echo esc_url( $ebcr_wa ); ?>" target="_blank" rel="noopener noreferrer">WhatsApp <?php echo esc_html( Service::format_phone( $ebcr_c['whatsapp'] ) ); ?></a>
						<?php else : ?>
							<?php echo esc_html( Service::format_phone( $ebcr_c['whatsapp'] ) ); ?>
						<?php endif; ?>
					<?php endif; ?>
					<?php
					if ( ! $ebcr_c['phone'] && ! $ebcr_c['whatsapp'] ) :
						?>
						—<?php endif; ?>
				</td>
				<td data-label="<?php esc_attr_e( 'Estágio', 'eb-credito-rural' ); ?>"><span class="ebcr-badge" style="--ebcr-badge:<?php echo esc_attr( Service::stage_color( $ebcr_c['stage'] ) ); ?>"><?php echo esc_html( Service::stage_label( $ebcr_c['stage'] ) ); ?></span></td>
				<td data-label="<?php esc_attr_e( 'Responsável', 'eb-credito-rural' ); ?>"><?php echo esc_html( $ebcr_c['owner_id'] ? Helpers::user_name( (int) $ebcr_c['owner_id'] ) : '—' ); ?></td>
				<td data-label="<?php esc_attr_e( 'Próxima ação', 'eb-credito-rural' ); ?>"><?php echo esc_html( $ebcr_c['next_action'] ? $ebcr_c['next_action'] : ( $ebcr_c['next_action_at'] ? '' : '—' ) ); ?>
					<?php if ( $ebcr_c['next_action_at'] ) : ?>
						<br><span class="ebcr-small <?php echo $ebcr_late ? 'ebcr-bad' : 'ebcr-muted'; ?>"><?php echo esc_html( Helpers::date( $ebcr_c['next_action_at'] ) ); ?></span>
					<?php endif; ?>
				</td>
				<td data-label="<?php esc_attr_e( 'Abertas', 'eb-credito-rural' ); ?>"><?php echo (int) $ebcr_c['open_submissions'] > 0 ? '<strong>' . (int) $ebcr_c['open_submissions'] . '</strong>' : '0'; ?></td>
				<td data-label="<?php esc_attr_e( 'Última atividade', 'eb-credito-rural' ); ?>"><?php echo esc_html( Helpers::date( $ebcr_c['last_activity_at'] ) ); ?></td>
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
