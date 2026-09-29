<?php
/**
 * CRM — ficha do cliente (painel da equipe).
 * Variáveis: $contact, $user, $submissions, $activities, $open_tasks, $team, $stages, $sources, $types, $open_status, $current_uid, $tab.
 *
 * @package EBCR
 */

use EBCR\Crm\Service;
use EBCR\Frontend\Team\Panel;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;

$ebcr_action = esc_url( ebcr_form_action() );
$ebcr_name   = $user ? $user->display_name : __( '(usuário removido)', 'eb-credito-rural' );
$ebcr_tags   = Service::tags_list( $contact['tags'] );
$ebcr_wa     = Service::whatsapp_link( $contact['whatsapp'] );
$ebcr_open   = 0;
foreach ( $submissions as $ebcr_s ) {
	if ( in_array( $ebcr_s['status'], $open_status, true ) ) {
		++$ebcr_open;
	}
}
$ebcr_tabs    = array(
	'ficha'        => __( 'Ficha', 'eb-credito-rural' ),
	/* translators: %d: quantidade */
	'solicitacoes' => sprintf( __( 'Solicitações (%d)', 'eb-credito-rural' ), count( $submissions ) ),
	/* translators: %d: quantidade */
	'atividades'   => sprintf( __( 'Linha do tempo (%d)', 'eb-credito-rural' ), count( $activities ) ),
);
$ebcr_active  = isset( $ebcr_tabs[ $tab ] ) ? $tab : 'ficha';
$ebcr_panel   = static function ( $key ) use ( $ebcr_active ) {
	return sprintf( '<section class="ebcr-tpanel ebcr-card" id="ebcr-tab-%1$s" data-panel="%1$s" role="tabpanel"%2$s>', esc_attr( $key ), $key === $ebcr_active ? '' : ' hidden' );
};
$ebcr_kv      = static function ( $label, $html ) {
	echo '<dt>' . esc_html( $label ) . '</dt><dd>' . $html . '</dd>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $html já escapado pelo chamador.
};
$ebcr_tab_url = static function ( $key ) use ( $contact ) {
	return Panel::url(
		array(
			'tela' => 'crm',
			'sub'  => 'contato',
			'id'   => (int) $contact['id'],
			'tab'  => $key,
		)
	);
};
?>
<p><a class="ebcr-link" href="<?php echo esc_url( Panel::url( array( 'tela' => 'crm' ) ) ); ?>">&larr; <?php esc_html_e( 'CRM', 'eb-credito-rural' ); ?></a></p>
<div class="ebcr-card ebcr-sub-head ebcr-tsub-head">
	<div>
		<h2><?php echo esc_html( $ebcr_name ); ?> <span class="ebcr-badge" style="--ebcr-badge:<?php echo esc_attr( Service::stage_color( $contact['stage'] ) ); ?>"><?php echo esc_html( Service::stage_label( $contact['stage'] ) ); ?></span></h2>
		<p class="ebcr-muted"><?php echo esc_html( $user ? $user->user_email : '' ); ?></p>
	</div>
	<div class="ebcr-actions ebcr-tsub-actions">
		<a class="ebcr-btn ebcr-btn--small" href="<?php echo esc_url( Panel::url( array( 'tela' => 'crm', 'sub' => 'quadro' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- curto. ?>"><?php esc_html_e( 'Quadro', 'eb-credito-rural' ); ?></a>
	</div>
