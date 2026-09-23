<?php
/**
 * CRM — quadro Kanban (painel da equipe). Arrastar e soltar via REST; sem JS, o select + "Mover" envia o formulário.
 * Variáveis: $stages, $columns, $others, $team, $owner.
 *
 * @package EBCR
 */

use EBCR\Crm\Service;
use EBCR\Frontend\Team\Panel;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;

$ebcr_action = esc_url( ebcr_form_action() );
$ebcr_card   = static function ( array $c ) use ( $stages, $ebcr_action ) {
	$url  = Panel::contact_url( (int) $c['id'] );
	$name = $c['user_name'] ? $c['user_name'] : __( '(usuário removido)', 'eb-credito-rural' );
	$late = $c['next_action_at'] && strtotime( $c['next_action_at'] . ' UTC' ) < time();
	?>
	<article class="ebcr-kcard" draggable="true" data-contact="<?php echo (int) $c['id']; ?>" tabindex="0" aria-label="<?php echo esc_attr( $name ); ?>">
		<a class="ebcr-kcard-name" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $name ); ?></a>
		<div class="ebcr-kcard-meta"><?php echo esc_html( $c['owner_id'] ? Helpers::user_name( (int) $c['owner_id'] ) : __( 'sem responsável', 'eb-credito-rural' ) ); ?></div>
		<?php if ( $c['next_action'] || $c['next_action_at'] ) : ?>
		<div class="ebcr-kcard-meta<?php echo $late ? ' is-late' : ''; ?>">
			<?php echo esc_html( $c['next_action'] ? mb_substr( $c['next_action'], 0, 60 ) : '' ); ?>
			<?php if ( $c['next_action_at'] ) : ?>
				<small><?php echo esc_html( Helpers::date( $c['next_action_at'], get_option( 'date_format' ) ) ); ?></small>
			<?php endif; ?>
		</div>
		<?php endif; ?>
		<div class="ebcr-kcard-foot">
			<span class="ebcr-pill<?php echo (int) $c['open_submissions'] > 0 ? '' : ' ebcr-pill--muted'; ?>">
				<?php
				/* translators: %d: quantidade */
				echo esc_html( sprintf( _n( '%d solicitação aberta', '%d solicitações abertas', (int) $c['open_submissions'], 'eb-credito-rural' ), (int) $c['open_submissions'] ) );
				?>
			</span>
			<form method="post" action="<?php echo $ebcr_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>" class="ebcr-kmove" data-move-form>
				<input type="hidden" name="action" value="ebcr_team_crm_stage"><input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>">
				<?php echo ebcr_nonce_field( 'team_crm_stage' ); ?>
				<label class="ebcr-sr" for="ebcr-move-<?php echo (int) $c['id']; ?>"><?php esc_html_e( 'Mover para', 'eb-credito-rural' ); ?></label>
				<select id="ebcr-move-<?php echo (int) $c['id']; ?>" name="stage" class="ebcr-input ebcr-kmove-select" data-move>
					<?php foreach ( $stages as $ebcr_k => $ebcr_l ) : ?>
						<option value="<?php echo esc_attr( $ebcr_k ); ?>" <?php selected( $c['stage'], $ebcr_k ); ?>><?php echo esc_html( $ebcr_l ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="ebcr-btn ebcr-btn--small" data-move-btn><?php esc_html_e( 'Mover', 'eb-credito-rural' ); ?></button>
			</form>
		</div>
	</article>
	<?php
};
?>
<section class="ebcr-card">
	<div class="ebcr-thead">
		<h2><?php esc_html_e( 'CRM — quadro', 'eb-credito-rural' ); ?></h2>
		<nav class="ebcr-tsubnav" aria-label="<?php esc_attr_e( 'Visões do CRM', 'eb-credito-rural' ); ?>">
			<a class="ebcr-btn ebcr-btn--small" href="<?php echo esc_url( Panel::url( array( 'tela' => 'crm' ) ) ); ?>"><?php esc_html_e( 'Lista', 'eb-credito-rural' ); ?></a>
			<a class="ebcr-btn ebcr-btn--small ebcr-btn--primary" aria-current="page" href="<?php echo esc_url( Panel::url( array( 'tela' => 'crm', 'sub' => 'quadro' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- curto. ?>"><?php esc_html_e( 'Quadro', 'eb-credito-rural' ); ?></a>
		</nav>
	</div>
	<form method="get" action="<?php echo esc_url( Helpers::portal_url() ); ?>" class="ebcr-tfilters">
		<input type="hidden" name="ebcr_view" value="equipe"><input type="hidden" name="tela" value="crm"><input type="hidden" name="sub" value="quadro">
		<label><span><?php esc_html_e( 'Responsável', 'eb-credito-rural' ); ?></span><select name="resp" class="ebcr-input"><option value=""><?php esc_html_e( 'Todos', 'eb-credito-rural' ); ?></option><option value="none" <?php selected( $owner, 'none' ); ?>><?php esc_html_e( 'Sem responsável', 'eb-credito-rural' ); ?></option>
			<?php foreach ( $team as $ebcr_u ) : ?>
				<option value="<?php echo (int) $ebcr_u->ID; ?>" <?php selected( (string) $owner, (string) $ebcr_u->ID ); ?>><?php echo esc_html( $ebcr_u->display_name ); ?></option>
			<?php endforeach; ?></select></label>
		<div class="ebcr-tfilters-actions"><button type="submit" class="ebcr-btn ebcr-btn--small"><?php esc_html_e( 'Filtrar', 'eb-credito-rural' ); ?></button></div>
		<span class="ebcr-muted ebcr-small"><?php esc_html_e( 'Arraste um cartão para outra coluna para mudar o estágio, ou use "Mover".', 'eb-credito-rural' ); ?></span>
	</form>
	<div class="ebcr-kanban" data-kanban>
		<?php foreach ( $stages as $ebcr_key => $ebcr_label ) : ?>
		<section class="ebcr-kcol" data-stage="<?php echo esc_attr( $ebcr_key ); ?>" style="--ebcr-stage:<?php echo esc_attr( Service::stage_color( $ebcr_key ) ); ?>" aria-label="<?php echo esc_attr( $ebcr_label ); ?>">
			<header class="ebcr-kcol-head"><h3><?php echo esc_html( $ebcr_label ); ?></h3><span class="ebcr-kcol-count" data-count><?php echo count( $columns[ $ebcr_key ] ); ?></span></header>
			<div class="ebcr-kcol-cards" data-dropzone>
				<?php
				foreach ( $columns[ $ebcr_key ] as $ebcr_c ) {
					$ebcr_card( $ebcr_c );
				}
				?>
				<p class="ebcr-kcol-empty ebcr-muted ebcr-small"><?php esc_html_e( 'Nenhum contato.', 'eb-credito-rural' ); ?></p>
			</div>
		</section>
		<?php endforeach; ?>
		<?php if ( $others ) : ?>
		<section class="ebcr-kcol ebcr-kcol--other" data-stage="" aria-label="<?php esc_attr_e( 'Estágio não configurado', 'eb-credito-rural' ); ?>">
			<header class="ebcr-kcol-head"><h3><?php esc_html_e( 'Estágio não configurado', 'eb-credito-rural' ); ?></h3><span class="ebcr-kcol-count" data-count><?php echo count( $others ); ?></span></header>
			<div class="ebcr-kcol-cards">
				<?php
				foreach ( $others as $ebcr_c ) {
					$ebcr_card( $ebcr_c );
				}
				?>
			</div>
		</section>
		<?php endif; ?>
	</div>
	<div class="ebcr-ktoast" data-toast role="status" aria-live="polite" hidden></div>
</section>
