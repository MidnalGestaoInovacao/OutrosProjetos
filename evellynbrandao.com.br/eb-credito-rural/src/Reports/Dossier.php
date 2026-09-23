<?php
/**
 * Dossiê em PDF para o comitê: capa, tomador, imóveis, produção, financeiro (com indicadores), garantias, documentos,
 * checklist de conferência, histórico, mensagens internas e aceites. Gerado com o Writer em PHP puro.
 *
 * @package EBCR
 */

namespace EBCR\Reports;

use EBCR\Database\CheckRepository;
use EBCR\Database\ConsentRepository;
use EBCR\Database\DocumentRepository;
use EBCR\Database\DocumentRequestRepository;
use EBCR\Database\GuaranteeRepository;
use EBCR\Database\MessageRepository;
use EBCR\Database\PropertyRepository;
use EBCR\Database\StatusHistoryRepository;
use EBCR\Database\SubmissionDataRepository;
use EBCR\Domain\Consent;
use EBCR\Domain\Status;
use EBCR\Forms\DocumentMatrix;
use EBCR\Forms\Steps;
use EBCR\Forms\Wizard;
use EBCR\Pdf\Writer;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Monta e entrega o dossiê. Os documentos (CPF/CNPJ) aparecem completos: o dossiê é de uso interno da equipe.
 */
final class Dossier {

	/**
	 * Travessão usado como "vazio".
	 *
	 * @var string
	 */
	const DASH = "\xE2\x80\x94";

	/**
	 * Gera os bytes do PDF de uma solicitação.
	 *
	 * @param array $submission Linha da solicitação.
	 * @return string
	 */
	public static function build( array $submission ) {
		$id         = (int) $submission['id'];
		$data       = ( new SubmissionDataRepository() )->all( $id );
		$ident      = self::section( $data, 'identificacao' );
		$prod       = self::section( $data, 'producao' );
		$fin        = self::section( $data, 'financeiro' );
		$gar_meta   = self::section( $data, 'garantias' );
		$properties = ( new PropertyRepository() )->for_submission( $id );
		$guarantees = ( new GuaranteeRepository() )->for_submission( $id );
		$documents  = ( new DocumentRepository() )->for_submission( $id );
		$requests   = ( new DocumentRequestRepository() )->for_submission( $id );
		$checks     = ( new CheckRepository() )->for_submission( $id );
		$history    = ( new StatusHistoryRepository() )->for_submission( $id );
		$consents   = ( new ConsentRepository() )->for_submission( $id );
		$messages   = array_values(
			array_filter(
				( new MessageRepository() )->for_submission( $id ),
				static function ( $m ) {
					return 'interno' === $m['visibility'];
				}
			)
		);
		$slots      = ( new Wizard() )->document_slots( $submission );
		$user       = get_userdata( (int) $submission['user_id'] );
		$client     = self::client_name( $ident, $user );
		$protocol   = ! empty( $submission['protocol'] ) ? (string) $submission['protocol'] : __( 'Rascunho', 'eb-credito-rural' );
		$w          = new Writer( sprintf( /* translators: 1: protocolo; 2: cliente */ __( 'Dossiê de crédito — %1$s — %2$s', 'eb-credito-rural' ), $protocol, $client ) );

		self::cover( $w, $submission, $ident, $fin, $guarantees, $slots, $user, $client, $protocol );
		$w->page_break();
		self::borrower( $w, $ident, $user );
		self::properties( $w, $submission, $properties );
		self::production( $w, $prod );
		self::financial( $w, $submission, $fin );
		self::guarantees( $w, $submission, $guarantees, $properties, $gar_meta );
		self::documents( $w, $documents, $requests, $slots );
		self::checks( $w, $checks );
		self::history( $w, $history );
		self::messages( $w, $messages );
		self::consents( $w, $consents );
		$w->p( __( 'Documento gerado automaticamente pelo plugin EB Crédito Rural a partir dos dados informados pelo solicitante e dos registros da equipe. Não substitui a análise técnica nem a decisão do comitê.', 'eb-credito-rural' ) );
		return $w->output();
	}

	/**
	 * Envia o PDF ao navegador (download) e encerra.
	 *
	 * @param array $submission Linha da solicitação.
	 * @return void
	 */
	public static function download( array $submission ) {
		$pdf = self::build( $submission );
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="dossie-' . sanitize_file_name( (string) $submission['protocol'] ) . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		header( 'X-Content-Type-Options: nosniff' );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binário PDF.
		exit;
	}

