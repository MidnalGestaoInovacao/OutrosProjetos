<?php
/**
 * Definição e validação das sete etapas.
 *
 * @package EBCR
 */

namespace EBCR\Forms;

use EBCR\Forms\Validators\Rules;
use EBCR\Forms\Validators\Validator;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Cada etapa: chave da seção, título, campos e validação (inclusive grupos repetíveis).
 */
final class Steps {

	/**
	 * Metadados das etapas.
	 *
	 * @return array
	 */
	public static function all() {
		return array(
			1 => array(
				'key'   => 'identificacao',
				'title' => __( 'Identificação do tomador', 'eb-credito-rural' ),
			),
			2 => array(
				'key'   => 'imoveis',
				'title' => __( 'Imóveis rurais', 'eb-credito-rural' ),
			),
			3 => array(
				'key'   => 'producao',
				'title' => __( 'Atividade produtiva', 'eb-credito-rural' ),
			),
			4 => array(
				'key'   => 'financeiro',
				'title' => __( 'Situação financeira', 'eb-credito-rural' ),
			),
			5 => array(
				'key'   => 'garantias',
				'title' => __( 'Garantias oferecidas', 'eb-credito-rural' ),
			),
			6 => array(
				'key'   => 'documentos',
				'title' => __( 'Documentos', 'eb-credito-rural' ),
			),
			7 => array(
				'key'   => 'envio',
				'title' => __( 'Declarações e envio', 'eb-credito-rural' ),
			),
		);
	}

	/**
	 * Listas de opções.
	 *
	 * @param string $which Lista.
	 * @return array
	 */
	public static function options( $which ) {
		switch ( $which ) {
			case 'estado_civil':
				return array(
					'solteiro'      => __( 'Solteiro(a)', 'eb-credito-rural' ),
					'casado'        => __( 'Casado(a)', 'eb-credito-rural' ),
					'uniao_estavel' => __( 'União estável', 'eb-credito-rural' ),
					'divorciado'    => __( 'Divorciado(a)', 'eb-credito-rural' ),
					'viuvo'         => __( 'Viúvo(a)', 'eb-credito-rural' ),
				);
			case 'regime_bens':
				return array(
					'comunhao_parcial'   => __( 'Comunhão parcial de bens', 'eb-credito-rural' ),
					'comunhao_universal' => __( 'Comunhão universal de bens', 'eb-credito-rural' ),
					'separacao_total'    => __( 'Separação total de bens', 'eb-credito-rural' ),
					'participacao_final' => __( 'Participação final nos aquestos', 'eb-credito-rural' ),
				);
			case 'tenure':
				return array(
					'propria'   => __( 'Própria', 'eb-credito-rural' ),
					'arrendada' => __( 'Arrendada', 'eb-credito-rural' ),
					'parceria'  => __( 'Parceria', 'eb-credito-rural' ),
				);
			case 'guarantee_types':
				return array(
					'alienacao_fiduciaria_imovel' => __( 'Alienação fiduciária de imóvel', 'eb-credito-rural' ),
					'hipoteca'                    => __( 'Hipoteca', 'eb-credito-rural' ),
					'penhor_agricola'             => __( 'Penhor agrícola', 'eb-credito-rural' ),
					'penhor_pecuario'             => __( 'Penhor pecuário', 'eb-credito-rural' ),
					'maquinas_equipamentos'       => __( 'Máquinas / equipamentos', 'eb-credito-rural' ),
					'cpr'                         => __( 'CPR (Cédula de Produto Rural)', 'eb-credito-rural' ),
					'cessao_recebiveis'           => __( 'Cessão de recebíveis', 'eb-credito-rural' ),
					'patrimonio_afetacao_cir'     => __( 'Patrimônio Rural em Afetação / CIR', 'eb-credito-rural' ),
					'aval_fianca'                 => __( 'Aval / fiança', 'eb-credito-rural' ),
				);
			case 'real_guarantees':
				return array( 'alienacao_fiduciaria_imovel', 'hipoteca', 'patrimonio_afetacao_cir' );
			case 'epoca_pagamento':
				return array(
					'safra'     => __( 'Após a colheita/safra', 'eb-credito-rural' ),
					'mensal'    => __( 'Mensal', 'eb-credito-rural' ),
					'semestral' => __( 'Semestral', 'eb-credito-rural' ),
					'anual'     => __( 'Anual', 'eb-credito-rural' ),
					'outro'     => __( 'Outro', 'eb-credito-rural' ),
				);
			case 'rebanho_categorias':
				return array(
					'matrizes' => __( 'Matrizes', 'eb-credito-rural' ),
					'bezerros' => __( 'Bezerros(as)', 'eb-credito-rural' ),
					'garrotes' => __( 'Garrotes / novilhas', 'eb-credito-rural' ),
					'bois'     => __( 'Bois / vacas de descarte', 'eb-credito-rural' ),
					'touros'   => __( 'Touros', 'eb-credito-rural' ),
					'lactacao' => __( 'Vacas em lactação', 'eb-credito-rural' ),
					'outros'   => __( 'Outros', 'eb-credito-rural' ),
				);
			case 'activities':
				return Options::pairs( 'activities' );
			case 'purposes':
				return Options::pairs( 'purposes' );
			default:
				return array();
		}
	}

