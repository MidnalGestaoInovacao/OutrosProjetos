<?php
/**
 * Painel administrativo. Variáveis: $cards, $by_status, $months, $uf, $activity, $guarantee, $stages, $tasks, $stale, $expiring, $days.
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
		<?php foreach ( $cards as $ebcr_c ) : ?>
			<div class="ebcr-cardk"><b><?php echo esc_html( (string) $ebcr_c[1] ); ?></b><span><?php echo esc_html( $ebcr_c[0] ); ?></span>
				<?php if ( ! empty( $ebcr_c[2] ) ) : ?>
					<small><?php echo esc_html( $ebcr_c[2] ); ?></small>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="ebcr-charts">
		<div class="ebcr-box">
			<h3><?php esc_html_e( 'Funil por status', 'eb-credito-rural' ); ?></h3>
			<div class="ebcr-chart ebcr-chart--tall"><canvas id="ebcr-chart-funnel" role="img" aria-label="<?php esc_attr_e( 'Gráfico de barras: quantidade de solicitações por status', 'eb-credito-rural' ); ?>"></canvas></div>
			<details class="ebcr-chart-table">
				<summary><?php esc_html_e( 'Ver tabela', 'eb-credito-rural' ); ?></summary>
				<table class="ebcr-t"><tbody>
				<?php foreach ( Status::all() as $ebcr_k => $ebcr_def ) : ?>
					<?php
					if ( Status::DRAFT === $ebcr_k ) :
						continue;
					endif;
					?>
					<tr><td><?php echo ebcr_status_badge( $ebcr_k ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge já escapado. ?></td><td class="ebcr-num"><a href="<?php echo esc_url( admin_url( 'admin.php?page=ebcr-submissions&status=' . $ebcr_k ) ); ?>"><?php echo isset( $by_status[ $ebcr_k ] ) ? (int) $by_status[ $ebcr_k ] : 0; ?></a></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			</details>
		</div>
		<div class="ebcr-box">
			<h3><?php esc_html_e( 'Submissões por mês (últimos 12 meses)', 'eb-credito-rural' ); ?></h3>
			<p class="ebcr-chart-sub"><?php esc_html_e( 'Quantidade', 'eb-credito-rural' ); ?></p>
			<div class="ebcr-chart ebcr-chart--short"><canvas id="ebcr-chart-months-count" role="img" aria-label="<?php esc_attr_e( 'Gráfico de barras: solicitações enviadas por mês', 'eb-credito-rural' ); ?>"></canvas></div>
			<p class="ebcr-chart-sub"><?php esc_html_e( 'Volume solicitado (R$)', 'eb-credito-rural' ); ?></p>
			<div class="ebcr-chart ebcr-chart--short"><canvas id="ebcr-chart-months-volume" role="img" aria-label="<?php esc_attr_e( 'Gráfico de linha: volume solicitado por mês', 'eb-credito-rural' ); ?>"></canvas></div>
			<details class="ebcr-chart-table">
				<summary><?php esc_html_e( 'Ver tabela', 'eb-credito-rural' ); ?></summary>
				<table class="ebcr-t"><thead><tr><th><?php esc_html_e( 'Mês', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Quantidade', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Volume', 'eb-credito-rural' ); ?></th></tr></thead><tbody>
				<?php foreach ( $months as $ebcr_m ) : ?>
					<tr><td><?php echo esc_html( $ebcr_m['label'] ); ?></td><td class="ebcr-num"><?php echo (int) $ebcr_m['count']; ?></td><td class="ebcr-num"><?php echo esc_html( Helpers::money( $ebcr_m['volume'] ) ); ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			</details>
		</div>
		<div class="ebcr-box">
			<h3><?php esc_html_e( 'Por UF do tomador', 'eb-credito-rural' ); ?></h3>
			<div class="ebcr-chart"><canvas id="ebcr-chart-uf" role="img" aria-label="<?php esc_attr_e( 'Gráfico de barras: solicitações por UF', 'eb-credito-rural' ); ?>"></canvas></div>
			<details class="ebcr-chart-table">
				<summary><?php esc_html_e( 'Ver tabela', 'eb-credito-rural' ); ?></summary>
				<table class="ebcr-t"><tbody>
				<?php foreach ( $uf as $ebcr_r ) : ?>
					<tr><td><?php echo esc_html( $ebcr_r['name'] ); ?></td><td class="ebcr-num"><?php echo (int) $ebcr_r['count']; ?></td></tr>
				<?php endforeach; ?>
				<?php if ( ! $uf ) : ?>
					<tr><td><?php esc_html_e( 'Sem dados.', 'eb-credito-rural' ); ?></td></tr>
				<?php endif; ?>
				</tbody></table>
			</details>
		</div>
		<div class="ebcr-box">
			<h3><?php esc_html_e( 'Por atividade produtiva', 'eb-credito-rural' ); ?></h3>
			<div class="ebcr-chart"><canvas id="ebcr-chart-activity" role="img" aria-label="<?php esc_attr_e( 'Gráfico de barras: solicitações por atividade', 'eb-credito-rural' ); ?>"></canvas></div>
			<details class="ebcr-chart-table">
				<summary><?php esc_html_e( 'Ver tabela', 'eb-credito-rural' ); ?></summary>
				<table class="ebcr-t"><tbody>
				<?php foreach ( $activity as $ebcr_r ) : ?>
					<tr><td><?php echo esc_html( $ebcr_r['label'] ); ?></td><td class="ebcr-num"><?php echo (int) $ebcr_r['count']; ?></td></tr>
				<?php endforeach; ?>
				<?php if ( ! $activity ) : ?>
					<tr><td><?php esc_html_e( 'Sem dados.', 'eb-credito-rural' ); ?></td></tr>
				<?php endif; ?>
				</tbody></table>
			</details>
			<p class="description"><?php esc_html_e( 'Uma solicitação com mais de uma atividade conta em cada uma delas.', 'eb-credito-rural' ); ?></p>
		</div>
		<div class="ebcr-box">
			<h3><?php esc_html_e( 'Por tipo de garantia', 'eb-credito-rural' ); ?></h3>
			<div class="ebcr-chart"><canvas id="ebcr-chart-guarantee" role="img" aria-label="<?php esc_attr_e( 'Gráfico de barras: garantias oferecidas por tipo', 'eb-credito-rural' ); ?>"></canvas></div>
			<details class="ebcr-chart-table">
				<summary><?php esc_html_e( 'Ver tabela', 'eb-credito-rural' ); ?></summary>
				<table class="ebcr-t"><tbody>
				<?php foreach ( $guarantee as $ebcr_r ) : ?>
					<tr><td><?php echo esc_html( $ebcr_r['label'] ); ?></td><td class="ebcr-num"><?php echo (int) $ebcr_r['count']; ?></td></tr>
				<?php endforeach; ?>
				<?php if ( ! $guarantee ) : ?>
					<tr><td><?php esc_html_e( 'Sem dados.', 'eb-credito-rural' ); ?></td></tr>
				<?php endif; ?>
				</tbody></table>
			</details>
		</div>
		<div class="ebcr-box">
			<h3><?php esc_html_e( 'Tempo médio por etapa', 'eb-credito-rural' ); ?></h3>
			<table class="ebcr-t ebcr-stages"><thead><tr><th><?php esc_html_e( 'Etapa', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Média (dias)', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Base', 'eb-credito-rural' ); ?></th></tr></thead><tbody>
			<?php foreach ( $stages as $ebcr_r ) : ?>
				<tr><td><?php echo esc_html( $ebcr_r['label'] ); ?></td><td class="ebcr-num"><?php echo esc_html( null === $ebcr_r['days'] ? '—' : number_format_i18n( $ebcr_r['days'], 1 ) ); ?></td><td class="ebcr-num"><?php echo (int) $ebcr_r['n']; ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<p class="description"><?php esc_html_e( 'Calculado a partir do histórico de status (primeira chegada a cada etapa). "Base" é o número de solicitações que passaram pelas duas etapas.', 'eb-credito-rural' ); ?></p>
		</div>
	</div>

	<div class="ebcr-detail">
		<div>
			<div class="ebcr-box">
				<h3><?php /* translators: %d: dias */ printf( esc_html__( 'Pendências paradas há mais de %d dias', 'eb-credito-rural' ), (int) $days ); ?></h3>
				<?php if ( ! $stale ) : ?>
					<p class="description"><?php esc_html_e( 'Nenhuma.', 'eb-credito-rural' ); ?></p>
				<?php else : ?>
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
				<?php if ( ! $expiring ) : ?>
					<p class="description"><?php esc_html_e( 'Nenhuma.', 'eb-credito-rural' ); ?></p>
				<?php else : ?>
				<ul>
					<?php foreach ( $expiring as $ebcr_d ) : ?>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=ebcr-submissions&view=' . $ebcr_d['submission_public_id'] ) ); ?>"><?php echo esc_html( $ebcr_d['protocol'] ); ?></a> — <?php echo esc_html( \EBCR\Forms\DocumentMatrix::label( $ebcr_d['doc_type'] ) ); ?> (<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $ebcr_d['expires_at'] ) ) ); ?>)</li>
				<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>
			<div class="ebcr-box">
				<h3><?php esc_html_e( 'Tarefas abertas (CRM)', 'eb-credito-rural' ); ?></h3>
				<?php if ( ! $tasks ) : ?>
					<p class="description"><?php esc_html_e( 'Nenhuma tarefa.', 'eb-credito-rural' ); ?></p>
				<?php else : ?>
				<ul>
					<?php foreach ( $tasks as $ebcr_t ) : ?>
					<li><?php echo esc_html( mb_substr( $ebcr_t['description'], 0, 120 ) ); ?>
						<?php if ( $ebcr_t['due_at'] ) : ?>
							<span class="description">(<?php echo esc_html( Helpers::date( $ebcr_t['due_at'], get_option( 'date_format' ) ) ); ?>)</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