	/**
	 * Capa.
	 *
	 * @param Writer         $w          Writer.
	 * @param array          $submission Solicitação.
	 * @param array          $ident      Etapa identificação.
	 * @param array          $fin        Etapa financeiro.
	 * @param array          $guarantees Garantias.
	 * @param array          $slots      Slots de documentos.
	 * @param \WP_User|false $user       Usuário.
	 * @param string         $client     Nome do cliente.
	 * @param string         $protocol   Protocolo.
	 * @return void
	 */
	private static function cover( Writer $w, array $submission, array $ident, array $fin, array $guarantees, array $slots, $user, $client, $protocol ) {
		$purposes = Steps::options( 'purposes' );
		$funds    = Options::pairs( 'funds' );
		$epocas   = Steps::options( 'epoca_pagamento' );
		$purpose  = ! empty( $submission['purpose'] ) ? $submission['purpose'] : ( isset( $fin['finalidade'] ) ? $fin['finalidade'] : '' );
		$amount   = null !== $submission['requested_amount'] && '' !== $submission['requested_amount'] ? $submission['requested_amount'] : ( isset( $fin['valor_solicitado'] ) ? $fin['valor_solicitado'] : null );
		$term     = ! empty( $submission['term_months'] ) ? (int) $submission['term_months'] : ( isset( $fin['prazo_meses'] ) ? (int) $fin['prazo_meses'] : 0 );
		$fund     = isset( $submission['fund'] ) ? (string) $submission['fund'] : '';
		$type     = isset( $ident['person_type'] ) ? $ident['person_type'] : ( ! empty( $submission['person_type'] ) ? $submission['person_type'] : '' );
		$doc      = 'PJ' === $type ? ( isset( $ident['cnpj'] ) ? $ident['cnpj'] : '' ) : ( isset( $ident['cpf'] ) ? $ident['cpf'] : '' );
		$current  = wp_get_current_user();

		$w->h1( __( 'Dossiê de crédito para o comitê', 'eb-credito-rural' ) );
		$w->p( __( 'Uso interno — comitê de crédito. Este documento reúne dados pessoais e financeiros do solicitante; trate-o como confidencial e não o compartilhe fora da equipe autorizada.', 'eb-credito-rural' ) );
		$w->kv(
			array(
				__( 'Protocolo', 'eb-credito-rural' )      => $protocol,
				__( 'Status atual', 'eb-credito-rural' )   => Status::label( $submission['status'] ),
				__( 'Cliente', 'eb-credito-rural' )        => $client . ( $user ? ' <' . $user->user_email . '>' : '' ),
				__( 'Tipo de pessoa', 'eb-credito-rural' ) => 'PJ' === $type ? __( 'Pessoa jurídica', 'eb-credito-rural' ) : ( 'PF' === $type ? __( 'Pessoa física', 'eb-credito-rural' ) : self::DASH ),
				__( 'CPF / CNPJ', 'eb-credito-rural' )     => $doc ? Helpers::format_document( $doc ) : self::DASH,
				__( 'Valor solicitado', 'eb-credito-rural' ) => Helpers::money( $amount ),
				__( 'Finalidade', 'eb-credito-rural' )     => isset( $purposes[ $purpose ] ) ? $purposes[ $purpose ] : $purpose,
				__( 'Prazo', 'eb-credito-rural' )          => $term ? sprintf( /* translators: %d: meses */ __( '%d meses', 'eb-credito-rural' ), $term ) : self::DASH,
				__( 'Época de pagamento', 'eb-credito-rural' ) => isset( $fin['epoca_pagamento'] ) ? ( isset( $epocas[ $fin['epoca_pagamento'] ] ) ? $epocas[ $fin['epoca_pagamento'] ] : $fin['epoca_pagamento'] ) . ( ! empty( $fin['epoca_detalhes'] ) ? ' — ' . $fin['epoca_detalhes'] : '' ) : self::DASH,
				__( 'Analista responsável', 'eb-credito-rural' ) => ! empty( $submission['assigned_to'] ) ? Helpers::user_name( (int) $submission['assigned_to'] ) : __( 'Não atribuído', 'eb-credito-rural' ),
				__( 'Carteira / fundo', 'eb-credito-rural' ) => '' !== $fund ? ( isset( $funds[ $fund ] ) ? $funds[ $fund ] : $fund ) : __( 'Sem carteira', 'eb-credito-rural' ),
				__( 'Enviada em', 'eb-credito-rural' )     => self::dt( $submission['submitted_at'] ),
				__( 'Última atualização', 'eb-credito-rural' ) => self::dt( $submission['updated_at'] ),
				__( 'Documentos obrigatórios', 'eb-credito-rural' ) => (int) $slots['required_done'] . ' / ' . (int) $slots['required_total'],
				__( 'Gerado por', 'eb-credito-rural' )     => $current && $current->ID ? $current->display_name : __( 'Sistema', 'eb-credito-rural' ),
				__( 'Gerado em', 'eb-credito-rural' )      => wp_date( 'd/m/Y H:i' ),
				__( 'Identificador', 'eb-credito-rural' )  => (string) $submission['public_id'],
			)
		);
		if ( ! empty( $submission['anonymized_at'] ) ) {
			$w->p( __( 'Atenção: esta solicitação foi anonimizada pela rotina de retenção/LGPD; dados pessoais e documentos foram removidos.', 'eb-credito-rural' ) );
		}
		$ind = isset( $fin['_indicadores'] ) && is_array( $fin['_indicadores'] ) ? $fin['_indicadores'] : array();
		if ( $ind || $guarantees ) {
			$total = 0.0;
			foreach ( $guarantees as $g ) {
				$total += (float) $g['declared_value'];
			}
			$w->h2( __( 'Resumo dos indicadores', 'eb-credito-rural' ) );
			$w->kv(
				array(
					__( 'Receita bruta média', 'eb-credito-rural' ) => Helpers::money( isset( $ind['receita_media'] ) ? $ind['receita_media'] : null ),
					__( 'Comprometimento da receita', 'eb-credito-rural' ) => self::percent( isset( $ind['comprometimento'] ) ? $ind['comprometimento'] : null ),
					__( 'Dívida / receita', 'eb-credito-rural' ) => self::ratio( isset( $ind['divida_receita'] ) ? $ind['divida_receita'] : null ),
					__( 'Solicitado / receita', 'eb-credito-rural' ) => self::ratio( isset( $ind['solicitado_receita'] ) ? $ind['solicitado_receita'] : null ),
					__( 'Garantias declaradas', 'eb-credito-rural' ) => Helpers::money( $total ),
					__( 'Garantia / valor solicitado', 'eb-credito-rural' ) => self::ratio( (float) $amount > 0 ? round( $total / (float) $amount, 2 ) : null ),
				)
			);
		}
	}

