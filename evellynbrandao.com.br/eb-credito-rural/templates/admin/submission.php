<?php
/**
 * Detalhe administrativo da solicitação.
 * Variáveis: $s, $user, $contact, $saved, $slots, $documents, $requests, $history, $messages, $checks, $check_items, $consents, $transitions, $can_edit, $analysts, $matrix, $notice, $notice_type.
 *
 * @package EBCR
 */

use EBCR\Domain\Status;
use EBCR\Files\DownloadController;
use EBCR\Forms\DocumentMatrix;
use EBCR\Forms\Steps;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_ident = isset( $saved['identificacao'] ) ? $saved['identificacao'] : array();
$ebcr_prod  = isset( $saved['producao'] ) ? $saved['producao'] : array();
$ebcr_fin   = isset( $saved['financeiro'] ) ? $saved['financeiro'] : array();
$ebcr_kv    = static function ( array $rows ) {
	echo '<dl class="ebcr-kv">';
	foreach ( $rows as $k => $v ) {
		echo '<dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( is_array( $v ) ? implode( ', ', $v ) : (string) ( null === $v || '' === $v ? '—' : $v ) ) . '</dd>';
	}
	echo '</dl>';
};
$ebcr_yn    = static function ( $v ) {
	return 'sim' === $v ? __( 'Sim', 'eb-credito-rural' ) : ( 'nao' === $v ? __( 'Não', 'eb-credito-rural' ) : ( 'na' === $v ? __( 'Não se aplica', 'eb-credito-rural' ) : $v ) );
};
$ebcr_tabs  = array(
	'resumo'      => __( 'Resumo', 'eb-credito-rural' ),
	'tomador'     => __( 'Tomador', 'eb-credito-rural' ),
	'imoveis'     => __( 'Imóveis', 'eb-credito-rural' ),
	'producao'    => __( 'Produção', 'eb-credito-rural' ),
	'financeiro'  => __( 'Financeiro', 'eb-credito-rural' ),
	'garantias'   => __( 'Garantias', 'eb-credito-rural' ),
	'documentos'  => __( 'Documentos', 'eb-credito-rural' ),
	'conferencia' => __( 'Conferência', 'eb-credito-rural' ),
	'historico'   => __( 'Histórico', 'eb-credito-rural' ),
	'mensagens'   => __( 'Mensagens', 'eb-credito-rural' ),
);
$ebcr_post  = esc_url( admin_url( 'admin-post.php' ) );
?>
<div class="wrap ebcr-admin">
	<h1 class="wp-heading-inline"><?php echo esc_html( $s['protocol'] ? $s['protocol'] : __( 'Rascunho', 'eb-credito-rural' ) ); ?> <?php echo ebcr_status_badge( $s['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=ebcr-submissions' ) ); ?>" class="page-title-action"><?php esc_html_e( '← Lista', 'eb-credito-rural' ); ?></a>
	<?php if ( current_user_can( \EBCR\Roles\Capabilities::CAP_CRM ) ) : ?>
	<a href="
		<?php
		echo esc_url(
			\EBCR\Admin\Crm::url(
				array(
					'view' => 'contato',
					'user' => (int) $s['user_id'],
				)
			)
		);
		?>
				" class="page-title-action"><?php esc_html_e( 'Ficha no CRM', 'eb-credito-rural' ); ?></a>
	<?php endif; ?>
	<?php
	if ( $notice ) :
		?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
	<?php
	if ( ! empty( $s['anonymized_at'] ) ) :
		?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'Esta solicitação foi anonimizada pela rotina de retenção/LGPD: dados pessoais e documentos foram removidos.', 'eb-credito-rural' ); ?></p></div><?php endif; ?>
	<div class="ebcr-detail">
		<div>
			<nav class="nav-tab-wrapper ebcr-tabs-nav">
				<?php
				foreach ( $ebcr_tabs as $ebcr_k => $ebcr_l ) :
					?>
					<a href="#" class="nav-tab" data-tab="<?php echo esc_attr( $ebcr_k ); ?>"><?php echo esc_html( $ebcr_l ); ?></a><?php endforeach; ?>
			</nav>

			<div class="ebcr-tabpanel ebcr-box" id="ebcr-tab-resumo">
				<?php
				$ebcr_kv(
					array(
						__( 'Cliente', 'eb-credito-rural' ) => $user ? $user->display_name . ' <' . $user->user_email . '>' : '—',
						__( 'Telefone', 'eb-credito-rural' ) => $contact['phone'] ? $contact['phone'] : get_user_meta( (int) $s['user_id'], 'ebcr_phone', true ),
						__( 'Tipo', 'eb-credito-rural' )  => $s['person_type'],
						__( 'Valor solicitado', 'eb-credito-rural' ) => Helpers::money( $s['requested_amount'] ),
						__( 'Finalidade', 'eb-credito-rural' ) => Steps::options( 'purposes' )[ $s['purpose'] ] ?? $s['purpose'],
						__( 'Prazo', 'eb-credito-rural' ) => $s['term_months'] ? $s['term_months'] . ' ' . __( 'meses', 'eb-credito-rural' ) : '—',
						__( 'Enviada em', 'eb-credito-rural' ) => Helpers::date( $s['submitted_at'] ),
						__( 'Analista', 'eb-credito-rural' ) => $s['assigned_to'] ? Helpers::user_name( (int) $s['assigned_to'] ) : '—',
						__( 'Documentos obrigatórios', 'eb-credito-rural' ) => $slots['required_done'] . ' / ' . $slots['required_total'],
						__( 'Identificador', 'eb-credito-rural' ) => $s['public_id'],
					)
				);
				?>
				<?php
				if ( ! empty( $ebcr_fin['_indicadores'] ) ) :
					$ebcr_i = $ebcr_fin['_indicadores'];
					?>
				<h4><?php esc_html_e( 'Indicadores (visíveis só à equipe)', 'eb-credito-rural' ); ?></h4>
				<div class="ebcr-indicators">
					<div><b><?php echo esc_html( Helpers::money( $ebcr_i['receita_media'] ) ); ?></b><?php esc_html_e( 'Receita média', 'eb-credito-rural' ); ?></div>
					<div><b><?php echo esc_html( Helpers::money( $ebcr_i['parcelas_anuais'] ) ); ?></b><?php esc_html_e( 'Parcelas/ano', 'eb-credito-rural' ); ?></div>
					<div><b><?php echo esc_html( null === $ebcr_i['comprometimento'] ? '—' : $ebcr_i['comprometimento'] . '%' ); ?></b><?php esc_html_e( 'Comprometimento', 'eb-credito-rural' ); ?></div>
					<div><b><?php echo esc_html( null === $ebcr_i['divida_receita'] ? '—' : $ebcr_i['divida_receita'] . 'x' ); ?></b><?php esc_html_e( 'Dívida / receita', 'eb-credito-rural' ); ?></div>
					<div><b><?php echo esc_html( null === $ebcr_i['solicitado_receita'] ? '—' : $ebcr_i['solicitado_receita'] . 'x' ); ?></b><?php esc_html_e( 'Solicitado / receita', 'eb-credito-rural' ); ?></div>
				</div>
				<?php endif; ?>
				<?php if ( $consents ) : ?>
				<h4><?php esc_html_e( 'Aceites registrados no envio', 'eb-credito-rural' ); ?></h4>
				<ul>
					<?php
					foreach ( $consents as $ebcr_c ) :
						?>
					<li><?php echo esc_html( $ebcr_c['policy_key'] . ' v' . $ebcr_c['policy_version'] . ' — ' . Helpers::date( $ebcr_c['accepted_at'] ) . ' — IP ' . $ebcr_c['ip'] ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
			</div>

			<div class="ebcr-tabpanel ebcr-box" id="ebcr-tab-tomador" hidden>
				<?php
				if ( isset( $ebcr_ident['_error'] ) ) {
					echo '<p class="ebcr-status-bad">' . esc_html__( 'Dados criptografados e chave indisponível.', 'eb-credito-rural' ) . '</p>';
				}
				$ebcr_rows = array();
				if ( 'PJ' === ( $ebcr_ident['person_type'] ?? '' ) ) {
					$ebcr_rows[ __( 'Razão social', 'eb-credito-rural' ) ]   = $ebcr_ident['razao_social'] ?? '';
					$ebcr_rows[ __( 'CNPJ', 'eb-credito-rural' ) ]           = Helpers::format_document( $ebcr_ident['cnpj'] ?? '' );
					$ebcr_rows[ __( 'Representantes', 'eb-credito-rural' ) ] = array_map(
						static function ( $r ) {
							return $r['nome'] . ' (' . Helpers::format_document( $r['cpf'] ) . ')';
						},
						$ebcr_ident['representantes'] ?? array()
					);
				} else {
					$ebcr_rows[ __( 'Nome', 'eb-credito-rural' ) ]           = $ebcr_ident['nome'] ?? '';
					$ebcr_rows[ __( 'CPF', 'eb-credito-rural' ) ]            = Helpers::format_document( $ebcr_ident['cpf'] ?? '' );
					$ebcr_rows[ __( 'RG', 'eb-credito-rural' ) ]             = ( $ebcr_ident['rg'] ?? '' ) . ' ' . ( $ebcr_ident['rg_orgao'] ?? '' );
					$ebcr_rows[ __( 'Nascimento', 'eb-credito-rural' ) ]     = $ebcr_ident['nascimento'] ?? '';
					$ebcr_rows[ __( 'Estado civil', 'eb-credito-rural' ) ]   = Steps::options( 'estado_civil' )[ $ebcr_ident['estado_civil'] ?? '' ] ?? '';
					$ebcr_rows[ __( 'Regime de bens', 'eb-credito-rural' ) ] = Steps::options( 'regime_bens' )[ $ebcr_ident['regime_bens'] ?? '' ] ?? '';
					$ebcr_rows[ __( 'Cônjuge', 'eb-credito-rural' ) ]        = ( $ebcr_ident['conjuge_nome'] ?? '' ) . ( ! empty( $ebcr_ident['conjuge_cpf'] ) ? ' (' . Helpers::format_document( $ebcr_ident['conjuge_cpf'] ) . ')' : '' );
				}
				$ebcr_rows[ __( 'Inscrição estadual', 'eb-credito-rural' ) ]  = $ebcr_ident['inscricao_estadual'] ?? '';
				$ebcr_rows[ __( 'CAF', 'eb-credito-rural' ) ]                 = $ebcr_ident['caf'] ?? '';
				$ebcr_rows[ __( 'Endereço', 'eb-credito-rural' ) ]            = trim( ( $ebcr_ident['logradouro'] ?? '' ) . ', ' . ( $ebcr_ident['numero'] ?? '' ) . ' ' . ( $ebcr_ident['complemento'] ?? '' ) . ' — ' . ( $ebcr_ident['bairro'] ?? '' ) . ' — ' . ( $ebcr_ident['cidade'] ?? '' ) . '/' . ( $ebcr_ident['uf'] ?? '' ) . ' — CEP ' . ( $ebcr_ident['cep'] ?? '' ) );
				$ebcr_rows[ __( 'Telefone / WhatsApp', 'eb-credito-rural' ) ] = ( $ebcr_ident['telefone'] ?? '' ) . ' / ' . ( $ebcr_ident['whatsapp'] ?? '' );
				$ebcr_rows[ __( 'PEP', 'eb-credito-rural' ) ]                 = $ebcr_yn( $ebcr_ident['pep'] ?? '' ) . ( ! empty( $ebcr_ident['pep_detalhes'] ) ? ' — ' . $ebcr_ident['pep_detalhes'] : '' );
				$ebcr_kv( $ebcr_rows );
				?>
			</div>

			<div class="ebcr-tabpanel ebcr-box" id="ebcr-tab-imoveis" hidden>
				<?php
				if ( ! $saved['imoveis']['imoveis'] ) :
					?>
					<p class="description"><?php esc_html_e( 'Nenhum imóvel informado.', 'eb-credito-rural' ); ?></p><?php endif; ?>
				<?php foreach ( $saved['imoveis']['imoveis'] as $ebcr_p ) : ?>
					<h4><?php echo esc_html( $ebcr_p['name'] ); ?> <span class="description">#<?php echo (int) $ebcr_p['id']; ?></span></h4>
					<?php
					$ebcr_kv(
						array(
							__( 'Município/UF', 'eb-credito-rural' ) => $ebcr_p['city'] . '/' . $ebcr_p['uf'],
							__( 'Matrícula / cartório', 'eb-credito-rural' ) => $ebcr_p['registration_number'] . ' — ' . $ebcr_p['registry_office'],
							__( 'Área total / útil (ha)', 'eb-credito-rural' ) => $ebcr_p['total_area'] . ' / ' . $ebcr_p['usable_area'],
							__( 'CAR', 'eb-credito-rural' ) => $ebcr_p['car_code'],
							__( 'CCIR / NIRF / SIGEF', 'eb-credito-rural' ) => $ebcr_p['ccir'] . ' / ' . $ebcr_p['nirf'] . ' / ' . $ebcr_p['sigef'],
							__( 'Condição', 'eb-credito-rural' ) => Steps::options( 'tenure' )[ $ebcr_p['tenure'] ] ?? $ebcr_p['tenure'],
							__( 'Término do arrendamento', 'eb-credito-rural' ) => $ebcr_p['lease_end'],
						)
					);
					?>
					<?php
					if ( $ebcr_p['lease_end'] && $s['term_months'] && strtotime( $ebcr_p['lease_end'] ) < strtotime( '+' . (int) $s['term_months'] . ' months' ) ) :
						?>
						<p class="ebcr-status-warn"><?php esc_html_e( 'Atenção: o arrendamento termina antes do prazo solicitado.', 'eb-credito-rural' ); ?></p><?php endif; ?>
				<?php endforeach; ?>
			</div>

			<div class="ebcr-tabpanel ebcr-box" id="ebcr-tab-producao" hidden>
				<?php
				$ebcr_acts = Steps::options( 'activities' );
				$ebcr_kv(
					array(
						__( 'Atividades', 'eb-credito-rural' )       => array_map(
							static function ( $a ) use ( $ebcr_acts ) {
								return $ebcr_acts[ $a ] ?? $a; },
							$ebcr_prod['atividades'] ?? array()
						),
						__( 'Outra atividade', 'eb-credito-rural' )  => $ebcr_prod['atividade_outra'] ?? '',
						__( 'Área por cultura', 'eb-credito-rural' ) => array_map(
							static function ( $r ) {
								return $r['cultura'] . ': ' . $r['area_ha'] . ' ha'; },
							$ebcr_prod['area_cultura'] ?? array()
						),
						__( 'Produtividade', 'eb-credito-rural' )    => array_map(
							static function ( $r ) {
								return $r['safra'] . ' ' . $r['cultura'] . ': ' . $r['valor'] . ' ' . $r['unidade']; },
							$ebcr_prod['produtividade'] ?? array()
						),
						__( 'Plano da safra', 'eb-credito-rural' )   => $ebcr_prod['plano_safra'] ?? '',
						__( 'Orçamento de custeio', 'eb-credito-rural' ) => Helpers::money( $ebcr_prod['orcamento_custeio'] ?? null ),
						__( 'Compradores', 'eb-credito-rural' )      => $ebcr_prod['compradores'] ?? '',
						__( 'Contratos de venda/barter', 'eb-credito-rural' ) => $ebcr_yn( $ebcr_prod['contratos_venda'] ?? '' ) . ' ' . ( $ebcr_prod['contratos_detalhes'] ?? '' ),
						__( 'Rebanho', 'eb-credito-rural' )          => array_map(
							static function ( $r ) {
								return ( Steps::options( 'rebanho_categorias' )[ $r['categoria'] ] ?? $r['categoria'] ) . ': ' . $r['quantidade']; },
							$ebcr_prod['rebanho'] ?? array()
						),
						__( 'Seguro rural', 'eb-credito-rural' )     => $ebcr_yn( $ebcr_prod['seguro_rural'] ?? '' ) . ' ' . ( $ebcr_prod['seguro_tipo'] ?? '' ),
						__( 'Irrigação', 'eb-credito-rural' )        => $ebcr_yn( $ebcr_prod['irrigacao'] ?? '' ),
						__( 'Maquinário', 'eb-credito-rural' )       => $ebcr_prod['maquinario'] ?? '',
						__( 'Licença ambiental / outorga', 'eb-credito-rural' ) => $ebcr_yn( $ebcr_prod['licenca_ambiental'] ?? '' ),
					)
				);
				?>
			</div>

			<div class="ebcr-tabpanel ebcr-box" id="ebcr-tab-financeiro" hidden>
				<?php
				$ebcr_kv(
					array(
						__( 'Receita (últimos 3 anos)', 'eb-credito-rural' ) => Helpers::money( $ebcr_fin['receita_ano1'] ?? null ) . ' · ' . Helpers::money( $ebcr_fin['receita_ano2'] ?? null ) . ' · ' . Helpers::money( $ebcr_fin['receita_ano3'] ?? null ),
						__( 'Dívidas', 'eb-credito-rural' ) => array_map(
							static function ( $d ) {
								return $d['credor'] . ': saldo ' . Helpers::money( $d['saldo'] ) . ', parcela ' . Helpers::money( $d['parcela'] ) . ' ' . $d['periodicidade'] . ', venc. ' . $d['vencimento'] . ( $d['garantia'] ? ' (' . $d['garantia'] . ')' : '' ); },
							$ebcr_fin['dividas'] ?? array()
						),
						__( 'Valor solicitado', 'eb-credito-rural' ) => Helpers::money( $ebcr_fin['valor_solicitado'] ?? null ),
						__( 'Finalidade', 'eb-credito-rural' ) => Steps::options( 'purposes' )[ $ebcr_fin['finalidade'] ?? '' ] ?? '',
						__( 'Prazo', 'eb-credito-rural' ) => isset( $ebcr_fin['prazo_meses'] ) ? $ebcr_fin['prazo_meses'] . ' ' . __( 'meses', 'eb-credito-rural' ) : '',
						__( 'Época de pagamento', 'eb-credito-rural' ) => ( Steps::options( 'epoca_pagamento' )[ $ebcr_fin['epoca_pagamento'] ?? '' ] ?? '' ) . ' ' . ( $ebcr_fin['epoca_detalhes'] ?? '' ),
					)
				);
				?>
			</div>

			<div class="ebcr-tabpanel ebcr-box" id="ebcr-tab-garantias" hidden>
				<?php
				if ( ! $saved['garantias']['garantias'] ) :
					?>
					<p class="description"><?php esc_html_e( 'Nenhuma garantia informada.', 'eb-credito-rural' ); ?></p><?php endif; ?>
				<?php foreach ( $saved['garantias']['garantias'] as $ebcr_g ) : ?>
					<h4><?php echo esc_html( Steps::options( 'guarantee_types' )[ $ebcr_g['type'] ] ?? $ebcr_g['type'] ); ?></h4>
					<?php
					$ebcr_kv(
						array(
							__( 'Valor declarado', 'eb-credito-rural' ) => Helpers::money( $ebcr_g['declared_value'] ),
							__( 'Valor avaliado', 'eb-credito-rural' ) => Helpers::money( $ebcr_g['appraised_value'] ),
							__( 'LTV', 'eb-credito-rural' )    => $ebcr_g['ltv'] ? $ebcr_g['ltv'] . '%' : '—',
							__( 'Imóvel', 'eb-credito-rural' ) => $ebcr_g['property_id'] ? '#' . $ebcr_g['property_id'] : '—',
							__( 'Descrição', 'eb-credito-rural' ) => $ebcr_g['description'],
							__( 'Formalização', 'eb-credito-rural' ) => $ebcr_g['formalization_status'],
						)
					);
					?>
				<?php endforeach; ?>
			</div>

			<div class="ebcr-tabpanel ebcr-box" id="ebcr-tab-documentos" hidden>
				<h4><?php esc_html_e( 'Checklist (matriz)', 'eb-credito-rural' ); ?></h4>
				<ul>
				<?php foreach ( $slots['slots'] as $ebcr_slot ) : ?>
					<li><?php echo $ebcr_slot['satisfied'] ? '✔' : ( $ebcr_slot['required'] ? '<span class="ebcr-status-bad">✖</span>' : '○' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal. ?> <?php echo esc_html( $ebcr_slot['label'] . ( $ebcr_slot['ref_label'] ? ' — ' . $ebcr_slot['ref_label'] : '' ) ); ?> <span class="description">(<?php echo esc_html( \EBCR\Forms\DocumentMatrix::levels()[ $ebcr_slot['level'] ] ?? $ebcr_slot['level'] ); ?>)</span></li>
				<?php endforeach; ?>
				</ul>
				<h4><?php esc_html_e( 'Arquivos enviados', 'eb-credito-rural' ); ?></h4>
				<?php
				if ( ! $documents ) :
					?>
					<p class="description"><?php esc_html_e( 'Nenhum arquivo.', 'eb-credito-rural' ); ?></p><?php endif; ?>
				<table class="ebcr-t"><thead><tr><th><?php esc_html_e( 'Tipo', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Arquivo', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Validade', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Revisão', 'eb-credito-rural' ); ?></th></tr></thead><tbody>
				<?php foreach ( $documents as $ebcr_d ) : ?>
					<tr>
						<td><?php echo esc_html( DocumentMatrix::label( $ebcr_d['doc_type'] ) ); ?>
						<?php
						if ( $ebcr_d['ref_key'] ) :
							?>
							<span class="description"><?php echo esc_html( $ebcr_d['ref_key'] ); ?></span><?php endif; ?></td>
						<td><a href="<?php echo esc_url( DownloadController::url( $ebcr_d ) ); ?>"><?php echo esc_html( $ebcr_d['original_name'] ); ?></a> <span class="description"><?php echo esc_html( Helpers::size( $ebcr_d['size'] ) ); ?></span>
						<?php
						if ( in_array( $ebcr_d['mime'], array( 'application/pdf', 'image/jpeg', 'image/png' ), true ) ) :
							?>
							· <a href="<?php echo esc_url( DownloadController::url( $ebcr_d, true ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'visualizar', 'eb-credito-rural' ); ?></a><?php endif; ?><br><span class="description"><?php echo esc_html( Helpers::date( $ebcr_d['uploaded_at'] ) ); ?> · SHA-256 <?php echo esc_html( substr( $ebcr_d['sha256'], 0, 12 ) ); ?>…<?php echo $ebcr_d['encrypted'] ? ' · 🔒' : ''; ?></span><?php echo \EBCR\Esign\Esign::badge_for_document( $ebcr_d ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapado pelo helper. ?></td>
						<td><?php echo $ebcr_d['expires_at'] ? esc_html( wp_date( get_option( 'date_format' ), strtotime( $ebcr_d['expires_at'] ) ) ) . ( strtotime( $ebcr_d['expires_at'] ) < time() ? ' <span class="ebcr-status-bad">' . esc_html__( 'vencida', 'eb-credito-rural' ) . '</span>' : '' ) : '—'; ?></td>
						<td>
							<strong class="<?php echo 'aceito' === $ebcr_d['review_status'] ? 'ebcr-status-ok' : ( 'recusado' === $ebcr_d['review_status'] ? 'ebcr-status-bad' : '' ); ?>"><?php echo esc_html( ucfirst( $ebcr_d['review_status'] ) ); ?></strong>
							<?php
							if ( $ebcr_d['review_note'] ) :
								?>
								<br><span class="description"><?php echo esc_html( $ebcr_d['review_note'] ); ?></span><?php endif; ?>
							<?php if ( $can_edit ) : ?>
							<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>" class="ebcr-review-form">
								<input type="hidden" name="action" value="ebcr_admin_review_document"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>"><input type="hidden" name="doc" value="<?php echo esc_attr( $ebcr_d['public_id'] ); ?>">
								<?php echo ebcr_nonce_field( 'admin_review' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<select name="result"><option value="aceito"><?php esc_html_e( 'Aceitar', 'eb-credito-rural' ); ?></option><option value="recusado"><?php esc_html_e( 'Recusar', 'eb-credito-rural' ); ?></option><option value="pendente"><?php esc_html_e( 'Pendente', 'eb-credito-rural' ); ?></option></select>
								<textarea name="note" rows="1" placeholder="<?php esc_attr_e( 'Motivo (obrigatório ao recusar; o cliente verá)', 'eb-credito-rural' ); ?>"></textarea>
								<button class="button button-small"><?php esc_html_e( 'Salvar', 'eb-credito-rural' ); ?></button>
							</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
				<?php if ( $requests ) : ?>
				<h4><?php esc_html_e( 'Documentos solicitados pela equipe', 'eb-credito-rural' ); ?></h4>
				<ul>
					<?php
					foreach ( $requests as $ebcr_r ) :
						?>
					<li><?php echo esc_html( $ebcr_r['label'] ); ?> — <?php echo $ebcr_r['fulfilled_at'] ? '<span class="ebcr-status-ok">' . esc_html__( 'atendido em', 'eb-credito-rural' ) . ' ' . esc_html( Helpers::date( $ebcr_r['fulfilled_at'] ) ) . '</span>' : '<span class="ebcr-status-warn">' . esc_html__( 'em aberto desde', 'eb-credito-rural' ) . ' ' . esc_html( Helpers::date( $ebcr_r['requested_at'] ) ) . '</span>'; ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
			</div>

			<div class="ebcr-tabpanel ebcr-box" id="ebcr-tab-conferencia" hidden>
				<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
					<input type="hidden" name="action" value="ebcr_admin_checks"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
					<?php echo ebcr_nonce_field( 'admin_checks' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<table class="ebcr-t"><thead><tr><th><?php esc_html_e( 'Item', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Resultado', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Observação / valor de referência', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Conferido por', 'eb-credito-rural' ); ?></th></tr></thead><tbody>
					<?php
					foreach ( $check_items as $ebcr_k => $ebcr_label ) :
						$ebcr_c = $checks[ $ebcr_k ] ?? array();
						?>
						<tr>
							<td><?php echo esc_html( $ebcr_label ); ?></td>
							<td><select name="check[<?php echo esc_attr( $ebcr_k ); ?>][result]" <?php disabled( ! $can_edit ); ?>>
								<?php
								foreach ( array(
									'na'        => __( 'N/A', 'eb-credito-rural' ),
									'ok'        => __( 'OK', 'eb-credito-rural' ),
									'alerta'    => __( 'Alerta', 'eb-credito-rural' ),
									'reprovado' => __( 'Reprovado', 'eb-credito-rural' ),
								) as $ebcr_rv => $ebcr_rl ) :
									?>
														<option value="<?php echo esc_attr( $ebcr_rv ); ?>" <?php selected( $ebcr_c['result'] ?? 'na', $ebcr_rv ); ?>><?php echo esc_html( $ebcr_rl ); ?></option><?php endforeach; ?>
							</select></td>
							<td><input type="text" name="check[<?php echo esc_attr( $ebcr_k ); ?>][note]" value="<?php echo esc_attr( $ebcr_c['note'] ?? '' ); ?>" style="width:100%" <?php disabled( ! $can_edit ); ?>></td>
							<td><?php echo ! empty( $ebcr_c['checked_by'] ) ? esc_html( Helpers::user_name( (int) $ebcr_c['checked_by'] ) . ' · ' . Helpers::date( $ebcr_c['checked_at'] ) ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody></table>
					<?php
					if ( $can_edit ) :
						?>
						<p><button class="button button-primary"><?php esc_html_e( 'Salvar checklist', 'eb-credito-rural' ); ?></button></p><?php endif; ?>
				</form>
			</div>

			<div class="ebcr-tabpanel ebcr-box" id="ebcr-tab-historico" hidden>
				<ul class="ebcr-timeline">
				<?php foreach ( $history as $ebcr_h ) : ?>
					<li style="--ebcr-badge:<?php echo esc_attr( Status::color( $ebcr_h['to_status'] ) ); ?>"><strong><?php echo esc_html( Status::label( $ebcr_h['to_status'] ) ); ?></strong> <span class="description"><?php echo esc_html( Helpers::date( $ebcr_h['created_at'] ) . ' · ' . Helpers::user_name( (int) $ebcr_h['changed_by'] ) ); ?></span>
					<?php
					if ( $ebcr_h['comment_internal'] ) :
						?>
						<br><em><?php esc_html_e( 'Interno:', 'eb-credito-rural' ); ?></em> <?php echo esc_html( $ebcr_h['comment_internal'] ); ?><?php endif; ?>
						<?php
						if ( $ebcr_h['comment_client'] ) :
							?>
						<br><em><?php esc_html_e( 'Ao cliente:', 'eb-credito-rural' ); ?></em> <?php echo esc_html( $ebcr_h['comment_client'] ); ?><?php endif; ?></li>
				<?php endforeach; ?>
				</ul>
			</div>

			<div class="ebcr-tabpanel ebcr-box" id="ebcr-tab-mensagens" hidden>
				<?php foreach ( $messages as $ebcr_m ) : ?>
					<div class="ebcr-msg ebcr-msg--<?php echo esc_attr( $ebcr_m['visibility'] ); ?>"><div class="ebcr-msg-meta"><strong><?php echo esc_html( Helpers::user_name( (int) $ebcr_m['author_id'] ) ); ?></strong> · <?php echo esc_html( Helpers::date( $ebcr_m['created_at'] ) ); ?> · <?php echo 'interno' === $ebcr_m['visibility'] ? esc_html__( 'nota interna', 'eb-credito-rural' ) : esc_html__( 'visível ao cliente', 'eb-credito-rural' ); ?></div><p><?php echo nl2br( esc_html( $ebcr_m['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html + nl2br. ?></p></div>
				<?php endforeach; ?>
				<?php
				if ( ! $messages ) :
					?>
					<p class="description"><?php esc_html_e( 'Nenhuma mensagem.', 'eb-credito-rural' ); ?></p><?php endif; ?>
				<?php if ( $can_edit ) : ?>
				<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
					<input type="hidden" name="action" value="ebcr_admin_message"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
					<?php echo ebcr_nonce_field( 'admin_message' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<p><textarea name="body" rows="3" class="large-text" required></textarea></p>
					<p><label><input type="radio" name="visibility" value="interno" checked> <?php esc_html_e( 'Nota interna', 'eb-credito-rural' ); ?></label> &nbsp; <label><input type="radio" name="visibility" value="cliente"> <?php esc_html_e( 'Enviar ao cliente (notifica por e-mail)', 'eb-credito-rural' ); ?></label></p>
					<p><button class="button button-primary"><?php esc_html_e( 'Registrar', 'eb-credito-rural' ); ?></button></p>
				</form>
				<?php endif; ?>
			</div>
		</div>

		<aside>
			<?php if ( $can_edit ) : ?>
			<div class="ebcr-box">
				<h3><?php esc_html_e( 'Alterar status', 'eb-credito-rural' ); ?></h3>
				<?php
				if ( ! $transitions ) :
					?>
					<p class="description"><?php esc_html_e( 'Nenhuma transição disponível para o seu perfil neste status.', 'eb-credito-rural' ); ?></p><?php else : ?>
				<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
					<input type="hidden" name="action" value="ebcr_admin_status"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
												<?php echo ebcr_nonce_field( 'admin_status' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<p><select name="status" class="widefat" data-status-select>
												<?php
												foreach ( $transitions as $ebcr_k => $ebcr_def ) :
													?>
						<option value="<?php echo esc_attr( $ebcr_k ); ?>" data-requires-comment="<?php echo ! empty( $ebcr_def['requires_internal_comment'] ) ? '1' : '0'; ?>"><?php echo esc_html( $ebcr_def['label'] ); ?></option><?php endforeach; ?></select></p>
					<p class="description" data-requires-comment hidden><?php esc_html_e( 'Esta decisão exige comentário interno (parecer).', 'eb-credito-rural' ); ?></p>
					<p><label><?php esc_html_e( 'Comentário interno', 'eb-credito-rural' ); ?><textarea name="comment_internal" rows="3" class="widefat" data-comment-internal></textarea></label></p>
					<p><label><?php esc_html_e( 'Comentário ao cliente (opcional; senão usa o texto padrão do status)', 'eb-credito-rural' ); ?><textarea name="comment_client" rows="3" class="widefat"></textarea></label></p>
					<p><button class="button button-primary" data-confirm="<?php esc_attr_e( 'Confirmar a mudança de status? O cliente será notificado se o status for visível.', 'eb-credito-rural' ); ?>"><?php esc_html_e( 'Aplicar', 'eb-credito-rural' ); ?></button></p>
				</form>
				<?php endif; ?>
			</div>
			<div class="ebcr-box">
				<h3><?php esc_html_e( 'Analista responsável', 'eb-credito-rural' ); ?></h3>
				<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
					<input type="hidden" name="action" value="ebcr_admin_assign"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
					<?php echo ebcr_nonce_field( 'admin_assign' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<p><select name="analyst_id" class="widefat"><option value="0"><?php esc_html_e( '— Nenhum —', 'eb-credito-rural' ); ?></option>
					<?php
					foreach ( $analysts as $ebcr_a ) :
						?>
						<option value="<?php echo (int) $ebcr_a->ID; ?>" <?php selected( (int) $s['assigned_to'], (int) $ebcr_a->ID ); ?>><?php echo esc_html( $ebcr_a->display_name ); ?></option><?php endforeach; ?></select></p>
					<p><button class="button"><?php esc_html_e( 'Atribuir', 'eb-credito-rural' ); ?></button></p>
				</form>
			</div>
			<div class="ebcr-box">
				<h3><?php esc_html_e( 'Fundo / carteira', 'eb-credito-rural' ); ?></h3>
				<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
					<input type="hidden" name="action" value="ebcr_admin_fund"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
					<?php echo ebcr_nonce_field( 'admin_fund' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<p><select name="fund" class="widefat"><option value=""><?php esc_html_e( '— Sem carteira —', 'eb-credito-rural' ); ?></option>
					<?php
					foreach ( \EBCR\Support\Options::pairs( 'funds' ) as $ebcr_fk => $ebcr_fl ) :
						?>
						<option value="<?php echo esc_attr( $ebcr_fk ); ?>" <?php selected( (string) ( $s['fund'] ?? '' ), $ebcr_fk ); ?>><?php echo esc_html( $ebcr_fl ); ?></option><?php endforeach; ?></select></p>
					<p><button class="button"><?php esc_html_e( 'Salvar carteira', 'eb-credito-rural' ); ?></button></p>
				</form>
				<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
					<input type="hidden" name="action" value="ebcr_admin_dossier"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
					<?php echo ebcr_nonce_field( 'admin_dossier' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<p><button class="button button-primary"><?php esc_html_e( 'Gerar dossiê em PDF para o comitê', 'eb-credito-rural' ); ?></button></p>
				</form>
			</div>
			<div class="ebcr-box">
				<h3><?php esc_html_e( 'Solicitar documento', 'eb-credito-rural' ); ?></h3>
				<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
					<input type="hidden" name="action" value="ebcr_admin_request_document"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
					<?php echo ebcr_nonce_field( 'admin_request' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<p><select name="doc_type" class="widefat">
					<?php
					foreach ( $matrix as $ebcr_k => $ebcr_m ) :
						?>
						<option value="<?php echo esc_attr( $ebcr_k ); ?>"><?php echo esc_html( $ebcr_m['label'] ); ?></option><?php endforeach; ?></select></p>
					<p><input type="text" name="label" class="widefat" placeholder="<?php esc_attr_e( 'Rótulo exibido ao cliente (opcional)', 'eb-credito-rural' ); ?>"></p>
					<p><textarea name="note" rows="2" class="widefat" placeholder="<?php esc_attr_e( 'Instruções ao cliente', 'eb-credito-rural' ); ?>"></textarea></p>
					<p><label><input type="checkbox" name="set_status" value="1" checked> <?php esc_html_e( 'Mudar status para "Pendência documental"', 'eb-credito-rural' ); ?></label></p>
					<p><button class="button"><?php esc_html_e( 'Solicitar', 'eb-credito-rural' ); ?></button></p>
				</form>
			</div>
			<?php endif; ?>
			<?php if ( current_user_can( \EBCR\Roles\Capabilities::CAP_EXPORT ) ) : ?>
			<div class="ebcr-box">
				<form method="post" action="<?php echo $ebcr_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
					<input type="hidden" name="action" value="ebcr_admin_export_one"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
					<?php echo ebcr_nonce_field( 'admin_export' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<button class="button"><?php esc_html_e( 'Exportar solicitação (JSON)', 'eb-credito-rural' ); ?></button>
				</form>
			</div>
			<?php endif; ?>
		</aside>
	</div>
</div><?php foreach ( \EBCR\Esign\Esign::signatures( $s ) as $ebcr_sig ) : ?>
					<?php echo \EBCR\Esign\Esign::evidence_html( $ebcr_sig ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapado pelo helper. ?>
				<?php endforeach; ?>
