<?php
/**
 * Provedor de captcha Cloudflare Turnstile (filtro ebcr_captcha_provider). Entregue pelo módulo "Integrações".
 *
 * @package EBCR
 */

namespace EBCR\Security;

use EBCR\Support\Ip;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Registra o provedor quando selecionado em Configurações → Segurança e as duas chaves (Integrações) estão preenchidas.
 *
 * O desafio é gerado no navegador pelo widget oficial; o token chega no campo `cf-turnstile-response` e é validado
 * no servidor em siteverify (uso único, garantido pela Cloudflare). Sem chaves, o plugin volta ao captcha matemático
 * e registra um aviso na auditoria (uma vez a cada 12 h) para o administrador perceber.
 */
final class Turnstile implements CaptchaProvider {

	const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
	const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
	const FIELD      = 'cf-turnstile-response';
	const HANDLE     = 'ebcr-turnstile';
	const TIMEOUT    = 6;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'ebcr_captcha_provider', array( $this, 'maybe_provider' ), 10, 2 );
		add_filter( 'script_loader_tag', array( $this, 'script_tag' ), 10, 2 );
	}

	/**
	 * Substitui o provedor matemático quando a chave configurada é "turnstile" e as chaves existem.
	 *
	 * @param CaptchaProvider $provider Provedor atual.
	 * @param string          $key      Chave configurada (math|turnstile).
	 * @return CaptchaProvider
	 */
	public function maybe_provider( $provider, $key ) {
		if ( 'turnstile' !== $key ) {
			return $provider;
		}
		if ( self::is_configured() ) {
			return new self();
		}
		self::warn_once();
		return $provider;
	}

	/**
	 * Site key e secret key preenchidas?
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== self::site_key() && '' !== self::secret_key();
	}

	/**
	 * Site key.
	 *
	 * @return string
	 */
	public static function site_key() {
		return trim( (string) Options::get( 'turnstile_site_key', '' ) );
	}

	/**
	 * Secret key (nunca sai para o HTML nem para logs).
	 *
	 * @return string
	 */
	private static function secret_key() {
		return trim( (string) Options::get( 'turnstile_secret_key', '' ) );
	}

	/**
	 * Aviso de configuração incompleta (uma vez a cada 12 h).
	 *
	 * @return void
	 */
	private static function warn_once() {
		if ( get_transient( 'ebcr_turnstile_warned' ) ) {
			return;
		}
		set_transient( 'ebcr_turnstile_warned', 1, 12 * HOUR_IN_SECONDS );
		AuditLog::log(
			'turnstile_misconfigured',
			'settings',
			'captcha_provider',
			array( 'message' => 'Turnstile selecionado sem site key/secret key em Integrações; usando o captcha matemático.' ),
			0
		);
	}

	/**
	 * Adiciona async/defer ao script oficial.
	 *
	 * @param string $tag    Tag HTML.
	 * @param string $handle Handle.
	 * @return string
	 */
	public function script_tag( $tag, $handle ) {
		if ( self::HANDLE !== $handle || false !== strpos( $tag, ' async' ) ) {
			return $tag;
		}
		return str_replace( '<script ', '<script async defer ', $tag );
	}

	/**
	 * O desafio é gerado no cliente; não há pergunta nem token do servidor.
	 *
	 * @return array{token:string,question:string,aria:string}
	 */
	public function issue() {
		return array(
			'token'    => '',
			'question' => '',
			'aria'     => '',
		);
	}

	/**
	 * Verifica o token do widget ($answer) em siteverify.
	 *
	 * @param string $token  Ignorado (compatibilidade com a interface).
	 * @param string $answer Token `cf-turnstile-response`.
	 * @return bool
	 */
	public function verify( $token, $answer ) {
		return self::siteverify( (string) $answer );
	}

	/**
	 * Lê `cf-turnstile-response` do request completo (chamado por MathCaptcha::verify_request).
	 *
	 * @param array $input Dados enviados ($_POST ou JSON).
	 * @return bool
	 */
	public function verify_input( array $input ) {
		$response = isset( $input[ self::FIELD ] ) ? (string) $input[ self::FIELD ] : '';
		return $this->verify( '', $response );
	}

	/**
	 * Campo HTML: contêiner do widget + script oficial (async defer, só quando o provedor está ativo).
	 *
	 * @return string
	 */
	public function field() {
		wp_enqueue_script( self::HANDLE, self::SCRIPT_URL, array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- script externo da Cloudflare, sem parâmetro de versão por recomendação do fornecedor.
		return sprintf(
			'<div class="ebcr-field ebcr-captcha ebcr-captcha--turnstile"><span class="ebcr-sr">%1$s</span><div class="cf-turnstile" data-sitekey="%2$s" data-theme="auto" data-language="pt-br" data-size="flexible"></div><noscript><span class="ebcr-help">%3$s</span></noscript></div>',
			esc_html__( 'Verificação de segurança', 'eb-credito-rural' ),
			esc_attr( self::site_key() ),
			esc_html__( 'Ative o JavaScript para concluir a verificação de segurança.', 'eb-credito-rural' )
		);
	}

	/**
	 * Valida um token na Cloudflare (POST secret, response, remoteip). Nunca registra o token nem a chave.
	 *
	 * @param string $response Token do widget.
	 * @return bool
	 */
	public static function siteverify( $response ) {
		$response = trim( (string) $response );
		if ( '' === $response || strlen( $response ) > 4096 || ! self::is_configured() ) {
			return false;
		}
		$r = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => self::TIMEOUT,
				'body'    => array(
					'secret'   => self::secret_key(),
					'response' => $response,
					'remoteip' => Ip::get(),
				),
			)
		);
		if ( is_wp_error( $r ) ) {
			AuditLog::log(
				'captcha_failed',
				'turnstile',
				'',
				array(
					'reason'  => 'http_error',
					'message' => mb_substr( $r->get_error_message(), 0, 200 ),
				)
			);
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		$json = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( 200 !== $code || ! is_array( $json ) ) {
			AuditLog::log(
				'captcha_failed',
				'turnstile',
				'',
				array(
					'reason' => 'bad_response',
					'code'   => $code,
				)
			);
			return false;
		}
		if ( empty( $json['success'] ) ) {
			$errors = isset( $json['error-codes'] ) && is_array( $json['error-codes'] ) ? array_map( 'sanitize_key', $json['error-codes'] ) : array();
			// Só erros de configuração vão para a auditoria; token inválido/expirado é rotina do usuário.
			if ( array_intersect( $errors, array( 'invalid-input-secret', 'missing-input-secret', 'invalid-widget-id', 'bad-request' ) ) ) {
				AuditLog::log( 'turnstile_misconfigured', 'settings', 'turnstile_secret_key', array( 'errors' => array_values( $errors ) ), 0 );
			}
			return false;
		}
		return true;
	}
}