	/**
	 * Seção "Tomador" (etapa identificação).
	 *
	 * @param Writer         $w     Writer.
	 * @param array          $ident Dados.
	 * @param \WP_User|false $user  Usuário.
	 * @return void
	 */
	private static function borrower( Writer $w, array $ident, $user ) {
		$w->h1( __( '1. Tomador', 'eb-credito-rural' ) );
		if ( isset( $ident['_error'] ) ) {
			$w->p( __( 'Dados criptografados e chave indisponível: a etapa de identificação não pôde ser lida.', 'eb-credito-rural' ) );
			return;
		}
		if ( ! $ident ) {
			$w->p( __( 'Etapa de identificação não preenchida.', 'eb-credito-rural' ) );
			return;
		}
		$rows = array();
		$pj   = isset( $ident['person_type'] ) && 'PJ' === $ident['person_type'];
		if ( $pj ) {
			$rows[ __( 'Razão social', 'eb-credito-rural' ) ] = self::v( $ident, 'razao_social' );
			$rows[ __( 'CNPJ', 'eb-credito-rural' ) ]         = Helpers::format_document( self::v( $ident, 'cnpj' ) );
		} else {
			$civil = Steps::options( 'estado_civil' );
			$bens  = Steps::options( 'regime_bens' );
			$rows[ __( 'Nome completo', 'eb-credito-rural' ) ]            = self::v( $ident, 'nome' );
			$rows[ __( 'CPF', 'eb-credito-rural' ) ]                      = Helpers::format_document( self::v( $ident, 'cpf' ) );
			$rows[ __( 'RG / órgão emissor', 'eb-credito-rural' ) ]       = trim( self::v( $ident, 'rg' ) . ' ' . self::v( $ident, 'rg_orgao' ) );
			$rows[ __( 'Data de nascimento', 'eb-credito-rural' ) ]       = self::day( self::v( $ident, 'nascimento' ) );
			$rows[ __( 'Estado civil', 'eb-credito-rural' ) ]             = self::opt( $civil, self::v( $ident, 'estado_civil' ) );
			$rows[ __( 'Regime de bens', 'eb-credito-rural' ) ]           = self::opt( $bens, self::v( $ident, 'regime_bens' ) );
			$rows[ __( 'Cônjuge / companheiro(a)', 'eb-credito-rural' ) ] = trim( self::v( $ident, 'conjuge_nome' ) . ( self::v( $ident, 'conjuge_cpf' ) ? ' (' . Helpers::format_document( self::v( $ident, 'conjuge_cpf' ) ) . ')' : '' ) );
		}
		$rows[ __( 'E-mail de acesso', 'eb-credito-rural' ) ]             = $user ? $user->user_email : '';
		$rows[ __( 'Inscrição estadual', 'eb-credito-rural' ) ]           = self::v( $ident, 'inscricao_estadual' );
		$rows[ __( 'CAF (Pronaf)', 'eb-credito-rural' ) ]                 = self::v( $ident, 'caf' );
		$rows[ __( 'Endereço', 'eb-credito-rural' ) ]                     = self::address( $ident );
		$rows[ __( 'Telefone', 'eb-credito-rural' ) ]                     = self::phone( self::v( $ident, 'telefone' ) );
		$rows[ __( 'WhatsApp', 'eb-credito-rural' ) ]                     = self::phone( self::v( $ident, 'whatsapp' ) );
		$rows[ __( 'Pessoa politicamente exposta', 'eb-credito-rural' ) ] = self::yn( self::v( $ident, 'pep' ) ) . ( self::v( $ident, 'pep_detalhes' ) ? ' — ' . self::v( $ident, 'pep_detalhes' ) : '' );
		$w->kv( $rows );
		if ( $pj ) {
			$reps = array();
			foreach ( isset( $ident['representantes'] ) && is_array( $ident['representantes'] ) ? $ident['representantes'] : array() as $r ) {
				$reps[] = array( self::v( $r, 'nome' ), Helpers::format_document( self::v( $r, 'cpf' ) ) );
			}
			$w->h2( __( 'Representantes legais', 'eb-credito-rural' ) );
			$w->table( array( __( 'Nome', 'eb-credito-rural' ), __( 'CPF', 'eb-credito-rural' ) ), $reps );
		}
	}

	/**
	 * Seção "Imóveis rurais".
	 *
	 * @param Writer $w          Writer.
	 * @param array  $submission Solicitação.
	 * @param array  $properties Imóveis.
	 * @return void
	 */
	private static function properties( Writer $w, array $submission, array $properties ) {
		$w->h1( __( '2. Imóveis rurais', 'eb-credito-rural' ) );
		if ( ! $properties ) {
			$w->p( __( 'Nenhum imóvel informado.', 'eb-credito-rural' ) );
			return;
		}
		$tenures = Steps::options( 'tenure' );
		$total   = 0.0;
		$usable  = 0.0;
		foreach ( array_values( $properties ) as $i => $p ) {
			$total  += (float) $p['total_area'];
			$usable += (float) $p['usable_area'];
			$w->h2( sprintf( /* translators: 1: número; 2: nome do imóvel; 3: ID */ __( 'Imóvel %1$d — %2$s (#%3$d)', 'eb-credito-rural' ), $i + 1, $p['name'], (int) $p['id'] ) );
			$rows = array(
				__( 'Município / UF', 'eb-credito-rural' ) => $p['city'] . '/' . $p['uf'],
				__( 'Matrícula', 'eb-credito-rural' )      => $p['registration_number'],
				__( 'Cartório de registro', 'eb-credito-rural' ) => $p['registry_office'],
				__( 'Área total', 'eb-credito-rural' )     => self::hectares( $p['total_area'] ),
				__( 'Área útil', 'eb-credito-rural' )      => self::hectares( $p['usable_area'] ),
				__( 'Código do CAR', 'eb-credito-rural' )  => $p['car_code'],
				__( 'CCIR', 'eb-credito-rural' )           => $p['ccir'],
				__( 'NIRF / CIB', 'eb-credito-rural' )     => $p['nirf'],
				__( 'Certificação SIGEF', 'eb-credito-rural' ) => $p['sigef'],
				__( 'Condição', 'eb-credito-rural' )       => self::opt( $tenures, $p['tenure'] ),
			);
			if ( 'propria' !== $p['tenure'] || ! empty( $p['lease_end'] ) ) {
				$rows[ __( 'Término do arrendamento/parceria', 'eb-credito-rural' ) ] = self::day( $p['lease_end'] );
				if ( ! empty( $p['lease_end'] ) && ! empty( $submission['term_months'] ) && strtotime( $p['lease_end'] ) < strtotime( '+' . (int) $submission['term_months'] . ' months' ) ) {
					$rows[ __( 'Alerta', 'eb-credito-rural' ) ] = __( 'O arrendamento/parceria termina antes do prazo solicitado.', 'eb-credito-rural' );
				}
			}
			$w->kv( $rows );
		}
		if ( count( $properties ) > 1 ) {
			$w->kv(
				array(
					__( 'Área total dos imóveis', 'eb-credito-rural' ) => self::hectares( $total ),
					__( 'Área útil dos imóveis', 'eb-credito-rural' )  => self::hectares( $usable ),
				)
			);
		}
	}

