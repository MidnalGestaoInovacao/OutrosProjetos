<?php
/**
 * Matriz de documentos: quais documentos são exigidos conforme as respostas.
 *
 * @package EBCR
 */

namespace EBCR\Forms;

use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Configurável no admin; aqui ficam os padrões e a avaliação das condições.
 */
final class DocumentMatrix {

	/**
	 * Condições disponíveis (chave => descrição).
	 *
	 * @return array
	 */
	public static function conditions() {
		return array(
			'always'         => __( 'Sempre', 'eb-credito-rural' ),
			'pf'             => __( 'Pessoa física', 'eb-credito-rural' ),
			'pj'             => __( 'Pessoa jurídica', 'eb-credito-rural' ),
			'married'        => __( 'Casado(a) ou união estável', 'eb-credito-rural' ),
			'property'       => __( 'Por imóvel', 'eb-credito-rural' ),
			'leased'         => __( 'Por imóvel arrendado/parceria', 'eb-credito-rural' ),
			'livestock'      => __( 'Atividade pecuária', 'eb-credito-rural' ),
			'insurance'      => __( 'Possui seguro rural', 'eb-credito-rural' ),
			'environmental'  => __( 'Se aplicável (licenças/outorga)', 'eb-credito-rural' ),
			'real_guarantee' => __( 'Garantia real oferecida', 'eb-credito-rural' ),
			'never'          => __( 'Apenas sob solicitação da equipe', 'eb-credito-rural' ),
		);
	}

	/**
	 * Níveis de obrigatoriedade.
	 *
	 * @return array
	 */
	public static function levels() {
		return array(
			'sim'         => __( 'Obrigatório', 'eb-credito-rural' ),
			'condicional' => __( 'Condicional (opcional, a critério da equipe)', 'eb-credito-rural' ),
			'recomendado' => __( 'Recomendado', 'eb-credito-rural' ),
			'nao'         => __( 'Opcional', 'eb-credito-rural' ),
		);
	}

