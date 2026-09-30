<?php
/**
 * Painel do cliente. Variáveis: $rows (cada um com submission, open, unread, next, missing), $rules, $draft,
 * $progress (etapa atual do rascunho: step, total, title), $quota, $verified, $can_duplicate.
 *
 * @package EBCR
 */

use EBCR\Domain\Status;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_flash = \EBCR\Frontend\Portal::unflash( 'dashboard' );
?>
<div class="ebcr-grid">
	<section class="ebcr-card ebcr-col-main">
		<h2><?php esc_html_e( 'Minhas solicitações', 'eb-credito-rural' ); ?></h2>
		<?php if ( ! empty( $ebcr_flash['errors']['_'] ) ) : ?>
			<div class="ebcr-alert ebcr-alert--error" role="alert"><?php echo esc_html( $ebcr_flash['errors']['_'] ); ?></div>
		<?php endif; ?>
		<?php
		if ( $draft ) :
			$ebcr_continue = '<p class="ebcr-notice__actions"><a class="ebcr-btn ebcr-btn--primary" href="' . esc_url(
				Helpers::portal_url(
					array(
						'ebcr_view' => 'formulario',
						'id'        => $draft['public_id'],
					)
				)
			) . '">' . esc_html__( 'Continuar preenchimento', 'eb-credito-rural' ) . '</a></p>';
			echo ebcr_notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado no helper e acima.
				! empty( $progress ) ? sprintf( /* translators: 1: etapa, 2: total, 3: título */ __( 'Você parou na etapa %1$d de %2$d (%3$s). Os dados já digitados estão salvos.', 'eb-credito-rural' ), (int) $progress['step'], (int) $progress['total'], $progress['title'] ) : __( 'Os dados já digitados estão salvos.', 'eb-credito-rural' ),
				__( 'Você tem uma solicitação em preenchimento', 'eb-credito-rural' ),
				$ebcr_continue
			);
		endif;
		foreach ( $rows as $ebcr_r ) :
			if ( empty( $ebcr_r['missing'] ) ) {
				continue;
			}
			$ebcr_s = $ebcr_r['submission'];
			$ebcr_a = '<p class="ebcr-notice__actions"><a class="ebcr-btn ebcr-btn--primary" href="' . esc_url(
				Helpers::portal_url(
					array(
						'ebcr_view' => 'solicitacao',
						'id'        => $ebcr_s['public_id'],
					)
				) . '#ebcr-bens'
			) . '">' . esc_html__( 'Completar agora', 'eb-credito-rural' ) . '</a></p>';
			echo ebcr_notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado no helper e acima.
				\EBCR\Forms\Complement::notice( in_array( 'garantias', $ebcr_r['missing'], true ) ? 'garantias' : 'imoveis' ),
				\EBCR\Forms\Complement::missing_title( (string) $ebcr_s['protocol'], $ebcr_r['missing'] ),
				$ebcr_a
			);
		endforeach;
		?>
		<?php if ( ! $rows ) : ?>
			<p class="ebcr-muted"><?php esc_html_e( 'Você ainda não tem solicitações. Comece uma nova ao lado.', 'eb-credito-rural' ); ?></p>
		<?php else : ?>
		<div class="ebcr-table-wrap"><table class="ebcr-table">
			<thead><tr><th><?php esc_html_e( 'Protocolo', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Status', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Data', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Próxima ação', 'eb-credito-rural' ); ?></th><th></th></tr></thead>
			<tbody>
			<?php
			foreach ( $rows as $ebcr_r ) :
				$ebcr_s = $ebcr_r['submission'];
				?>
				<tr>
					<td data-label="<?php esc_attr_e( 'Protocolo', 'eb-credito-rural' ); ?>"><strong><?php echo esc_html( $ebcr_s['protocol'] ? $ebcr_s['protocol'] : __( 'Rascunho', 'eb-credito-rural' ) ); ?></strong>
					<?php
					if ( $ebcr_s['requested_amount'] ) :
						?>
						<br><span class="ebcr-muted"><?php echo esc_html( Helpers::money( $ebcr_s['requested_amount'] ) ); ?></span><?php endif; ?></td>
					<td data-label="<?php esc_attr_e( 'Status', 'eb-credito-rural' ); ?>"><?php echo ebcr_status_badge( $ebcr_s['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php
					if ( $ebcr_r['unread'] ) :
						?>
						<span class="ebcr-pill"><?php /* translators: %d: mensagens */ printf( esc_html( _n( '%d nova mensagem', '%d novas mensagens', $ebcr_r['unread'], 'eb-credito-rural' ) ), (int) $ebcr_r['unread'] ); ?></span><?php endif; ?></td>
					<td data-label="<?php esc_attr_e( 'Data', 'eb-credito-rural' ); ?>"><?php echo esc_html( Helpers::date( $ebcr_s['submitted_at'] ? $ebcr_s['submitted_at'] : $ebcr_s['created_at'], get_option( 'date_format' ) ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Próxima ação', 'eb-credito-rural' ); ?>"><?php echo esc_html( $ebcr_r['next'] ); ?>
					<?php
					if ( $ebcr_r['open'] ) :
						?>
						<span class="ebcr-pill ebcr-pill--warn"><?php esc_html_e( 'pendência', 'eb-credito-rural' ); ?></span><?php endif; ?></td>
					<td>
						<?php if ( Status::DRAFT === $ebcr_s['status'] ) : ?>
							<a class="ebcr-btn ebcr-btn--small ebcr-btn--primary" href="
							<?php
							echo esc_url(
								Helpers::portal_url(
									array(
										'ebcr_view' => 'formulario',
										'id'        => $ebcr_s['public_id'],
									)
								)
							);
							?>
																						"><?php esc_html_e( 'Continuar preenchimento', 'eb-credito-rural' ); ?></a>
						<?php else : ?>
							<a class="ebcr-btn ebcr-btn--small" href="
							<?php
							echo esc_url(
								Helpers::portal_url(
									array(
										'ebcr_view' => 'solicitacao',
										'id'        => $ebcr_s['public_id'],
									)
								)
							);
							?>
																		"><?php esc_html_e( 'Abrir', 'eb-credito-rural' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php endif; ?>
	</section>
	<aside class="ebcr-col-side">
		<div class="ebcr-card">
			<h3><?php esc_html_e( 'Nova solicitação', 'eb-credito-rural' ); ?></h3>
			<?php if ( $draft ) : ?>
				<p><?php esc_html_e( 'Você tem um rascunho em andamento.', 'eb-credito-rural' ); ?></p>
				<a class="ebcr-btn ebcr-btn--primary ebcr-btn--block" href="
				<?php
				echo esc_url(
					Helpers::portal_url(
						array(
							'ebcr_view' => 'formulario',
							'id'        => $draft['public_id'],
						)
					)
				);
				?>
																			"><?php esc_html_e( 'Continuar preenchimento', 'eb-credito-rural' ); ?></a>
			<?php elseif ( ! $rules['allowed'] ) : ?>
				<p class="ebcr-muted"><?php echo esc_html( $rules['message'] ); ?></p>
				<?php if ( $rules['wait_seconds'] > 0 ) : ?>
					<p class="ebcr-countdown" data-countdown="<?php echo esc_attr( (string) $rules['wait_seconds'] ); ?>" aria-live="polite"></p>
				<?php endif; ?>
				<button class="ebcr-btn ebcr-btn--block" type="button" disabled><?php esc_html_e( 'Nova solicitação', 'eb-credito-rural' ); ?></button>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>">
					<input type="hidden" name="action" value="ebcr_new_submission">
					<?php echo ebcr_nonce_field( 'new_submission' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( $can_duplicate ) : ?>
						<label class="ebcr-check" for="ebcr-duplicar"><input type="checkbox" id="ebcr-duplicar" name="duplicar" value="1"> <?php esc_html_e( 'Aproveitar os dados da solicitação anterior (documentos vencidos não são copiados)', 'eb-credito-rural' ); ?></label>
					<?php endif; ?>
					<button type="submit" class="ebcr-btn ebcr-btn--primary ebcr-btn--block"><?php esc_html_e( 'Iniciar solicitação', 'eb-credito-rural' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
		<div class="ebcr-card">
			<h3><?php esc_html_e( 'Armazenamento', 'eb-credito-rural' ); ?></h3>
			<div class="ebcr-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $quota['percent'] ); ?>"><span style="width:<?php echo esc_attr( (string) $quota['percent'] ); ?>%"></span></div>
			<p class="ebcr-muted"><?php /* translators: 1: usado, 2: cota */ printf( esc_html__( '%1$s de %2$s usados', 'eb-credito-rural' ), esc_html( Helpers::size( $quota['used'] ) ), esc_html( Helpers::size( $quota['quota'] ) ) ); ?></p>
		</div>
	</aside>
</div>
