<?php
/**
 * Relatórios (painel da equipe). Variáveis: $filters, $funds, $statuses, $analysts, $totals, $by_fund, $by_status, $by_month, $by_analyst, $stages, $can_export.
 *
 * @package EBCR
 */

use EBCR\Frontend\Team\Panel;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;

$ebcr_rate = static function ( $rate ) {
	return null === $rate ? '—' : number_format_i18n( (float) $rate, 1 ) . '%';
};
$ebcr_agg  = static function ( array $r, $with_volume = true ) use ( $ebcr_rate ) {
	echo '<td class="ebcr-num" data-label="' . esc_attr__( 'Solicitações', 'eb-credito-rural' ) . '">' . (int) $r['count'] . '</td>';
	echo '<td class="ebcr-num" data-label="' . esc_attr__( 'Em andamento', 'eb-credito-rural' ) . '">' . (int) $r['in_progress'] . '</td>';
	echo '<td class="ebcr-num" data-label="' . esc_attr__( 'Aprovadas', 'eb-credito-rural' ) . '">' . (int) $r['approved_count'] . '</td>';
	echo '<td class="ebcr-num" data-label="' . esc_attr__( 'Não aprovadas', 'eb-credito-rural' ) . '">' . (int) $r['rejected_count'] . '</td>';
	echo '<td class="ebcr-num" data-label="' . esc_attr__( 'Volume solicitado', 'eb-credito-rural' ) . '">' . esc_html( Helpers::money( $r['requested'] ) ) . '</td>';
	if ( $with_volume ) {
		echo '<td class="ebcr-num" data-label="' . esc_attr__( 'Volume aprovado', 'eb-credito-rural' ) . '">' . esc_html( Helpers::money( $r['approved_volume'] ) ) . '</td>';
		echo '<td class="ebcr-num" data-label="' . esc_attr__( 'Ticket médio', 'eb-credito-rural' ) . '">' . esc_html( Helpers::money( $r['ticket'] ) ) . '</td>';
	}
	echo '<td class="ebcr-num" data-label="' . esc_attr__( 'Taxa de aprovação', 'eb-credito-rural' ) . '">' . esc_html( $ebcr_rate( $r['approval_rate'] ) ) . '</td>';
};
?>
<section class="ebcr-card">
	<div class="ebcr-thead">
		<h2><?php esc_html_e( 'Relatórios', 'eb-credito-rural' ); ?></h2>
		<span class="ebcr-muted ebcr-small"><?php esc_html_e( 'O período considera a data de envio; rascunhos e excluídas ficam de fora.', 'eb-credito-rural' ); ?></span>
	</div>
	<form method="get" action="<?php echo esc_url( Helpers::portal_url() ); ?>" class="ebcr-tfilters">
		<input type="hidden" name="ebcr_view" value="equipe"><input type="hidden" name="tela" value="relatorios">
		<label><span><?php esc_html_e( 'De', 'eb-credito-rural' ); ?></span><input type="date" name="date_from" class="ebcr-input" value="<?php echo esc_attr( $filters['date_from'] ); ?>"></label>
		<label><span><?php esc_html_e( 'Até', 'eb-credito-rural' ); ?></span><input type="date" name="date_to" class="ebcr-input" value="<?php echo esc_attr( $filters['date_to'] ); ?>"></label>
		<label><span><?php esc_html_e( 'Carteira', 'eb-credito-rural' ); ?></span><select name="fund" class="ebcr-input"><option value=""><?php esc_html_e( 'Todas', 'eb-credito-rural' ); ?></option>
			<?php foreach ( $funds as $ebcr_fk => $ebcr_fl ) : ?>
				<option value="<?php echo esc_attr( $ebcr_fk ); ?>" <?php selected( $filters['fund'], $ebcr_fk ); ?>><?php echo esc_html( $ebcr_fl ); ?></option>
			<?php endforeach; ?></select></label>
		<label><span><?php esc_html_e( 'Status', 'eb-credito-rural' ); ?></span><select name="status" class="ebcr-input"><option value=""><?php esc_html_e( 'Todos', 'eb-credito-rural' ); ?></option>
			<?php foreach ( $statuses as $ebcr_sk => $ebcr_sd ) : ?>
				<option value="<?php echo esc_attr( $ebcr_sk ); ?>" <?php selected( $filters['status'], $ebcr_sk ); ?>><?php echo esc_html( $ebcr_sd['label'] ); ?></option>
			<?php endforeach; ?></select></label>
		<label><span><?php esc_html_e( 'Analista', 'eb-credito-rural' ); ?></span><select name="assigned_to" class="ebcr-input"><option value=""><?php esc_html_e( 'Todos', 'eb-credito-rural' ); ?></option>
			<?php foreach ( $analysts as $ebcr_a ) : ?>
				<option value="<?php echo (int) $ebcr_a->ID; ?>" <?php selected( $filters['assigned_to'], (int) $ebcr_a->ID ); ?>><?php echo esc_html( $ebcr_a->display_name ); ?></option>
			<?php endforeach; ?></select></label>
		<div class="ebcr-tfilters-actions">
			<button type="submit" class="ebcr-btn ebcr-btn--primary ebcr-btn--small"><?php esc_html_e( 'Filtrar', 'eb-credito-rural' ); ?></button>
			<a class="ebcr-btn ebcr-btn--ghost ebcr-btn--small" href="<?php echo esc_url( Panel::url( array( 'tela' => 'relatorios' ) ) ); ?>"><?php esc_html_e( 'Limpar', 'eb-credito-rural' ); ?></a>
		</div>
	</form>
	<?php if ( $can_export ) : ?>
	<form method="post" action="<?php echo esc_url( ebcr_form_action() ); ?>" class="ebcr-inline-form ebcr-texport">
		<input type="hidden" name="action" value="ebcr_team_reports_csv">
		<?php foreach ( array( 'date_from', 'date_to', 'fund', 'status', 'assigned_to' ) as $ebcr_k ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $ebcr_k ); ?>" value="<?php echo esc_attr( (string) $filters[ $ebcr_k ] ); ?>">
		<?php endforeach; ?>
		<?php echo ebcr_nonce_field( 'team_reports_csv' ); ?>
		<button type="submit" class="ebcr-btn ebcr-btn--small"><?php esc_html_e( 'Exportar CSV', 'eb-credito-rural' ); ?></button>
		<span class="ebcr-muted ebcr-small"><?php esc_html_e( 'Exporta as tabelas abaixo com o filtro atual (UTF-8, separador ponto e vírgula).', 'eb-credito-rural' ); ?></span>
	</form>
	<?php endif; ?>