	/**
	 * Matriz padrão (§6 do SPEC).
	 *
	 * @return array
	 */
	public static function defaults() {
		$rows = array(
			array( 'identidade', 'Documento de identidade (tomador e cônjuge)', 'pf', 'sim', 0, 'RG/CNH do tomador e, se houver, do cônjuge.' ),
			array( 'contrato_social', 'Contrato social e atos de representação', 'pj', 'sim', 0, 'Contrato social consolidado e documentos que comprovem os poderes dos representantes.' ),
			array( 'comprovante_residencia', 'Comprovante de residência', 'always', 'sim', 90, 'Conta de água, luz, telefone ou similar em nome do tomador.' ),
			array( 'certidao_casamento', 'Certidão de casamento/união estável', 'married', 'sim', 0, 'Necessária para a outorga do cônjuge nas garantias reais.' ),
			array( 'certidoes_negativas', 'Certidões negativas (Federal/PGFN, CNDT, FGTS, protestos, distribuidores)', 'always', 'sim', 30, 'Envie cada certidão; a validade é contada a partir da emissão.' ),
			array( 'matricula', 'Matrícula atualizada do imóvel', 'property', 'sim', 30, 'Certidão de inteiro teor com ônus e ações, emitida há no máximo 30 dias.' ),
			array( 'ccir_itr', 'CCIR, ITR (5 anos) e CND do ITR', 'property', 'sim', 0, 'CCIR do exercício vigente, declarações do ITR dos últimos 5 anos e certidão negativa de débitos do ITR.' ),
			array( 'recibo_car', 'Recibo de inscrição no CAR', 'property', 'sim', 0, 'Recibo atualizado do Cadastro Ambiental Rural.' ),
			array( 'sigef', 'Certificação SIGEF / georreferenciamento', 'property', 'condicional', 0, 'Quando o imóvel possuir georreferenciamento certificado.' ),
			array( 'contrato_arrendamento', 'Contrato de arrendamento/parceria', 'leased', 'sim', 0, 'Contrato vigente, com firmas reconhecidas quando possível.' ),
			array( 'irpf_lcdpr', 'IRPF com LCDPR (ou balanço/DRE se PJ)', 'always', 'sim', 0, 'Últimas declarações completas com recibo, incluindo o Livro Caixa Digital do Produtor Rural.' ),
			array( 'notas_fiscais', 'Notas fiscais de produtor / contratos de venda', 'always', 'recomendado', 0, 'Comprovam faturamento e comercialização.' ),
			array( 'rebanho_gta', 'Declaração de rebanho e GTAs', 'livestock', 'sim', 90, 'Declaração anual de rebanho e guias de trânsito animal recentes.' ),
			array( 'apolice_seguro', 'Apólice de seguro rural', 'insurance', 'nao', 0, 'Apólice vigente, se possuir.' ),
			array( 'licencas_ambientais', 'Licenças ambientais / outorga de água', 'environmental', 'condicional', 0, 'Quando a atividade exigir licenciamento ou outorga.' ),
			array( 'laudo_avaliacao', 'Laudo de avaliação com ART', 'real_guarantee', 'recomendado', 180, 'Pode ser solicitado depois pela equipe.' ),
			array( 'autorizacao_scr', 'Autorização de consulta SCR assinada', 'always', 'sim', 0, 'Baixe o modelo, assine e envie.' ),
			array( 'outro', 'Outro documento', 'never', 'nao', 0, 'Documentos adicionais solicitados pela equipe ou enviados espontaneamente.' ),
		);
		$out  = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'key'           => $r[0],
				'label'         => $r[1],
				'condition'     => $r[2],
				'required'      => $r[3],
				'validity_days' => (int) $r[4],
				'help'          => $r[5],
			);
		}
		return $out;
	}

	/**
	 * Matriz efetiva.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = Options::get( 'document_matrix', array() );
		$rows  = is_array( $saved ) && $saved ? $saved : self::defaults();
		$out   = array();
		foreach ( $rows as $r ) {
			if ( empty( $r['key'] ) ) {
				continue;
			}
			$out[ sanitize_key( $r['key'] ) ] = array(
				'key'           => sanitize_key( $r['key'] ),
				'label'         => isset( $r['label'] ) ? (string) $r['label'] : sanitize_key( $r['key'] ),
				'condition'     => isset( $r['condition'] ) && isset( self::conditions()[ $r['condition'] ] ) ? $r['condition'] : 'never',
				'required'      => isset( $r['required'] ) && isset( self::levels()[ $r['required'] ] ) ? $r['required'] : 'nao',
				'validity_days' => isset( $r['validity_days'] ) ? max( 0, (int) $r['validity_days'] ) : 0,
				'help'          => isset( $r['help'] ) ? (string) $r['help'] : '',
			);
		}
		if ( ! isset( $out['outro'] ) ) {
			$out['outro'] = array(
				'key'           => 'outro',
				'label'         => __( 'Outro documento', 'eb-credito-rural' ),
				'condition'     => 'never',
				'required'      => 'nao',
				'validity_days' => 0,
				'help'          => '',
			);
		}
		return $out;
	}

	/**
	 * Tipo válido?
	 *
	 * @param string $key Chave.
	 * @return bool
	 */
	public static function is_valid_type( $key ) {
		return isset( self::all()[ sanitize_key( $key ) ] );
	}

	/**
	 * Rótulo de um tipo.
	 *
	 * @param string $key Chave.
	 * @return string
	 */
	public static function label( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ]['label'] : (string) $key;
	}

	/**
	 * Calcula os "slots" de documentos para um contexto de solicitação.
	 *
	 * @param array $ctx Contexto: person_type (PF|PJ), married (bool), properties (array de imóveis com id/name/tenure),
	 *                   livestock (bool), insurance (bool), environmental (bool), real_guarantee (bool).
	 * @return array Lista de slots: type, label, required(bool), level, ref_key, ref_label, validity_days, help.
	 */
	public static function slots( array $ctx ) {
		$ctx   = array_merge(
			array(
				'person_type'    => 'PF',
				'married'        => false,
				'properties'     => array(),
				'livestock'      => false,
				'insurance'      => false,
				'environmental'  => false,
				'real_guarantee' => false,
			),
			$ctx
		);
		$slots = array();
		foreach ( self::all() as $doc ) {
			$cond     = $doc['condition'];
			$required = 'sim' === $doc['required'];
			$base     = array(
				'type'          => $doc['key'],
				'label'         => $doc['label'],
				'required'      => $required,
				'level'         => $doc['required'],
				'ref_key'       => '',
				'ref_label'     => '',
				'validity_days' => $doc['validity_days'],
				'help'          => $doc['help'],
			);
			switch ( $cond ) {
				case 'always':
					$slots[] = $base;
					break;
				case 'pf':
					if ( 'PF' === $ctx['person_type'] ) {
						$slots[] = $base;
					}
					break;
				case 'pj':
					if ( 'PJ' === $ctx['person_type'] ) {
						$slots[] = $base;
					}
					break;
				case 'married':
					if ( 'PF' === $ctx['person_type'] && $ctx['married'] ) {
						$slots[] = $base;
					}
					break;
				case 'property':
				case 'leased':
					foreach ( (array) $ctx['properties'] as $i => $prop ) {
						$tenure = isset( $prop['tenure'] ) ? $prop['tenure'] : 'propria';
						if ( 'leased' === $cond && 'propria' === $tenure ) {
							continue;
						}
						$slot              = $base;
						$slot['ref_key']   = 'p:' . ( isset( $prop['id'] ) ? (int) $prop['id'] : (int) $i );
						$slot['ref_label'] = isset( $prop['name'] ) && '' !== $prop['name'] ? $prop['name'] : sprintf( /* translators: %d: número do imóvel */ __( 'Imóvel %d', 'eb-credito-rural' ), $i + 1 );
						$slots[]           = $slot;
					}
					break;
				case 'livestock':
					if ( $ctx['livestock'] ) {
						$slots[] = $base;
					}
					break;
				case 'insurance':
					if ( $ctx['insurance'] ) {
						$slots[] = $base;
					}
					break;
				case 'environmental':
					if ( $ctx['environmental'] ) {
						$slots[] = $base;
					}
					break;
				case 'real_guarantee':
					if ( $ctx['real_guarantee'] ) {
						$slots[] = $base;
					}
					break;
				default:
					break;
			}
		}
		return $slots;
	}
}
