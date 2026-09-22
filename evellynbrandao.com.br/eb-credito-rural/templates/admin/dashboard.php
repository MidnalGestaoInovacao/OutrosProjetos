<?php
/**
 * Painel administrativo. Variáveis: $cards, $by_status, $tasks, $stale, $expiring, $days.
 *
 * @package EBCR
 */

use EBCR\Domain\Status;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ebcr-admin">
	<h1><?php esc_html_e( 'Crédito Rural — Painel', 'eb-credito-rural' ); ?></h1>
	<div class="ebcr-cards">
		<?php
		foreach ( $cards as $ebcr_c ) :
			?>
			<div class="ebcr-cardk"><b><?php echo esc_html( (string) $ebcr_c[1] ); ?></b><span><?php echo esc_html( $ebcr_c[0] ); ?></span></div><?php endforeach; ?>
	</div>
	<div class="ebcr-detail">
		<div>
			<div class="ebcr-box">
				<h3><?php esc_html_e( 'Funil por status', 'eb-credito-rural' ); ?></h3>
				<table class="ebcr-t"><tbody>
				<?php foreach ( Status::all() as $ebcr_k => $ebcr_def ) : ?>
					<tr><td><?php echo ebcr_status_badge( $ebcr_k ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=ebcr-submissions&status=' . $ebcr_k ) ); ?>"><?php echo isset( $by_status[ $ebcr_k ] ) ? (int) $by_status[ $ebcr_k ] : 0; ?></a></td></tr>
				<?php endforeach; ?>
				</tbody></table>
				<p class="description"><?php esc_html_e( 'Gráficos e indicadores por UF, atividade e garantia serão adicionados na Fase 2.', 'eb-credito-rural' ); ?></p>
			</div>
			<div class="ebcr-box">
				<h3><?php /* translators: %d: dias */ printf( esc_html__( 'Pendências paradas há mais de %d dias', 'eb-credito-rural' ), (int) $days ); ?></h3>
				<?php
				if ( ! $stale ) :
					?>
					<p class="description"><?php esc_html_e( 'Nenhuma.', 'eb-credito-rural' ); ?></p><?php else : ?>
				<table class="ebcr-t"><thead><tr><th><?php esc_html_e( 'Protocolo', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Documento', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Desde', 'eb-credito-rural' ); ?></th></tr></thead><tbody>
												<?php foreach ( $stale as $ebcr_r ) : ?>
					<tr><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=ebcr-submissions&view=' . $ebcr_r['s']['public_id'] ) ); ?>"><?php echo esc_html( $ebcr_r['s']['protocol'] ); ?></a></td><td><?php echo esc_html( $ebcr_r['label'] ); ?></td><td><?php echo esc_html( Helpers::date( $ebcr_r['since'] ) ); ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
				<?php endif; ?>
			</div>
		</div>
		<div>
			<div class="ebcr-box">
				<h3><?php esc_html_e( 'Certidões vencendo (15 dias)', 'eb-credito-rural' ); ?></h3>
				<?php
				if ( ! $expiring ) :
					?>
					<p class="description"><?php esc_html_e( 'Nenhuma.', 'eb-credito-rural' ); ?></p><?php else : ?>
				<ul>
												<?php foreach ( $expiring as $ebcr_d ) : ?>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=ebcr-submissions&view=' . $ebcr_d['submission_public_id'] ) ); ?>"><?php echo esc_html( $ebcr_d['protocol'] ); ?></a> — <?php echo esc_html( \EBCR\Forms\DocumentMatrix::label( $ebcr_d['doc_type'] ) ); ?> (<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $ebcr_d['expires_at'] ) ) ); ?>)</li>
				<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>
			<div class="ebcr-box">
				<h3><?php esc_html_e( 'Tarefas abertas (CRM)', 'eb-credito-rural' ); ?></h3>
				<?php
				if ( ! $tasks ) :
					?>
					<p class="description"><?php esc_html_e( 'Nenhuma tarefa.', 'eb-credito-rural' ); ?></p><?php else : ?>
				<ul>
												<?php foreach ( $tasks as $ebcr_t ) : ?>
					<li><?php echo esc_html( mb_substr( $ebcr_t['description'], 0, 120 ) ); ?>
													<?php
													if ( $ebcr_t['due_at'] ) :
														?>
						<span class="description">(<?php echo esc_html( Helpers::date( $ebcr_t['due_at'], get_option( 'date_format' ) ) ); ?>)</span><?php endif; ?></li>
				<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