	/**
	 * Seção "Produção" (etapa producao).
	 *
	 * @param Writer $w    Writer.
	 * @param array  $prod Dados.
	 * @return void
	 */
	private static function production( Writer $w, array $prod ) {
		$w->h1( __( '3. Atividade produtiva', 'eb-credito-rural' ) );
		if ( isset( $prod['_error'] ) ) {
			$w->p( __( 'Dados criptografados e chave indisponível.', 'eb-credito-rural' ) );
			return;
		}
		if ( ! $prod ) {
			$w->p( __( 'Etapa de produção não preenchida.', 'eb-credito-rural' ) );
			return;
		}
		$acts = Steps::options( 'activities' );
		$list = array();
		foreach ( isset( $prod['atividades'] ) ? (array) $prod['atividades'] : array() as $a ) {
			$list[] = self::opt( $acts, $a );
		}
		$w->kv(
			array(
				__( 'Atividades', 'eb-credito-rural' )     => implode( ', ', $list ),
				__( 'Outra atividade', 'eb-credito-rural' ) => self::v( $prod, 'atividade_outra' ),
				__( 'Plano da safra', 'eb-credito-rural' ) => self::v( $prod, 'plano_safra' ),
				__( 'Orçamento de custeio', 'eb-credito-rural' ) => Helpers::money( isset( $prod['orcamento_custeio'] ) ? $prod['orcamento_custeio'] : null ),
				__( 'Principais compradores', 'eb-credito-rural' ) => self::v( $prod, 'compradores' ),
				__( 'Contratos de venda futura / barter', 'eb-credito-rural' ) => self::yn( self::v( $prod, 'contratos_venda' ) ) . ( self::v( $prod, 'contratos_detalhes' ) ? ' — ' . self::v( $prod, 'contratos_detalhes' ) : '' ),
				__( 'Seguro rural', 'eb-credito-rural' )   => self::yn( self::v( $prod, 'seguro_rural' ) ) . ( self::v( $prod, 'seguro_tipo' ) ? ' — ' . self::v( $prod, 'seguro_tipo' ) : '' ),
				__( 'Irrigação', 'eb-credito-rural' )      => self::yn( self::v( $prod, 'irrigacao' ) ),
				__( 'Maquinário principal', 'eb-credito-rural' ) => self::v( $prod, 'maquinario' ),
				__( 'Licenças ambientais / outorga', 'eb-credito-rural' ) => self::yn( self::v( $prod, 'licenca_ambiental' ) ),
			)
		);
		$areas = array();
		foreach ( isset( $prod['area_cultura'] ) && is_array( $prod['area_cultura'] ) ? $prod['area_cultura'] : array() as $r ) {
			$areas[] = array( self::v( $r, 'cultura' ), self::hectares( self::v( $r, 'area_ha' ) ) );
		}
		if ( $areas ) {
			$w->h2( __( 'Área por cultura', 'eb-credito-rural' ) );
			$w->table( array( __( 'Cultura', 'eb-credito-rural' ), __( 'Área plantada', 'eb-credito-rural' ) ), $areas );
		}
		$yields = array();
		foreach ( isset( $prod['produtividade'] ) && is_array( $prod['produtividade'] ) ? $prod['produtividade'] : array() as $r ) {
			$yields[] = array( self::v( $r, 'safra' ), self::v( $r, 'cultura' ), self::number( self::v( $r, 'valor' ) ) . ' ' . self::v( $r, 'unidade' ) );
		}
		if ( $yields ) {
			$w->h2( __( 'Produtividade histórica', 'eb-credito-rural' ) );
			$w->table( array( __( 'Safra', 'eb-credito-rural' ), __( 'Cultura', 'eb-credito-rural' ), __( 'Produtividade', 'eb-credito-rural' ) ), $yields );
		}
		$herd = array();
		$cats = Steps::options( 'rebanho_categorias' );
		$sum  = 0;
		foreach ( isset( $prod['rebanho'] ) && is_array( $prod['rebanho'] ) ? $prod['rebanho'] : array() as $r ) {
			$sum   += (int) self::v( $r, 'quantidade' );
			$herd[] = array( self::opt( $cats, self::v( $r, 'categoria' ) ), self::number( self::v( $r, 'quantidade' ), 0 ) );
		}
		if ( $herd ) {
			$herd[] = array( __( 'Total', 'eb-credito-rural' ), self::number( $sum, 0 ) );
			$w->h2( __( 'Rebanho', 'eb-credito-rural' ) );
			$w->table( array( __( 'Categoria', 'eb-credito-rural' ), __( 'Quantidade', 'eb-credito-rural' ) ), $herd );
		}
	}