	/**
	 * Valida uma etapa. Retorna [dados limpos, erros (campo => msg), avisos (lista)].
	 *
	 * @param int   $step  Etapa.
	 * @param array $input Entrada bruta.
	 * @return array
	 */
	public static function validate( $step, array $input ) {
		switch ( (int) $step ) {
			case 1:
				return self::step1( $input );
			case 2:
				return self::step2( $input );
			case 3:
				return self::step3( $input );
			case 4:
				return self::step4( $input );
			case 5:
				return self::step5( $input );
			default:
				return array( array(), array(), array() );
		}
	}

	/**
	 * Etapa 1.
	 *
	 * @param array $in Entrada.
	 * @return array
	 */
	private static function step1( array $in ) {
		$type   = isset( $in['person_type'] ) && 'PJ' === $in['person_type'] ? 'PJ' : 'PF';
		$fields = array(
			'person_type'        => array(
				'label' => __( 'Tipo de pessoa', 'eb-credito-rural' ),
				'rules' => array( 'required', 'in:PF|PJ' ),
			),
			'inscricao_estadual' => array(
				'label' => __( 'Inscrição estadual de produtor', 'eb-credito-rural' ),
				'rules' => array( 'max:30' ),
			),
			'caf'                => array(
				'label' => __( 'CAF (Pronaf)', 'eb-credito-rural' ),
				'rules' => array( 'max:40' ),
			),
			'cep'                => array(
				'label' => __( 'CEP', 'eb-credito-rural' ),
				'rules' => array( 'required', 'cep' ),
				'type'  => 'cep',
			),
			'logradouro'         => array(
				'label' => __( 'Endereço', 'eb-credito-rural' ),
				'rules' => array( 'required', 'max:190' ),
			),
			'numero'             => array(
				'label' => __( 'Número', 'eb-credito-rural' ),
				'rules' => array( 'required', 'max:20' ),
			),
			'complemento'        => array(
				'label' => __( 'Complemento', 'eb-credito-rural' ),
				'rules' => array( 'max:100' ),
			),
			'bairro'             => array(
				'label' => __( 'Bairro', 'eb-credito-rural' ),
				'rules' => array( 'max:100' ),
			),
			'cidade'             => array(
				'label' => __( 'Cidade', 'eb-credito-rural' ),
				'rules' => array( 'required', 'max:120' ),
			),
			'uf'                 => array(
				'label' => __( 'UF', 'eb-credito-rural' ),
				'rules' => array( 'required', 'uf' ),
				'type'  => 'upper',
			),
			'telefone'           => array(
				'label' => __( 'Telefone', 'eb-credito-rural' ),
				'rules' => array( 'required', 'phone' ),
				'type'  => 'phone',
			),
			'whatsapp'           => array(
				'label' => __( 'WhatsApp', 'eb-credito-rural' ),
				'rules' => array( 'phone' ),
				'type'  => 'phone',
			),
			'pep'                => array(
				'label' => __( 'Pessoa politicamente exposta', 'eb-credito-rural' ),
				'rules' => array( 'required', 'in:sim|nao' ),
			),
			'pep_detalhes'       => array(
				'label' => __( 'Detalhes da exposição política', 'eb-credito-rural' ),
				'rules' => array( 'max:1000' ),
				'type'  => 'textarea',
			),
		);
		if ( 'PF' === $type ) {
			$fields += array(
				'nome'         => array(
					'label' => __( 'Nome completo', 'eb-credito-rural' ),
					'rules' => array( 'required', 'min:5', 'max:190' ),
				),
				'cpf'          => array(
					'label' => __( 'CPF', 'eb-credito-rural' ),
					'rules' => array( 'required', 'cpf' ),
					'type'  => 'cpf',
				),
				'rg'           => array(
					'label' => __( 'RG', 'eb-credito-rural' ),
					'rules' => array( 'required', 'max:30' ),
				),
				'rg_orgao'     => array(
					'label' => __( 'Órgão emissor', 'eb-credito-rural' ),
					'rules' => array( 'required', 'max:30' ),
				),
				'nascimento'   => array(
					'label' => __( 'Data de nascimento', 'eb-credito-rural' ),
					'rules' => array( 'required', 'date', 'min_age:18' ),
					'type'  => 'date',
				),
				'estado_civil' => array(
					'label' => __( 'Estado civil', 'eb-credito-rural' ),
					'rules' => array( 'required', 'in:' . implode( '|', array_keys( self::options( 'estado_civil' ) ) ) ),
				),
				'regime_bens'  => array(
					'label' => __( 'Regime de bens', 'eb-credito-rural' ),
					'rules' => array( 'in:' . implode( '|', array_keys( self::options( 'regime_bens' ) ) ) ),
				),
				'conjuge_nome' => array(
					'label' => __( 'Nome do cônjuge/companheiro(a)', 'eb-credito-rural' ),
					'rules' => array( 'max:190' ),
				),
				'conjuge_cpf'  => array(
					'label' => __( 'CPF do cônjuge/companheiro(a)', 'eb-credito-rural' ),
					'rules' => array( 'cpf' ),
					'type'  => 'cpf',
				),
			);
		} else {
			$fields += array(
				'razao_social' => array(
					'label' => __( 'Razão social', 'eb-credito-rural' ),
					'rules' => array( 'required', 'min:3', 'max:190' ),
				),
				'cnpj'         => array(
					'label' => __( 'CNPJ', 'eb-credito-rural' ),
					'rules' => array( 'required', 'cnpj' ),
					'type'  => 'cnpj',
				),
			);
		}
		list( $clean, $errors ) = Validator::run( $fields, $in );
		$clean['person_type']   = $type;
		if ( 'PF' === $type ) {
			$married = in_array( $clean['estado_civil'], array( 'casado', 'uniao_estavel' ), true );
			if ( $married ) {
				foreach ( array( 'regime_bens', 'conjuge_nome', 'conjuge_cpf' ) as $f ) {
					if ( empty( $clean[ $f ] ) && ! isset( $errors[ $f ] ) ) {
						$errors[ $f ] = sprintf( /* translators: %s: rótulo */ __( '%s é obrigatório para casados/união estável (outorga nas garantias).', 'eb-credito-rural' ), $fields[ $f ]['label'] );
					}
				}
				if ( ! empty( $clean['conjuge_cpf'] ) && ! empty( $clean['cpf'] ) && $clean['conjuge_cpf'] === $clean['cpf'] ) {
					$errors['conjuge_cpf'] = __( 'O CPF do cônjuge não pode ser igual ao do tomador.', 'eb-credito-rural' );
				}
			}
			$clean['married'] = $married;
		} else {
			$reps = self::repeat(
				isset( $in['representantes'] ) ? $in['representantes'] : array(),
				array(
					'nome' => array(
						'label' => __( 'Nome do representante', 'eb-credito-rural' ),
						'rules' => array( 'required', 'max:190' ),
					),
					'cpf'  => array(
						'label' => __( 'CPF do representante', 'eb-credito-rural' ),
						'rules' => array( 'required', 'cpf' ),
						'type'  => 'cpf',
					),
				),
				'representantes',
				$errors
			);
			if ( ! $reps ) {
				$errors['representantes'] = __( 'Informe pelo menos um representante legal.', 'eb-credito-rural' );
			}
			$clean['representantes'] = $reps;
			$clean['married']        = false;
		}
		if ( 'sim' === $clean['pep'] && empty( $clean['pep_detalhes'] ) ) {
			$errors['pep_detalhes'] = __( 'Descreva o cargo/função e o vínculo (PEP).', 'eb-credito-rural' );
		}
		return array( $clean, $errors, array() );
	}