</section>

<section class="ebcr-tcards" aria-label="<?php esc_attr_e( 'Totais', 'eb-credito-rural' ); ?>">
	<div class="ebcr-tcard"><b><?php echo (int) $totals['count']; ?></b><span><?php esc_html_e( 'Solicitações no filtro', 'eb-credito-rural' ); ?></span></div>
	<div class="ebcr-tcard"><b><?php echo esc_html( Helpers::money( $totals['requested'] ) ); ?></b><span><?php esc_html_e( 'Volume solicitado', 'eb-credito-rural' ); ?></span></div>
	<div class="ebcr-tcard"><b><?php echo esc_html( Helpers::money( $totals['approved_volume'] ) ); ?></b><span><?php esc_html_e( 'Volume aprovado', 'eb-credito-rural' ); ?></span></div>
	<div class="ebcr-tcard"><b><?php echo esc_html( Helpers::money( $totals['ticket'] ) ); ?></b><span><?php esc_html_e( 'Ticket médio', 'eb-credito-rural' ); ?></span></div>
	<div class="ebcr-tcard"><b><?php echo esc_html( $ebcr_rate( $totals['approval_rate'] ) ); ?></b><span><?php esc_html_e( 'Taxa de aprovação', 'eb-credito-rural' ); ?></span><small><?php echo (int) $totals['approved_count']; ?> <?php esc_html_e( 'aprovadas', 'eb-credito-rural' ); ?> · <?php echo (int) $totals['rejected_count']; ?> <?php esc_html_e( 'não aprovadas', 'eb-credito-rural' ); ?></small></div>
</section>

