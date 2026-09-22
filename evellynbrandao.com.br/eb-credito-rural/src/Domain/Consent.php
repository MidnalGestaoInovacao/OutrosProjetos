<?php
/**
 * Políticas, declarações e prova de aceite.
 *
 * @package EBCR
 */

namespace EBCR\Domain;

use EBCR\Database\ConsentRepository;
use EBCR\Support\Ip;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Aceites versionados com hash do texto.
 */
final class Consent {

	/**
	 * Políticas padrão (chave => título, versão, página, texto, obrigatória, momento).
	 * "moment": register (cadastro), submit (etapa 7) ou both.
	 *
	 * @return array
	 */
	public static function default_policies() {
		return array(
			'privacidade'    => array(
				'title'     => 'Política de Privacidade e tratamento de dados (LGPD)',
				'version'   => '1.0',
				'page_id'   => 0,
				'page_slug' => 'politica-de-privacidade',
				'text'      => 'Declaro que li e concordo com a Política de Privacidade, que descreve como meus dados pessoais são tratados para fins de análise de crédito (procedimentos preliminares ao contrato), cumprimento de obrigações legais e regulatórias e proteção do crédito.',
				'required'  => true,
				'moment'    => 'both',
			),
			'termos'         => array(
				'title'     => 'Termos de uso da plataforma',
				'version'   => '1.0',
				'page_id'   => 0,
				'page_slug' => '',
				'text'      => 'Declaro que li e aceito os Termos de Uso da plataforma: o envio de uma solicitação não garante aprovação de crédito; as informações serão analisadas pela equipe e a decisão é sempre humana; devo manter meus dados de acesso em sigilo e informar alterações relevantes.',
				'required'  => true,
				'moment'    => 'both',
			),
			'scr'            => array(
				'title'     => 'Autorização de consulta ao SCR/Bacen e birôs de crédito',
				'version'   => '1.0',
				'page_id'   => 0,
				'page_slug' => '',
				'text'      => 'Autorizo a consulta das minhas informações no Sistema de Informações de Crédito do Banco Central (SCR) e em birôs de crédito (como Serasa e SPC), bem como o registro de dados sobre esta operação nesses sistemas, conforme a regulamentação vigente, para fins de análise desta solicitação.',
				'required'  => true,
				'moment'    => 'submit',
			),
			'veracidade'     => array(
				'title'     => 'Declaração de veracidade das informações e documentos',
				'version'   => '1.0',
				'page_id'   => 0,
				'page_slug' => '',
				'text'      => 'Declaro, sob as penas da lei, que todas as informações prestadas e os documentos enviados são verdadeiros, completos e atuais, e me comprometo a comunicar qualquer alteração.',
				'required'  => true,
				'moment'    => 'submit',
			),
			'socioambiental' => array(
				'title'     => 'Declaração socioambiental',
				'version'   => '1.0',
				'page_id'   => 0,
				'page_slug' => 'politica-de-esg',
				'text'      => 'Declaro, conforme meu conhecimento, que as atividades e os imóveis informados não empregam trabalho análogo ao escravo ou infantil, não possuem embargos ambientais vigentes e não estão associados a desmatamento ilegal, comprometendo-me a observar a legislação socioambiental aplicável.',
				'required'  => true,
				'moment'    => 'submit',
			),
			'anticorrupcao'  => array(
				'title'     => 'Declaração anticorrupção e de conformidade',
				'version'   => '1.0',
				'page_id'   => 0,
				'page_slug' => 'politica-de-compliance-anticorrupcao',
				'text'      => 'Declaro conhecer e cumprir a Política de Compliance e Anticorrupção, não oferecer ou receber vantagens indevidas relacionadas a esta operação e informar imediatamente qualquer conflito de interesse.',
				'required'  => true,
				'moment'    => 'submit',
			),
			'marketing'      => array(
				'title'     => 'Comunicações de marketing (opcional)',
				'version'   => '1.0',
				'page_id'   => 0,
				'page_slug' => '',
				'text'      => 'Aceito receber comunicações sobre produtos, conteúdos e novidades. Posso cancelar a qualquer momento.',
				'required'  => false,
				'moment'    => 'both',
			),
		);
	}

