<?php
/**
 * Detalhe da solicitação no painel da equipe (abas + ações laterais).
 * Variáveis: $s, $user, $contact, $saved, $slots, $documents, $requests, $history, $messages, $checks, $check_items,
 * $consents, $transitions, $can_edit, $can_export, $can_crm, $analysts, $funds, $matrix, $tab.
 *
 * @package EBCR
 */

use EBCR\Domain\Status;
use EBCR\Files\DownloadController;
use EBCR\Forms\DocumentMatrix;
use EBCR\Forms\Steps;
use EBCR\Frontend\Team\Panel;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;
$ebcr_ident  = isset( $saved['identificacao'] ) ? $saved['identificacao'] : array();
$ebcr_prod   = isset( $saved['producao'] ) ? $saved['producao'] : array();
$ebcr_fin    = isset( $saved['financeiro'] ) ? $saved['financeiro'] : array();
$ebcr_action = esc_url( ebcr_form_action() );
$ebcr_kv     = static function ( array $rows ) {
	echo '<dl class="ebcr-kv">';
	foreach ( $rows as $k => $v ) {
		echo '<dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( is_array( $v ) ? ( $v ? implode( ', ', $v ) : '—' ) : (string) ( null === $v || '' === $v ? '—' : $v ) ) . '</dd>';
	}
	echo '</dl>';
};
$ebcr_yn     = static function ( $v ) {
	return 'sim' === $v ? __( 'Sim', 'eb-credito-rural' ) : ( 'nao' === $v ? __( 'Não', 'eb-credito-rural' ) : ( 'na' === $v ? __( 'Não se aplica', 'eb-credito-rural' ) : $v ) );
};
$ebcr_tabs   = array(
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
$ebcr_active = isset( $ebcr_tabs[ $tab ] ) ? $tab : 'resumo';
$ebcr_panel  = static function ( $key ) use ( $ebcr_active ) {
	return sprintf( '<section class="ebcr-tpanel ebcr-card" id="ebcr-tab-%1$s" data-panel="%1$s" role="tabpanel"%2$s>', esc_attr( $key ), $key === $ebcr_active ? '' : ' hidden' );
};
?>
<p><a class="ebcr-link" href="<?php echo esc_url( Panel::url( array( 'tela' => 'solicitacoes' ) ) ); ?>">&larr; <?php esc_html_e( 'Solicitações', 'eb-credito-rural' ); ?></a></p>
<div class="ebcr-card ebcr-sub-head ebcr-tsub-head">
	<div>
		<h2><?php echo esc_html( $s['protocol'] ? $s['protocol'] : __( 'Rascunho', 'eb-credito-rural' ) ); ?> <?php echo ebcr_status_badge( $s['status'] ); ?></h2>
		<p class="ebcr-muted"><?php echo esc_html( $user ? $user->display_name : '—' ); ?> · <?php echo esc_html( Helpers::money( $s['requested_amount'] ) ); ?> · <?php echo esc_html( Helpers::date( $s['submitted_at'] ? $s['submitted_at'] : $s['created_at'] ) ); ?></p>
	</div>
	<div class="ebcr-actions ebcr-tsub-actions">
		<?php if ( $can_crm ) : ?>
			<a class="ebcr-btn ebcr-btn--small" href="<?php echo esc_url( Panel::url( array( 'tela' => 'crm', 'sub' => 'contato', 'usuario' => (int) $s['user_id'] ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- curto. ?>"><?php esc_html_e( 'Ficha no CRM', 'eb-credito-rural' ); ?></a>
		<?php endif; ?>
		<form method="post" action="<?php echo $ebcr_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>" class="ebcr-inline-form">
			<input type="hidden" name="action" value="ebcr_team_dossier"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
			<?php echo ebcr_nonce_field( 'team_dossier' ); ?>
			<button type="submit" class="ebcr-btn ebcr-btn--primary ebcr-btn--small"><?php esc_html_e( 'Dossiê em PDF', 'eb-credito-rural' ); ?></button>
		</form>
	</div>
</div>
<?php if ( ! empty( $s['anonymized_at'] ) ) : ?>
	<div class="ebcr-alert ebcr-alert--warning" role="status"><?php esc_html_e( 'Esta solicitação foi anonimizada pela rotina de retenção/LGPD: dados pessoais e documentos foram removidos.', 'eb-credito-rural' ); ?></div>
<?php endif; ?>

<div class="ebcr-tdetail">
	<div class="ebcr-tdetail-main">
		<nav class="ebcr-ttabs" role="tablist" aria-label="<?php esc_attr_e( 'Seções da solicitação', 'eb-credito-rural' ); ?>">
			<?php foreach ( $ebcr_tabs as $ebcr_k => $ebcr_l ) : ?>
				<a role="tab" class="ebcr-ttab<?php echo $ebcr_k === $ebcr_active ? ' is-active' : ''; ?>" data-tab="<?php echo esc_attr( $ebcr_k ); ?>" aria-selected="<?php echo $ebcr_k === $ebcr_active ? 'true' : 'false'; ?>" aria-controls="ebcr-tab-<?php echo esc_attr( $ebcr_k ); ?>" href="<?php echo esc_url( Panel::submission_url( $s, $ebcr_k ) ); ?>"><?php echo esc_html( $ebcr_l ); ?></a>
			<?php endforeach; ?>
		</nav>

		<?php echo $ebcr_panel( 'resumo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado na closure. ?>
			<?php
			$ebcr_kv(
				array(
					__( 'Cliente', 'eb-credito-rural' )    => $user ? $user->display_name . ' <' . $user->user_email . '>' : '—',
					__( 'Telefone', 'eb-credito-rural' )   => $contact['phone'] ? \EBCR\Crm\Service::format_phone( $contact['phone'] ) : get_user_meta( (int) $s['user_id'], 'ebcr_phone', true ),
					__( 'Tipo', 'eb-credito-rural' )       => $s['person_type'],
					__( 'Valor solicitado', 'eb-credito-rural' ) => Helpers::money( $s['requested_amount'] ),
					__( 'Finalidade', 'eb-credito-rural' ) => isset( Steps::options( 'purposes' )[ $s['purpose'] ] ) ? Steps::options( 'purposes' )[ $s['purpose'] ] : $s['purpose'],
					__( 'Prazo', 'eb-credito-rural' )      => $s['term_months'] ? $s['term_months'] . ' ' . __( 'meses', 'eb-credito-rural' ) : '—',
					__( 'Carteira', 'eb-credito-rural' )   => isset( $funds[ $s['fund'] ] ) ? $funds[ $s['fund'] ] : ( $s['fund'] ? $s['fund'] : '—' ),
					__( 'Enviada em', 'eb-credito-rural' ) => Helpers::date( $s['submitted_at'] ),
					__( 'Analista', 'eb-credito-rural' )   => $s['assigned_to'] ? Helpers::user_name( (int) $s['assigned_to'] ) : '—',
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
			<div class="ebcr-tindicators">
				<div><b><?php echo esc_html( Helpers::money( $ebcr_i['receita_media'] ) ); ?></b><?php esc_html_e( 'Receita média', 'eb-credito-rural' ); ?></div>
				<div><b><?php echo esc_html( Helpers::money( $ebcr_i['parcelas_anuais'] ) ); ?></b><?php esc_html_e( 'Parcelas/ano', 'eb-credito-rural' ); ?></div>
				<div><b><?php echo esc_html( null === $ebcr_i['comprometimento'] ? '—' : $ebcr_i['comprometimento'] . '%' ); ?></b><?php esc_html_e( 'Comprometimento', 'eb-credito-rural' ); ?></div>
				<div><b><?php echo esc_html( null === $ebcr_i['divida_receita'] ? '—' : $ebcr_i['divida_receita'] . 'x' ); ?></b><?php esc_html_e( 'Dívida / receita', 'eb-credito-rural' ); ?></div>
				<div><b><?php echo esc_html( null === $ebcr_i['solicitado_receita'] ? '—' : $ebcr_i['solicitado_receita'] . 'x' ); ?></b><?php esc_html_e( 'Solicitado / receita', 'eb-credito-rural' ); ?></div>
			</div>
			<?php endif; ?>
			<?php if ( $consents ) : ?>
			<h4><?php esc_html_e( 'Aceites registrados no envio', 'eb-credito-rural' ); ?></h4>
			<ul class="ebcr-tlist">
				<?php foreach ( $consents as $ebcr_c ) : ?>
				<li><?php echo esc_html( $ebcr_c['policy_key'] . ' v' . $ebcr_c['policy_version'] . ' — ' . Helpers::date( $ebcr_c['accepted_at'] ) . ' — IP ' . $ebcr_c['ip'] ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</section>

		<?php echo $ebcr_panel( 'tomador' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado na closure. ?>
			<?php
			if ( isset( $ebcr_ident['_error'] ) ) {
				echo '<p class="ebcr-bad">' . esc_html__( 'Dados criptografados e chave indisponível.', 'eb-credito-rural' ) . '</p>';
			}
			$ebcr_rows = array();
			if ( 'PJ' === ( isset( $ebcr_ident['person_type'] ) ? $ebcr_ident['person_type'] : '' ) ) {
				$ebcr_rows[ __( 'Razão social', 'eb-credito-rural' ) ]   = isset( $ebcr_ident['razao_social'] ) ? $ebcr_ident['razao_social'] : '';
				$ebcr_rows[ __( 'CNPJ', 'eb-credito-rural' ) ]           = Helpers::format_document( isset( $ebcr_ident['cnpj'] ) ? $ebcr_ident['cnpj'] : '' );
				$ebcr_rows[ __( 'Representantes', 'eb-credito-rural' ) ] = array_map(
					static function ( $r ) {
						return $r['nome'] . ' (' . Helpers::format_document( $r['cpf'] ) . ')';
					},
					isset( $ebcr_ident['representantes'] ) ? (array) $ebcr_ident['representantes'] : array()
				);
			} else {
				$ebcr_rows[ __( 'Nome', 'eb-credito-rural' ) ]           = isset( $ebcr_ident['nome'] ) ? $ebcr_ident['nome'] : '';
				$ebcr_rows[ __( 'CPF', 'eb-credito-rural' ) ]            = Helpers::format_document( isset( $ebcr_ident['cpf'] ) ? $ebcr_ident['cpf'] : '' );
				$ebcr_rows[ __( 'RG', 'eb-credito-rural' ) ]             = trim( ( isset( $ebcr_ident['rg'] ) ? $ebcr_ident['rg'] : '' ) . ' ' . ( isset( $ebcr_ident['rg_orgao'] ) ? $ebcr_ident['rg_orgao'] : '' ) );
				$ebcr_rows[ __( 'Nascimento', 'eb-credito-rural' ) ]     = isset( $ebcr_ident['nascimento'] ) ? $ebcr_ident['nascimento'] : '';
				$ebcr_rows[ __( 'Estado civil', 'eb-credito-rural' ) ]   = isset( $ebcr_ident['estado_civil'], Steps::options( 'estado_civil' )[ $ebcr_ident['estado_civil'] ] ) ? Steps::options( 'estado_civil' )[ $ebcr_ident['estado_civil'] ] : '';
				$ebcr_rows[ __( 'Regime de bens', 'eb-credito-rural' ) ] = isset( $ebcr_ident['regime_bens'], Steps::options( 'regime_bens' )[ $ebcr_ident['regime_bens'] ] ) ? Steps::options( 'regime_bens' )[ $ebcr_ident['regime_bens'] ] : '';
				$ebcr_rows[ __( 'Cônjuge', 'eb-credito-rural' ) ]        = ( isset( $ebcr_ident['conjuge_nome'] ) ? $ebcr_ident['conjuge_nome'] : '' ) . ( ! empty( $ebcr_ident['conjuge_cpf'] ) ? ' (' . Helpers::format_document( $ebcr_ident['conjuge_cpf'] ) . ')' : '' );
			}
			$ebcr_rows[ __( 'Inscrição estadual', 'eb-credito-rural' ) ]  = isset( $ebcr_ident['inscricao_estadual'] ) ? $ebcr_ident['inscricao_estadual'] : '';
			$ebcr_rows[ __( 'CAF', 'eb-credito-rural' ) ]                 = isset( $ebcr_ident['caf'] ) ? $ebcr_ident['caf'] : '';
			$ebcr_rows[ __( 'Endereço', 'eb-credito-rural' ) ]            = trim( ( isset( $ebcr_ident['logradouro'] ) ? $ebcr_ident['logradouro'] : '' ) . ', ' . ( isset( $ebcr_ident['numero'] ) ? $ebcr_ident['numero'] : '' ) . ' ' . ( isset( $ebcr_ident['complemento'] ) ? $ebcr_ident['complemento'] : '' ) . ' — ' . ( isset( $ebcr_ident['bairro'] ) ? $ebcr_ident['bairro'] : '' ) . ' — ' . ( isset( $ebcr_ident['cidade'] ) ? $ebcr_ident['cidade'] : '' ) . '/' . ( isset( $ebcr_ident['uf'] ) ? $ebcr_ident['uf'] : '' ) . ' — CEP ' . ( isset( $ebcr_ident['cep'] ) ? $ebcr_ident['cep'] : '' ) );
			$ebcr_rows[ __( 'Telefone / WhatsApp', 'eb-credito-rural' ) ] = ( isset( $ebcr_ident['telefone'] ) ? $ebcr_ident['telefone'] : '' ) . ' / ' . ( isset( $ebcr_ident['whatsapp'] ) ? $ebcr_ident['whatsapp'] : '' );
			$ebcr_rows[ __( 'PEP', 'eb-credito-rural' ) ]                 = $ebcr_yn( isset( $ebcr_ident['pep'] ) ? $ebcr_ident['pep'] : '' ) . ( ! empty( $ebcr_ident['pep_detalhes'] ) ? ' — ' . $ebcr_ident['pep_detalhes'] : '' );
			$ebcr_kv( $ebcr_rows );
			?>
		</section>

		<?php echo $ebcr_panel( 'imoveis' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado na closure. ?>
			<?php if ( empty( $saved['imoveis']['imoveis'] ) ) : ?>
				<p class="ebcr-muted"><?php esc_html_e( 'Nenhum imóvel informado.', 'eb-credito-rural' ); ?></p>
			<?php else : ?>
				<?php foreach ( $saved['imoveis']['imoveis'] as $ebcr_p ) : ?>
				<h4><?php echo esc_html( $ebcr_p['name'] ); ?> <span class="ebcr-muted ebcr-small">#<?php echo (int) $ebcr_p['id']; ?></span></h4>
					<?php
					$ebcr_kv(
						array(
							__( 'Município/UF', 'eb-credito-rural' ) => $ebcr_p['city'] . '/' . $ebcr_p['uf'],
							__( 'Matrícula / cartório', 'eb-credito-rural' ) => $ebcr_p['registration_number'] . ' — ' . $ebcr_p['registry_office'],
							__( 'Área total / útil (ha)', 'eb-credito-rural' ) => $ebcr_p['total_area'] . ' / ' . $ebcr_p['usable_area'],
							__( 'CAR', 'eb-credito-rural' ) => $ebcr_p['car_code'],
							__( 'CCIR / NIRF / SIGEF', 'eb-credito-rural' ) => $ebcr_p['ccir'] . ' / ' . $ebcr_p['nirf'] . ' / ' . $ebcr_p['sigef'],
							__( 'Condição', 'eb-credito-rural' ) => isset( Steps::options( 'tenure' )[ $ebcr_p['tenure'] ] ) ? Steps::options( 'tenure' )[ $ebcr_p['tenure'] ] : $ebcr_p['tenure'],
							__( 'Término do arrendamento', 'eb-credito-rural' ) => $ebcr_p['lease_end'],
						)
					);
					?>
					<?php if ( $ebcr_p['lease_end'] && $s['term_months'] && strtotime( $ebcr_p['lease_end'] ) < strtotime( '+' . (int) $s['term_months'] . ' months' ) ) : ?>
					<p class="ebcr-warning"><?php esc_html_e( 'Atenção: o arrendamento termina antes do prazo solicitado.', 'eb-credito-rural' ); ?></p>
				<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</section>

		<?php echo $ebcr_panel( 'producao' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado na closure. ?>
			<?php
			$ebcr_acts = Steps::options( 'activities' );
			$ebcr_kv(
				array(
					__( 'Atividades', 'eb-credito-rural' ) => array_map(
						static function ( $a ) use ( $ebcr_acts ) {
							return isset( $ebcr_acts[ $a ] ) ? $ebcr_acts[ $a ] : $a;
						},
						isset( $ebcr_prod['atividades'] ) ? (array) $ebcr_prod['atividades'] : array()
					),
					__( 'Outra atividade', 'eb-credito-rural' ) => isset( $ebcr_prod['atividade_outra'] ) ? $ebcr_prod['atividade_outra'] : '',
					__( 'Área por cultura', 'eb-credito-rural' ) => array_map(
						static function ( $r ) {
							return $r['cultura'] . ': ' . $r['area_ha'] . ' ha';
						},
						isset( $ebcr_prod['area_cultura'] ) ? (array) $ebcr_prod['area_cultura'] : array()
					),
					__( 'Produtividade', 'eb-credito-rural' ) => array_map(
						static function ( $r ) {
							return $r['safra'] . ' ' . $r['cultura'] . ': ' . $r['valor'] . ' ' . $r['unidade'];
						},
						isset( $ebcr_prod['produtividade'] ) ? (array) $ebcr_prod['produtividade'] : array()
					),
					__( 'Plano da safra', 'eb-credito-rural' ) => isset( $ebcr_prod['plano_safra'] ) ? $ebcr_prod['plano_safra'] : '',
					__( 'Orçamento de custeio', 'eb-credito-rural' ) => Helpers::money( isset( $ebcr_prod['orcamento_custeio'] ) ? $ebcr_prod['orcamento_custeio'] : null ),
					__( 'Compradores', 'eb-credito-rural' ) => isset( $ebcr_prod['compradores'] ) ? $ebcr_prod['compradores'] : '',
					__( 'Contratos de venda/barter', 'eb-credito-rural' ) => $ebcr_yn( isset( $ebcr_prod['contratos_venda'] ) ? $ebcr_prod['contratos_venda'] : '' ) . ' ' . ( isset( $ebcr_prod['contratos_detalhes'] ) ? $ebcr_prod['contratos_detalhes'] : '' ),
					__( 'Rebanho', 'eb-credito-rural' )    => array_map(
						static function ( $r ) {
							return ( isset( Steps::options( 'rebanho_categorias' )[ $r['categoria'] ] ) ? Steps::options( 'rebanho_categorias' )[ $r['categoria'] ] : $r['categoria'] ) . ': ' . $r['quantidade'];
						},
						isset( $ebcr_prod['rebanho'] ) ? (array) $ebcr_prod['rebanho'] : array()
					),
					__( 'Seguro rural', 'eb-credito-rural' ) => $ebcr_yn( isset( $ebcr_prod['seguro_rural'] ) ? $ebcr_prod['seguro_rural'] : '' ) . ' ' . ( isset( $ebcr_prod['seguro_tipo'] ) ? $ebcr_prod['seguro_tipo'] : '' ),
					__( 'Irrigação', 'eb-credito-rural' )  => $ebcr_yn( isset( $ebcr_prod['irrigacao'] ) ? $ebcr_prod['irrigacao'] : '' ),
					__( 'Maquinário', 'eb-credito-rural' ) => isset( $ebcr_prod['maquinario'] ) ? $ebcr_prod['maquinario'] : '',
					__( 'Licença ambiental / outorga', 'eb-credito-rural' ) => $ebcr_yn( isset( $ebcr_prod['licenca_ambiental'] ) ? $ebcr_prod['licenca_ambiental'] : '' ),
				)
			);
			?>
		</section>

		<?php echo $ebcr_panel( 'financeiro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado na closure. ?>
			<?php
			$ebcr_kv(
				array(
					__( 'Receita (últimos 3 anos)', 'eb-credito-rural' ) => Helpers::money( isset( $ebcr_fin['receita_ano1'] ) ? $ebcr_fin['receita_ano1'] : null ) . ' · ' . Helpers::money( isset( $ebcr_fin['receita_ano2'] ) ? $ebcr_fin['receita_ano2'] : null ) . ' · ' . Helpers::money( isset( $ebcr_fin['receita_ano3'] ) ? $ebcr_fin['receita_ano3'] : null ),
					__( 'Dívidas', 'eb-credito-rural' )    => array_map(
						static function ( $d ) {
							return $d['credor'] . ': ' . __( 'saldo', 'eb-credito-rural' ) . ' ' . Helpers::money( $d['saldo'] ) . ', ' . __( 'parcela', 'eb-credito-rural' ) . ' ' . Helpers::money( $d['parcela'] ) . ' ' . $d['periodicidade'] . ', ' . __( 'venc.', 'eb-credito-rural' ) . ' ' . $d['vencimento'] . ( $d['garantia'] ? ' (' . $d['garantia'] . ')' : '' );
						},
						isset( $ebcr_fin['dividas'] ) ? (array) $ebcr_fin['dividas'] : array()
					),
					__( 'Valor solicitado', 'eb-credito-rural' ) => Helpers::money( isset( $ebcr_fin['valor_solicitado'] ) ? $ebcr_fin['valor_solicitado'] : null ),
					__( 'Finalidade', 'eb-credito-rural' ) => isset( $ebcr_fin['finalidade'], Steps::options( 'purposes' )[ $ebcr_fin['finalidade'] ] ) ? Steps::options( 'purposes' )[ $ebcr_fin['finalidade'] ] : '',
					__( 'Prazo', 'eb-credito-rural' )      => isset( $ebcr_fin['prazo_meses'] ) ? $ebcr_fin['prazo_meses'] . ' ' . __( 'meses', 'eb-credito-rural' ) : '',
					__( 'Época de pagamento', 'eb-credito-rural' ) => ( isset( $ebcr_fin['epoca_pagamento'], Steps::options( 'epoca_pagamento' )[ $ebcr_fin['epoca_pagamento'] ] ) ? Steps::options( 'epoca_pagamento' )[ $ebcr_fin['epoca_pagamento'] ] : '' ) . ' ' . ( isset( $ebcr_fin['epoca_detalhes'] ) ? $ebcr_fin['epoca_detalhes'] : '' ),
				)
			);
			?>
		</section>

		<?php echo $ebcr_panel( 'garantias' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado na closure. ?>
			<?php if ( empty( $saved['garantias']['garantias'] ) ) : ?>
				<p class="ebcr-muted"><?php esc_html_e( 'Nenhuma garantia informada.', 'eb-credito-rural' ); ?></p>
			<?php else : ?>
				<?php foreach ( $saved['garantias']['garantias'] as $ebcr_g ) : ?>
				<h4><?php echo esc_html( isset( Steps::options( 'guarantee_types' )[ $ebcr_g['type'] ] ) ? Steps::options( 'guarantee_types' )[ $ebcr_g['type'] ] : $ebcr_g['type'] ); ?></h4>
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
			<?php endif; ?>
		</section>

		<?php echo $ebcr_panel( 'documentos' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado na closure. ?>
			<h4><?php esc_html_e( 'Checklist (matriz)', 'eb-credito-rural' ); ?></h4>
			<ul class="ebcr-checklist">
			<?php foreach ( $slots['slots'] as $ebcr_slot ) : ?>
				<li class="<?php echo $ebcr_slot['satisfied'] ? 'is-done' : ( $ebcr_slot['required'] ? 'is-missing' : '' ); ?>"><?php echo esc_html( $ebcr_slot['label'] . ( $ebcr_slot['ref_label'] ? ' — ' . $ebcr_slot['ref_label'] : '' ) ); ?> <span class="ebcr-muted ebcr-small">(<?php echo esc_html( isset( DocumentMatrix::levels()[ $ebcr_slot['level'] ] ) ? DocumentMatrix::levels()[ $ebcr_slot['level'] ] : $ebcr_slot['level'] ); ?>)</span></li>
			<?php endforeach; ?>
			</ul>
			<h4><?php esc_html_e( 'Arquivos enviados', 'eb-credito-rural' ); ?></h4>
			<?php if ( ! $documents ) : ?>
				<p class="ebcr-muted"><?php esc_html_e( 'Nenhum arquivo.', 'eb-credito-rural' ); ?></p>
			<?php else : ?>
			<ul class="ebcr-doclist ebcr-tdocs">
				<?php foreach ( $documents as $ebcr_d ) : ?>
				<li>
					<div class="ebcr-tdoc-info">
						<strong><?php echo esc_html( DocumentMatrix::label( $ebcr_d['doc_type'] ) ); ?></strong>
						<?php if ( $ebcr_d['ref_key'] ) : ?>
							<span class="ebcr-muted ebcr-small"><?php echo esc_html( $ebcr_d['ref_key'] ); ?></span>
						<?php endif; ?>
						<br><a href="<?php echo esc_url( DownloadController::url( $ebcr_d ) ); ?>"><?php echo esc_html( $ebcr_d['original_name'] ); ?></a> <span class="ebcr-muted ebcr-small">(<?php echo esc_html( Helpers::size( $ebcr_d['size'] ) ); ?>)</span>
						<?php if ( in_array( $ebcr_d['mime'], array( 'application/pdf', 'image/jpeg', 'image/png' ), true ) ) : ?>
							· <a href="<?php echo esc_url( DownloadController::url( $ebcr_d, true ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'visualizar', 'eb-credito-rural' ); ?></a>
						<?php endif; ?>
						<br><span class="ebcr-muted ebcr-small"><?php echo esc_html( Helpers::date( $ebcr_d['uploaded_at'] ) ); ?> · SHA-256 <?php echo esc_html( substr( (string) $ebcr_d['sha256'], 0, 12 ) ); ?>…<?php echo $ebcr_d['encrypted'] ? ' · 🔒' : ''; ?></span>
						<?php if ( $ebcr_d['expires_at'] ) : ?>
							<br><span class="ebcr-small <?php echo strtotime( $ebcr_d['expires_at'] ) < time() ? 'ebcr-bad' : 'ebcr-muted'; ?>"><?php /* translators: %s: data */ printf( esc_html__( 'válido até %s', 'eb-credito-rural' ), esc_html( wp_date( get_option( 'date_format' ), strtotime( $ebcr_d['expires_at'] ) ) ) ); ?><?php echo strtotime( $ebcr_d['expires_at'] ) < time() ? ' — ' . esc_html__( 'vencida', 'eb-credito-rural' ) : ''; ?></span>
						<?php endif; ?>
						<?php echo \EBCR\Esign\Esign::badge_for_document( $ebcr_d ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapado pelo helper. ?>
					</div>
					<div class="ebcr-tdoc-review">
						<span class="ebcr-doc-review ebcr-review--<?php echo esc_attr( $ebcr_d['review_status'] ); ?>"><?php echo esc_html( ucfirst( $ebcr_d['review_status'] ) ); ?></span>
						<?php if ( $ebcr_d['review_note'] ) : ?>
							<br><span class="ebcr-muted ebcr-small"><?php echo esc_html( $ebcr_d['review_note'] ); ?></span>
						<?php endif; ?>
						<?php if ( $can_edit ) : ?>
						<form method="post" action="<?php echo $ebcr_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>" class="ebcr-treview">
							<input type="hidden" name="action" value="ebcr_team_review_document"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>"><input type="hidden" name="doc" value="<?php echo esc_attr( $ebcr_d['public_id'] ); ?>">
							<?php echo ebcr_nonce_field( 'team_review' ); ?>
							<label class="ebcr-sr" for="ebcr-rev-<?php echo esc_attr( $ebcr_d['public_id'] ); ?>"><?php esc_html_e( 'Resultado', 'eb-credito-rural' ); ?></label>
							<select id="ebcr-rev-<?php echo esc_attr( $ebcr_d['public_id'] ); ?>" name="result" class="ebcr-input ebcr-input--short"><option value="aceito"><?php esc_html_e( 'Aceitar', 'eb-credito-rural' ); ?></option><option value="recusado"><?php esc_html_e( 'Recusar', 'eb-credito-rural' ); ?></option><option value="pendente"><?php esc_html_e( 'Pendente', 'eb-credito-rural' ); ?></option></select>
							<label class="ebcr-sr" for="ebcr-revn-<?php echo esc_attr( $ebcr_d['public_id'] ); ?>"><?php esc_html_e( 'Motivo', 'eb-credito-rural' ); ?></label>
							<input type="text" id="ebcr-revn-<?php echo esc_attr( $ebcr_d['public_id'] ); ?>" name="note" class="ebcr-input" placeholder="<?php esc_attr_e( 'Motivo (obrigatório ao recusar; o cliente verá)', 'eb-credito-rural' ); ?>">
							<button type="submit" class="ebcr-btn ebcr-btn--small"><?php esc_html_e( 'Salvar', 'eb-credito-rural' ); ?></button>
						</form>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
			</ul>
			<?php endif; ?>
			<?php if ( $requests ) : ?>
			<h4><?php esc_html_e( 'Documentos solicitados pela equipe', 'eb-credito-rural' ); ?></h4>
			<ul class="ebcr-tlist">
				<?php foreach ( $requests as $ebcr_r ) : ?>
				<li><?php echo esc_html( $ebcr_r['label'] ); ?> — <?php echo $ebcr_r['fulfilled_at'] ? '<span class="ebcr-ok">' . esc_html__( 'atendido em', 'eb-credito-rural' ) . ' ' . esc_html( Helpers::date( $ebcr_r['fulfilled_at'] ) ) . '</span>' : '<span class="ebcr-warning">' . esc_html__( 'em aberto desde', 'eb-credito-rural' ) . ' ' . esc_html( Helpers::date( $ebcr_r['requested_at'] ) ) . '</span>'; ?></li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
			<?php foreach ( \EBCR\Esign\Esign::signatures( $s ) as $ebcr_sig ) : ?>
				<?php echo \EBCR\Esign\Esign::evidence_html( $ebcr_sig ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapado pelo helper. ?>
			<?php endforeach; ?>
		</section>

		<?php echo $ebcr_panel( 'conferencia' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado na closure. ?>
			<form method="post" action="<?php echo $ebcr_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>">
				<input type="hidden" name="action" value="ebcr_team_checks"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
				<?php echo ebcr_nonce_field( 'team_checks' ); ?>
				<div class="ebcr-table-wrap"><table class="ebcr-table ebcr-tchecks"><thead><tr><th><?php esc_html_e( 'Item', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Resultado', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Observação / valor de referência', 'eb-credito-rural' ); ?></th><th><?php esc_html_e( 'Conferido por', 'eb-credito-rural' ); ?></th></tr></thead><tbody>
				<?php
				foreach ( $check_items as $ebcr_k => $ebcr_label ) :
					$ebcr_c = isset( $checks[ $ebcr_k ] ) ? $checks[ $ebcr_k ] : array();
					$ebcr_v = isset( $ebcr_c['result'] ) ? $ebcr_c['result'] : 'na';
					?>
					<tr class="ebcr-tcheck ebcr-tcheck--<?php echo esc_attr( $ebcr_v ); ?>">
						<td data-label="<?php esc_attr_e( 'Item', 'eb-credito-rural' ); ?>"><?php echo esc_html( $ebcr_label ); ?></td>
						<td data-label="<?php esc_attr_e( 'Resultado', 'eb-credito-rural' ); ?>"><select name="check[<?php echo esc_attr( $ebcr_k ); ?>][result]" class="ebcr-input" aria-label="<?php echo esc_attr( $ebcr_label ); ?>" <?php disabled( ! $can_edit ); ?>>
							<?php
							foreach ( array(
								'na'        => __( 'N/A', 'eb-credito-rural' ),
								'ok'        => __( 'OK', 'eb-credito-rural' ),
								'alerta'    => __( 'Alerta', 'eb-credito-rural' ),
								'reprovado' => __( 'Reprovado', 'eb-credito-rural' ),
							) as $ebcr_rv => $ebcr_rl ) :
								?>
								<option value="<?php echo esc_attr( $ebcr_rv ); ?>" <?php selected( $ebcr_v, $ebcr_rv ); ?>><?php echo esc_html( $ebcr_rl ); ?></option>
							<?php endforeach; ?>
						</select></td>
						<td data-label="<?php esc_attr_e( 'Observação', 'eb-credito-rural' ); ?>"><input type="text" name="check[<?php echo esc_attr( $ebcr_k ); ?>][note]" class="ebcr-input" value="<?php echo esc_attr( isset( $ebcr_c['note'] ) ? $ebcr_c['note'] : '' ); ?>" <?php disabled( ! $can_edit ); ?>></td>
						<td data-label="<?php esc_attr_e( 'Conferido por', 'eb-credito-rural' ); ?>" class="ebcr-small ebcr-muted"><?php echo ! empty( $ebcr_c['checked_by'] ) ? esc_html( Helpers::user_name( (int) $ebcr_c['checked_by'] ) . ' · ' . Helpers::date( $ebcr_c['checked_at'] ) ) : '—'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table></div>
				<?php if ( $can_edit ) : ?>
					<p><button type="submit" class="ebcr-btn ebcr-btn--primary"><?php esc_html_e( 'Salvar checklist', 'eb-credito-rural' ); ?></button></p>
				<?php endif; ?>
			</form>
		</section>

		<?php echo $ebcr_panel( 'historico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado na closure. ?>
			<ol class="ebcr-timeline">
			<?php foreach ( $history as $ebcr_h ) : ?>
				<li><span class="ebcr-timeline-dot" style="--ebcr-badge:<?php echo esc_attr( Status::color( $ebcr_h['to_status'] ) ); ?>"></span><div><strong><?php echo esc_html( Status::label( $ebcr_h['to_status'] ) ); ?></strong> <span class="ebcr-muted ebcr-small"><?php echo esc_html( Helpers::date( $ebcr_h['created_at'] ) . ' · ' . Helpers::user_name( (int) $ebcr_h['changed_by'] ) ); ?></span>
				<?php if ( $ebcr_h['comment_internal'] ) : ?>
					<p class="ebcr-small"><em><?php esc_html_e( 'Interno:', 'eb-credito-rural' ); ?></em> <?php echo esc_html( $ebcr_h['comment_internal'] ); ?></p>
				<?php endif; ?>
				<?php if ( $ebcr_h['comment_client'] ) : ?>
					<p class="ebcr-small"><em><?php esc_html_e( 'Ao cliente:', 'eb-credito-rural' ); ?></em> <?php echo esc_html( $ebcr_h['comment_client'] ); ?></p>
				<?php endif; ?>
				</div></li>
			<?php endforeach; ?>
			<?php if ( ! $history ) : ?>
				<li class="ebcr-muted"><?php esc_html_e( 'Sem histórico.', 'eb-credito-rural' ); ?></li>
			<?php endif; ?>
			</ol>
		</section>

		<?php echo $ebcr_panel( 'mensagens' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado na closure. ?>
			<div class="ebcr-thread">
			<?php foreach ( $messages as $ebcr_m ) : ?>
				<div class="ebcr-msg ebcr-msg--<?php echo esc_attr( $ebcr_m['visibility'] ); ?>"><div class="ebcr-msg-meta"><strong><?php echo esc_html( Helpers::user_name( (int) $ebcr_m['author_id'] ) ); ?></strong> · <?php echo esc_html( Helpers::date( $ebcr_m['created_at'] ) ); ?> · <?php echo 'interno' === $ebcr_m['visibility'] ? esc_html__( 'nota interna', 'eb-credito-rural' ) : esc_html__( 'visível ao cliente', 'eb-credito-rural' ); ?></div><p><?php echo nl2br( esc_html( $ebcr_m['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html + nl2br. ?></p></div>
			<?php endforeach; ?>
			<?php if ( ! $messages ) : ?>
				<p class="ebcr-muted"><?php esc_html_e( 'Nenhuma mensagem.', 'eb-credito-rural' ); ?></p>
			<?php endif; ?>
			</div>
			<?php if ( $can_edit ) : ?>
			<form method="post" action="<?php echo $ebcr_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>" class="ebcr-form">
				<input type="hidden" name="action" value="ebcr_team_message"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
				<?php echo ebcr_nonce_field( 'team_message' ); ?>
				<?php
				echo ebcr_textarea(
					'body',
					__( 'Nova mensagem', 'eb-credito-rural' ),
					'',
					array(
						'required'  => true,
						'rows'      => 3,
						'maxlength' => 5000,
					)
				);
				?>
				<?php
				echo ebcr_radios(
					'visibility',
					__( 'Visibilidade', 'eb-credito-rural' ),
					array(
						'interno' => __( 'Nota interna (só a equipe vê)', 'eb-credito-rural' ),
						'cliente' => __( 'Enviar ao cliente (notifica por e-mail)', 'eb-credito-rural' ),
					),
					'interno'
				);
				?>
				<button type="submit" class="ebcr-btn ebcr-btn--primary"><?php esc_html_e( 'Registrar', 'eb-credito-rural' ); ?></button>
			</form>
			<?php endif; ?>
		</section>
	</div>

	<aside class="ebcr-tdetail-side">
		<?php if ( $can_edit ) : ?>
		<div class="ebcr-card">
			<h3><?php esc_html_e( 'Alterar status', 'eb-credito-rural' ); ?></h3>
			<?php if ( ! $transitions ) : ?>
				<p class="ebcr-muted"><?php esc_html_e( 'Nenhuma transição disponível para o seu perfil neste status.', 'eb-credito-rural' ); ?></p>
			<?php else : ?>
			<form method="post" action="<?php echo $ebcr_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>" class="ebcr-form" data-status-form>
				<input type="hidden" name="action" value="ebcr_team_status"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
				<?php echo ebcr_nonce_field( 'team_status' ); ?>
				<div class="ebcr-field"><label for="ebcr-tstatus"><?php esc_html_e( 'Novo status', 'eb-credito-rural' ); ?></label>
					<select id="ebcr-tstatus" name="status" class="ebcr-input" data-status-select>
					<?php foreach ( $transitions as $ebcr_k => $ebcr_def ) : ?>
						<option value="<?php echo esc_attr( $ebcr_k ); ?>" data-requires-comment="<?php echo ! empty( $ebcr_def['requires_internal_comment'] ) ? '1' : '0'; ?>"><?php echo esc_html( $ebcr_def['label'] ); ?></option>
					<?php endforeach; ?>
					</select>
					<span class="ebcr-help" data-requires-comment hidden><?php esc_html_e( 'Esta decisão exige comentário interno (parecer).', 'eb-credito-rural' ); ?></span>
				</div>
				<?php
				echo ebcr_textarea(
					'comment_internal',
					__( 'Comentário interno', 'eb-credito-rural' ),
					'',
					array(
						'rows'                  => 3,
						'data-comment-internal' => '1',
					)
				);
				?>
				<?php
				echo ebcr_textarea(
					'comment_client',
					__( 'Comentário ao cliente', 'eb-credito-rural' ),
					'',
					array( 'rows' => 3 ),
					'',
					__( 'Opcional; senão usa o texto padrão do status.', 'eb-credito-rural' )
				);
				?>
				<button type="submit" class="ebcr-btn ebcr-btn--primary ebcr-btn--block" data-confirm><?php esc_html_e( 'Aplicar', 'eb-credito-rural' ); ?></button>
			</form>
			<?php endif; ?>
		</div>
		<div class="ebcr-card">
			<h3><?php esc_html_e( 'Analista responsável', 'eb-credito-rural' ); ?></h3>
			<form method="post" action="<?php echo $ebcr_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>" class="ebcr-form">
				<input type="hidden" name="action" value="ebcr_team_assign"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
				<?php echo ebcr_nonce_field( 'team_assign' ); ?>
				<div class="ebcr-field"><label for="ebcr-tanalyst" class="ebcr-sr"><?php esc_html_e( 'Analista', 'eb-credito-rural' ); ?></label>
					<select id="ebcr-tanalyst" name="analyst_id" class="ebcr-input"><option value="0"><?php esc_html_e( '— Nenhum —', 'eb-credito-rural' ); ?></option>
					<?php foreach ( $analysts as $ebcr_a ) : ?>
						<option value="<?php echo (int) $ebcr_a->ID; ?>" <?php selected( (int) $s['assigned_to'], (int) $ebcr_a->ID ); ?>><?php echo esc_html( $ebcr_a->display_name ); ?></option>
					<?php endforeach; ?>
					</select>
				</div>
				<button type="submit" class="ebcr-btn ebcr-btn--block"><?php esc_html_e( 'Atribuir', 'eb-credito-rural' ); ?></button>
			</form>
		</div>
		<div class="ebcr-card">
			<h3><?php esc_html_e( 'Fundo / carteira', 'eb-credito-rural' ); ?></h3>
			<form method="post" action="<?php echo $ebcr_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>" class="ebcr-form">
				<input type="hidden" name="action" value="ebcr_team_fund"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
				<?php echo ebcr_nonce_field( 'team_fund' ); ?>
				<div class="ebcr-field"><label for="ebcr-tfund" class="ebcr-sr"><?php esc_html_e( 'Carteira', 'eb-credito-rural' ); ?></label>
					<select id="ebcr-tfund" name="fund" class="ebcr-input"><option value=""><?php esc_html_e( '— Sem carteira —', 'eb-credito-rural' ); ?></option>
					<?php foreach ( $funds as $ebcr_fk => $ebcr_fl ) : ?>
						<option value="<?php echo esc_attr( $ebcr_fk ); ?>" <?php selected( (string) $s['fund'], $ebcr_fk ); ?>><?php echo esc_html( $ebcr_fl ); ?></option>
					<?php endforeach; ?>
					</select>
				</div>
				<button type="submit" class="ebcr-btn ebcr-btn--block"><?php esc_html_e( 'Salvar carteira', 'eb-credito-rural' ); ?></button>
			</form>
		</div>
		<div class="ebcr-card">
			<h3><?php esc_html_e( 'Solicitar documento', 'eb-credito-rural' ); ?></h3>
			<form method="post" action="<?php echo $ebcr_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>" class="ebcr-form">
				<input type="hidden" name="action" value="ebcr_team_request_document"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
				<?php echo ebcr_nonce_field( 'team_request' ); ?>
				<div class="ebcr-field"><label for="ebcr-tdoctype"><?php esc_html_e( 'Tipo', 'eb-credito-rural' ); ?></label>
					<select id="ebcr-tdoctype" name="doc_type" class="ebcr-input">
					<?php foreach ( $matrix as $ebcr_k => $ebcr_m ) : ?>
						<option value="<?php echo esc_attr( $ebcr_k ); ?>"><?php echo esc_html( $ebcr_m['label'] ); ?></option>
					<?php endforeach; ?>
					</select>
				</div>
				<?php echo ebcr_input( 'label', __( 'Rótulo exibido ao cliente', 'eb-credito-rural' ), '', array( 'maxlength' => 190 ), '', __( 'Opcional.', 'eb-credito-rural' ) ); ?>
				<?php echo ebcr_textarea( 'note', __( 'Instruções ao cliente', 'eb-credito-rural' ), '', array( 'rows' => 2 ) ); ?>
				<label class="ebcr-check" for="ebcr-tsetstatus"><input type="checkbox" id="ebcr-tsetstatus" name="set_status" value="1" checked> <?php esc_html_e( 'Mudar status para "Pendência documental"', 'eb-credito-rural' ); ?></label>
				<button type="submit" class="ebcr-btn ebcr-btn--block"><?php esc_html_e( 'Solicitar', 'eb-credito-rural' ); ?></button>
			</form>
		</div>
		<div class="ebcr-card">
			<h3><?php esc_html_e( 'Enviar mensagem', 'eb-credito-rural' ); ?></h3>
			<form method="post" action="<?php echo $ebcr_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>" class="ebcr-form">
				<input type="hidden" name="action" value="ebcr_team_message"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
				<?php echo ebcr_nonce_field( 'team_message' ); ?>
				<div class="ebcr-field"><label for="ebcr-tmsg-body"><?php esc_html_e( 'Mensagem', 'eb-credito-rural' ); ?> <span class="ebcr-req" aria-hidden="true">*</span></label><textarea id="ebcr-tmsg-body" name="body" class="ebcr-input" rows="3" maxlength="5000" required></textarea></div>
				<fieldset class="ebcr-field ebcr-radios"><legend><?php esc_html_e( 'Visibilidade', 'eb-credito-rural' ); ?></legend>
					<label class="ebcr-radio" for="ebcr-tmsg-vis-interno"><input type="radio" id="ebcr-tmsg-vis-interno" name="visibility" value="interno" checked> <?php esc_html_e( 'Nota interna', 'eb-credito-rural' ); ?></label>
					<label class="ebcr-radio" for="ebcr-tmsg-vis-cliente"><input type="radio" id="ebcr-tmsg-vis-cliente" name="visibility" value="cliente"> <?php esc_html_e( 'Ao cliente (notifica por e-mail)', 'eb-credito-rural' ); ?></label>
				</fieldset>
				<button type="submit" class="ebcr-btn ebcr-btn--block"><?php esc_html_e( 'Enviar', 'eb-credito-rural' ); ?></button>
			</form>
		</div>
		<?php else : ?>
		<div class="ebcr-card"><p class="ebcr-muted"><?php esc_html_e( 'Seu perfil pode consultar esta solicitação, mas não operá-la.', 'eb-credito-rural' ); ?></p></div>
		<?php endif; ?>
		<?php if ( $can_export ) : ?>
		<div class="ebcr-card">
			<form method="post" action="<?php echo $ebcr_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- já escapado. ?>">
				<input type="hidden" name="action" value="ebcr_team_export_one"><input type="hidden" name="id" value="<?php echo esc_attr( $s['public_id'] ); ?>">
				<?php echo ebcr_nonce_field( 'team_export' ); ?>
				<button type="submit" class="ebcr-btn ebcr-btn--ghost ebcr-btn--block"><?php esc_html_e( 'Exportar solicitação (JSON)', 'eb-credito-rural' ); ?></button>
			</form>
		</div>
		<?php endif; ?>
	</aside>
</div>
