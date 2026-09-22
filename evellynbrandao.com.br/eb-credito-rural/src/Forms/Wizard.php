<?php
/**
 * Formulário em etapas: persistência de rascunho, validação por etapa e envio final.
 *
 * @package EBCR
 */

namespace EBCR\Forms;

use EBCR\Database\DocumentRepository;
use EBCR\Database\GuaranteeRepository;
use EBCR\Database\PropertyRepository;
use EBCR\Database\SubmissionDataRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Consent;
use EBCR\Domain\Status;
use EBCR\Security\Authorization;

defined( 'ABSPATH' ) || exit;

/**
 * Fonte da verdade da validação (o JS só melhora a experiência).
 */
final class Wizard {

	/**
	 * Repositórios.
	 *
	 * @var SubmissionRepository
	 */
	private $submissions;

	/**
	 * Dados.
	 *
	 * @var SubmissionDataRepository
	 */
	private $data;

	/**
	 * Construtor.
	 */
	public function __construct() {
		$this->submissions = new SubmissionRepository();
		$this->data        = new SubmissionDataRepository();
	}

	/**
	 * Dados salvos de todas as seções (+ imóveis e garantias das tabelas próprias).
	 *
	 * @param array $submission Linha.
	 * @return array
	 */
	public function saved( array $submission ) {
		$all              = $this->data->all( (int) $submission['id'] );
		$all['imoveis']   = array( 'imoveis' => ( new PropertyRepository() )->for_submission( (int) $submission['id'] ) );
		$all['garantias'] = array_merge( isset( $all['garantias'] ) ? $all['garantias'] : array(), array( 'garantias' => ( new GuaranteeRepository() )->for_submission( (int) $submission['id'] ) ) );
		return $all;
	}

	/**
	 * Processa uma etapa (1–5). Retorna [ok(bool), errors, warnings].
	 *
	 * @param int   $user_id    Usuário.
	 * @param array $submission Linha.
	 * @param int   $step       Etapa.
	 * @param array $input      Entrada bruta.
	 * @param bool  $draft      Salvar como rascunho mesmo com erros.
	 * @return array
	 */
	public function handle_step( $user_id, array $submission, $step, array $input, $draft = false ) {
		if ( ! Authorization::client_can_edit( $user_id, $submission ) ) {
			return array( false, array( '_' => __( 'Esta solicitação não pode ser editada.', 'eb-credito-rural' ) ), array() );
		}
		$step = (int) $step;
		if ( $step < 1 || $step > 5 ) {
			return array( false, array( '_' => __( 'Etapa inválida.', 'eb-credito-rural' ) ), array() );
		}
		list( $clean, $errors, $warnings ) = Steps::validate( $step, $input );
		if ( $errors && ! $draft ) {
			// Mesmo com erros, salva o rascunho (sem avançar) para o cliente não perder o que digitou.
			$this->persist( $submission, $step, $clean, true );
			return array( false, $errors, $warnings );
		}
		$this->persist( $submission, $step, $clean, (bool) $errors );
		if ( ! $errors ) {
			$next = max( (int) $submission['current_step'], min( 7, $step + 1 ) );
			$this->submissions->update( (int) $submission['id'], array( 'current_step' => $next ) );
		}
		return array( ! $errors, $errors, $warnings );
	}

	/**
	 * Persiste os dados de uma etapa.
	 *
	 * @param array $submission Linha.
	 * @param int   $step       Etapa.
	 * @param array $clean      Dados limpos.
	 * @param bool  $incomplete Marcado como incompleto.
	 * @return void
	 */
	private function persist( array $submission, $step, array $clean, $incomplete ) {
		$key                  = Steps::all()[ $step ]['key'];
		$clean['_incomplete'] = $incomplete;
		$id                   = (int) $submission['id'];
		if ( 2 === $step ) {
			$ids = ( new PropertyRepository() )->replace_all( $id, $clean['imoveis'] );
			$this->data->save(
				$id,
				$key,
				array(
					'_incomplete' => $incomplete,
					'count'       => count( $ids ),
				)
			);
			return;
		}
		if ( 5 === $step ) {
			( new GuaranteeRepository() )->replace_all( $id, $clean['garantias'] );
			$this->data->save(
				$id,
				$key,
				array(
					'_incomplete'    => $incomplete,
					'real_guarantee' => ! empty( $clean['real_guarantee'] ),
					'count'          => count( $clean['garantias'] ),
				)
			);
			return;
		}
		$this->data->save( $id, $key, $clean );
		if ( 1 === $step ) {
			$this->submissions->update( $id, array( 'person_type' => $clean['person_type'] ) );
		}
		if ( 4 === $step ) {
			$this->submissions->update(
				$id,
				array(
					'requested_amount' => $clean['valor_solicitado'],
					'purpose'          => $clean['finalidade'],
					'term_months'      => $clean['prazo_meses'],
				)
			);
		}
	}