</div>
<div class="ebcr-tdetail">
	<div class="ebcr-tdetail-main">
		<nav class="ebcr-ttabs" role="tablist" aria-label="<?php esc_attr_e( 'Seções da ficha', 'eb-credito-rural' ); ?>">
			<?php foreach ( $ebcr_tabs as $ebcr_k => $ebcr_l ) : ?>
				<a role="tab" class="ebcr-ttab<?php echo $ebcr_k === $ebcr_active ? ' is-active' : ''; ?>" data-tab="<?php echo esc_attr( $ebcr_k ); ?>" aria-selected="<?php echo $ebcr_k === $ebcr_active ? 'true' : 'false'; ?>" aria-controls="ebcr-tab-<?php echo esc_attr( $ebcr_k ); ?>" href="<?php echo esc_url( $ebcr_tab_url( $ebcr_k ) ); ?>"><?php echo esc_html( $ebcr_l ); ?></a>
			<?php endforeach; ?>
		</nav>

		<?php echo $ebcr_panel( 'ficha' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado na closure. ?>
			<form method="post" action="<?php echo $ebcr_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>" class="ebcr-form">
				<input type="hidden" name="action" value="ebcr_team_crm_contact"><input type="hidden" name="contact_id" value="<?php echo (int) $contact['id']; ?>">
				<?php echo ebcr_nonce_field( 'team_crm_contact' ); ?>
				<div class="ebcr-row">
					<?php echo ebcr_input( 'nome_ro', __( 'Nome', 'eb-credito-rural' ), $ebcr_name, array( 'readonly' => true ) ); ?>
					<?php echo ebcr_input( 'email_ro', __( 'E-mail', 'eb-credito-rural' ), $user ? $user->user_email : '', array( 'readonly' => true, 'type' => 'email' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- curto. ?>
					<?php echo ebcr_input( 'phone', __( 'Telefone', 'eb-credito-rural' ), Service::format_phone( $contact['phone'] ), array( 'type' => 'tel', 'placeholder' => '(00) 00000-0000', 'data-mask' => 'phone' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- curto. ?>
					<?php echo ebcr_input( 'whatsapp', __( 'WhatsApp', 'eb-credito-rural' ), Service::format_phone( $contact['whatsapp'] ), array( 'type' => 'tel', 'placeholder' => '(00) 00000-0000', 'data-mask' => 'phone' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- curto. ?>
					<?php echo ebcr_select( 'lead_source', __( 'Origem do lead', 'eb-credito-rural' ), $sources, $contact['lead_source'] ); ?>
					<?php echo ebcr_input( 'tags', __( 'Tags (separadas por vírgula)', 'eb-credito-rural' ), implode( ', ', $ebcr_tags ), array( 'placeholder' => 'ex.: soja, prioridade, safra 2026' ) ); ?>
					<?php
					$ebcr_stage_opts = $stages;
					if ( ! isset( $ebcr_stage_opts[ $contact['stage'] ] ) && $contact['stage'] ) {
						$ebcr_stage_opts[ $contact['stage'] ] = $contact['stage'] . ' (' . __( 'não configurado', 'eb-credito-rural' ) . ')';
					}
					echo ebcr_select( 'stage', __( 'Estágio', 'eb-credito-rural' ), $ebcr_stage_opts, $contact['stage'], array( 'required' => true ) );
					$ebcr_team_opts = array( '0' => __( '— Nenhum —', 'eb-credito-rural' ) );
					foreach ( $team as $ebcr_u ) {
						$ebcr_team_opts[ (string) $ebcr_u->ID ] = $ebcr_u->display_name;
					}
					echo ebcr_select( 'owner_id', __( 'Responsável', 'eb-credito-rural' ), $ebcr_team_opts, (string) (int) $contact['owner_id'] );
					?>
					<?php echo ebcr_input( 'next_action', __( 'Próxima ação', 'eb-credito-rural' ), $contact['next_action'], array( 'maxlength' => 255 ) ); ?>
					<?php echo ebcr_input( 'next_action_at', __( 'Data da próxima ação', 'eb-credito-rural' ), Service::utc_to_input( $contact['next_action_at'] ), array( 'type' => 'datetime-local' ) ); ?>
				</div>
				<?php echo ebcr_textarea( 'notes', __( 'Notas internas', 'eb-credito-rural' ), (string) $contact['notes'], array( 'rows' => 4 ) ); ?>
				<div class="ebcr-actions"><button type="submit" class="ebcr-btn ebcr-btn--primary"><?php esc_html_e( 'Salvar ficha', 'eb-credito-rural' ); ?></button> <span class="ebcr-muted ebcr-small"><?php esc_html_e( 'Criada em', 'eb-credito-rural' ); ?> <?php echo esc_html( Helpers::date( $contact['created_at'] ) ); ?> · <?php esc_html_e( 'atualizada em', 'eb-credito-rural' ); ?> <?php echo esc_html( Helpers::date( $contact['updated_at'] ) ); ?></span></div>
			</form>
		</section>

		<?php echo $ebcr_panel( 'solicitacoes' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado na closure. ?>
			<?php if ( ! $submissions ) : ?>
				<p class="ebcr-muted"><?php esc_html_e( 'Este cliente ainda não tem solicitações.', 'eb-credito-rural' ); ?></p>
			<?php else : ?>
			<div class="ebcr-table-wrap"><table class="ebcr-table"><thead><tr><th><?php esc_html_e( 'Protocolo', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Status', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Valor', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Enviada em', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Analista', 'eb-credito-rural' ); ?></th></tr></thead><tbody>
				<?php foreach ( $submissions as $ebcr_s ) : ?>
				<tr>
					<td data-label="<?php esc_attr_e( 'Protocolo', 'eb-credito-rural' ); ?>"><strong><a href="<?php echo esc_url( Panel::submission_url( $ebcr_s ) ); ?>"><?php echo esc_html( $ebcr_s['protocol'] ? $ebcr_s['protocol'] : __( '(rascunho)', 'eb-credito-rural' ) ); ?></a></strong></td>
					<td data-label="<?php esc_attr_e( 'Status', 'eb-credito-rural' ); ?>"><?php echo ebcr_status_badge( $ebcr_s['status'] ); ?></td>
					<td data-label="<?php esc_attr_e( 'Valor', 'eb-credito-rural' ); ?>"><?php echo esc_html( Helpers::money( $ebcr_s['requested_amount'] ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Enviada em', 'eb-credito-rural' ); ?>"><?php echo esc_html( Helpers::date( $ebcr_s['submitted_at'] ? $ebcr_s['submitted_at'] : $ebcr_s['created_at'] ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Analista', 'eb-credito-rural' ); ?>"><?php echo esc_html( $ebcr_s['assigned_to'] ? Helpers::user_name( (int) $ebcr_s['assigned_to'] ) : '—' ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody></table></div>
			<?php endif; ?>
		</section>

		<?php echo $ebcr_panel( 'atividades' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado na closure. ?>
			<?php if ( ! $activities ) : ?>
				<p class="ebcr-muted"><?php esc_html_e( 'Nenhuma atividade registrada. Use os formulários ao lado.', 'eb-credito-rural' ); ?></p>
			<?php else : ?>
			<ol class="ebcr-timeline">
				<?php
				foreach ( $activities as $ebcr_a ) :
					$ebcr_is_task = Service::TYPE_TASK === $ebcr_a['type'];
					$ebcr_late    = $ebcr_is_task && empty( $ebcr_a['done_at'] ) && $ebcr_a['due_at'] && strtotime( $ebcr_a['due_at'] . ' UTC' ) < time();
					?>
				<li class="ebcr-tact ebcr-tact--<?php echo esc_attr( $ebcr_a['type'] ); ?><?php echo $ebcr_is_task && ! empty( $ebcr_a['done_at'] ) ? ' is-done' : ''; ?>"><span class="ebcr-timeline-dot"></span><div>
					<strong><?php echo esc_html( Service::type_label( $ebcr_a['type'] ) ); ?></strong>
					<span class="ebcr-muted ebcr-small"><?php echo esc_html( Helpers::date( $ebcr_a['created_at'] ) . ' · ' . Helpers::user_name( (int) $ebcr_a['created_by'] ) ); ?></span>
					<?php if ( $ebcr_is_task ) : ?>
						<span class="ebcr-pill <?php echo ! empty( $ebcr_a['done_at'] ) ? 'ebcr-pill--ok' : ( $ebcr_late ? 'ebcr-pill--warn' : '' ); ?>">
							<?php
							if ( ! empty( $ebcr_a['done_at'] ) ) {
								/* translators: %s: data */
								echo esc_html( sprintf( __( 'concluída em %s', 'eb-credito-rural' ), Helpers::date( $ebcr_a['done_at'] ) ) );
							} elseif ( $ebcr_a['due_at'] ) {
								/* translators: %s: data */
								echo esc_html( sprintf( $ebcr_late ? __( 'vencida em %s', 'eb-credito-rural' ) : __( 'vence em %s', 'eb-credito-rural' ), Helpers::date( $ebcr_a['due_at'] ) ) );
							} else {
								esc_html_e( 'sem prazo', 'eb-credito-rural' );
							}
							?>
						</span>
					<?php endif; ?>
					<p><?php echo nl2br( esc_html( $ebcr_a['description'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html + nl2br. ?></p>
					<?php
					if ( $ebcr_a['submission_id'] ) :
						foreach ( $submissions as $ebcr_s ) :
							if ( (int) $ebcr_s['id'] === (int) $ebcr_a['submission_id'] ) :
								?>
								<span class="ebcr-muted ebcr-small"><?php esc_html_e( 'Solicitação:', 'eb-credito-rural' ); ?> <a href="<?php echo esc_url( Panel::submission_url( $ebcr_s ) ); ?>"><?php echo esc_html( $ebcr_s['protocol'] ? $ebcr_s['protocol'] : __( '(rascunho)', 'eb-credito-rural' ) ); ?></a></span>
								<?php
							endif;
						endforeach;
					endif;
					?>
				</div></li>
				<?php endforeach; ?>
			</ol>
			<?php endif; ?>
		</section>
	</div>

	<aside class="ebcr-tdetail-side">
		<div class="ebcr-card">
			<h3><?php esc_html_e( 'Resumo', 'eb-credito-rural' ); ?></h3>
			<dl class="ebcr-kv">
				<?php
				$ebcr_kv( __( 'E-mail', 'eb-credito-rural' ), $user ? '<a href="mailto:' . esc_attr( $user->user_email ) . '">' . esc_html( $user->user_email ) . '</a>' : '—' );
				$ebcr_kv( __( 'Telefone', 'eb-credito-rural' ), $contact['phone'] ? '<a href="tel:+55' . esc_attr( $contact['phone'] ) . '">' . esc_html( Service::format_phone( $contact['phone'] ) ) . '</a>' : '—' );
				$ebcr_kv( __( 'WhatsApp', 'eb-credito-rural' ), $ebcr_wa ? '<a href="' . esc_url( $ebcr_wa ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( Service::format_phone( $contact['whatsapp'] ) ) . ' ↗</a>' : '—' );
				$ebcr_kv( __( 'Origem', 'eb-credito-rural' ), esc_html( isset( $sources[ $contact['lead_source'] ] ) ? $sources[ $contact['lead_source'] ] : ( $contact['lead_source'] ? $contact['lead_source'] : '—' ) ) );
				$ebcr_kv( __( 'Responsável', 'eb-credito-rural' ), esc_html( $contact['owner_id'] ? Helpers::user_name( (int) $contact['owner_id'] ) : '—' ) );
				$ebcr_kv( __( 'Próxima ação', 'eb-credito-rural' ), esc_html( $contact['next_action'] ? $contact['next_action'] : '—' ) . ( $contact['next_action_at'] ? '<br><span class="ebcr-muted ebcr-small">' . esc_html( Helpers::date( $contact['next_action_at'] ) ) . '</span>' : '' ) );
				$ebcr_kv( __( 'Solicitações abertas', 'eb-credito-rural' ), '<strong>' . (int) $ebcr_open . '</strong> / ' . count( $submissions ) );
				?>
			</dl>
		</div>

		<div class="ebcr-card">
			<h3><?php esc_html_e( 'Registrar atividade', 'eb-credito-rural' ); ?></h3>
			<form method="post" action="<?php echo $ebcr_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>" class="ebcr-form">
				<input type="hidden" name="action" value="ebcr_team_crm_activity"><input type="hidden" name="contact_id" value="<?php echo (int) $contact['id']; ?>">
				<?php echo ebcr_nonce_field( 'team_crm_activity' ); ?>
				<?php
				$ebcr_type_opts = $types;
				unset( $ebcr_type_opts[ Service::TYPE_TASK ] );
				echo ebcr_select( 'type', __( 'Tipo', 'eb-credito-rural' ), $ebcr_type_opts, 'ligacao', array( 'required' => true ) );
				if ( $submissions ) {
					$ebcr_sub_opts = array();
					foreach ( $submissions as $ebcr_s ) {
						$ebcr_sub_opts[ (string) $ebcr_s['id'] ] = $ebcr_s['protocol'] ? $ebcr_s['protocol'] : __( '(rascunho)', 'eb-credito-rural' );
					}
					echo ebcr_select( 'submission_id', __( 'Solicitação relacionada', 'eb-credito-rural' ), $ebcr_sub_opts, '', array(), '', __( 'Opcional.', 'eb-credito-rural' ) );
				}
				echo ebcr_textarea(
					'description',
					__( 'Descrição', 'eb-credito-rural' ),
					'',
					array(
						'required' => true,
						'rows'     => 3,
						'id'       => 'ebcr-act-desc',
					)
				);
				?>
				<button type="submit" class="ebcr-btn ebcr-btn--primary ebcr-btn--block"><?php esc_html_e( 'Registrar', 'eb-credito-rural' ); ?></button>
			</form>
		</div>

		<div class="ebcr-card">
			<h3><?php esc_html_e( 'Nova tarefa', 'eb-credito-rural' ); ?></h3>
			<form method="post" action="<?php echo $ebcr_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>" class="ebcr-form">
				<input type="hidden" name="action" value="ebcr_team_crm_activity"><input type="hidden" name="contact_id" value="<?php echo (int) $contact['id']; ?>"><input type="hidden" name="type" value="<?php echo esc_attr( Service::TYPE_TASK ); ?>">
				<?php echo ebcr_nonce_field( 'team_crm_activity' ); ?>
				<div class="ebcr-field"><label for="ebcr-task-desc"><?php esc_html_e( 'Descrição', 'eb-credito-rural' ); ?> <span class="ebcr-req">*</span></label><textarea id="ebcr-task-desc" name="description" class="ebcr-input" rows="2" required></textarea></div>
				<div class="ebcr-field"><label for="ebcr-task-due"><?php esc_html_e( 'Vencimento', 'eb-credito-rural' ); ?></label><input type="datetime-local" id="ebcr-task-due" name="due_at" class="ebcr-input" value="<?php echo esc_attr( wp_date( 'Y-m-d\TH:i', strtotime( '+1 day 09:00' ) ) ); ?>"></div>
				<div class="ebcr-field"><label for="ebcr-task-owner"><?php esc_html_e( 'Responsável', 'eb-credito-rural' ); ?></label><select id="ebcr-task-owner" name="assignee_id" class="ebcr-input">
					<?php foreach ( $team as $ebcr_u ) : ?>
						<option value="<?php echo (int) $ebcr_u->ID; ?>" <?php selected( (int) $ebcr_u->ID, (int) ( $contact['owner_id'] ? $contact['owner_id'] : $current_uid ) ); ?>><?php echo esc_html( $ebcr_u->display_name ); ?></option>
					<?php endforeach; ?></select></div>
				<?php if ( $submissions ) : ?>
				<div class="ebcr-field"><label for="ebcr-task-sub"><?php esc_html_e( 'Solicitação relacionada (opcional)', 'eb-credito-rural' ); ?></label><select id="ebcr-task-sub" name="submission_id" class="ebcr-input"><option value="0">—</option>
					<?php foreach ( $submissions as $ebcr_s ) : ?>
						<option value="<?php echo (int) $ebcr_s['id']; ?>"><?php echo esc_html( $ebcr_s['protocol'] ? $ebcr_s['protocol'] : __( '(rascunho)', 'eb-credito-rural' ) ); ?></option>
					<?php endforeach; ?></select></div>
				<?php endif; ?>
				<button type="submit" class="ebcr-btn ebcr-btn--primary ebcr-btn--block"><?php esc_html_e( 'Criar tarefa', 'eb-credito-rural' ); ?></button>
			</form>
		</div>

		<div class="ebcr-card">
			<h3><?php esc_html_e( 'Tarefas abertas', 'eb-credito-rural' ); ?></h3>
			<?php if ( ! $open_tasks ) : ?>
				<p class="ebcr-muted"><?php esc_html_e( 'Nenhuma tarefa aberta.', 'eb-credito-rural' ); ?></p>
			<?php else : ?>
			<ul class="ebcr-tlist ebcr-ttasks">
				<?php
				foreach ( $open_tasks as $ebcr_t ) :
					$ebcr_late = $ebcr_t['due_at'] && strtotime( $ebcr_t['due_at'] . ' UTC' ) < time();
					?>
				<li class="<?php echo $ebcr_late ? 'is-late' : ''; ?>">
					<div><?php echo esc_html( mb_substr( $ebcr_t['description'], 0, 140 ) ); ?></div>
					<span class="ebcr-small <?php echo $ebcr_late ? 'ebcr-bad' : 'ebcr-muted'; ?>"><?php echo esc_html( ( $ebcr_t['due_at'] ? Helpers::date( $ebcr_t['due_at'] ) : __( 'sem prazo', 'eb-credito-rural' ) ) . ' · ' . Helpers::user_name( (int) $ebcr_t['created_by'] ) ); ?></span>
					<form method="post" action="<?php echo $ebcr_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>" class="ebcr-inline-form">
						<input type="hidden" name="action" value="ebcr_team_crm_task_done"><input type="hidden" name="contact_id" value="<?php echo (int) $contact['id']; ?>"><input type="hidden" name="activity_id" value="<?php echo (int) $ebcr_t['id']; ?>">
						<?php echo ebcr_nonce_field( 'team_crm_task' ); ?>
						<button type="submit" class="ebcr-btn ebcr-btn--small"><?php esc_html_e( 'Concluir', 'eb-credito-rural' ); ?></button>
					</form>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</div>
	</aside>
</div>