	/**
	 * Etapa 2 — imóveis (repetível).
	 *
	 * @param array $in Entrada.
	 * @return array
	 */
	private static function step2( array $in ) {
		$errors = array();
		$max_ha = max( 1, (float) Options::get( 'max_area_ha', 500000 ) );
		$items  = self::repeat(
			isset( $in['imoveis'] ) ? $in['imoveis'] : array(),
			array(
				'id'                  => array(
					'label' => 'id',
					'rules' => array( 'int:0,' ),
					'type'  => 'int',
				),
				'name'                => array(
					'label' => __( 'Nome do imóvel', 'eb-credito-rural' ),
					'rules' => array( 'required', 'max:190' ),
				),
				'city'                => array(
					'label' => __( 'Município', 'eb-credito-rural' ),
					'rules' => array( 'required', 'max:120' ),
				),
				'uf'                  => array(
					'label' => __( 'UF', 'eb-credito-rural' ),
					'rules' => array( 'required', 'uf' ),
					'type'  => 'upper',
				),
				'registration_number' => array(
					'label' => __( 'Matrícula', 'eb-credito-rural' ),
					'rules' => array( 'required', 'max:60' ),
				),
				'registry_office'     => array(
					'label' => __( 'Cartório de registro', 'eb-credito-rural' ),
					'rules' => array( 'required', 'max:190' ),
				),
				'total_area'          => array(
					'label' => __( 'Área total (ha)', 'eb-credito-rural' ),
					'rules' => array( 'required', 'amount:0.01,' . $max_ha ),
					'type'  => 'amount',
				),
				'usable_area'         => array(
					'label' => __( 'Área útil (ha)', 'eb-credito-rural' ),
					'rules' => array( 'required', 'amount:0.01,' . $max_ha ),
					'type'  => 'amount',
				),
				'car_code'            => array(
					'label' => __( 'Código do CAR', 'eb-credito-rural' ),
					'rules' => array( 'required', 'car' ),
					'type'  => 'upper',
				),
				'ccir'                => array(
					'label' => __( 'CCIR', 'eb-credito-rural' ),
					'rules' => array( 'max:60' ),
				),
				'nirf'                => array(
					'label' => __( 'NIRF/CIB', 'eb-credito-rural' ),
					'rules' => array( 'max:60' ),
				),
				'sigef'               => array(
					'label' => __( 'Certificação SIGEF', 'eb-credito-rural' ),
					'rules' => array( 'max:80' ),
				),
				'tenure'              => array(
					'label' => __( 'Condição', 'eb-credito-rural' ),
					'rules' => array( 'required', 'in:' . implode( '|', array_keys( self::options( 'tenure' ) ) ) ),
				),
				'lease_end'           => array(
					'label' => __( 'Término do arrendamento/parceria', 'eb-credito-rural' ),
					'rules' => array( 'date', 'future' ),
					'type'  => 'date',
				),
			),
			'imoveis',
			$errors
		);
		foreach ( $items as $i => $item ) {
			if ( null !== $item['usable_area'] && null !== $item['total_area'] && $item['usable_area'] > $item['total_area'] ) {
				$errors[ "imoveis.{$i}.usable_area" ] = __( 'A área útil não pode ser maior que a área total.', 'eb-credito-rural' );
			}
			if ( 'propria' !== $item['tenure'] && empty( $item['lease_end'] ) && ! isset( $errors[ "imoveis.{$i}.lease_end" ] ) ) {
				$errors[ "imoveis.{$i}.lease_end" ] = __( 'Informe a data de término do contrato de arrendamento/parceria.', 'eb-credito-rural' );
			}
		}
		if ( ! $items ) {
			$errors['imoveis'] = __( 'Informe pelo menos um imóvel rural.', 'eb-credito-rural' );
		}
		return array( array( 'imoveis' => $items ), $errors, array() );
	}