<section class="ebcr-card">
	<h3><?php esc_html_e( 'Por fundo/carteira', 'eb-credito-rural' ); ?></h3>
	<div class="ebcr-table-wrap"><table class="ebcr-table ebcr-table--compact">
		<thead><tr><th><?php esc_html_e( 'Carteira', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Solicitações', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Em andamento', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Aprovadas', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Não aprovadas', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Volume solicitado', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Volume aprovado', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Ticket médio', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Taxa de aprovação', 'eb-credito-rural' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $by_fund as $ebcr_r ) : ?>
			<tr><td data-label="<?php esc_attr_e( 'Carteira', 'eb-credito-rural' ); ?>"><?php echo esc_html( $ebcr_r['label'] ); ?></td><?php $ebcr_agg( $ebcr_r ); ?></tr>
		<?php endforeach; ?>
		<?php if ( ! $by_fund ) : ?>
			<tr><td colspan="9"><?php esc_html_e( 'Nenhuma carteira configurada e nenhuma solicitação no filtro.', 'eb-credito-rural' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table></div>
	<p class="ebcr-muted ebcr-small"><?php esc_html_e( 'Volume aprovado considera os status Aprovada, Formalização e Concluída. Taxa de aprovação = aprovadas ÷ (aprovadas + não aprovadas). A carteira de cada solicitação é definida na tela de detalhe.', 'eb-credito-rural' ); ?></p>
</section>

<section class="ebcr-card">
	<h3><?php esc_html_e( 'Por analista', 'eb-credito-rural' ); ?></h3>
	<div class="ebcr-table-wrap"><table class="ebcr-table ebcr-table--compact">
		<thead><tr><th><?php esc_html_e( 'Analista', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Solicitações', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Em andamento', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Aprovadas', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Não aprovadas', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Volume solicitado', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Taxa de aprovação', 'eb-credito-rural' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $by_analyst as $ebcr_r ) : ?>
			<tr><td data-label="<?php esc_attr_e( 'Analista', 'eb-credito-rural' ); ?>"><?php echo esc_html( $ebcr_r['label'] ); ?></td><?php $ebcr_agg( $ebcr_r, false ); ?></tr>
		<?php endforeach; ?>
		<?php if ( ! $by_analyst ) : ?>
			<tr><td colspan="7"><?php esc_html_e( 'Nenhuma solicitação no filtro.', 'eb-credito-rural' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table></div>
</section>

<div class="ebcr-tcharts">
	<div class="ebcr-card">
		<h3><?php esc_html_e( 'Por status', 'eb-credito-rural' ); ?></h3>
		<div class="ebcr-table-wrap"><table class="ebcr-table ebcr-table--compact">
			<thead><tr><th><?php esc_html_e( 'Status', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Quantidade', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Volume solicitado', 'eb-credito-rural' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $by_status as $ebcr_r ) : ?>
				<tr><td data-label="<?php esc_attr_e( 'Status', 'eb-credito-rural' ); ?>"><?php echo ebcr_status_badge( $ebcr_r['key'] ); ?></td><td class="ebcr-num" data-label="<?php esc_attr_e( 'Quantidade', 'eb-credito-rural' ); ?>"><?php echo (int) $ebcr_r['count']; ?></td><td class="ebcr-num" data-label="<?php esc_attr_e( 'Volume solicitado', 'eb-credito-rural' ); ?>"><?php echo esc_html( Helpers::money( $ebcr_r['requested'] ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table></div>
	</div>
	<div class="ebcr-card">
		<h3><?php esc_html_e( 'Por mês (envio)', 'eb-credito-rural' ); ?></h3>
		<div class="ebcr-tchart ebcr-tchart--short"><canvas id="ebcr-chart-months-count" role="img" aria-label="<?php esc_attr_e( 'Gráfico de barras: solicitações enviadas por mês', 'eb-credito-rural' ); ?>"></canvas></div>
		<div class="ebcr-table-wrap"><table class="ebcr-table ebcr-table--compact">
			<thead><tr><th><?php esc_html_e( 'Mês', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Quantidade', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Volume solicitado', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Aprovadas', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Volume aprovado', 'eb-credito-rural' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $by_month as $ebcr_r ) : ?>
				<tr><td data-label="<?php esc_attr_e( 'Mês', 'eb-credito-rural' ); ?>"><?php echo esc_html( $ebcr_r['label'] ); ?></td><td class="ebcr-num" data-label="<?php esc_attr_e( 'Quantidade', 'eb-credito-rural' ); ?>"><?php echo (int) $ebcr_r['count']; ?></td><td class="ebcr-num" data-label="<?php esc_attr_e( 'Volume solicitado', 'eb-credito-rural' ); ?>"><?php echo esc_html( Helpers::money( $ebcr_r['volume'] ) ); ?></td><td class="ebcr-num" data-label="<?php esc_attr_e( 'Aprovadas', 'eb-credito-rural' ); ?>"><?php echo (int) $ebcr_r['approved_count']; ?></td><td class="ebcr-num" data-label="<?php esc_attr_e( 'Volume aprovado', 'eb-credito-rural' ); ?>"><?php echo esc_html( Helpers::money( $ebcr_r['approved_volume'] ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table></div>
		<p class="ebcr-muted ebcr-small"><?php esc_html_e( 'Sem período no filtro: últimos 12 meses. Com período: meses entre as datas informadas (até 36).', 'eb-credito-rural' ); ?></p>
	</div>
	<div class="ebcr-card">
		<h3><?php esc_html_e( 'Tempo médio por etapa', 'eb-credito-rural' ); ?></h3>
		<div class="ebcr-table-wrap"><table class="ebcr-table ebcr-table--compact">
			<thead><tr><th><?php esc_html_e( 'Etapa', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Média (dias)', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Base', 'eb-credito-rural' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $stages as $ebcr_r ) : ?>
				<tr><td data-label="<?php esc_attr_e( 'Etapa', 'eb-credito-rural' ); ?>"><?php echo esc_html( $ebcr_r['label'] ); ?></td><td class="ebcr-num" data-label="<?php esc_attr_e( 'Média (dias)', 'eb-credito-rural' ); ?>"><?php echo esc_html( null === $ebcr_r['days'] ? '—' : number_format_i18n( $ebcr_r['days'], 1 ) ); ?></td><td class="ebcr-num" data-label="<?php esc_attr_e( 'Base', 'eb-credito-rural' ); ?>"><?php echo (int) $ebcr_r['n']; ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table></div>
		<p class="ebcr-muted ebcr-small"><?php esc_html_e( 'Calculado a partir do histórico de status (primeira chegada a cada etapa). "Base" é o número de solicitações que passaram pelas duas etapas.', 'eb-credito-rural' ); ?></p>
	</div>
</div>
