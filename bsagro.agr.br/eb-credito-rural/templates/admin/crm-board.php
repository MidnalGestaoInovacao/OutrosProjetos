<?php
/**
 * Quadro Kanban do CRM (uma coluna por estágio).
 * Variáveis: $stages, $columns, $others, $team, $owner, $notice.
 *
 * @package EBCR
 */

use EBCR\Admin\Crm;
use EBCR\Crm\Service;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;

$ebcr_card = static function ( array $c ) use ( $stages ) {
	$url  = Crm::contact_url( (int) $c['id'] );
	$name = $c['user_name'] ? $c['user_name'] : __( '(usuário removido)', 'eb-credito-rural' );
	$late = $c['next_action_at'] && strtotime( $c['next_action_at'] . ' UTC' ) < time();
	?>
	<article class="ebcr-card" draggable="true" data-contact="<?php echo (int) $c['id']; ?>" tabindex="0" aria-label="<?php echo esc_attr( $name ); ?>">
		<a class="ebcr-card-name" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $name ); ?></a>
		<div class="ebcr-card-meta">
			<span class="dashicons dashicons-admin-users" aria-hidden="true"></span> <?php echo esc_html( $c['owner_id'] ? Helpers::user_name( (int) $c['owner_id'] ) : __( 'sem responsável', 'eb-credito-rural' ) ); ?>
		</div>
		<?php if ( $c['next_action'] || $c['next_action_at'] ) : ?>
		<div class="ebcr-card-meta<?php echo $late ? ' is-late' : ''; ?>">
			<span class="dashicons dashicons-flag" aria-hidden="true"></span> <?php echo esc_html( $c['next_action'] ? mb_substr( $c['next_action'], 0, 60 ) : '' ); ?>
			<?php
			if ( $c['next_action_at'] ) :
				?>
				<small><?php echo esc_html( Helpers::date( $c['next_action_at'], get_option( 'date_format' ) ) ); ?></small><?php endif; ?>
		</div>
		<?php endif; ?>
		<div class="ebcr-card-foot">
			<span class="ebcr-chip <?php echo (int) $c['open_submissions'] > 0 ? 'ebcr-chip--open' : ''; ?>">
				<?php
				/* translators: %d: quantidade */
				echo esc_html( sprintf( _n( '%d solicitação aberta', '%d solicitações abertas', (int) $c['open_submissions'], 'eb-credito-rural' ), (int) $c['open_submissions'] ) );
				?>
			</span>
			<label class="ebcr-card-move screen-reader-text" for="ebcr-move-<?php echo (int) $c['id']; ?>"><?php esc_html_e( 'Mover para', 'eb-credito-rural' ); ?></label>
			<select id="ebcr-move-<?php echo (int) $c['id']; ?>" class="ebcr-card-move" data-move hidden>
				<?php foreach ( $stages as $ebcr_k => $ebcr_l ) : ?>
					<option value="<?php echo esc_attr( $ebcr_k ); ?>" <?php selected( $c['stage'], $ebcr_k ); ?>><?php echo esc_html( $ebcr_l ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</article>
	<?php
};
?>
<div class="wrap ebcr-admin ebcr-crm">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'CRM — quadro', 'eb-credito-rural' ); ?></h1>
	<?php echo Crm::nav( 'quadro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapado no helper. ?>
	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapado em Crm::notice_html(). ?>
	<form method="get" class="ebcr-crm-boardfilter">
		<input type="hidden" name="page" value="<?php echo esc_attr( Crm::PAGE ); ?>"><input type="hidden" name="view" value="quadro">
		<label for="ebcr-board-owner" class="screen-reader-text"><?php esc_html_e( 'Responsável', 'eb-credito-rural' ); ?></label>
		<select id="ebcr-board-owner" name="owner_id"><option value=""><?php esc_html_e( 'Todos os responsáveis', 'eb-credito-rural' ); ?></option><option value="none" <?php selected( $owner, 'none' ); ?>><?php esc_html_e( 'Sem responsável', 'eb-credito-rural' ); ?></option>
			<?php foreach ( $team as $ebcr_u ) : ?>
				<option value="<?php echo (int) $ebcr_u->ID; ?>" <?php selected( (string) $owner, (string) $ebcr_u->ID ); ?>><?php echo esc_html( $ebcr_u->display_name ); ?></option>
			<?php endforeach; ?></select>
		<button class="button"><?php esc_html_e( 'Filtrar', 'eb-credito-rural' ); ?></button>
		<span class="description"><?php esc_html_e( 'Arraste um cartão para outra coluna para mudar o estágio. Sem JavaScript, altere o estágio na ficha do contato.', 'eb-credito-rural' ); ?></span>
	</form>
	<div class="ebcr-kanban" data-kanban>
		<?php foreach ( $stages as $ebcr_key => $ebcr_label ) : ?>
		<section class="ebcr-kanban-col" data-stage="<?php echo esc_attr( $ebcr_key ); ?>" style="--ebcr-stage:<?php echo esc_attr( Service::stage_color( $ebcr_key ) ); ?>" aria-label="<?php echo esc_attr( $ebcr_label ); ?>">
			<header class="ebcr-kanban-head"><h2><?php echo esc_html( $ebcr_label ); ?></h2><span class="ebcr-kanban-count" data-count><?php echo count( $columns[ $ebcr_key ] ); ?></span></header>
			<div class="ebcr-kanban-cards" data-dropzone>
				<?php
				foreach ( $columns[ $ebcr_key ] as $ebcr_c ) {
					$ebcr_card( $ebcr_c );
				}
				?>
				<p class="ebcr-kanban-empty description"><?php esc_html_e( 'Nenhum contato.', 'eb-credito-rural' ); ?></p>
			</div>
		</section>
		<?php endforeach; ?>
		<?php if ( $others ) : ?>
		<section class="ebcr-kanban-col ebcr-kanban-col--other" data-stage="" aria-label="<?php esc_attr_e( 'Estágio não configurado', 'eb-credito-rural' ); ?>">
			<header class="ebcr-kanban-head"><h2><?php esc_html_e( 'Estágio não configurado', 'eb-credito-rural' ); ?></h2><span class="ebcr-kanban-count" data-count><?php echo count( $others ); ?></span></header>
			<div class="ebcr-kanban-cards">
				<?php
				foreach ( $others as $ebcr_c ) {
					$ebcr_card( $ebcr_c );
				}
				?>
			</div>
		</section>
		<?php endif; ?>
	</div>
	<div class="ebcr-kanban-toast" data-toast role="status" aria-live="polite" hidden></div>
</div>