	/**
	 * Contexto para a matriz de documentos.
	 *
	 * @param array $submission Linha.
	 * @return array
	 */
	public function context( array $submission ) {
		$saved = $this->saved( $submission );
		$ident = isset( $saved['identificacao'] ) ? $saved['identificacao'] : array();
		$prod  = isset( $saved['producao'] ) ? $saved['producao'] : array();
		$gar   = isset( $saved['garantias'] ) ? $saved['garantias'] : array();
		return array(
			'person_type'    => isset( $ident['person_type'] ) ? $ident['person_type'] : ( $submission['person_type'] ? $submission['person_type'] : 'PF' ),
			'married'        => ! empty( $ident['married'] ),
			'properties'     => $saved['imoveis']['imoveis'],
			'livestock'      => ! empty( $prod['livestock'] ),
			'insurance'      => isset( $prod['seguro_rural'] ) && 'sim' === $prod['seguro_rural'],
			'environmental'  => isset( $prod['licenca_ambiental'] ) && 'sim' === $prod['licenca_ambiental'],
			'real_guarantee' => ! empty( $gar['real_guarantee'] ),
		);
	}

	/**
	 * Slots de documentos com os arquivos já enviados.
	 *
	 * @param array $submission Linha.
	 * @return array{slots:array,required_total:int,required_done:int}
	 */
	public function document_slots( array $submission ) {
		$slots = DocumentMatrix::slots( $this->context( $submission ) );
		$docs  = ( new DocumentRepository() )->for_submission( (int) $submission['id'] );
		$total = 0;
		$done  = 0;
		foreach ( $slots as &$slot ) {
			$slot['documents'] = array();
			foreach ( $docs as $d ) {
				if ( $d['doc_type'] === $slot['type'] && ( '' === $slot['ref_key'] || $d['ref_key'] === $slot['ref_key'] ) ) {
					$slot['documents'][] = $d;
				}
			}
			$slot['satisfied'] = (bool) array_filter(
				$slot['documents'],
				static function ( $d ) {
					return 'recusado' !== $d['review_status'];
				}
			);
			if ( $slot['required'] ) {
				++$total;
				if ( $slot['satisfied'] ) {
					++$done;
				}
			}
		}
		unset( $slot );
		// Documentos que não pertencem a slot algum (tipo "outro" ou referências antigas).
		$others = array();
		foreach ( $docs as $d ) {
			$in_slot = false;
			foreach ( $slots as $slot ) {
				if ( $d['doc_type'] === $slot['type'] && ( '' === $slot['ref_key'] || $d['ref_key'] === $slot['ref_key'] ) ) {
					$in_slot = true;
					break;
				}
			}
			if ( ! $in_slot ) {
				$others[] = $d;
			}
		}
		return array(
			'slots'          => $slots,
			'others'         => $others,
			'required_total' => $total,
			'required_done'  => $done,
		);
	}

	/**
	 * Revalida todas as etapas a partir dos dados salvos. Retorna erros por etapa.
	 *
	 * @param array $submission Linha.
	 * @return array etapa => erros
	 */
	public function validate_all( array $submission ) {
		$saved  = $this->saved( $submission );
		$errors = array();
		foreach ( range( 1, 5 ) as $step ) {
			$key   = Steps::all()[ $step ]['key'];
			$input = isset( $saved[ $key ] ) ? $saved[ $key ] : array();
			if ( 2 === $step ) {
				$input = array( 'imoveis' => self::rows_for_revalidation( $saved['imoveis']['imoveis'] ) );
			}
			if ( 5 === $step ) {
				$input = array( 'garantias' => $saved['garantias']['garantias'] );
			}
			$input          = self::flatten_for_revalidation( $input );
			list( , $errs ) = Steps::validate( $step, $input );
			if ( $errs ) {
				$errors[ $step ] = $errs;
			}
		}
		$slots = $this->document_slots( $submission );
		if ( $slots['required_done'] < $slots['required_total'] ) {
			$missing = array();
			foreach ( $slots['slots'] as $s ) {
				if ( $s['required'] && ! $s['satisfied'] ) {
					$missing[] = $s['label'] . ( $s['ref_label'] ? ' (' . $s['ref_label'] . ')' : '' );
				}
			}
			$errors[6] = array( 'documentos' => sprintf( /* translators: %s: lista */ __( 'Faltam documentos obrigatórios: %s.', 'eb-credito-rural' ), implode( '; ', $missing ) ) );
		}
		return $errors;
	}

	/**
	 * Converte linhas do banco em entrada para revalidação.
	 *
	 * @param array $rows Linhas.
	 * @return array
	 */
	private static function rows_for_revalidation( array $rows ) {
		$out = array();
		foreach ( $rows as $r ) {
			$out[] = array_map(
				static function ( $v ) {
					return null === $v ? '' : (string) $v;
				},
				$r
			);
		}
		return $out;
	}