	/**
	 * Etapa 3 — produção.
	 *
	 * @param array $in Entrada.
	 * @return array
	 */
	private static function step3( array $in ) {
		$acts                   = array_keys( self::options( 'activities' ) );
		$fields                 = array(
			'atividades'         => array(
				'label' => __( 'Atividades', 'eb-credito-rural' ),
				'rules' => array( 'required', 'in:' . implode( '|', $acts ) ),
				'type'  => 'multi',
			),
			'atividade_outra'    => array(
				'label' => __( 'Outra atividade', 'eb-credito-rural' ),
				'rules' => array( 'max:190' ),
			),
			'plano_safra'        => array(
				'label' => __( 'Plano da safra', 'eb-credito-rural' ),
				'rules' => array( 'required', 'max:4000' ),
				'type'  => 'textarea',
			),
			'orcamento_custeio'  => array(
				'label' => __( 'Orçamento de custeio (R$)', 'eb-credito-rural' ),
				'rules' => array( 'amount:0,' ),
				'type'  => 'amount',
			),
			'compradores'        => array(
				'label' => __( 'Principais compradores', 'eb-credito-rural' ),
				'rules' => array( 'max:2000' ),
				'type'  => 'textarea',
			),
			'contratos_venda'    => array(
				'label' => __( 'Contratos de venda futura/barter', 'eb-credito-rural' ),
				'rules' => array( 'required', 'in:sim|nao' ),
			),
			'contratos_detalhes' => array(
				'label' => __( 'Detalhes dos contratos', 'eb-credito-rural' ),
				'rules' => array( 'max:2000' ),
				'type'  => 'textarea',
			),
			'seguro_rural'       => array(
				'label' => __( 'Seguro rural', 'eb-credito-rural' ),
				'rules' => array( 'required', 'in:sim|nao' ),
			),
			'seguro_tipo'        => array(
				'label' => __( 'Tipo de seguro', 'eb-credito-rural' ),
				'rules' => array( 'max:190' ),
			),
			'irrigacao'          => array(
				'label' => __( 'Irrigação', 'eb-credito-rural' ),
				'rules' => array( 'required', 'in:sim|nao' ),
			),
			'maquinario'         => array(
				'label' => __( 'Maquinário principal', 'eb-credito-rural' ),
				'rules' => array( 'max:2000' ),
				'type'  => 'textarea',
			),
			'licenca_ambiental'  => array(
				'label' => __( 'Licenças ambientais / outorga', 'eb-credito-rural' ),
				'rules' => array( 'required', 'in:sim|nao|na' ),
			),
		);
		list( $clean, $errors ) = Validator::run( $fields, $in );
		$warnings               = array();
		$clean['area_cultura']  = self::repeat(
			isset( $in['area_cultura'] ) ? $in['area_cultura'] : array(),
			array(
				'cultura' => array(
					'label' => __( 'Cultura', 'eb-credito-rural' ),
					'rules' => array( 'required', 'max:100' ),
				),
				'area_ha' => array(
					'label' => __( 'Área plantada (ha)', 'eb-credito-rural' ),
					'rules' => array( 'required', 'amount:0.01,' ),
					'type'  => 'amount',
				),
			),
			'area_cultura',
			$errors
		);
		$clean['produtividade'] = self::repeat(
			isset( $in['produtividade'] ) ? $in['produtividade'] : array(),
			array(
				'safra'   => array(
					'label' => __( 'Safra', 'eb-credito-rural' ),
					'rules' => array( 'required', 'max:20' ),
				),
				'cultura' => array(
					'label' => __( 'Cultura', 'eb-credito-rural' ),
					'rules' => array( 'required', 'max:100' ),
				),
				'valor'   => array(
					'label' => __( 'Produtividade', 'eb-credito-rural' ),
					'rules' => array( 'required', 'amount:0,' ),
					'type'  => 'amount',
				),
				'unidade' => array(
					'label' => __( 'Unidade', 'eb-credito-rural' ),
					'rules' => array( 'required', 'max:20' ),
				),
			),
			'produtividade',
			$errors
		);
		$livestock              = (bool) array_intersect( (array) $clean['atividades'], array( 'pecuaria_corte', 'pecuaria_leite' ) );
		$clean['rebanho']       = self::repeat(
			isset( $in['rebanho'] ) ? $in['rebanho'] : array(),
			array(
				'categoria'  => array(
					'label' => __( 'Categoria', 'eb-credito-rural' ),
					'rules' => array( 'required', 'in:' . implode( '|', array_keys( self::options( 'rebanho_categorias' ) ) ) ),
				),
				'quantidade' => array(
					'label' => __( 'Quantidade', 'eb-credito-rural' ),
					'rules' => array( 'required', 'int:1,10000000' ),
					'type'  => 'int',
				),
			),
			'rebanho',
			$errors
		);
		if ( $livestock && ! $clean['rebanho'] ) {
			$errors['rebanho'] = __( 'Informe o rebanho por categoria.', 'eb-credito-rural' );
		}
		$clean['livestock'] = $livestock;
		if ( in_array( 'outras', (array) $clean['atividades'], true ) && empty( $clean['atividade_outra'] ) ) {
			$errors['atividade_outra'] = __( 'Descreva a outra atividade.', 'eb-credito-rural' );
		}
		if ( 'sim' === $clean['contratos_venda'] && empty( $clean['contratos_detalhes'] ) ) {
			$errors['contratos_detalhes'] = __( 'Descreva os contratos de venda futura/barter.', 'eb-credito-rural' );
		}
		if ( 'sim' === $clean['seguro_rural'] && empty( $clean['seguro_tipo'] ) ) {
			$errors['seguro_tipo'] = __( 'Informe o tipo de seguro.', 'eb-credito-rural' );
		}
		return array( $clean, $errors, $warnings );
	}

