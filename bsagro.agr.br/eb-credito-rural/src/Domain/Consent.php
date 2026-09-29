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
				'title'      => 'Política de Privacidade e tratamento de dados (LGPD)',
				'version'    => '1.0',
				'page_id'    => 0,
				'page_slug'  => 'aviso-de-privacidade',
				'page_slugs' => array( 'aviso-de-privacidade', 'politica-de-privacidade' ),
				'wp_privacy' => true,
				'text'       => 'Declaro que li e concordo com a Política de Privacidade, que descreve como meus dados pessoais são tratados para fins de análise de crédito (procedimentos preliminares ao contrato), cumprimento de obrigações legais e regulatórias e proteção do crédito.',
				'required'   => true,
				'moment'     => 'both',
			),
			'termos'         => array(
				'title'      => 'Termos de uso da plataforma',
				'version'    => '1.0',
				'page_id'    => 0,
				'page_slug'  => 'termos-de-uso',
				'page_slugs' => array( 'termos-de-uso' ),
				'text'       => 'Declaro que li e aceito os Termos de Uso da plataforma: o envio de uma solicitação não garante aprovação de crédito; as informações serão analisadas pela equipe e a decisão é sempre humana; devo manter meus dados de acesso em sigilo e informar alterações relevantes.',
				'required'   => true,
				'moment'     => 'both',
			),
			'scr'            => array(
				'title'      => 'Autorização de consulta ao SCR/Bacen e birôs de crédito',
				'version'    => '1.0',
				'page_id'    => 0,
				'page_slug'  => 'autorizacao-consulta-scr',
				'page_slugs' => array( 'autorizacao-consulta-scr' ),
				'text'       => 'Autorizo a consulta das minhas informações no Sistema de Informações de Crédito do Banco Central (SCR) e em birôs de crédito (como Serasa e SPC), bem como o registro de dados sobre esta operação nesses sistemas, conforme a regulamentação vigente, para fins de análise desta solicitação.',
				'required'   => true,
				'moment'     => 'both',
			),
			'veracidade'     => array(
				'title'      => 'Declaração de veracidade das informações e documentos',
				'version'    => '1.0',
				'page_id'    => 0,
				'page_slug'  => 'declaracao-de-veracidade',
				'page_slugs' => array( 'declaracao-de-veracidade' ),
				'text'       => 'Declaro, sob as penas da lei, que todas as informações prestadas e os documentos enviados são verdadeiros, completos e atuais, e me comprometo a comunicar qualquer alteração.',
				'required'   => true,
				'moment'     => 'both',
			),
			'socioambiental' => array(
				'title'      => 'Declaração socioambiental',
				'version'    => '1.0',
				'page_id'    => 0,
				'page_slug'  => 'politica-de-esg',
				'page_slugs' => array( 'politica-de-esg', 'politica-socioambiental' ),
				'text'       => 'Declaro, conforme meu conhecimento, que as atividades e os imóveis informados não empregam trabalho análogo ao escravo ou infantil, não possuem embargos ambientais vigentes e não estão associados a desmatamento ilegal, comprometendo-me a observar a legislação socioambiental aplicável.',
				'required'   => true,
				'moment'     => 'both',
			),
			'anticorrupcao'  => array(
				'title'      => 'Declaração anticorrupção e de conformidade',
				'version'    => '1.0',
				'page_id'    => 0,
				'page_slug'  => 'politica-de-compliance-anticorrupcao',
				'page_slugs' => array( 'politica-de-compliance-anticorrupcao', 'politica-de-compliance' ),
				'text'       => 'Declaro conhecer e cumprir a Política de Compliance e Anticorrupção, não oferecer ou receber vantagens indevidas relacionadas a esta operação e informar imediatamente qualquer conflito de interesse.',
				'required'   => true,
				'moment'     => 'both',
			),
			'marketing'      => array(
				'title'      => 'Comunicações de marketing (opcional)',
				'version'    => '1.0',
				'page_id'    => 0,
				'page_slug'  => 'comunicacoes-de-marketing',
				'page_slugs' => array( 'comunicacoes-de-marketing' ),
				'text'       => 'Aceito receber comunicações sobre produtos, conteúdos e novidades. Posso cancelar a qualquer momento.',
				'required'   => false,
				'moment'     => 'both',
			),
		);
	}

	/**
	 * Políticas efetivas (padrão + configuração). Sem página escolhida em Configurações → Privacidade e compliance,
	 * procura a primeira página publicada entre os slugs candidatos (ex.: privacidade → aviso-de-privacidade,
	 * politica-de-privacidade e, por fim, a página de privacidade definida em Configurações → Privacidade do WordPress).
	 * Os links sempre usam get_permalink(), então funcionam com links permanentes simples (?page_id=) ou amigáveis.
	 *
	 * @return array
	 */
	public static function policies() {
		$defaults = self::default_policies();
		$saved    = Options::get( 'policies', array() );
		$out      = array();
		foreach ( $defaults as $key => $def ) {
			$p = array_merge( $def, isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array() );
			if ( ! empty( $p['page_id'] ) && 'publish' !== get_post_status( (int) $p['page_id'] ) ) {
				$p['page_id'] = 0;
			}
			if ( empty( $p['page_id'] ) ) {
				$p['page_id'] = self::find_page( self::candidate_slugs( $key, $p ), ! empty( $def['wp_privacy'] ) );
			}
			$p['page_id']  = (int) $p['page_id'];
			$p['version']  = (string) $p['version'];
			$p['required'] = ! empty( $p['required'] );
			$out[ $key ]   = $p;
		}
		return $out;
	}

	/**
	 * Slugs candidatos de uma política (padrão + filtro ebcr_policy_page_slugs).
	 *
	 * @param string $key Chave da política.
	 * @param array  $p   Política.
	 * @return string[]
	 */
	private static function candidate_slugs( $key, array $p ) {
		$slugs = isset( $p['page_slugs'] ) && is_array( $p['page_slugs'] ) ? $p['page_slugs'] : array();
		if ( ! empty( $p['page_slug'] ) ) {
			$slugs[] = (string) $p['page_slug'];
		}
		/**
		 * Slugs de página procurados (em ordem) quando a política não tem página escolhida nas configurações.
		 *
		 * @param string[] $slugs Slugs.
		 * @param string   $key   Chave da política (privacidade, termos, scr, veracidade, socioambiental, anticorrupcao, marketing).
		 */
		return array_values( array_unique( array_filter( array_map( 'sanitize_title', (array) apply_filters( 'ebcr_policy_page_slugs', $slugs, $key ) ) ) ) );
	}

	/**
	 * Primeira página publicada entre os slugs; opcionalmente cai na página de privacidade do WordPress.
	 *
	 * @param string[] $slugs      Slugs em ordem de preferência.
	 * @param bool     $wp_privacy Usar wp_page_for_privacy_policy como último recurso.
	 * @return int ID ou 0.
	 */
	public static function find_page( array $slugs, $wp_privacy = false ) {
		foreach ( $slugs as $slug ) {
			$page = get_page_by_path( (string) $slug, OBJECT, 'page' );
			if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
				return (int) $page->ID;
			}
		}
		if ( $wp_privacy ) {
			$id = (int) get_option( 'wp_page_for_privacy_policy', 0 );
			if ( $id && 'publish' === get_post_status( $id ) ) {
				return $id;
			}
		}
		return 0;
	}

	/**
	 * Páginas legais complementares exibidas na área do cliente (Privacidade): chave da configuração =>
	 * [título, slugs candidatos]. A página escolhida em Configurações → Privacidade e compliance prevalece.
	 *
	 * @return array
	 */
	public static function legal_pages() {
		return array(
			'legal_page_titular'         => array( __( 'Portal do titular (direitos LGPD)', 'eb-credito-rural' ), array( 'portal-do-titular' ) ),
			'legal_page_cookies'         => array( __( 'Política de cookies', 'eb-credito-rural' ), array( 'politica-de-cookies' ) ),
			'legal_page_comercializacao' => array( __( 'Política de comercialização', 'eb-credito-rural' ), array( 'politica-de-comercializacao' ) ),
			'legal_page_integridade'     => array( __( 'Canal de integridade', 'eb-credito-rural' ), array( 'canal-de-integridade' ) ),
		);
	}

	/**
	 * Links das páginas legais complementares que existem no site: chave => [title, url, page_id].
	 *
	 * @return array
	 */
	public static function legal_links() {
		$out = array();
		foreach ( self::legal_pages() as $key => $def ) {
			$id = (int) Options::get( $key, 0 );
			if ( ! $id || 'publish' !== get_post_status( $id ) ) {
				$id = self::find_page( (array) apply_filters( 'ebcr_policy_page_slugs', $def[1], $key ) );
			}
			if ( $id ) {
				$out[ $key ] = array(
					'title'   => $def[0],
					'url'     => (string) get_permalink( $id ),
					'page_id' => $id,
				);
			}
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