	/**
	 * Seção "Financeiro" (etapa financeiro + indicadores).
	 *
	 * @param Writer $w          Writer.
	 * @param array  $submission Solicitação.
	 * @param array  $fin        Dados.
	 * @return void
	 */
	private static function financial( Writer $w, array $submission, array $fin ) {
		$w->h1( __( '4. Situação financeira', 'eb-credito-rural' ) );
		if ( isset( $fin['_error'] ) ) {
			$w->p( __( 'Dados criptografados e chave indisponível.', 'eb-credito-rural' ) );
			return;
		}
		if ( ! $fin ) {
			$w->p( __( 'Etapa financeira não preenchida.', 'eb-credito-rural' ) );
			return;
		}
		$purposes = Steps::options( 'purposes' );
		$epocas   = Steps::options( 'epoca_pagamento' );
		$w->kv(
			array(
				__( 'Receita bruta — último ano', 'eb-credito-rural' ) => Helpers::money( isset( $fin['receita_ano1'] ) ? $fin['receita_ano1'] : null ),
				__( 'Receita bruta — ano anterior', 'eb-credito-rural' ) => Helpers::money( isset( $fin['receita_ano2'] ) ? $fin['receita_ano2'] : null ),
				__( 'Receita bruta — dois anos antes', 'eb-credito-rural' ) => Helpers::money( isset( $fin['receita_ano3'] ) ? $fin['receita_ano3'] : null ),
				__( 'Valor solicitado', 'eb-credito-rural' ) => Helpers::money( isset( $fin['valor_solicitado'] ) ? $fin['valor_solicitado'] : $submission['requested_amount'] ),
				__( 'Finalidade', 'eb-credito-rural' )     => self::opt( $purposes, self::v( $fin, 'finalidade' ) ),
				__( 'Prazo desejado', 'eb-credito-rural' ) => isset( $fin['prazo_meses'] ) && '' !== $fin['prazo_meses'] ? sprintf( /* translators: %d: meses */ __( '%d meses', 'eb-credito-rural' ), (int) $fin['prazo_meses'] ) : '',
				__( 'Época de pagamento', 'eb-credito-rural' ) => self::opt( $epocas, self::v( $fin, 'epoca_pagamento' ) ) . ( self::v( $fin, 'epoca_detalhes' ) ? ' — ' . self::v( $fin, 'epoca_detalhes' ) : '' ),
			)
		);
		$w->h2( __( 'Dívidas declaradas', 'eb-credito-rural' ) );
		$rows  = array();
		$saldo = 0.0;
		$per   = array(
			'mensal'    => __( 'Mensal', 'eb-credito-rural' ),
			'semestral' => __( 'Semestral', 'eb-credito-rural' ),
			'anual'     => __( 'Anual', 'eb-credito-rural' ),
		);
		foreach ( isset( $fin['dividas'] ) && is_array( $fin['dividas'] ) ? $fin['dividas'] : array() as $d ) {
			$saldo += (float) self::v( $d, 'saldo' );
			$rows[] = array(
				self::v( $d, 'credor' ),
				Helpers::money( self::v( $d, 'saldo' ) ),
				Helpers::money( self::v( $d, 'parcela' ) ),
				self::opt( $per, self::v( $d, 'periodicidade' ) ),
				self::day( self::v( $d, 'vencimento' ) ),
				self::v( $d, 'garantia' ),
			);
		}
		if ( $rows ) {
			$rows[] = array( __( 'Total', 'eb-credito-rural' ), Helpers::money( $saldo ), '', '', '', '' );
		}
		$w->table(
			array(
				__( 'Credor', 'eb-credito-rural' ),
				__( 'Saldo devedor', 'eb-credito-rural' ),
				__( 'Parcela', 'eb-credito-rural' ),
				__( 'Periodicidade', 'eb-credito-rural' ),
				__( 'Vencimento final', 'eb-credito-rural' ),
				__( 'Garantia dada', 'eb-credito-rural' ),
			),
			$rows
		);
		$ind = isset( $fin['_indicadores'] ) && is_array( $fin['_indicadores'] ) ? $fin['_indicadores'] : array();
		if ( $ind ) {
			$w->h2( __( 'Indicadores (uso interno)', 'eb-credito-rural' ) );
			$w->kv(
				array(
					__( 'Receita bruta média (até 3 anos)', 'eb-credito-rural' ) => Helpers::money( isset( $ind['receita_media'] ) ? $ind['receita_media'] : null ),
					__( 'Parcelas anuais das dívidas', 'eb-credito-rural' )   => Helpers::money( isset( $ind['parcelas_anuais'] ) ? $ind['parcelas_anuais'] : null ),
					__( 'Saldo total das dívidas', 'eb-credito-rural' )       => Helpers::money( isset( $ind['saldo_dividas'] ) ? $ind['saldo_dividas'] : null ),
					__( 'Comprometimento (parcelas / receita)', 'eb-credito-rural' ) => self::percent( isset( $ind['comprometimento'] ) ? $ind['comprometimento'] : null ),
					__( 'Dívida / receita', 'eb-credito-rural' )              => self::ratio( isset( $ind['divida_receita'] ) ? $ind['divida_receita'] : null ),
					__( 'Solicitado / receita', 'eb-credito-rural' )          => self::ratio( isset( $ind['solicitado_receita'] ) ? $ind['solicitado_receita'] : null ),
				)
			);
		}
	}

