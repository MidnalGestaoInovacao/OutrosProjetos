<?php
/**
 * Visão geral do painel da equipe.
 * Variáveis: $has_dashboard, $cards, $by_status, $months, $uf, $activity, $guarantee, $stages, $tasks, $stale, $expiring, $days, $can_crm.
 *
 * @package EBCR
 */

use EBCR\Domain\Status;
use EBCR\Frontend\Team\Panel;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
?>
<?php if ( $has_dashboard ) : ?>
<section class="ebcr-tcards" aria-label="<?php esc_attr_e( 'Indicadores', 'eb-credito-rural' ); ?>">
	<?php foreach ( $cards as $ebcr_c ) : ?>
		<div class="ebcr-tcard"><b><?php echo esc_html( $ebcr_c['value'] ); ?></b><span><?php echo esc_html( $ebcr_c['label'] ); ?></span>
			<?php if ( ! empty( $ebcr_c['sub'] ) ) : ?>
				<small><?php echo esc_html( $ebcr_c['sub'] ); ?></small>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</section>

<div class="ebcr-tcharts">
	<div class="ebcr-card">
		<h3><?php esc_html_e( 'Funil por status', 'eb-credito-rural' ); ?></h3>
		<div class="ebcr-tchart ebcr-tchart--tall"><canvas id="ebcr-chart-funnel" role="img" aria-label="<?php esc_attr_e( 'Gráfico de barras: quantidade de solicitações por status', 'eb-credito-rural' ); ?>"></canvas></div>
		<details class="ebcr-tchart-table">
			<summary><?php esc_html_e( 'Ver tabela', 'eb-credito-rural' ); ?></summary>
			<table class="ebcr-table ebcr-table--compact"><tbody>
			<?php foreach ( Status::all() as $ebcr_k => $ebcr_def ) : ?>
				<?php
				if ( Status::DRAFT === $ebcr_k ) :
					continue;
				endif;
				?>
				<tr><td data-label="<?php esc_attr_e( 'Status', 'eb-credito-rural' ); ?>"><?php echo ebcr_status_badge( $ebcr_k ); ?></td><td class="ebcr-num" data-label="<?php esc_attr_e( 'Quantidade', 'eb-credito-rural' ); ?>"><a href="<?php echo esc_url( Panel::url( array( 'tela' => 'solicitacoes', 'status' => $ebcr_k ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- curto. ?>"><?php echo isset( $by_status[ $ebcr_k ] ) ? (int) $by_status[ $ebcr_k ] : 0; ?></a></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</details>
	</div>
	<div class="ebcr-card">
		<h3><?php esc_html_e( 'Solicitações por mês (últimos 12 meses)', 'eb-credito-rural' ); ?></h3>
		<p class="ebcr-tchart-sub"><?php esc_html_e( 'Quantidade', 'eb-credito-rural' ); ?></p>
		<div class="ebcr-tchart ebcr-tchart--short"><canvas id="ebcr-chart-months-count" role="img" aria-label="<?php esc_attr_e( 'Gráfico de barras: solicitações enviadas por mês', 'eb-credito-rural' ); ?>"></canvas></div>
		<p class="ebcr-tchart-sub"><?php esc_html_e( 'Volume solicitado (R$)', 'eb-credito-rural' ); ?></p>
		<div class="ebcr-tchart ebcr-tchart--short"><canvas id="ebcr-chart-months-volume" role="img" aria-label="<?php esc_attr_e( 'Gráfico de linha: volume solicitado por mês', 'eb-credito-rural' ); ?>"></canvas></div>
		<details class="ebcr-tchart-table">
			<summary><?php esc_html_e( 'Ver tabela', 'eb-credito-rural' ); ?></summary>
			<table class="ebcr-table ebcr-table--compact"><thead><tr><th><?php esc_html_e( 'Mês', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Quantidade', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Volume', 'eb-credito-rural' ); ?></th></tr></thead><tbody>
			<?php foreach ( $months as $ebcr_m ) : ?>
				<tr><td data-label="<?php esc_attr_e( 'Mês', 'eb-credito-rural' ); ?>"><?php echo esc_html( $ebcr_m['label'] ); ?></td><td class="ebcr-num" data-label="<?php esc_attr_e( 'Quantidade', 'eb-credito-rural' ); ?>"><?php echo (int) $ebcr_m['count']; ?></td><td class="ebcr-num" data-label="<?php esc_attr_e( 'Volume', 'eb-credito-rural' ); ?>"><?php echo esc_html( Helpers::money( $ebcr_m['volume'] ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</details>
	</div>
	<?php
	$ebcr_bars = array(
		'uf'        => array( __( 'Por UF do tomador', 'eb-credito-rural' ), $uf, 'name' ),
		'activity'  => array( __( 'Por atividade produtiva', 'eb-credito-rural' ), $activity, 'label' ),
		'guarantee' => array( __( 'Por tipo de garantia', 'eb-credito-rural' ), $guarantee, 'label' ),
	);
	foreach ( $ebcr_bars as $ebcr_id => $ebcr_b ) :
		?>
	<div class="ebcr-card">
		<h3><?php echo esc_html( $ebcr_b[0] ); ?></h3>
		<div class="ebcr-tchart"><canvas id="ebcr-chart-<?php echo esc_attr( $ebcr_id ); ?>" role="img" aria-label="<?php echo esc_attr( $ebcr_b[0] ); ?>"></canvas></div>
		<details class="ebcr-tchart-table">
			<summary><?php esc_html_e( 'Ver tabela', 'eb-credito-rural' ); ?></summary>
			<table class="ebcr-table ebcr-table--compact"><tbody>
			<?php foreach ( $ebcr_b[1] as $ebcr_r ) : ?>
				<tr><td data-label="<?php esc_attr_e( 'Categoria', 'eb-credito-rural' ); ?>"><?php echo esc_html( $ebcr_r[ $ebcr_b[2] ] ); ?></td><td class="ebcr-num" data-label="<?php esc_attr_e( 'Quantidade', 'eb-credito-rural' ); ?>"><?php echo (int) $ebcr_r['count']; ?></td></tr>
			<?php endforeach; ?>
			<?php if ( ! $ebcr_b[1] ) : ?>
				<tr><td><?php esc_html_e( 'Sem dados.', 'eb-credito-rural' ); ?></td></tr>
			<?php endif; ?>
			</tbody></table>
		</details>
		<?php if ( 'activity' === $ebcr_id ) : ?>
			<p class="ebcr-muted ebcr-small"><?php esc_html_e( 'Uma solicitação com mais de uma atividade conta em cada uma delas.', 'eb-credito-rural' ); ?></p>
		<?php endif; ?>
	</div>
	<?php endforeach; ?>
	<div class="ebcr-card">
		<h3><?php esc_html_e( 'Tempo médio por etapa', 'eb-credito-rural' ); ?></h3>
		<div class="ebcr-table-wrap"><table class="ebcr-table ebcr-table--compact"><thead><tr><th><?php esc_html_e( 'Etapa', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Média (dias)', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Base', 'eb-credito-rural' ); ?></th></tr></thead><tbody>
		<?php foreach ( $stages as $ebcr_r ) : ?>
			<tr><td data-label="<?php esc_attr_e( 'Etapa', 'eb-credito-rural' ); ?>"><?php echo esc_html( $ebcr_r['label'] ); ?></td><td class="ebcr-num" data-label="<?php esc_attr_e( 'Média (dias)', 'eb-credito-rural' ); ?>"><?php echo esc_html( null === $ebcr_r['days'] ? '—' : number_format_i18n( $ebcr_r['days'], 1 ) ); ?></td><td class="ebcr-num" data-label="<?php esc_attr_e( 'Base', 'eb-credito-rural' ); ?>"><?php echo (int) $ebcr_r['n']; ?></td></tr>
		<?php endforeach; ?>
		</tbody></table></div>
		<p class="ebcr-muted ebcr-small"><?php esc_html_e( 'Calculado a partir do histórico de status (primeira chegada a cada etapa). "Base" é o número de solicitações que passaram pelas duas etapas.', 'eb-credito-rural' ); ?></p>
	</div>
</div>
<?php else : ?>
<div class="ebcr-alert ebcr-alert--info" role="status"><?php esc_html_e( 'Seu perfil vê as listas de acompanhamento. Indicadores e gráficos ficam disponíveis para gestores.', 'eb-credito-rural' ); ?></div>
<?php endif; ?>

<div class="ebcr-grid ebcr-tlists">
	<section class="ebcr-card">
		<h3><?php /* translators: %d: dias */ printf( esc_html__( 'Pendências paradas há mais de %d dias', 'eb-credito-rural' ), (int) $days ); ?></h3>
		<?php if ( ! $stale ) : ?>
			<p class="ebcr-muted"><?php esc_html_e( 'Nenhuma.', 'eb-credito-rural' ); ?></p>
		<?php else : ?>
		<div class="ebcr-table-wrap"><table class="ebcr-table ebcr-table--compact"><thead><tr><th><?php esc_html_e( 'Protocolo', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Documento', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Desde', 'eb-credito-rural' ); ?></th></tr></thead><tbody>
			<?php foreach ( $stale as $ebcr_r ) : ?>
			<tr><td data-label="<?php esc_attr_e( 'Protocolo', 'eb-credito-rural' ); ?>"><a href="<?php echo esc_url( Panel::submission_url( $ebcr_r['s'], 'documentos' ) ); ?>"><?php echo esc_html( $ebcr_r['s']['protocol'] ); ?></a></td><td data-label="<?php esc_attr_e( 'Documento', 'eb-credito-rural' ); ?>"><?php echo esc_html( $ebcr_r['label'] ); ?></td><td data-label="<?php esc_attr_e( 'Desde', 'eb-credito-rural' ); ?>"><?php echo esc_html( Helpers::date( $ebcr_r['since'] ) ); ?></td></tr>
			<?php endforeach; ?>
		</tbody></table></div>
		<?php endif; ?>
	</section>
	<div>
		<section class="ebcr-card">
			<h3><?php esc_html_e( 'Certidões vencendo (15 dias)', 'eb-credito-rural' ); ?></h3>
			<?php if ( ! $expiring ) : ?>
				<p class="ebcr-muted"><?php esc_html_e( 'Nenhuma.', 'eb-credito-rural' ); ?></p>
			<?php else : ?>
			<ul class="ebcr-tlist">
				<?php foreach ( $expiring as $ebcr_d ) : ?>
				<li><a href="<?php echo esc_url( Panel::submission_url( $ebcr_d['s'], 'documentos' ) ); ?>"><?php echo esc_html( $ebcr_d['protocol'] ); ?></a> — <?php echo esc_html( \EBCR\Forms\DocumentMatrix::label( $ebcr_d['doc_type'] ) ); ?> <span class="ebcr-muted ebcr-small">(<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $ebcr_d['expires_at'] ) ) ); ?>)</span></li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</section>
		<?php if ( $can_crm ) : ?>
		<section class="ebcr-card">
			<h3><?php esc_html_e( 'Minhas tarefas (CRM)', 'eb-credito-rural' ); ?></h3>
			<?php if ( ! $tasks ) : ?>
				<p class="ebcr-muted"><?php esc_html_e( 'Nenhuma tarefa aberta.', 'eb-credito-rural' ); ?></p>
			<?php else : ?>
			<ul class="ebcr-tlist">
				<?php
				foreach ( $tasks as $ebcr_t ) :
					$ebcr_late = $ebcr_t['due_at'] && strtotime( $ebcr_t['due_at'] . ' UTC' ) < time();
					?>
				<li class="<?php echo $ebcr_late ? 'is-late' : ''; ?>">
					<?php if ( ! empty( $ebcr_t['contact'] ) ) : ?>
						<a href="<?php echo esc_url( Panel::contact_url( (int) $ebcr_t['contact']['id'] ) ); ?>"><?php echo esc_html( $ebcr_t['name'] ); ?></a>:
					<?php endif; ?>
					<?php echo esc_html( mb_substr( $ebcr_t['description'], 0, 120 ) ); ?>
					<?php if ( $ebcr_t['due_at'] ) : ?>
						<span class="ebcr-small <?php echo $ebcr_late ? 'ebcr-bad' : 'ebcr-muted'; ?>">(<?php echo esc_html( Helpers::date( $ebcr_t['due_at'], get_option( 'date_format' ) ) ); ?>)</span>
					<?php endif; ?>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</section>
		<?php endif; ?>
	</div>
</div>
