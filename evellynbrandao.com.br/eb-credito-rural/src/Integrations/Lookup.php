<?php
/**
 * Consultas automáticas de CEP (ViaCEP/BrasilAPI) e CNPJ (BrasilAPI) via REST do plugin, com cache. Entregue pelo módulo "Integrações".
 *
 * @package EBCR
 */

namespace EBCR\Integrations;

use EBCR\Forms\Validators\Rules;
use EBCR\Security\AuditLog;
use EBCR\Security\RateLimiter;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Rotas ebcr/v1/lookup/cep/{cep} e ebcr/v1/lookup/cnpj/{cnpj}.
 *
 * Só para usuários logados (o formulário exige login), com limite de 30 consultas por minuto por usuário.
 * As respostas válidas ficam em transient por 30 dias; falhas de rede nunca derrubam o formulário (o campo continua manual).
 */
final class Lookup {

	const TIMEOUT   = 6;
	const CACHE_TTL = 30 * DAY_IN_SECONDS;
	const RATE_MAX  = 30;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'ebcr_rest_routes', array( $this, 'routes' ) );
		// Depois de Frontend::register_assets (prioridade 10), que registra o handle ebcr-wizard.
		add_action( 'wp_enqueue_scripts', array( $this, 'inline_flags' ), 20 );
	}

	/**
	 * Registra as rotas no namespace do plugin.
	 *
	 * @param string $ns Namespace REST (ebcr/v1).
	 * @return void
	 */
	public function routes( $ns ) {
		register_rest_route(
			$ns,
			'/lookup/cep/(?P<cep>\d{8})',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_cep' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'cep' => array(
						'sanitize_callback' => array( Helpers::class, 'digits' ),
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/lookup/cnpj/(?P<cnpj>\d{14})',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_cnpj' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'cnpj' => array(
						'sanitize_callback' => array( Helpers::class, 'digits' ),
					),
				),
			)
		);
	}

	/**
	 * Expõe ao JS do formulário quais consultas estão ligadas (window.EBCR_LOOKUP).
	 *
	 * @return void
	 */
	public function inline_flags() {
		if ( ! wp_script_is( 'ebcr-wizard', 'registered' ) ) {
			return;
		}
		wp_add_inline_script(
			'ebcr-wizard',
			'window.EBCR_LOOKUP = ' . wp_json_encode(
				array(
					'cep'  => Options::bool( 'cep_lookup' ),
					'cnpj' => Options::bool( 'cnpj_lookup' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Usuário logado + limite por usuário.
	 *
	 * @return bool|\WP_Error
	 */
	public function permission() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'rest_forbidden', __( 'É necessário estar autenticado.', 'eb-credito-rural' ), array( 'status' => 401 ) );
		}
		if ( ! RateLimiter::hit( 'lookup', get_current_user_id(), self::RATE_MAX, MINUTE_IN_SECONDS ) ) {
			return new \WP_Error( 'rest_too_many', __( 'Muitas consultas em pouco tempo. Aguarde um minuto.', 'eb-credito-rural' ), array( 'status' => 429 ) );
		}
		return true;
	}

	/**
	 * GET /lookup/cep/{cep}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_cep( $request ) {
		$r = self::cep( (string) $request['cep'] );
		return is_wp_error( $r ) ? $r : rest_ensure_response( $r );
	}

	/**
	 * GET /lookup/cnpj/{cnpj}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_cnpj( $request ) {
		$r = self::cnpj( (string) $request['cnpj'] );
		return is_wp_error( $r ) ? $r : rest_ensure_response( $r );
	}

	/**
	 * Chave do transient de cache.
	 *
	 * @param string $kind  cep|cnpj.
	 * @param string $value Dígitos.
	 * @return string
	 */
	public static function cache_key( $kind, $value ) {
		return 'ebcr_lk_' . sanitize_key( $kind ) . '_' . Helpers::digits( $value );
	}

	/**
	 * Consulta um CEP: ViaCEP com fallback BrasilAPI. Resultado normalizado {cep, logradouro, bairro, cidade, uf, fonte}.
	 *
	 * @param string $cep CEP (8 dígitos, com ou sem máscara).
	 * @return array|\WP_Error
	 */
	public static function cep( $cep ) {
		if ( ! Options::bool( 'cep_lookup' ) ) {
			return new \WP_Error( 'lookup_disabled', __( 'Consulta de CEP desativada.', 'eb-credito-rural' ), array( 'status' => 404 ) );
		}
		$cep = Helpers::digits( $cep );
		if ( ! Rules::cep( $cep ) ) {
			return new \WP_Error( 'lookup_invalid', __( 'CEP inválido.', 'eb-credito-rural' ), array( 'status' => 400 ) );
		}
		$cached = get_transient( self::cache_key( 'cep', $cep ) );
		if ( is_array( $cached ) && ! empty( $cached['cidade'] ) ) {
			return $cached;
		}
		$found = false;
		// 1) ViaCEP.
		$json = self::fetch_json( 'https://viacep.com.br/ws/' . $cep . '/json/' );
		if ( is_array( $json ) && empty( $json['erro'] ) && ! empty( $json['localidade'] ) ) {
			$found = array(
				'cep'        => $cep,
				'logradouro' => self::text( isset( $json['logradouro'] ) ? $json['logradouro'] : '', 190 ),
				'bairro'     => self::text( isset( $json['bairro'] ) ? $json['bairro'] : '', 100 ),
				'cidade'     => self::text( $json['localidade'], 120 ),
				'uf'         => self::uf( isset( $json['uf'] ) ? $json['uf'] : '' ),
				'fonte'      => 'viacep',
			);
		}
		// 2) BrasilAPI (fallback quando ViaCEP falha ou não conhece o CEP).
		if ( ! $found ) {
			$json = self::fetch_json( 'https://brasilapi.com.br/api/cep/v2/' . $cep );
			if ( is_array( $json ) && ! empty( $json['city'] ) ) {
				$found = array(
					'cep'        => $cep,
					'logradouro' => self::text( isset( $json['street'] ) ? $json['street'] : '', 190 ),
					'bairro'     => self::text( isset( $json['neighborhood'] ) ? $json['neighborhood'] : '', 100 ),
					'cidade'     => self::text( $json['city'], 120 ),
					'uf'         => self::uf( isset( $json['state'] ) ? $json['state'] : '' ),
					'fonte'      => 'brasilapi',
				);
			} elseif ( is_wp_error( $json ) && 'lookup_not_found' !== $json->get_error_code() ) {
				self::audit_failure( 'cep', $json );
				return new \WP_Error( 'lookup_unavailable', __( 'Serviço de CEP indisponível no momento. Preencha o endereço manualmente.', 'eb-credito-rural' ), array( 'status' => 502 ) );
			}
		}
		if ( ! $found ) {
			return new \WP_Error( 'lookup_not_found', __( 'CEP não encontrado.', 'eb-credito-rural' ), array( 'status' => 404 ) );
		}
		set_transient( self::cache_key( 'cep', $cep ), $found, self::CACHE_TTL );
		return $found;
	}

	/**
	 * Consulta um CNPJ na BrasilAPI (dados públicos da Receita Federal).
	 * Resultado normalizado {cnpj, razao_social, nome_fantasia, logradouro, numero, complemento, bairro, cidade, uf, cep, situacao}.
	 *
	 * @param string $cnpj CNPJ (14 dígitos, com ou sem máscara).
	 * @return array|\WP_Error
	 */
	public static function cnpj( $cnpj ) {
		if ( ! Options::bool( 'cnpj_lookup' ) ) {
			return new \WP_Error( 'lookup_disabled', __( 'Consulta de CNPJ desativada.', 'eb-credito-rural' ), array( 'status' => 404 ) );
		}
		$cnpj = Helpers::digits( $cnpj );
		if ( ! Rules::cnpj( $cnpj ) ) {
			return new \WP_Error( 'lookup_invalid', __( 'CNPJ inválido.', 'eb-credito-rural' ), array( 'status' => 400 ) );
		}
		$cached = get_transient( self::cache_key( 'cnpj', $cnpj ) );
		if ( is_array( $cached ) && ! empty( $cached['razao_social'] ) ) {
			return $cached;
		}
		$json = self::fetch_json( 'https://brasilapi.com.br/api/cnpj/v1/' . $cnpj );
		if ( is_wp_error( $json ) ) {
			if ( 'lookup_not_found' === $json->get_error_code() ) {
				return new \WP_Error( 'lookup_not_found', __( 'CNPJ não encontrado.', 'eb-credito-rural' ), array( 'status' => 404 ) );
			}
			self::audit_failure( 'cnpj', $json );
			return new \WP_Error( 'lookup_unavailable', __( 'Serviço de consulta de CNPJ indisponível no momento. Preencha os dados manualmente.', 'eb-credito-rural' ), array( 'status' => 502 ) );
		}
		if ( ! is_array( $json ) || empty( $json['razao_social'] ) ) {
			return new \WP_Error( 'lookup_not_found', __( 'CNPJ não encontrado.', 'eb-credito-rural' ), array( 'status' => 404 ) );
		}
		$tipo       = self::text( isset( $json['descricao_tipo_de_logradouro'] ) ? $json['descricao_tipo_de_logradouro'] : '', 40 );
		$logradouro = self::text( isset( $json['logradouro'] ) ? $json['logradouro'] : '', 190 );
		if ( '' !== $tipo && 0 !== stripos( $logradouro, $tipo ) ) {
			$logradouro = trim( $tipo . ' ' . $logradouro );
		}
		$found = array(
			'cnpj'          => $cnpj,
			'razao_social'  => self::text( $json['razao_social'], 190 ),
			'nome_fantasia' => self::text( isset( $json['nome_fantasia'] ) ? $json['nome_fantasia'] : '', 190 ),
			'logradouro'    => self::title_case( $logradouro ),
			'numero'        => self::text( isset( $json['numero'] ) ? $json['numero'] : '', 20 ),
			'complemento'   => self::title_case( self::text( isset( $json['complemento'] ) ? $json['complemento'] : '', 100 ) ),
			'bairro'        => self::title_case( self::text( isset( $json['bairro'] ) ? $json['bairro'] : '', 100 ) ),
			'cidade'        => self::title_case( self::text( isset( $json['municipio'] ) ? $json['municipio'] : '', 120 ) ),
			'uf'            => self::uf( isset( $json['uf'] ) ? $json['uf'] : '' ),
			'cep'           => Helpers::digits( isset( $json['cep'] ) ? $json['cep'] : '' ),
			'situacao'      => self::text( isset( $json['descricao_situacao_cadastral'] ) ? $json['descricao_situacao_cadastral'] : '', 40 ),
		);
		set_transient( self::cache_key( 'cnpj', $cnpj ), $found, self::CACHE_TTL );
		return $found;
	}

	/**
	 * GET com timeout curto; devolve o JSON decodificado ou WP_Error (lookup_not_found para 404, lookup_http nos demais).
	 *
	 * @param string $url URL.
	 * @return array|\WP_Error
	 */
	private static function fetch_json( $url ) {
		$r = wp_remote_get(
			$url,
			array(
				'timeout'    => self::TIMEOUT,
				'user-agent' => 'EB-Credito-Rural/' . EBCR_VERSION . ' (' . home_url() . ')',
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return new \WP_Error( 'lookup_http', $r->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		if ( 404 === $code || 400 === $code ) {
			return new \WP_Error( 'lookup_not_found', 'not_found' );
		}
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error( 'lookup_http', 'HTTP ' . $code, array( 'code' => $code ) );
		}
		$json = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		return is_array( $json ) ? $json : new \WP_Error( 'lookup_http', 'invalid_json' );
	}

	/**
	 * Registra a indisponibilidade do serviço (no máximo uma vez por hora por tipo, sem dados do usuário).
	 *
	 * @param string    $kind  cep|cnpj.
	 * @param \WP_Error $error Erro.
	 * @return void
	 */
	private static function audit_failure( $kind, $error ) {
		$flag = 'ebcr_lookup_failed_' . sanitize_key( $kind );
		if ( get_transient( $flag ) ) {
			return;
		}
		set_transient( $flag, 1, HOUR_IN_SECONDS );
		AuditLog::log(
			'lookup_failed',
			'integration',
			$kind,
			array( 'message' => mb_substr( $error->get_error_message(), 0, 200 ) )
		);
	}

	/**
	 * Texto limpo e limitado.
	 *
	 * @param mixed $value  Valor.
	 * @param int   $max    Tamanho máximo.
	 * @return string
	 */
	private static function text( $value, $max ) {
		return mb_substr( sanitize_text_field( (string) $value ), 0, $max );
	}

	/**
	 * UF válida (2 letras maiúsculas) ou vazio.
	 *
	 * @param mixed $value Valor.
	 * @return string
	 */
	private static function uf( $value ) {
		$uf = strtoupper( sanitize_text_field( (string) $value ) );
		return Rules::uf( $uf ) ? $uf : '';
	}

	/**
	 * Converte MAIÚSCULAS da Receita em Título (mantém siglas curtas e conectivos em minúsculas).
	 *
	 * @param string $value Texto.
	 * @return string
	 */
	private static function title_case( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || mb_strtoupper( $value ) !== $value ) {
			return $value;
		}
		$small = array( 'de', 'da', 'do', 'das', 'dos', 'e' );
		$words = explode( ' ', mb_strtolower( $value ) );
		foreach ( $words as $i => $w ) {
			if ( '' === $w ) {
				continue;
			}
			if ( $i > 0 && in_array( $w, $small, true ) ) {
				continue;
			}
			$words[ $i ] = mb_strtoupper( mb_substr( $w, 0, 1 ) ) . mb_substr( $w, 1 );
		}
		return implode( ' ', $words );
	}
}
