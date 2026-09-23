<?php
/**
 * Ficha CRM do cliente.
 * Variáveis: $contact, $user, $submissions, $activities, $open_tasks, $team, $stages, $sources, $types, $open_status, $notice, $current_uid.
 *
 * @package EBCR
 */

use EBCR\Admin\Crm;
use EBCR\Crm\Service;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;

$ebcr_post  = esc_url( admin_url( 'admin-post.php' ) );
$ebcr_name  = $user ? $user->display_name : __( '(usuário removido)', 'eb-credito-rural' );
$ebcr_tags  = Service::tags_list( $contact['tags'] );
$ebcr_wa    = Service::whatsapp_link( $contact['whatsapp'] );
$ebcr_open  = 0;
$ebcr_tasks = 0;
foreach ( $submissions as $ebcr_s ) {
	if ( in_array( $ebcr_s['status'], $open_status, true ) ) {
		++$ebcr_open;
	}
}
$ebcr_tabs = array(
	'ficha'        => __( 'Ficha', 'eb-credito-rural' ),
	/* translators: %d: quantidade */
	'solicitacoes' => sprintf( __( 'Solicitações (%d)', 'eb-credito-rural' ), count( $submissions ) ),
	/* translators: %d: quantidade */
	'atividades'   => sprintf( __( 'Linha do tempo (%d)', 'eb-credito-rural' ), count( $activities ) ),
);
$ebcr_kv   = static function ( $label, $html ) {
	echo '<dt>' . esc_html( $label ) . '</dt><dd>' . $html . '</dd>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $html já escapado pelo chamador.
};
?>
<div class="wrap ebcr-admin ebcr-crm">
	<h1 class="wp-heading-inline"><?php echo esc_html( $ebcr_name ); ?> <span class="ebcr-badge" style="--ebcr-badge:<?php echo esc_attr( Service::stage_color( $contact['stage'] ) ); ?>"><?php echo esc_html( Service::stage_label( $contact['stage'] ) ); ?></span></h1>
	<a href="<?php echo esc_url( Crm::url( array( 'view' => 'lista' ) ) ); ?>" class="page-title-action"><?php esc_html_e( '← Lista', 'eb-credito-rural' ); ?></a>
	<a href="<?php echo esc_url( Crm::url( array( 'view' => 'quadro' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Quadro', 'eb-credito-rural' ); ?></a>
	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapado em Crm::notice_html(). ?>
	<div class="ebcr-detail">
		<div>
			<nav class="nav-tab-wrapper ebcr-tabs-nav">
				<?php foreach ( $ebcr_tabs as $ebcr_k => $ebcr_l ) : ?>
					<a href="#" class="nav-tab" data-tab="<?php echo esc_attr( $ebcr_k ); ?>"><?php echo esc_html( $ebcr_l ); ?></a>
				<?php endforeach; ?>
			</nav>

			<div class="ebcr-tabpanel ebcr-box" id="ebcr-tab-ficha">
				<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>" class="ebcr-crm-form">
					<input type="hidden" name="action" value="ebcr_admin_crm_contact"><input type="hidden" name="contact_id" value="<?php echo (int) $contact['id']; ?>">
					<?php echo ebcr_nonce_field( 'crm_contact' ); ?>
					<div class="ebcr-crm-grid">
						<p><label for="ebcr-crm-name"><?php esc_html_e( 'Nome', 'eb-credito-rural' ); ?></label><input type="text" id="ebcr-crm-name" class="widefat" value="<?php echo esc_attr( $ebcr_name ); ?>" readonly>
							<?php if ( $user && current_user_can( 'edit_user', $user->ID ) ) : ?>
							<span class="description"><a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php esc_html_e( 'Editar cadastro do usuário', 'eb-credito-rural' ); ?></a></span>
							<?php endif; ?>
						</p>
						<p><label for="ebcr-crm-email"><?php esc_html_e( 'E-mail', 'eb-credito-rural' ); ?></label><input type="email" id="ebcr-crm-email" class="widefat" value="<?php echo esc_attr( $user ? $user->user_email : '' ); ?>" readonly></p>
						<p><label for="ebcr-crm-phone"><?php esc_html_e( 'Telefone', 'eb-credito-rural' ); ?></label><input type="tel" id="ebcr-crm-phone" name="phone" class="widefat" value="<?php echo esc_attr( Service::format_phone( $contact['phone'] ) ); ?>" placeholder="(00) 00000-0000"></p>
						<p><label for="ebcr-crm-whatsapp"><?php esc_html_e( 'WhatsApp', 'eb-credito-rural' ); ?></label><input type="tel" id="ebcr-crm-whatsapp" name="whatsapp" class="widefat" value="<?php echo esc_attr( Service::format_phone( $contact['whatsapp'] ) ); ?>" placeholder="(00) 00000-0000"></p>
						<p><label for="ebcr-crm-source"><?php esc_html_e( 'Origem do lead', 'eb-credito-rural' ); ?></label><select id="ebcr-crm-source" name="lead_source" class="widefat"><option value=""><?php esc_html_e( '— Não informada —', 'eb-credito-rural' ); ?></option>
							<?php foreach ( $sources as $ebcr_k => $ebcr_l ) : ?>
								<option value="<?php echo esc_attr( $ebcr_k ); ?>" <?php selected( $contact['lead_source'], $ebcr_k ); ?>><?php echo esc_html( $ebcr_l ); ?></option>
							<?php endforeach; ?></select></p>
						<p><label for="ebcr-crm-tags"><?php esc_html_e( 'Tags (separadas por vírgula)', 'eb-credito-rural' ); ?></label><input type="text" id="ebcr-crm-tags" name="tags" class="widefat" value="<?php echo esc_attr( implode( ', ', $ebcr_tags ) ); ?>" placeholder="<?php esc_attr_e( 'ex.: soja, prioridade, safra 2026', 'eb-credito-rural' ); ?>">
							<?php if ( $ebcr_tags ) : ?>
							<span class="ebcr-chips">
								<?php foreach ( $ebcr_tags as $ebcr_t ) : ?>
									<a class="ebcr-chip" href="<?php echo esc_url( Crm::url( array( 'view' => 'lista', 'tag' => $ebcr_t ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- curto. ?>"><?php echo esc_html( $ebcr_t ); ?></a>
								<?php endforeach; ?>
							</span>
							<?php endif; ?>
						</p>
						<p><label for="ebcr-crm-stage"><?php esc_html_e( 'Estágio', 'eb-credito-rural' ); ?></label><select id="ebcr-crm-stage" name="stage" class="widefat">
							<?php foreach ( $stages as $ebcr_k => $ebcr_l ) : ?>
								<option value="<?php echo esc_attr( $ebcr_k ); ?>" <?php selected( $contact['stage'], $ebcr_k ); ?>><?php echo esc_html( $ebcr_l ); ?></option>
							<?php endforeach; ?>
							<?php if ( ! isset( $stages[ $contact['stage'] ] ) ) : ?>
								<option value="<?php echo esc_attr( $contact['stage'] ); ?>" selected><?php echo esc_html( $contact['stage'] ); ?> (<?php esc_html_e( 'não configurado', 'eb-credito-rural' ); ?>)</option>
							<?php endif; ?></select></p>
						<p><label for="ebcr-crm-owner"><?php esc_html_e( 'Responsável', 'eb-credito-rural' ); ?></label><select id="ebcr-crm-owner" name="owner_id" class="widefat"><option value="0"><?php esc_html_e( '— Nenhum —', 'eb-credito-rural' ); ?></option>
							<?php foreach ( $team as $ebcr_u ) : ?>
								<option value="<?php echo (int) $ebcr_u->ID; ?>" <?php selected( (int) $contact['owner_id'], (int) $ebcr_u->ID ); ?>><?php echo esc_html( $ebcr_u->display_name ); ?></option>
							<?php endforeach; ?></select></p>
						<p><label for="ebcr-crm-next"><?php esc_html_e( 'Próxima ação', 'eb-credito-rural' ); ?></label><input type="text" id="ebcr-crm-next" name="next_action" class="widefat" value="<?php echo esc_attr( $contact['next_action'] ); ?>" maxlength="255"></p>
						<p><label for="ebcr-crm-next-at"><?php esc_html_e( 'Data da próxima ação', 'eb-credito-rural' ); ?></label><input type="datetime-local" id="ebcr-crm-next-at" name="next_action_at" class="widefat" value="<?php echo esc_attr( Service::utc_to_input( $contact['next_action_at'] ) ); ?>"></p>
					</div>
					<p><label for="ebcr-crm-notes"><?php esc_html_e( 'Notas internas', 'eb-credito-rural' ); ?></label><textarea id="ebcr-crm-notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( (string) $contact['notes'] ); ?></textarea></p>
					<p><button class="button button-primary"><?php esc_html_e( 'Salvar ficha', 'eb-credito-rural' ); ?></button> <span class="description"><?php esc_html_e( 'Criada em', 'eb-credito-rural' ); ?> <?php echo esc_html( Helpers::date( $contact['created_at'] ) ); ?> · <?php esc_html_e( 'atualizada em', 'eb-credito-rural' ); ?> <?php echo esc_html( Helpers::date( $contact['updated_at'] ) ); ?></span></p>
				</form>
			</div>

			<div class="ebcr-tabpanel ebcr-box" id="ebcr-tab-solicitacoes" hidden>
				<?php if ( ! $submissions ) : ?>
					<p class="description"><?php esc_html_e( 'Este cliente ainda não tem solicitações.', 'eb-credito-rural' ); ?></p>
				<?php else : ?>
				<table class="ebcr-t"><thead><tr><th><?php esc_html_e( 'Protocolo', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Status', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Valor', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Enviada em', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Analista', 'eb-credito-rural' ); ?></th></tr></thead><tbody>
					<?php foreach ( $submissions as $ebcr_s ) : ?>
					<tr>
						<td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=ebcr-submissions&view=' . rawurlencode( $ebcr_s['public_id'] ) ) ); ?>"><?php echo esc_html( $ebcr_s['protocol'] ? $ebcr_s['protocol'] : __( '(rascunho)', 'eb-credito-rural' ) ); ?></a></strong></td>
						<td><?php echo ebcr_status_badge( $ebcr_s['status'] ); ?></td>
						<td><?php echo esc_html( Helpers::money( $ebcr_s['requested_amount'] ) ); ?></td>
						<td><?php echo esc_html( Helpers::date( $ebcr_s['submitted_at'] ? $ebcr_s['submitted_at'] : $ebcr_s['created_at'] ) ); ?></td>
						<td><?php echo esc_html( $ebcr_s['assigned_to'] ? Helpers::user_name( (int) $ebcr_s['assigned_to'] ) : '—' ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody></table>
				<?php endif; ?>
			</div>

			<div class="ebcr-tabpanel ebcr-box" id="ebcr-tab-atividades" hidden>
				<?php if ( ! $activities ) : ?>
					<p class="description"><?php esc_html_e( 'Nenhuma atividade registrada. Use os formulários ao lado.', 'eb-credito-rural' ); ?></p>
				<?php else : ?>
				<ul class="ebcr-timeline ebcr-crm-timeline">
					<?php
					foreach ( $activities as $ebcr_a ) :
						$ebcr_is_task = Service::TYPE_TASK === $ebcr_a['type'];
						$ebcr_late    = $ebcr_is_task && empty( $ebcr_a['done_at'] ) && $ebcr_a['due_at'] && strtotime( $ebcr_a['due_at'] . ' UTC' ) < time();
						?>
					<li class="ebcr-act ebcr-act--<?php echo esc_attr( $ebcr_a['type'] ); ?><?php echo $ebcr_is_task && ! empty( $ebcr_a['done_at'] ) ? ' is-done' : ''; ?><?php echo $ebcr_late ? ' is-late' : ''; ?>">
						<strong><?php echo esc_html( Service::type_label( $ebcr_a['type'] ) ); ?></strong>
						<span class="description"><?php echo esc_html( Helpers::date( $ebcr_a['created_at'] ) . ' · ' . Helpers::user_name( (int) $ebcr_a['created_by'] ) ); ?></span>
						<?php if ( $ebcr_is_task ) : ?>
							<span class="ebcr-chip <?php echo ! empty( $ebcr_a['done_at'] ) ? 'ebcr-chip--ok' : ( $ebcr_late ? 'ebcr-chip--late' : 'ebcr-chip--open' ); ?>">
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
									<span class="description"><?php esc_html_e( 'Solicitação:', 'eb-credito-rural' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=ebcr-submissions&view=' . rawurlencode( $ebcr_s['public_id'] ) ) ); ?>"><?php echo esc_html( $ebcr_s['protocol'] ? $ebcr_s['protocol'] : __( '(rascunho)', 'eb-credito-rural' ) ); ?></a></span>
									<?php
								endif;
							endforeach;
						endif;
						?>
					</li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>
		</div>

		<aside>
			<div class="ebcr-box">
				<h3><?php esc_html_e( 'Resumo', 'eb-credito-rural' ); ?></h3>
				<dl class="ebcr-kv">
					<?php
					$ebcr_kv( __( 'E-mail', 'eb-credito-rural' ), $user ? '<a href="mailto:' . esc_attr( $user->user_email ) . '">' . esc_html( $user->user_email ) . '</a>' : '—' );
					$ebcr_kv( __( 'Telefone', 'eb-credito-rural' ), $contact['phone'] ? '<a href="tel:+55' . esc_attr( $contact['phone'] ) . '">' . esc_html( Service::format_phone( $contact['phone'] ) ) . '</a>' : '—' );
					$ebcr_kv( __( 'WhatsApp', 'eb-credito-rural' ), $ebcr_wa ? '<a href="' . esc_url( $ebcr_wa ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( Service::format_phone( $contact['whatsapp'] ) ) . ' ↗</a>' : '—' );
					$ebcr_kv( __( 'Origem', 'eb-credito-rural' ), esc_html( isset( $sources[ $contact['lead_source'] ] ) ? $sources[ $contact['lead_source'] ] : ( $contact['lead_source'] ? $contact['lead_source'] : '—' ) ) );
					$ebcr_kv( __( 'Responsável', 'eb-credito-rural' ), esc_html( $contact['owner_id'] ? Helpers::user_name( (int) $contact['owner_id'] ) : '—' ) );
					$ebcr_kv( __( 'Próxima ação', 'eb-credito-rural' ), esc_html( $contact['next_action'] ? $contact['next_action'] : '—' ) . ( $contact['next_action_at'] ? '<br><span class="description">' . esc_html( Helpers::date( $contact['next_action_at'] ) ) . '</span>' : '' ) );
					$ebcr_kv( __( 'Solicitações abertas', 'eb-credito-rural' ), '<strong>' . (int) $ebcr_open . '</strong> / ' . count( $submissions ) );
					?>
				</dl>
			</div>

			<div class="ebcr-box">
				<h3><?php esc_html_e( 'Registrar atividade', 'eb-credito-rural' ); ?></h3>
				<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
					<input type="hidden" name="action" value="ebcr_admin_crm_activity"><input type="hidden" name="contact_id" value="<?php echo (int) $contact['id']; ?>">
					<?php echo ebcr_nonce_field( 'crm_activity' ); ?>
					<p><label for="ebcr-act-type"><?php esc_html_e( 'Tipo', 'eb-credito-rural' ); ?></label><select id="ebcr-act-type" name="type" class="widefat">
						<?php
						foreach ( $types as $ebcr_k => $ebcr_l ) :
							if ( Service::TYPE_TASK === $ebcr_k ) {
								continue;
							}
							?>
							<option value="<?php echo esc_attr( $ebcr_k ); ?>"><?php echo esc_html( $ebcr_l ); ?></option>
						<?php endforeach; ?></select></p>
					<?php if ( $submissions ) : ?>
					<p><label for="ebcr-act-sub"><?php esc_html_e( 'Solicitação relacionada (opcional)', 'eb-credito-rural' ); ?></label><select id="ebcr-act-sub" name="submission_id" class="widefat"><option value="0">—</option>
						<?php foreach ( $submissions as $ebcr_s ) : ?>
							<option value="<?php echo (int) $ebcr_s['id']; ?>"><?php echo esc_html( $ebcr_s['protocol'] ? $ebcr_s['protocol'] : __( '(rascunho)', 'eb-credito-rural' ) ); ?></option>
						<?php endforeach; ?></select></p>
					<?php endif; ?>
					<p><label for="ebcr-act-desc"><?php esc_html_e( 'Descrição', 'eb-credito-rural' ); ?></label><textarea id="ebcr-act-desc" name="description" rows="3" class="widefat" required></textarea></p>
					<p><button class="button button-primary"><?php esc_html_e( 'Registrar', 'eb-credito-rural' ); ?></button></p>
				</form>
			</div>

			<div class="ebcr-box">
				<h3><?php esc_html_e( 'Nova tarefa', 'eb-credito-rural' ); ?></h3>
				<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
					<input type="hidden" name="action" value="ebcr_admin_crm_activity"><input type="hidden" name="contact_id" value="<?php echo (int) $contact['id']; ?>"><input type="hidden" name="type" value="<?php echo esc_attr( Service::TYPE_TASK ); ?>">
					<?php echo ebcr_nonce_field( 'crm_activity' ); ?>
					<p><label for="ebcr-task-desc"><?php esc_html_e( 'Descrição', 'eb-credito-rural' ); ?></label><textarea id="ebcr-task-desc" name="description" rows="2" class="widefat" required></textarea></p>
					<p><label for="ebcr-task-due"><?php esc_html_e( 'Vencimento', 'eb-credito-rural' ); ?></label><input type="datetime-local" id="ebcr-task-due" name="due_at" class="widefat" value="<?php echo esc_attr( wp_date( 'Y-m-d\TH:i', strtotime( '+1 day 09:00' ) ) ); ?>"></p>
					<p><label for="ebcr-task-owner"><?php esc_html_e( 'Responsável', 'eb-credito-rural' ); ?></label><select id="ebcr-task-owner" name="assignee_id" class="widefat">
						<?php foreach ( $team as $ebcr_u ) : ?>
							<option value="<?php echo (int) $ebcr_u->ID; ?>" <?php selected( (int) $ebcr_u->ID, (int) ( $contact['owner_id'] ? $contact['owner_id'] : $current_uid ) ); ?>><?php echo esc_html( $ebcr_u->display_name ); ?></option>
						<?php endforeach; ?></select></p>
					<?php if ( $submissions ) : ?>
					<p><label for="ebcr-task-sub"><?php esc_html_e( 'Solicitação relacionada (opcional)', 'eb-credito-rural' ); ?></label><select id="ebcr-task-sub" name="submission_id" class="widefat"><option value="0">—</option>
						<?php foreach ( $submissions as $ebcr_s ) : ?>
							<option value="<?php echo (int) $ebcr_s['id']; ?>"><?php echo esc_html( $ebcr_s['protocol'] ? $ebcr_s['protocol'] : __( '(rascunho)', 'eb-credito-rural' ) ); ?></option>
						<?php endforeach; ?></select></p>
					<?php endif; ?>
					<p><button class="button button-primary"><?php esc_html_e( 'Criar tarefa', 'eb-credito-rural' ); ?></button></p>
				</form>
			</div>

			<div class="ebcr-box">
				<h3><?php esc_html_e( 'Tarefas abertas', 'eb-credito-rural' ); ?></h3>
				<?php if ( ! $open_tasks ) : ?>
					<p class="description"><?php esc_html_e( 'Nenhuma tarefa aberta.', 'eb-credito-rural' ); ?></p>
				<?php else : ?>
				<ul class="ebcr-crm-tasks">
					<?php
					foreach ( $open_tasks as $ebcr_t ) :
						$ebcr_late = $ebcr_t['due_at'] && strtotime( $ebcr_t['due_at'] . ' UTC' ) < time();
						?>
					<li class="<?php echo $ebcr_late ? 'is-late' : ''; ?>">
						<div><?php echo esc_html( mb_substr( $ebcr_t['description'], 0, 140 ) ); ?></div>
						<span class="description"><?php echo esc_html( ( $ebcr_t['due_at'] ? Helpers::date( $ebcr_t['due_at'] ) : __( 'sem prazo', 'eb-credito-rural' ) ) . ' · ' . Helpers::user_name( (int) $ebcr_t['created_by'] ) ); ?></span>
						<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="ebcr-crm-inline">
							<input type="hidden" name="action" value="ebcr_admin_crm_task_done"><input type="hidden" name="contact_id" value="<?php echo (int) $contact['id']; ?>"><input type="hidden" name="activity_id" value="<?php echo (int) $ebcr_t['id']; ?>">
							<?php echo ebcr_nonce_field( 'crm_task' ); ?>
							<button class="button button-small"><?php esc_html_e( 'Concluir', 'eb-credito-rural' ); ?></button>
						</form>
					</li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>
		</aside>
	</div>
</div>