	/**
	 * Seção "Garantias".
	 *
	 * @param Writer $w          Writer.
	 * @param array  $submission Solicitação.
	 * @param array  $guarantees Garantias.
	 * @param array  $properties Imóveis (para resolver o vínculo).
	 * @param array  $meta       Dados da etapa 5 (real_guarantee).
	 * @return void
	 */
	private static function guarantees( Writer $w, array $submission, array $guarantees, array $properties, array $meta ) {
		$w->h1( __( '5. Garantias oferecidas', 'eb-credito-rural' ) );
		$types = Steps::options( 'guarantee_types' );
		$names = array();
		foreach ( $properties as $p ) {
			$names[ (int) $p['id'] ] = $p['name'] . ' (#' . (int) $p['id'] . ')';
		}
		$rows  = array();
		$total = 0.0;
		foreach ( $guarantees as $g ) {
			$total += (float) $g['declared_value'];
			$pid    = (int) $g['property_id'];
			$rows[] = array(
				self::opt( $types, $g['type'] ),
				(string) $g['description'],
				$pid ? ( isset( $names[ $pid ] ) ? $names[ $pid ] : '#' . $pid ) : self::DASH,
				Helpers::money( $g['declared_value'] ),
				Helpers::money( $g['appraised_value'] ),
				null !== $g['ltv'] && '' !== $g['ltv'] ? self::percent( $g['ltv'] ) : self::DASH,
				ucfirst( (string) $g['formalization_status'] ),
			);
		}
		$w->table(
			array(
				__( 'Tipo', 'eb-credito-rural' ),
				__( 'Descrição', 'eb-credito-rural' ),
				__( 'Imóvel vinculado', 'eb-credito-rural' ),
				__( 'Valor declarado', 'eb-credito-rural' ),
				__( 'Valor avaliado', 'eb-credito-rural' ),
				__( 'LTV', 'eb-credito-rural' ),
				__( 'Formalização', 'eb-credito-rural' ),
			),
			$rows
		);
		if ( $guarantees ) {
			$amount = (float) $submission['requested_amount'];
			$w->kv(
				array(
					__( 'Total declarado em garantias', 'eb-credito-rural' ) => Helpers::money( $total ),
					__( 'Valor solicitado', 'eb-credito-rural' )            => Helpers::money( $submission['requested_amount'] ),
					__( 'Garantia / valor solicitado', 'eb-credito-rural' ) => self::ratio( $amount > 0 ? round( $total / $amount, 2 ) : null ),
					__( 'Garantia real oferecida', 'eb-credito-rural' )     => ! empty( $meta['real_guarantee'] ) ? __( 'Sim', 'eb-credito-rural' ) : __( 'Não', 'eb-credito-rural' ),
				)
			);
		}
	}

	/**
	 * Seção "Documentos" (matriz, arquivos enviados e pedidos da equipe). Não expõe caminhos internos.
	 *
	 * @param Writer $w         Writer.
	 * @param array  $documents Arquivos.
	 * @param array  $requests  Pedidos.
	 * @param array  $slots     Slots da matriz.
	 * @return void
	 */
	private static function documents( Writer $w, array $documents, array $requests, array $slots ) {
		$w->h1( __( '6. Documentos', 'eb-credito-rural' ) );
		$levels = DocumentMatrix::levels();
		$rows   = array();
		foreach ( $slots['slots'] as $slot ) {
			$rows[] = array(
				$slot['label'],
				$slot['ref_label'] ? $slot['ref_label'] : self::DASH,
				self::opt( $levels, $slot['level'] ),
				$slot['satisfied'] ? __( 'Enviado', 'eb-credito-rural' ) : ( $slot['required'] ? __( 'FALTANDO', 'eb-credito-rural' ) : __( 'Não enviado', 'eb-credito-rural' ) ),
			);
		}
		$w->h2( __( 'Checklist da matriz de documentos', 'eb-credito-rural' ) );
		$w->table( array( __( 'Documento', 'eb-credito-rural' ), __( 'Referência', 'eb-credito-rural' ), __( 'Obrigatoriedade', 'eb-credito-rural' ), __( 'Situação', 'eb-credito-rural' ) ), $rows );

		$review = array(
			'pendente' => __( 'Pendente', 'eb-credito-rural' ),
			'aceito'   => __( 'Aceito', 'eb-credito-rural' ),
			'recusado' => __( 'Recusado', 'eb-credito-rural' ),
		);
		$files  = array();
		foreach ( $documents as $d ) {
			$status = self::opt( $review, $d['review_status'] );
			if ( ! empty( $d['reviewed_by'] ) ) {
				$status .= ' — ' . Helpers::user_name( (int) $d['reviewed_by'] ) . ' · ' . self::dt( $d['reviewed_at'] );
			}
			if ( ! empty( $d['review_note'] ) ) {
				$status .= "\n" . $d['review_note'];
			}
			$validity = self::DASH;
			if ( ! empty( $d['expires_at'] ) ) {
				$validity = self::day( $d['expires_at'] ) . ( strtotime( $d['expires_at'] ) < time() ? ' (' . __( 'vencida', 'eb-credito-rural' ) . ')' : '' );
			}
			$files[] = array(
				DocumentMatrix::label( $d['doc_type'] ) . ( $d['ref_key'] ? ' [' . $d['ref_key'] . ']' : '' ),
				$d['original_name'],
				Helpers::size( $d['size'] ),
				self::dt( $d['uploaded_at'] ),
				$validity,
				$status,
			);
		}
		$w->h2( __( 'Arquivos enviados', 'eb-credito-rural' ) );
		$w->table(
			array(
				__( 'Tipo', 'eb-credito-rural' ),
				__( 'Nome original', 'eb-credito-rural' ),
				__( 'Tamanho', 'eb-credito-rural' ),
				__( 'Enviado em', 'eb-credito-rural' ),
				__( 'Validade', 'eb-credito-rural' ),
				__( 'Revisão', 'eb-credito-rural' ),
			),
			$files
		);
		if ( $requests ) {
			$rows = array();
			foreach ( $requests as $r ) {
				$rows[] = array(
					$r['label'],
					self::dt( $r['requested_at'] ) . ' · ' . Helpers::user_name( (int) $r['requested_by'] ),
					! empty( $r['fulfilled_at'] ) ? sprintf( /* translators: %s: data */ __( 'Atendido em %s', 'eb-credito-rural' ), self::dt( $r['fulfilled_at'] ) ) : __( 'Em aberto', 'eb-credito-rural' ),
					(string) $r['note'],
				);
			}
			$w->h2( __( 'Documentos solicitados pela equipe', 'eb-credito-rural' ) );
			$w->table( array( __( 'Documento', 'eb-credito-rural' ), __( 'Solicitado em / por', 'eb-credito-rural' ), __( 'Situação', 'eb-credito-rural' ), __( 'Instruções', 'eb-credito-rural' ) ), $rows );
		}
	}