	/**
	 * Etapa 4 — financeiro.
	 *
	 * @param array $in Entrada.
	 * @return array
	 */
	private static function step4( array $in ) {
		$min                    = (float) Options::get( 'min_amount', 0 );
		$max                    = (float) Options::get( 'max_amount', 0 );
		$tmin                   = max( 1, Options::int( 'min_term_months' ) );
		$tmax                   = max( $tmin, Options::int( 'max_term_months' ) );
		$fields                 = array(
			'receita_ano1'     => array(
				'label' => __( 'Receita bruta — último ano', 'eb-credito-rural' ),
				'rules' => array( 'required', 'amount:0,' ),
				'type'  => 'amount',
			),
			'receita_ano2'     => array(
				'label' => __( 'Receita bruta — ano anterior', 'eb-credito-rural' ),
				'rules' => array( 'amount:0,' ),
				'type'  => 'amount',
			),
			'receita_ano3'     => array(
				'label' => __( 'Receita bruta — dois anos antes', 'eb-credito-rural' ),
				'rules' => array( 'amount:0,' ),
				'type'  => 'amount',
			),
			'valor_solicitado' => array(
				'label' => __( 'Valor solicitado (R$)', 'eb-credito-rural' ),
				'rules' => array( 'required', 'amount:' . $min . ',' . ( $max > 0 ? $max : '' ) ),
				'type'  => 'amount',
			),
			'finalidade'       => array(
				'label' => __( 'Finalidade', 'eb-credito-rural' ),
				'rules' => array( 'required', 'in:' . implode( '|', array_keys( self::options( 'purposes' ) ) ) ),
			),
			'prazo_meses'      => array(
				'label' => __( 'Prazo desejado (meses)', 'eb-credito-rural' ),
				'rules' => array( 'required', 'int:' . $tmin . ',' . $tmax ),
				'type'  => 'int',
			),
			'epoca_pagamento'  => array(
				'label' => __( 'Época preferida de pagamento', 'eb-credito-rural' ),
				'rules' => array( 'required', 'in:' . implode( '|', array_keys( self::options( 'epoca_pagamento' ) ) ) ),
			),
			'epoca_detalhes'   => array(
				'label' => __( 'Detalhes da época de pagamento', 'eb-credito-rural' ),
				'rules' => array( 'max:500' ),
			),
		);
		list( $clean, $errors ) = Validator::run( $fields, $in );
		$clean['dividas']       = self::repeat(
			isset( $in['dividas'] ) ? $in['dividas'] : array(),
			array(
				'credor'        => array(
					'label' => __( 'Credor', 'eb-credito-rural' ),
					'rules' => array( 'required', 'max:190' ),
				),
				'saldo'         => array(
					'label' => __( 'Saldo devedor (R$)', 'eb-credito-rural' ),
					'rules' => array( 'required', 'amount:0,' ),
					'type'  => 'amount',
				),
				'parcela'       => array(
					'label' => __( 'Parcela (R$)', 'eb-credito-rural' ),
					'rules' => array( 'required', 'amount:0,' ),
					'type'  => 'amount',
				),
				'periodicidade' => array(
					'label' => __( 'Periodicidade', 'eb-credito-rural' ),
					'rules' => array( 'required', 'in:mensal|semestral|anual' ),
				),
				'vencimento'    => array(
					'label' => __( 'Vencimento final', 'eb-credito-rural' ),
					'rules' => array( 'required', 'date' ),
					'type'  => 'date',
				),
				'garantia'      => array(
					'label' => __( 'Garantia dada', 'eb-credito-rural' ),
					'rules' => array( 'max:190' ),
				),
			),
			'dividas',
			$errors
		);
		// Indicadores para o analista (não exibidos ao cliente).
		$receitas = array_filter(
			array( $clean['receita_ano1'], $clean['receita_ano2'], $clean['receita_ano3'] ),
			static function ( $v ) {
				return null !== $v;
			}
		);
		$media    = $receitas ? array_sum( $receitas ) / count( $receitas ) : 0;
		$anual    = 0;
		$saldo    = 0;
		foreach ( $clean['dividas'] as $d ) {
			$mult   = 'mensal' === $d['periodicidade'] ? 12 : ( 'semestral' === $d['periodicidade'] ? 2 : 1 );
			$anual += (float) $d['parcela'] * $mult;
			$saldo += (float) $d['saldo'];
		}
		$clean['_indicadores'] = array(
			'receita_media'      => round( $media, 2 ),
			'parcelas_anuais'    => round( $anual, 2 ),
			'saldo_dividas'      => round( $saldo, 2 ),
			'comprometimento'    => $media > 0 ? round( 100 * $anual / $media, 1 ) : null,
			'divida_receita'     => $media > 0 ? round( $saldo / $media, 2 ) : null,
			'solicitado_receita' => $media > 0 && null !== $clean['valor_solicitado'] ? round( $clean['valor_solicitado'] / $media, 2 ) : null,
		);
		if ( 'outro' === $clean['epoca_pagamento'] && empty( $clean['epoca_detalhes'] ) ) {
			$errors['epoca_detalhes'] = __( 'Descreva a época de pagamento desejada.', 'eb-credito-rural' );
		}
		return array( $clean, $errors, array() );
	}