	/**
	 * Converte valores tipados (bool/null/float) de volta em strings para o validador.
	 *
	 * @param array $data Dados.
	 * @return array
	 */
	private static function flatten_for_revalidation( array $data ) {
		$out = array();
		foreach ( $data as $k => $v ) {
			if ( is_array( $v ) ) {
				$out[ $k ] = isset( $v[0] ) && is_array( $v[0] ) ? array_map( array( __CLASS__, 'flatten_for_revalidation' ), $v ) : $v;
			} elseif ( is_bool( $v ) ) {
				$out[ $k ] = $v ? '1' : '0';
			} elseif ( null === $v ) {
				$out[ $k ] = '';
			} else {
				$out[ $k ] = (string) $v;
			}
		}
		return $out;
	}

	/**
	 * Resumo para a etapa 7.
	 *
	 * @param array $submission Linha.
	 * @return array
	 */
	public function summary( array $submission ) {
		$saved = $this->saved( $submission );
		$ident = isset( $saved['identificacao'] ) ? $saved['identificacao'] : array();
		$fin   = isset( $saved['financeiro'] ) ? $saved['financeiro'] : array();
		$prod  = isset( $saved['producao'] ) ? $saved['producao'] : array();
		$acts  = Steps::options( 'activities' );
		return array(
			'tomador'    => 'PJ' === ( isset( $ident['person_type'] ) ? $ident['person_type'] : 'PF' ) ? ( isset( $ident['razao_social'] ) ? $ident['razao_social'] : '' ) : ( isset( $ident['nome'] ) ? $ident['nome'] : '' ),
			'documento'  => isset( $ident['cnpj'] ) && $ident['cnpj'] ? \EBCR\Support\Helpers::format_document( $ident['cnpj'] ) : ( isset( $ident['cpf'] ) ? \EBCR\Support\Helpers::format_document( $ident['cpf'] ) : '' ),
			'imoveis'    => count( $saved['imoveis']['imoveis'] ),
			'atividades' => implode(
				', ',
				array_map(
					static function ( $a ) use ( $acts ) {
						return isset( $acts[ $a ] ) ? $acts[ $a ] : $a; },
					isset( $prod['atividades'] ) ? (array) $prod['atividades'] : array()
				)
			),
			'valor'      => isset( $fin['valor_solicitado'] ) ? $fin['valor_solicitado'] : null,
			'finalidade' => isset( $fin['finalidade'] ) ? ( Steps::options( 'purposes' )[ $fin['finalidade'] ] ?? $fin['finalidade'] ) : '',
			'prazo'      => isset( $fin['prazo_meses'] ) ? $fin['prazo_meses'] : null,
			'garantias'  => count( $saved['garantias']['garantias'] ),
			'documentos' => $this->document_slots( $submission ),
		);
	}

	/**
	 * Envio final: revalida tudo, regras, aceites; muda status para "enviada".
	 *
	 * @param int   $user_id    Usuário.
	 * @param array $submission Linha.
	 * @param array $input      Entrada (aceites já verificados de captcha/honeypot pelo chamador).
	 * @return array|\WP_Error Linha atualizada.
	 */
	public function submit( $user_id, array $submission, array $input ) {
		if ( ! Authorization::owns( $user_id, $submission ) || Status::DRAFT !== $submission['status'] ) {
			return new \WP_Error( 'forbidden', __( 'Esta solicitação não pode ser enviada.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		$rules = SubmissionRules::can_submit( $user_id, (int) $submission['id'] );
		if ( ! $rules['allowed'] ) {
			return new \WP_Error(
				'rules',
				$rules['message'],
				array(
					'status' => 422,
					'reason' => $rules['reason'],
				)
			);
		}
		$errors = $this->validate_all( $submission );
		if ( $errors ) {
			return new \WP_Error(
				'incomplete',
				__( 'Há etapas com pendências. Revise os itens indicados antes de enviar.', 'eb-credito-rural' ),
				array(
					'status' => 422,
					'steps'  => $errors,
				)
			);
		}
		$accepted = array();
		$missing  = array();
		foreach ( Consent::for_moment( 'submit' ) as $key => $policy ) {
			$checked = ! empty( $input[ 'consent_' . $key ] );
			if ( $policy['required'] && ! $checked ) {
				$missing[] = $policy['title'];
			}
			if ( $checked ) {
				$accepted[] = $key;
			}
		}
		if ( $missing ) {
			return new \WP_Error( 'consents', sprintf( /* translators: %s: lista */ __( 'É necessário aceitar: %s.', 'eb-credito-rural' ), implode( '; ', $missing ) ), array( 'status' => 422 ) );
		}
		return \EBCR\Forms\SubmissionService::submit( $user_id, $submission, $accepted );
	}
}