	/**
	 * Seção "Conferência" (checklist do analista).
	 *
	 * @param Writer $w      Writer.
	 * @param array  $checks Resultados por chave.
	 * @return void
	 */
	private static function checks( Writer $w, array $checks ) {
		$w->h1( __( '7. Conferência (checklist do analista)', 'eb-credito-rural' ) );
		$labels = array(
			'ok'        => __( 'OK', 'eb-credito-rural' ),
			'alerta'    => __( 'Alerta', 'eb-credito-rural' ),
			'reprovado' => __( 'Reprovado', 'eb-credito-rural' ),
			'na'        => __( 'N/A', 'eb-credito-rural' ),
		);
		$count  = array(
			'ok'        => 0,
			'alerta'    => 0,
			'reprovado' => 0,
			'na'        => 0,
		);
		$rows   = array();
		foreach ( CheckRepository::defaults() as $key => $label ) {
			$c      = isset( $checks[ $key ] ) ? $checks[ $key ] : array();
			$result = isset( $c['result'] ) && isset( $labels[ $c['result'] ] ) ? $c['result'] : 'na';
			++$count[ $result ];
			$rows[] = array(
				$label,
				$labels[ $result ],
				isset( $c['note'] ) ? (string) $c['note'] : '',
				! empty( $c['checked_by'] ) ? Helpers::user_name( (int) $c['checked_by'] ) . ' · ' . self::dt( isset( $c['checked_at'] ) ? $c['checked_at'] : null ) : self::DASH,
			);
		}
		$w->table( array( __( 'Item', 'eb-credito-rural' ), __( 'Resultado', 'eb-credito-rural' ), __( 'Observação / valor de referência', 'eb-credito-rural' ), __( 'Conferido por', 'eb-credito-rural' ) ), $rows );
		$w->p( sprintf( /* translators: 1: ok; 2: alertas; 3: reprovados; 4: não se aplica */ __( 'Resumo: %1$d OK · %2$d alerta(s) · %3$d reprovado(s) · %4$d não se aplica.', 'eb-credito-rural' ), $count['ok'], $count['alerta'], $count['reprovado'], $count['na'] ) );
	}

	/**
	 * Seção "Histórico de status".
	 *
	 * @param Writer $w       Writer.
	 * @param array  $history Transições.
	 * @return void
	 */
	private static function history( Writer $w, array $history ) {
		$w->h1( __( '8. Histórico de status', 'eb-credito-rural' ) );
		$rows = array();
		foreach ( $history as $h ) {
			$comments = array();
			if ( ! empty( $h['comment_internal'] ) ) {
				$comments[] = __( 'Interno:', 'eb-credito-rural' ) . ' ' . $h['comment_internal'];
			}
			if ( ! empty( $h['comment_client'] ) ) {
				$comments[] = __( 'Ao cliente:', 'eb-credito-rural' ) . ' ' . $h['comment_client'];
			}
			$rows[] = array(
				self::dt( $h['created_at'] ),
				( $h['from_status'] ? Status::label( $h['from_status'] ) : self::DASH ) . ' » ' . Status::label( $h['to_status'] ),
				Helpers::user_name( (int) $h['changed_by'] ),
				implode( "\n", $comments ),
			);
		}
		$w->table( array( __( 'Quando', 'eb-credito-rural' ), __( 'De » para', 'eb-credito-rural' ), __( 'Quem', 'eb-credito-rural' ), __( 'Comentários', 'eb-credito-rural' ) ), $rows );
	}

	/**
	 * Seção "Mensagens internas" (somente se houver).
	 *
	 * @param Writer $w        Writer.
	 * @param array  $messages Mensagens internas.
	 * @return void
	 */
	private static function messages( Writer $w, array $messages ) {
		if ( ! $messages ) {
			return;
		}
		$w->h1( __( '9. Mensagens internas da equipe', 'eb-credito-rural' ) );
		$rows = array();
		foreach ( $messages as $m ) {
			$rows[] = array( self::dt( $m['created_at'] ), Helpers::user_name( (int) $m['author_id'] ), (string) $m['body'] );
		}
		$w->table( array( __( 'Quando', 'eb-credito-rural' ), __( 'Autor', 'eb-credito-rural' ), __( 'Mensagem', 'eb-credito-rural' ) ), $rows );
	}

	/**
	 * Seção "Aceites".
	 *
	 * @param Writer $w        Writer.
	 * @param array  $consents Aceites.
	 * @return void
	 */
	private static function consents( Writer $w, array $consents ) {
		$w->h1( __( '10. Aceites e declarações', 'eb-credito-rural' ) );
		$policies = Consent::policies();
		$rows     = array();
		foreach ( $consents as $c ) {
			$rows[] = array(
				isset( $policies[ $c['policy_key'] ] ) ? $policies[ $c['policy_key'] ]['title'] : $c['policy_key'],
				(string) $c['policy_version'],
				self::dt( $c['accepted_at'] ),
				(string) $c['ip'],
			);
		}
		$w->table( array( __( 'Política / declaração', 'eb-credito-rural' ), __( 'Versão', 'eb-credito-rural' ), __( 'Aceito em', 'eb-credito-rural' ), __( 'IP', 'eb-credito-rural' ) ), $rows );
	}