	/**
	 * Políticas efetivas (padrão + configuração), resolvendo página pelo slug quando não configurada.
	 *
	 * @return array
	 */
	public static function policies() {
		$defaults = self::default_policies();
		$saved    = Options::get( 'policies', array() );
		$out      = array();
		foreach ( $defaults as $key => $def ) {
			$p = array_merge( $def, isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array() );
			if ( empty( $p['page_id'] ) && ! empty( $p['page_slug'] ) ) {
				$page = get_page_by_path( $p['page_slug'] );
				if ( $page ) {
					$p['page_id'] = (int) $page->ID;
				}
			}
			$p['version']  = (string) $p['version'];
			$p['required'] = ! empty( $p['required'] );
			$out[ $key ]   = $p;
		}
		return $out;
	}

	/**
	 * Políticas exigidas em um momento (register|submit).
	 *
	 * @param string $moment Momento.
	 * @return array
	 */
	public static function for_moment( $moment ) {
		$out = array();
		foreach ( self::policies() as $key => $p ) {
			if ( 'both' === $p['moment'] || $moment === $p['moment'] ) {
				$out[ $key ] = $p;
			}
		}
		return $out;
	}

	/**
	 * Texto completo (para hash): texto da declaração + conteúdo da página vinculada.
	 *
	 * @param string $key Chave.
	 * @return string
	 */
	public static function full_text( $key ) {
		$p = self::policies();
		if ( ! isset( $p[ $key ] ) ) {
			return '';
		}
		$text = (string) $p[ $key ]['text'];
		if ( ! empty( $p[ $key ]['page_id'] ) ) {
			$page = get_post( (int) $p[ $key ]['page_id'] );
			if ( $page ) {
				$text .= "\n\n" . wp_strip_all_tags( $page->post_content );
			}
		}
		return $text;
	}

	/**
	 * Hash SHA-256 do texto vigente.
	 *
	 * @param string $key Chave.
	 * @return string
	 */
	public static function hash( $key ) {
		return hash( 'sha256', self::full_text( $key ) );
	}

	/**
	 * URL do texto completo (página) ou vazio.
	 *
	 * @param string $key Chave.
	 * @return string
	 */
	public static function url( $key ) {
		$p = self::policies();
		return ! empty( $p[ $key ]['page_id'] ) ? (string) get_permalink( (int) $p[ $key ]['page_id'] ) : '';
	}

	/**
	 * Registra aceites de um usuário.
	 *
	 * @param int      $user_id       Usuário.
	 * @param string[] $keys          Chaves aceitas.
	 * @param int|null $submission_id Solicitação (opcional).
	 * @return void
	 */
	public static function record( $user_id, array $keys, $submission_id = null ) {
		$repo     = new ConsentRepository();
		$policies = self::policies();
		foreach ( $keys as $key ) {
			if ( ! isset( $policies[ $key ] ) ) {
				continue;
			}
			$repo->insert(
				array(
					'user_id'        => (int) $user_id,
					'submission_id'  => $submission_id ? (int) $submission_id : null,
					'policy_key'     => $key,
					'policy_version' => $policies[ $key ]['version'],
					'policy_hash'    => self::hash( $key ),
					'accepted_at'    => current_time( 'mysql', true ),
					'ip'             => Ip::get(),
					'user_agent'     => Ip::user_agent(),
				)
			);
		}
	}

	/**
	 * Políticas obrigatórias cuja versão vigente ainda não foi aceita pelo usuário (novo aceite exigido).
	 *
	 * @param int $user_id Usuário.
	 * @return array chave => política
	 */
	public static function pending_reaccept( $user_id ) {
		$repo   = new ConsentRepository();
		$latest = $repo->latest_versions( (int) $user_id );
		$out    = array();
		foreach ( self::for_moment( 'register' ) as $key => $p ) {
			if ( ! $p['required'] ) {
				continue;
			}
			if ( ! isset( $latest[ $key ] ) || $latest[ $key ] !== $p['version'] ) {
				$out[ $key ] = $p;
			}
		}
		return $out;
	}
}