	/**
	 * Etapa 5 — garantias.
	 *
	 * @param array $in Entrada.
	 * @return array
	 */
	private static function step5( array $in ) {
		$errors = array();
		$types  = array_keys( self::options( 'guarantee_types' ) );
		$items  = self::repeat(
			isset( $in['garantias'] ) ? $in['garantias'] : array(),
			array(
				'type'           => array(
					'label' => __( 'Tipo de garantia', 'eb-credito-rural' ),
					'rules' => array( 'required', 'in:' . implode( '|', $types ) ),
				),
				'description'    => array(
					'label' => __( 'Descrição', 'eb-credito-rural' ),
					'rules' => array( 'required', 'max:2000' ),
					'type'  => 'textarea',
				),
				'property_id'    => array(
					'label' => __( 'Imóvel vinculado', 'eb-credito-rural' ),
					'rules' => array( 'int:0,' ),
					'type'  => 'int',
				),
				'declared_value' => array(
					'label' => __( 'Valor declarado (R$)', 'eb-credito-rural' ),
					'rules' => array( 'required', 'amount:0.01,' ),
					'type'  => 'amount',
				),
			),
			'garantias',
			$errors
		);
		$real   = self::options( 'real_guarantees' );
		foreach ( $items as $i => $item ) {
			if ( in_array( $item['type'], $real, true ) && empty( $item['property_id'] ) ) {
				$errors[ "garantias.{$i}.property_id" ] = __( 'Selecione o imóvel vinculado à garantia real.', 'eb-credito-rural' );
			}
		}
		if ( ! $items ) {
			$errors['garantias'] = __( 'Informe pelo menos uma garantia.', 'eb-credito-rural' );
		}
		return array(
			array(
				'garantias'      => $items,
				'real_guarantee' => (bool) array_filter(
					$items,
					static function ( $g ) use ( $real ) {
						return in_array( $g['type'], $real, true );
					}
				),
			),
			$errors,
			array(),
		);
	}

	/**
	 * Valida grupo repetível; erros com chave "grupo.i.campo". Linhas totalmente vazias são ignoradas.
	 *
	 * @param mixed  $rows   Entrada (array de linhas).
	 * @param array  $fields Campos.
	 * @param string $group  Nome do grupo.
	 * @param array  $errors Erros (por referência).
	 * @return array
	 */
	private static function repeat( $rows, array $fields, $group, array &$errors ) {
		$out = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		$i = 0;
		foreach ( array_values( $rows ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$non_empty = array_filter(
				$row,
				static function ( $v, $k ) {
					return 'id' !== $k && ( is_array( $v ) ? array_filter( $v ) : '' !== trim( (string) $v ) );
				},
				ARRAY_FILTER_USE_BOTH
			);
			if ( ! $non_empty ) {
				continue;
			}
			list( $clean, $errs ) = Validator::run( $fields, $row );
			foreach ( $errs as $f => $msg ) {
				$errors[ "{$group}.{$i}.{$f}" ] = $msg;
			}
			$out[] = $clean;
			++$i;
			if ( $i >= 50 ) {
				break;
			}
		}
		return $out;
	}
}
