<?php
/**
 * Relatórios. Variáveis: $filters, $funds, $statuses, $analysts, $totals, $by_fund, $by_status, $by_month, $by_analyst, $stages, $can_export.
 *
 * @package EBCR
 */

use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;

$ebcr_rate = static function ( $rate ) {
	return null === $rate ? '—' : number_format_i18n( (float) $rate, 1 ) . '%';
};
?>
<div class="wrap ebcr-admin ebcr-reports">
	<h1><?php esc_html_e( 'Crédito Rural — Relatórios', 'eb-credito-rural' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Totais por fundo/carteira, status, mês e analista. O período considera a data de envio da solicitação; rascunhos e solicitações excluídas ficam de fora.', 'eb-credito-rural' ); ?></p>

	<form method="get" class="ebcr-filters">
		<input type="hidden" name="page" value="ebcr-reports">
		<label><?php esc_html_e( 'De', 'eb-credito-rural' ); ?><input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>"></label>
		<label><?php esc_html_e( 'Até', 'eb-credito-rural' ); ?><input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>"></label>
		<label><?php esc_html_e( 'Carteira', 'eb-credito-rural' ); ?>
			<select name="fund"><option value=""><?php esc_html_e( 'Todas as carteiras', 'eb-credito-rural' ); ?></option>
			<?php foreach ( $funds as $ebcr_fk => $ebcr_fl ) : ?>
				<option value="<?php echo esc_attr( $ebcr_fk ); ?>" <?php selected( $filters['fund'], $ebcr_fk ); ?>><?php echo esc_html( $ebcr_fl ); ?></option>
			<?php endforeach; ?>
			</select>
		</label>
		<label><?php esc_html_e( 'Status', 'eb-credito-rural' ); ?>
			<select name="status"><option value=""><?php esc_html_e( 'Todos os status', 'eb-credito-rural' ); ?></option>
			<?php foreach ( $statuses as $ebcr_sk => $ebcr_sd ) : ?>
				<option value="<?php echo esc_attr( $ebcr_sk ); ?>" <?php selected( $filters['status'], $ebcr_sk ); ?>><?php echo esc_html( $ebcr_sd['label'] ); ?></option>
			<?php endforeach; ?>
			</select>
		</label>
		<label><?php esc_html_e( 'Analista', 'eb-credito-rural' ); ?>
			<select name="assigned_to"><option value=""><?php esc_html_e( 'Todos os analistas', 'eb-credito-rural' ); ?></option>
			<?php foreach ( $analysts as $ebcr_a ) : ?>
				<option value="<?php echo (int) $ebcr_a->ID; ?>" <?php selected( $filters['assigned_to'], (int) $ebcr_a->ID ); ?>><?php echo esc_html( $ebcr_a->display_name ); ?></option>
			<?php endforeach; ?>
			</select>
		</label>
		<span>
			<button class="button button-primary"><?php esc_html_e( 'Filtrar', 'eb-credito-rural' ); ?></button>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ebcr-reports' ) ); ?>"><?php esc_html_e( 'Limpar', 'eb-credito-rural' ); ?></a>
		</span>
	</form>
	<?php if ( $can_export ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ebcr-inline">
		<input type="hidden" name="action" value="ebcr_admin_reports_csv">
		<?php foreach ( array( 'date_from', 'date_to', 'fund', 'status', 'assigned_to' ) as $ebcr_k ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $ebcr_k ); ?>" value="<?php echo esc_attr( (string) $filters[ $ebcr_k ] ); ?>">
		<?php endforeach; ?>
		<?php echo ebcr_nonce_field( 'reports_csv' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- campo de nonce já escapado. ?>
		<button class="button"><?php esc_html_e( 'Exportar CSV', 'eb-credito-rural' ); ?></button>
		<span class="description"><?php esc_html_e( 'Exporta as tabelas abaixo com o filtro atual (UTF-8, separador ponto e vírgula).', 'eb-credito-rural' ); ?></span>
	</form>
	<?php endif; ?>

	<div class="ebcr-cards">
		<div class="ebcr-cardk"><b><?php echo (int) $totals['count']; ?></b><span><?php esc_html_e( 'Solicitações no filtro', 'eb-credito-rural' ); ?></span></div>
		<div class="ebcr-cardk"><b><?php echo esc_html( Helpers::money( $totals['requested'] ) ); ?></b><span><?php esc_html_e( 'Volume solicitado', 'eb-credito-rural' ); ?></span></div>
		<div class="ebcr-cardk"><b><?php echo esc_html( Helpers::money( $totals['approved_volume'] ) ); ?></b><span><?php esc_html_e( 'Volume aprovado', 'eb-credito-rural' ); ?></span></div>
		<div class="ebcr-cardk"><b><?php echo esc_html( Helpers::money( $totals['ticket'] ) ); ?></b><span><?php esc_html_e( 'Ticket médio', 'eb-credito-rural' ); ?></span></div>
		<div class="ebcr-cardk"><b><?php echo esc_html( $ebcr_rate( $totals['approval_rate'] ) ); ?></b><span><?php esc_html_e( 'Taxa de aprovação', 'eb-credito-rural' ); ?></span><small><?php echo (int) $totals['approved_count']; ?> <?php esc_html_e( 'aprovadas', 'eb-credito-rural' ); ?> · <?php echo (int) $totals['rejected_count']; ?> <?php esc_html_e( 'não aprovadas', 'eb-credito-rural' ); ?></small></div>
	</div>

	<div class="ebcr-box ebcr-report-section">
		<h3><?php esc_html_e( 'Por fundo/carteira', 'eb-credito-rural' ); ?></h3>
		<table class="widefat striped ebcr-t">
			<thead><tr><th><?php esc_html_e( 'Carteira', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Solicitações', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Em andamento', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Aprovadas', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Não aprovadas', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Volume solicitado', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Volume aprovado', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Ticket médio', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Taxa de aprovação', 'eb-credito-rural' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $by_fund as $ebcr_r ) : ?>
				<tr><td><?php echo esc_html( $ebcr_r['label'] ); ?></td><td class="ebcr-num"><?php echo (int) $ebcr_r['count']; ?></td><td class="ebcr-num"><?php echo (int) $ebcr_r['in_progress']; ?></td><td class="ebcr-num"><?php echo (int) $ebcr_r['approved_count']; ?></td><td class="ebcr-num"><?php echo (int) $ebcr_r['rejected_count']; ?></td><td class="ebcr-num"><?php echo esc_html( Helpers::money( $ebcr_r['requested'] ) ); ?></td><td class="ebcr-num"><?php echo esc_html( Helpers::money( $ebcr_r['approved_volume'] ) ); ?></td><td class="ebcr-num"><?php echo esc_html( Helpers::money( $ebcr_r['ticket'] ) ); ?></td><td class="ebcr-num"><?php echo esc_html( $ebcr_rate( $ebcr_r['approval_rate'] ) ); ?></td></tr>
			<?php endforeach; ?>
			<?php if ( ! $by_fund ) : ?>
				<tr><td colspan="9"><?php esc_html_e( 'Nenhuma carteira configurada e nenhuma solicitação no filtro.', 'eb-credito-rural' ); ?></td></tr>
			<?php endif; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Volume aprovado considera os status Aprovada, Formalização e Concluída. Taxa de aprovação = aprovadas ÷ (aprovadas + não aprovadas). A carteira de cada solicitação é definida na tela de detalhe.', 'eb-credito-rural' ); ?></p>
	</div>

	<div class="ebcr-box ebcr-report-section">
		<h3><?php esc_html_e( 'Por analista', 'eb-credito-rural' ); ?></h3>
		<table class="widefat striped ebcr-t">
			<thead><tr><th><?php esc_html_e( 'Analista', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Solicitações', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Em andamento', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Aprovadas', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Não aprovadas', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Volume solicitado', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Taxa de aprovação', 'eb-credito-rural' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $by_analyst as $ebcr_r ) : ?>
				<tr><td><?php echo esc_html( $ebcr_r['label'] ); ?></td><td class="ebcr-num"><?php echo (int) $ebcr_r['count']; ?></td><td class="ebcr-num"><?php echo (int) $ebcr_r['in_progress']; ?></td><td class="ebcr-num"><?php echo (int) $ebcr_r['approved_count']; ?></td><td class="ebcr-num"><?php echo (int) $ebcr_r['rejected_count']; ?></td><td class="ebcr-num"><?php echo esc_html( Helpers::money( $ebcr_r['requested'] ) ); ?></td><td class="ebcr-num"><?php echo esc_html( $ebcr_rate( $ebcr_r['approval_rate'] ) ); ?></td></tr>
			<?php endforeach; ?>
			<?php if ( ! $by_analyst ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'Nenhuma solicitação no filtro.', 'eb-credito-rural' ); ?></td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>

	<div class="ebcr-charts">
		<div class="ebcr-box">
			<h3><?php esc_html_e( 'Por status', 'eb-credito-rural' ); ?></h3>
			<table class="widefat striped ebcr-t">
				<thead><tr><th><?php esc_html_e( 'Status', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Quantidade', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Volume solicitado', 'eb-credito-rural' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $by_status as $ebcr_r ) : ?>
					<tr><td><?php echo ebcr_status_badge( $ebcr_r['key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge já escapado. ?></td><td class="ebcr-num"><?php echo (int) $ebcr_r['count']; ?></td><td class="ebcr-num"><?php echo esc_html( Helpers::money( $ebcr_r['requested'] ) ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="ebcr-box">
			<h3><?php esc_html_e( 'Por mês (envio)', 'eb-credito-rural' ); ?></h3>
			<table class="widefat striped ebcr-t">
				<thead><tr><th><?php esc_html_e( 'Mês', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Quantidade', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Volume solicitado', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Aprovadas', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Volume aprovado', 'eb-credito-rural' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $by_month as $ebcr_r ) : ?>
					<tr><td><?php echo esc_html( $ebcr_r['label'] ); ?></td><td class="ebcr-num"><?php echo (int) $ebcr_r['count']; ?></td><td class="ebcr-num"><?php echo esc_html( Helpers::money( $ebcr_r['volume'] ) ); ?></td><td class="ebcr-num"><?php echo (int) $ebcr_r['approved_count']; ?></td><td class="ebcr-num"><?php echo esc_html( Helpers::money( $ebcr_r['approved_volume'] ) ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Sem período no filtro: últimos 12 meses. Com período: meses entre as datas informadas (até 36).', 'eb-credito-rural' ); ?></p>
		</div>
		<div class="ebcr-box">
			<h3><?php esc_html_e( 'Tempo médio por etapa', 'eb-credito-rural' ); ?></h3>
			<table class="widefat striped ebcr-t">
				<thead><tr><th><?php esc_html_e( 'Etapa', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Média (dias)', 'eb-credito-rural' ); ?></th><th class="ebcr-num"><?php esc_html_e( 'Base', 'eb-credito-rural' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $stages as $ebcr_r ) : ?>
					<tr><td><?php echo esc_html( $ebcr_r['label'] ); ?></td><td class="ebcr-num"><?php echo esc_html( null === $ebcr_r['days'] ? '—' : number_format_i18n( $ebcr_r['days'], 1 ) ); ?></td><td class="ebcr-num"><?php echo (int) $ebcr_r['n']; ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Calculado a partir do histórico de status (primeira chegada a cada etapa). "Base" é o número de solicitações que passaram pelas duas etapas.', 'eb-credito-rural' ); ?></p>
		</div>
	</div>
</div>