	/**
	 * Seção dos dados salvos (array vazio quando ausente).
	 *
	 * @param array  $data Todas as seções.
	 * @param string $key  Seção.
	 * @return array
	 */
	private static function section( array $data, $key ) {
		return isset( $data[ $key ] ) && is_array( $data[ $key ] ) ? $data[ $key ] : array();
	}

	/**
	 * Valor escalar de um array como string ('' se ausente).
	 *
	 * @param array  $arr Array.
	 * @param string $key Chave.
	 * @return string
	 */
	private static function v( array $arr, $key ) {
		if ( ! isset( $arr[ $key ] ) || is_array( $arr[ $key ] ) ) {
			return '';
		}
		return trim( (string) $arr[ $key ] );
	}

	/**
	 * Rótulo de uma opção (ou a própria chave).
	 *
	 * @param array  $options Opções.
	 * @param string $key     Chave.
	 * @return string
	 */
	private static function opt( array $options, $key ) {
		$key = (string) $key;
		return isset( $options[ $key ] ) ? (string) $options[ $key ] : $key;
	}

	/**
	 * Sim / Não / Não se aplica.
	 *
	 * @param string $value sim|nao|na.
	 * @return string
	 */
	private static function yn( $value ) {
		switch ( $value ) {
			case 'sim':
				return __( 'Sim', 'eb-credito-rural' );
			case 'nao':
				return __( 'Não', 'eb-credito-rural' );
			case 'na':
				return __( 'Não se aplica', 'eb-credito-rural' );
			default:
				return (string) $value;
		}
	}

	/**
	 * Data/hora (UTC do banco) no fuso do site, sempre em d/m/Y H:i.
	 *
	 * @param string|null $mysql_utc Data MySQL.
	 * @return string
	 */
	private static function dt( $mysql_utc ) {
		return Helpers::date( $mysql_utc, 'd/m/Y H:i' );
	}

	/**
	 * CEP com máscara.
	 *
	 * @param string $cep CEP (dígitos ou já formatado).
	 * @return string
	 */
	private static function cep( $cep ) {
		$d = Helpers::digits( $cep );
		return 8 === strlen( $d ) ? substr( $d, 0, 5 ) . '-' . substr( $d, 5 ) : (string) $cep;
	}

	/**
	 * Telefone com máscara.
	 *
	 * @param string $phone Telefone (dígitos ou já formatado).
	 * @return string
	 */
	private static function phone( $phone ) {
		$d = Helpers::digits( $phone );
		if ( 11 === strlen( $d ) ) {
			return '(' . substr( $d, 0, 2 ) . ') ' . substr( $d, 2, 5 ) . '-' . substr( $d, 7 );
		}
		if ( 10 === strlen( $d ) ) {
			return '(' . substr( $d, 0, 2 ) . ') ' . substr( $d, 2, 4 ) . '-' . substr( $d, 6 );
		}
		return (string) $phone;
	}

	/**
	 * Data (Y-m-d) em d/m/Y.
	 *
	 * @param string|null $ymd Data.
	 * @return string
	 */
	private static function day( $ymd ) {
		if ( empty( $ymd ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', (string) $ymd, $m ) ) {
			return $ymd ? (string) $ymd : self::DASH;
		}
		return $m[3] . '/' . $m[2] . '/' . $m[1];
	}

	/**
	 * Número em formato brasileiro.
	 *
	 * @param mixed $value    Valor.
	 * @param int   $decimals Casas decimais.
	 * @return string
	 */
	private static function number( $value, $decimals = 2 ) {
		if ( null === $value || '' === $value ) {
			return self::DASH;
		}
		return number_format( (float) $value, $decimals, ',', '.' );
	}

	/**
	 * Área em hectares.
	 *
	 * @param mixed $value Valor.
	 * @return string
	 */
	private static function hectares( $value ) {
		return null === $value || '' === $value ? self::DASH : self::number( $value ) . ' ha';
	}

	/**
	 * Percentual.
	 *
	 * @param mixed $value Valor.
	 * @return string
	 */
	private static function percent( $value ) {
		return null === $value || '' === $value ? self::DASH : self::number( $value, 1 ) . '%';
	}

	/**
	 * Razão (ex.: 1,60x).
	 *
	 * @param mixed $value Valor.
	 * @return string
	 */
	private static function ratio( $value ) {
		return null === $value || '' === $value ? self::DASH : self::number( $value ) . 'x';
	}

	/**
	 * Endereço em uma linha.
	 *
	 * @param array $ident Identificação.
	 * @return string
	 */
	private static function address( array $ident ) {
		$line  = trim( self::v( $ident, 'logradouro' ) . ( self::v( $ident, 'numero' ) ? ', ' . self::v( $ident, 'numero' ) : '' ) . ( self::v( $ident, 'complemento' ) ? ' ' . self::v( $ident, 'complemento' ) : '' ) );
		$parts = array_filter(
			array(
				$line,
				self::v( $ident, 'bairro' ),
				trim( self::v( $ident, 'cidade' ) . ( self::v( $ident, 'uf' ) ? '/' . self::v( $ident, 'uf' ) : '' ) ),
				self::v( $ident, 'cep' ) ? 'CEP ' . self::cep( self::v( $ident, 'cep' ) ) : '',
			)
		);
		return implode( ' — ', $parts );
	}

	/**
	 * Nome do cliente: tomador (PF/PJ) ou, na falta, nome de exibição do usuário.
	 *
	 * @param array          $ident Identificação.
	 * @param \WP_User|false $user  Usuário.
	 * @return string
	 */
	private static function client_name( array $ident, $user ) {
		$name = isset( $ident['person_type'] ) && 'PJ' === $ident['person_type'] ? self::v( $ident, 'razao_social' ) : self::v( $ident, 'nome' );
		if ( '' === $name ) {
			$name = $user ? $user->display_name : __( 'Cliente', 'eb-credito-rural' );
		}
		return $name;
	}
}
