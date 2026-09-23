<?php
/**
 * Verificação em duas etapas da equipe (TOTP por aplicativo ou código por e-mail), com códigos de backup e dispositivo confiável.
 * Entregue pelo módulo "2FA".
 *
 * Como funciona:
 * - No login (`wp_login`), se o usuário tem capacidade de equipe e 2FA ativo, a sessão recém-criada (token capturado em
 *   `set_logged_in_cookie`) é marcada como "pendente" dentro do próprio registro de sessão do WordPress. Enquanto pendente,
 *   painel, AJAX, REST, portal e front-end redirecionam para a tela `?ebcr_2fa=1` (front-end, porque a hospedagem bloqueia POSTs
 *   a wp-admin/admin-post.php e wp-login.php). A verificação grava "verificado" na sessão.
 * - Com modo "obrigatório", quem ainda não configurou fica restrito ao próprio perfil até ativar.
 * - XML-RPC (que autentica só com senha) é recusado para contas sujeitas ao 2FA.
 * - `define( 'EBCR_DISABLE_2FA', true )` no wp-config.php desliga tudo (recuperação de emergência).
 *
 * @package EBCR
 */

namespace EBCR\Security;

use EBCR\Mail\Mailer;
use EBCR\Roles\Capabilities;
use EBCR\Support\Helpers;
use EBCR\Support\Ip;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks de login, perfil e bloqueio do painel até a verificação.
 */
final class TwoFactor {

	const META_METHOD         = 'ebcr_2fa_method';
	const META_SECRET         = 'ebcr_2fa_secret';
	const META_PENDING_SECRET = 'ebcr_2fa_pending_secret';
	const META_BACKUP         = 'ebcr_2fa_backup_codes';
	const META_TRUSTED        = 'ebcr_2fa_trusted';
	const META_ENABLED_AT     = 'ebcr_2fa_enabled_at';
	const META_LAST_STEP      = 'ebcr_2fa_last_step';

	const SESSION_KEY  = 'ebcr_2fa';
	const COOKIE_TRUST = 'ebcr_2fa_trust';
	const QUERY_VAR    = 'ebcr_2fa';

	const CODE_TTL        = 600;
	const MAX_ATTEMPTS    = 5;
	const LOCK_SECONDS    = 600;
	const SEND_MAX        = 3;
	const TRUST_DAYS      = 30;
	const BACKUP_COUNT    = 10;
	const PENDING_TTL     = 1800;
	const BACKUP_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

	/**
	 * Token da sessão criada no login desta requisição (capturado em set_logged_in_cookie).
	 *
	 * @var string
	 */
	private static $login_token = '';

	/**
	 * Usuário dono do token capturado.
	 *
	 * @var int
	 */
	private static $login_token_user = 0;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'set_logged_in_cookie', array( __CLASS__, 'capture_token' ), 10, 6 );
		add_action( 'wp_login', array( __CLASS__, 'on_login' ), 20, 2 );
		add_filter( 'authenticate', array( __CLASS__, 'block_xmlrpc' ), 100, 3 );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 100, 3 );
		add_action( 'init', array( __CLASS__, 'front_gate' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'portal_gate' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'admin_gate' ), 1 );
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'rest_gate' ), 200 );
		( new TwoFactorProfile() )->register();
	}

	/* ---------------------------------------------------------------------
	 * Política e estado
	 * ------------------------------------------------------------------ */

	/**
	 * Modo configurado: off | optional | required (a constante EBCR_DISABLE_2FA força "off").
	 *
	 * @return string
	 */
	public static function mode() {
		if ( defined( 'EBCR_DISABLE_2FA' ) && EBCR_DISABLE_2FA ) {
			return 'off';
		}
		$mode = (string) Options::get( 'team_2fa_mode', 'optional' );
		return in_array( $mode, array( 'off', 'optional', 'required' ), true ) ? $mode : 'optional';
	}

	/**
	 * O 2FA se aplica ao usuário? (equipe: analistas, gestores, administradores; clientes ficam fora).
	 *
	 * @param int $user_id Usuário.
	 * @return bool
	 */
	public static function applies_to( $user_id ) {
		return $user_id > 0 && user_can( (int) $user_id, Capabilities::CAP_VIEW );
	}

	/**
	 * Método ativo do usuário: totp | email | '' (desativado).
	 *
	 * @param int $user_id Usuário.
	 * @return string
	 */
	public static function method( $user_id ) {
		$m = (string) get_user_meta( (int) $user_id, self::META_METHOD, true );
		return in_array( $m, array( 'totp', 'email' ), true ) ? $m : '';
	}

	/**
	 * 2FA ativo?
	 *
	 * @param int $user_id Usuário.
	 * @return bool
	 */
	public static function is_enabled( $user_id ) {
		return '' !== self::method( $user_id );
	}

	/**
	 * O que a sessão atual precisa fazer: '' (nada), 'verify' (confirmar código) ou 'setup' (configurar, modo obrigatório).
	 *
	 * @param int|null    $user_id Usuário (padrão: atual).
	 * @param string|null $token   Token de sessão (padrão: o do cookie atual).
	 * @return string
	 */
	public static function required_action( $user_id = null, $token = null ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id || 'off' === self::mode() || ! self::applies_to( $user_id ) ) {
			return '';
		}
		if ( self::is_enabled( $user_id ) ) {
			$token = null === $token ? wp_get_session_token() : (string) $token;
			return 'pending' === self::session_flag( $user_id, $token ) ? 'verify' : '';
		}
		return 'required' === self::mode() ? 'setup' : '';
	}

	/**
	 * Marca gravada na sessão do WordPress: pending | verified | ''.
	 *
	 * @param int    $user_id Usuário.
	 * @param string $token   Token de sessão.
	 * @return string
	 */
	public static function session_flag( $user_id, $token ) {
		if ( '' === (string) $token ) {
			return '';
		}
		$session = \WP_Session_Tokens::get_instance( (int) $user_id )->get( (string) $token );
		return is_array( $session ) && isset( $session[ self::SESSION_KEY ] ) ? (string) $session[ self::SESSION_KEY ] : '';
	}

	/**
	 * Grava a marca na sessão (o registro vive em user meta `session_tokens` e morre com a sessão).
	 *
	 * @param int    $user_id Usuário.
	 * @param string $token   Token de sessão.
	 * @param string $flag    pending | verified.
	 * @return bool
	 */
	public static function set_session_flag( $user_id, $token, $flag ) {
		if ( '' === (string) $token ) {
			return false;
		}
		$manager = \WP_Session_Tokens::get_instance( (int) $user_id );
		$session = $manager->get( (string) $token );
		if ( ! is_array( $session ) ) {
			return false;
		}
		$session[ self::SESSION_KEY ] = (string) $flag;
		$manager->update( (string) $token, $session );
		return true;
	}

	/* ---------------------------------------------------------------------
	 * Login
	 * ------------------------------------------------------------------ */

	/**
	 * Guarda o token da sessão criada em wp_set_auth_cookie() (o cookie ainda não existe em $_COOKIE nesta requisição).
	 *
	 * @param string $cookie     Cookie.
	 * @param int    $expire     Expiração do cookie.
	 * @param int    $expiration Expiração da sessão.
	 * @param int    $user_id    Usuário.
	 * @param string $scheme     Esquema.
	 * @param string $token      Token de sessão.
	 * @return void
	 */
	public static function capture_token( $cookie, $expire, $expiration, $user_id, $scheme, $token ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- assinatura do hook.
		self::$login_token      = (string) $token;
		self::$login_token_user = (int) $user_id;
	}

	/**
	 * Token da sessão criada nesta requisição (para o usuário informado).
	 *
	 * @param int $user_id Usuário.
	 * @return string
	 */
	public static function login_token( $user_id ) {
		return (int) $user_id === self::$login_token_user ? self::$login_token : '';
	}

	/**
	 * Após a senha correta: marca a sessão como pendente (ou verificada, se o dispositivo é confiável).
	 * Usamos `wp_login` (e não `authenticate`) porque só aqui existe uma sessão para vincular a pendência; no filtro
	 * `authenticate` ainda não há cookie/token, e devolver WP_Error ali quebraria o login do portal, que trata qualquer
	 * erro como "e-mail ou senha inválidos".
	 *
	 * @param string   $login Login.
	 * @param \WP_User $user  Usuário.
	 * @return void
	 */
	public static function on_login( $login, $user ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- assinatura do hook.
		if ( ! ( $user instanceof \WP_User ) || 'off' === self::mode() || ! self::applies_to( $user->ID ) || ! self::is_enabled( $user->ID ) ) {
			return;
		}
		$token = self::login_token( $user->ID );
		if ( '' === $token ) {
			$token = wp_get_session_token();
		}
		if ( '' === $token ) {
			return; // Login sem sessão de cookie (programático): não há o que proteger.
		}
		if ( self::trusted_cookie_valid( $user->ID ) ) {
			self::set_session_flag( $user->ID, $token, 'verified' );
			AuditLog::log( '2fa_trusted_device', 'user', $user->ID, array( 'event' => 'used' ), $user->ID );
			return;
		}
		self::set_session_flag( $user->ID, $token, 'pending' );
	}

	/**
	 * XML-RPC autentica só com senha e contornaria o 2FA: recusa para contas sujeitas à verificação.
	 *
	 * @param \WP_User|\WP_Error|null $user      Resultado até aqui.
	 * @param string                  $username  Login.
	 * @param string                  $password  Senha.
	 * @param bool|null               $is_xmlrpc Força o contexto (testes); padrão: constante XMLRPC_REQUEST.
	 * @return \WP_User|\WP_Error|null
	 */
	public static function block_xmlrpc( $user, $username, $password, $is_xmlrpc = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- assinatura do filtro.
		$is_xmlrpc = null === $is_xmlrpc ? ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) : (bool) $is_xmlrpc;
		if ( ! $is_xmlrpc || ! ( $user instanceof \WP_User ) || 'off' === self::mode() || ! self::applies_to( $user->ID ) ) {
			return $user;
		}
		if ( self::is_enabled( $user->ID ) || 'required' === self::mode() ) {
			AuditLog::log( '2fa_failed', 'user', $user->ID, array( 'reason' => 'xmlrpc' ), $user->ID );
			return new \WP_Error( 'ebcr_2fa_xmlrpc', __( 'XML-RPC não está disponível para contas com verificação em duas etapas. Use o painel.', 'eb-credito-rural' ) );
		}
		return $user;
	}

	/**
	 * Login pelo wp-login.php: manda direto para a tela de verificação, preservando o destino.
	 *
	 * @param string             $redirect_to Destino.
	 * @param string             $requested   Destino pedido.
	 * @param \WP_User|\WP_Error $user        Usuário.
	 * @return string
	 */
	public static function login_redirect( $redirect_to, $requested, $user ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- assinatura do filtro.
		if ( $user instanceof \WP_User && 'verify' === self::required_action( $user->ID, self::login_token( $user->ID ) ) ) {
			return self::screen_url( $redirect_to );
		}
		return $redirect_to;
	}

	/* ---------------------------------------------------------------------
	 * Bloqueios enquanto pendente
	 * ------------------------------------------------------------------ */

	/**
	 * URL da tela de verificação (front-end).
	 *
	 * @param string $redirect_to Destino após verificar.
	 * @return string
	 */
	public static function screen_url( $redirect_to = '' ) {
		$args = array( self::QUERY_VAR => '1' );
		if ( $redirect_to ) {
			$args['redirect_to'] = rawurlencode( $redirect_to );
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * URL da configuração obrigatória (perfil do usuário).
	 *
	 * @return string
	 */
	public static function setup_url() {
		return admin_url( 'profile.php?ebcr_2fa_setup=1#ebcr-2fa' );
	}

	/**
	 * Destino padrão depois de verificar.
	 *
	 * @return string
	 */
	public static function default_destination() {
		return admin_url( 'admin.php?page=ebcr-submissions' );
	}

	/**
	 * URL relativa da requisição atual (para voltar depois da verificação).
	 *
	 * @return string
	 */
	private static function current_uri() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return ( $uri && '/' === $uri[0] && 0 !== strpos( $uri, '//' ) ) ? $uri : '';
	}

	/**
	 * A requisição é da REST API? (em `init` a constante REST_REQUEST ainda não existe).
	 *
	 * @return bool
	 */
	private static function is_rest_request() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- apenas identifica o tipo de requisição.
		return ( false !== strpos( $uri, '/' . rest_get_url_prefix() . '/' ) ) || isset( $_GET['rest_route'] );
	}

	/**
	 * Front-end: atende a tela de verificação e bloqueia o resto do site para sessões pendentes.
	 *
	 * @return void
	 */
	public static function front_gate() {
		if ( isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- roteamento; a tela verifica nonces dos formulários.
			( new TwoFactorScreen() )->handle();
			return;
		}
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || self::is_rest_request() || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) {
			return; // permite sair (wp-login.php?action=logout).
		}
		if ( is_user_logged_in() && 'verify' === self::required_action() ) {
			self::redirect_to_screen();
		}
	}

	/**
	 * Portal do plugin: quem precisa configurar (modo obrigatório) vai para o perfil.
	 *
	 * @return void
	 */
	public static function portal_gate() {
		if ( ! is_user_logged_in() || 'setup' !== self::required_action() ) {
			return;
		}
		$portal = Helpers::portal_page_id();
		if ( $portal && is_page( $portal ) ) {
			wp_safe_redirect( self::setup_url() );
			exit;
		}
	}

	/**
	 * Painel e AJAX: pendente → tela de verificação; configuração obrigatória → só o perfil fica acessível.
	 *
	 * @return void
	 */
	public static function admin_gate() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$action = self::required_action();
		if ( '' === $action ) {
			return;
		}
		if ( wp_doing_ajax() ) {
			wp_send_json_error(
				array(
					'code'    => 'ebcr_2fa_' . $action,
					'message' => 'verify' === $action ? __( 'Confirme a verificação em duas etapas para continuar.', 'eb-credito-rural' ) : __( 'Configure a verificação em duas etapas no seu perfil para continuar.', 'eb-credito-rural' ),
				),
				403
			);
		}
		if ( 'verify' === $action ) {
			self::redirect_to_screen();
		}
		if ( isset( $GLOBALS['pagenow'] ) && 'profile.php' === $GLOBALS['pagenow'] ) {
			return;
		}
		wp_safe_redirect( self::setup_url() );
		exit;
	}

	/**
	 * REST: sessão pendente ou configuração obrigatória → 403 (roda depois da checagem de cookie/nonce do core).
	 *
	 * @param \WP_Error|null|bool $result Resultado anterior.
	 * @return \WP_Error|null|bool
	 */
	public static function rest_gate( $result ) {
		if ( is_wp_error( $result ) || ! is_user_logged_in() ) {
			return $result;
		}
		$action = self::required_action();
		if ( '' === $action ) {
			return $result;
		}
		return new \WP_Error(
			'ebcr_2fa_' . $action,
			'verify' === $action ? __( 'Confirme a verificação em duas etapas para continuar.', 'eb-credito-rural' ) : __( 'Configure a verificação em duas etapas no seu perfil para continuar.', 'eb-credito-rural' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Redireciona para a tela de verificação guardando o destino.
	 *
	 * @return void
	 */
	private static function redirect_to_screen() {
		nocache_headers();
		wp_safe_redirect( self::screen_url( self::current_uri() ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Segredo TOTP (criptografado quando há chave)
	 * ------------------------------------------------------------------ */

	/**
	 * Serializa um segredo para o user meta: cifrado com a chave do wp-config quando disponível; senão em claro (com aviso na tela).
	 *
	 * @param string $secret Segredo em base32.
	 * @return string
	 */
	private static function seal( $secret ) {
		if ( Crypto::is_available() ) {
			try {
				return Crypto::encrypt( (string) $secret );
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- cai para o armazenamento em claro.
				// Continua.
			}
		}
		return (string) $secret;
	}

	/**
	 * Lê um segredo serializado. Retorna '' se estiver cifrado e a chave não puder abri-lo.
	 *
	 * @param string $stored Valor guardado.
	 * @return string
	 */
	private static function unseal( $stored ) {
		$stored = (string) $stored;
		if ( '' === $stored ) {
			return '';
		}
		if ( 0 === strpos( $stored, Crypto::PREFIX ) ) {
			$plain = Crypto::decrypt( $stored );
			return null === $plain ? '' : $plain;
		}
		return $stored;
	}

	/**
	 * Segredo ativo do usuário ('' se não houver ou se não puder ser decifrado).
	 *
	 * @param int $user_id Usuário.
	 * @return string
	 */
	public static function secret( $user_id ) {
		return self::unseal( get_user_meta( (int) $user_id, self::META_SECRET, true ) );
	}

	/**
	 * O segredo guardado está cifrado?
	 *
	 * @param int $user_id Usuário.
	 * @return bool
	 */
	public static function secret_is_encrypted( $user_id ) {
		return 0 === strpos( (string) get_user_meta( (int) $user_id, self::META_SECRET, true ), Crypto::PREFIX );
	}

	/**
	 * Segredo provisório para o cadastro (QR). Gera um novo se não houver ou se expirou (30 min).
	 *
	 * @param int  $user_id Usuário.
	 * @param bool $create  Criar se não existir.
	 * @return string
	 */
	public static function pending_secret( $user_id, $create = true ) {
		$user_id = (int) $user_id;
		$row     = get_user_meta( $user_id, self::META_PENDING_SECRET, true );
		if ( is_array( $row ) && ! empty( $row['secret'] ) && ! empty( $row['created'] ) && (int) $row['created'] + self::PENDING_TTL > time() ) {
			$secret = self::unseal( $row['secret'] );
			if ( '' !== $secret ) {
				return $secret;
			}
		}
		if ( ! $create ) {
			return '';
		}
		$secret = Totp::generate_secret();
		update_user_meta(
			$user_id,
			self::META_PENDING_SECRET,
			array(
				'secret'  => self::seal( $secret ),
				'created' => time(),
			)
		);
		return $secret;
	}

	/**
	 * URI otpauth para o usuário.
	 *
	 * @param \WP_User $user   Usuário.
	 * @param string   $secret Segredo.
	 * @return string
	 */
	public static function otpauth_uri( $user, $secret ) {
		return Totp::uri( $secret, $user->user_email ? $user->user_email : $user->user_login, wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	}

	/* ---------------------------------------------------------------------
	 * Ativar / desativar
	 * ------------------------------------------------------------------ */

	/**
	 * Ativa o TOTP se o código bater com o segredo provisório. Retorna os códigos de backup (mostrados uma única vez).
	 *
	 * @param int    $user_id Usuário.
	 * @param string $code    Código do aplicativo.
	 * @return string[]|\WP_Error
	 */
	public static function activate_totp( $user_id, $code ) {
		$user_id = (int) $user_id;
		$secret  = self::pending_secret( $user_id, false );
		if ( '' === $secret ) {
			return new \WP_Error( 'expired', __( 'A configuração expirou. Recarregue a página, leia o novo QR code e tente de novo.', 'eb-credito-rural' ) );
		}
		$step = Totp::verify( $secret, $code );
		if ( false === $step ) {
			AuditLog::log( '2fa_failed', 'user', $user_id, array( 'reason' => 'activation_totp' ), $user_id );
			return new \WP_Error( 'invalid', __( 'Código inválido. Confira o relógio do celular e digite o código atual do aplicativo.', 'eb-credito-rural' ) );
		}
		$codes = self::enable( $user_id, 'totp', $secret );
		update_user_meta( $user_id, self::META_LAST_STEP, (int) $step );
		return $codes;
	}

	/**
	 * Ativa o método por e-mail se o código de ativação (enviado ao e-mail da conta) estiver correto.
	 *
	 * @param int    $user_id Usuário.
	 * @param string $code    Código recebido.
	 * @return string[]|\WP_Error
	 */
	public static function activate_email( $user_id, $code ) {
		$user_id = (int) $user_id;
		if ( ! self::check_email_code( $user_id, $code, 'activation' ) ) {
			AuditLog::log( '2fa_failed', 'user', $user_id, array( 'reason' => 'activation_email' ), $user_id );
			return new \WP_Error( 'invalid', __( 'Código inválido ou expirado. Peça um novo código de ativação.', 'eb-credito-rural' ) );
		}
		return self::enable( $user_id, 'email' );
	}

	/**
	 * Grava o método, o segredo e novos códigos de backup; zera dispositivos confiáveis antigos.
	 *
	 * @param int    $user_id Usuário.
	 * @param string $method  totp | email.
	 * @param string $secret  Segredo (TOTP).
	 * @return string[] Códigos de backup em claro.
	 */
	public static function enable( $user_id, $method, $secret = '' ) {
		$user_id = (int) $user_id;
		update_user_meta( $user_id, self::META_METHOD, 'email' === $method ? 'email' : 'totp' );
		if ( 'totp' === $method ) {
			update_user_meta( $user_id, self::META_SECRET, self::seal( $secret ) );
		} else {
			delete_user_meta( $user_id, self::META_SECRET );
		}
		delete_user_meta( $user_id, self::META_PENDING_SECRET );
		delete_user_meta( $user_id, self::META_LAST_STEP );
		delete_user_meta( $user_id, self::META_TRUSTED );
		update_user_meta( $user_id, self::META_ENABLED_AT, time() );
		delete_transient( self::code_key( $user_id, 'activation' ) );
		$codes = self::generate_backup_codes( $user_id );
		AuditLog::log( '2fa_enabled', 'user', $user_id, array( 'method' => $method ), get_current_user_id() ? get_current_user_id() : $user_id );
		return $codes;
	}

	/**
	 * Desativa o 2FA (remove segredo, backup e dispositivos confiáveis).
	 *
	 * @param int  $user_id  Usuário.
	 * @param bool $by_admin Feito por um administrador em nome do usuário.
	 * @return void
	 */
	public static function disable( $user_id, $by_admin = false ) {
		$user_id = (int) $user_id;
		$method  = self::method( $user_id );
		foreach ( array( self::META_METHOD, self::META_SECRET, self::META_PENDING_SECRET, self::META_BACKUP, self::META_TRUSTED, self::META_ENABLED_AT, self::META_LAST_STEP ) as $key ) {
			delete_user_meta( $user_id, $key );
		}
		delete_transient( self::code_key( $user_id, 'login' ) );
		delete_transient( self::code_key( $user_id, 'activation' ) );
		RateLimiter::clear( '2fa_verify', $user_id );
		AuditLog::log(
			'2fa_disabled',
			'user',
			$user_id,
			array(
				'method'   => $method,
				'by_admin' => (bool) $by_admin,
			)
		);
	}

	/**
	 * Confirma a identidade para ações sensíveis do perfil: senha atual, código do aplicativo ou código de backup.
	 *
	 * @param int    $user_id Usuário.
	 * @param string $input   Valor digitado.
	 * @return bool
	 */
	public static function confirm_identity( $user_id, $input ) {
		$user_id = (int) $user_id;
		$input   = (string) $input;
		if ( '' === $input ) {
			return false;
		}
		$user = get_userdata( $user_id );
		if ( $user && wp_check_password( $input, $user->user_pass, $user_id ) ) {
			return true;
		}
		if ( 'totp' === self::method( $user_id ) && self::check_totp( $user_id, $input ) ) {
			return true;
		}
		return self::consume_backup_code( $user_id, $input );
	}

	/* ---------------------------------------------------------------------
	 * Verificação de códigos
	 * ------------------------------------------------------------------ */

	/**
	 * Verifica o código digitado na tela (TOTP, e-mail ou backup) com limite de tentativas.
	 * Não altera a sessão: chame complete_verification() em seguida.
	 *
	 * @param int    $user_id    Usuário.
	 * @param string $code       Código.
	 * @param bool   $use_backup Interpretar como código de backup.
	 * @return true|\WP_Error Códigos: locked | invalid | no_method.
	 */
	public static function verify_code( $user_id, $code, $use_backup = false ) {
		$user_id = (int) $user_id;
		$wait    = RateLimiter::blocked_for( '2fa_verify', $user_id );
		if ( $wait > 0 ) {
			AuditLog::log( '2fa_failed', 'user', $user_id, array( 'reason' => 'locked' ), $user_id );
			return new \WP_Error( 'locked', self::locked_message( $wait ), array( 'wait' => $wait ) );
		}
		$method = self::method( $user_id );
		if ( '' === $method ) {
			return new \WP_Error( 'no_method', __( 'A verificação em duas etapas não está ativa para esta conta.', 'eb-credito-rural' ) );
		}
		$via = $method;
		if ( $use_backup ) {
			$via = 'backup';
			$ok  = self::consume_backup_code( $user_id, $code );
		} elseif ( 'totp' === $method ) {
			$ok = self::check_totp( $user_id, $code );
		} else {
			$ok = self::check_email_code( $user_id, $code, 'login' );
		}
		if ( ! $ok ) {
			return self::register_failure( $user_id, $via );
		}
		RateLimiter::clear( '2fa_verify', $user_id );
		return true;
	}

	/**
	 * Marca a sessão como verificada, registra auditoria e (opcionalmente) confia no dispositivo.
	 *
	 * @param int    $user_id Usuário.
	 * @param string $token   Token de sessão.
	 * @param string $via     totp | email | backup.
	 * @param bool   $trust   Lembrar este dispositivo por 30 dias.
	 * @return void
	 */
	public static function complete_verification( $user_id, $token, $via, $trust = false ) {
		$user_id = (int) $user_id;
		self::set_session_flag( $user_id, $token, 'verified' );
		delete_transient( self::code_key( $user_id, 'login' ) );
		AuditLog::log(
			'2fa_verified',
			'user',
			$user_id,
			array(
				'method'  => $via,
				'trusted' => (bool) $trust,
			),
			$user_id
		);
		if ( $trust ) {
			self::trust_device( $user_id );
		}
	}

	/**
	 * Registra a falha, aplica o bloqueio progressivo na 5ª tentativa e devolve o erro.
	 *
	 * @param int    $user_id Usuário.
	 * @param string $via     Método tentado.
	 * @return \WP_Error
	 */
	private static function register_failure( $user_id, $via ) {
		// RateLimiter::hit devolve false quando a contagem passa de $max; com MAX_ATTEMPTS - 1 o bloqueio cai exatamente na 5ª falha.
		$allowed = RateLimiter::hit( '2fa_verify', $user_id, self::MAX_ATTEMPTS - 1, self::LOCK_SECONDS );
		$meta    = array(
			'reason' => 'invalid',
			'method' => $via,
		);
		if ( ! $allowed ) {
			$meta['locked'] = RateLimiter::lock( '2fa_verify', $user_id, self::LOCK_SECONDS );
		}
		AuditLog::log( '2fa_failed', 'user', $user_id, $meta, $user_id );
		if ( ! $allowed ) {
			return new \WP_Error( 'locked', self::locked_message( (int) $meta['locked'] ), array( 'wait' => (int) $meta['locked'] ) );
		}
		return new \WP_Error( 'invalid', 'backup' === $via ? __( 'Código de backup inválido ou já usado.', 'eb-credito-rural' ) : __( 'Código inválido ou expirado. Tente novamente.', 'eb-credito-rural' ) );
	}

	/**
	 * Mensagem de bloqueio.
	 *
	 * @param int $wait Segundos.
	 * @return string
	 */
	private static function locked_message( $wait ) {
		return sprintf(
			/* translators: %d: minutos */
			__( 'Muitas tentativas incorretas. Aguarde %d minuto(s) e tente novamente.', 'eb-credito-rural' ),
			max( 1, (int) ceil( $wait / 60 ) )
		);
	}

	/**
	 * Confere um código TOTP contra o segredo ativo, recusando reuso do mesmo passo de tempo.
	 *
	 * @param int    $user_id Usuário.
	 * @param string $code    Código.
	 * @return bool
	 */
	public static function check_totp( $user_id, $code ) {
		$user_id = (int) $user_id;
		$secret  = self::secret( $user_id );
		if ( '' === $secret ) {
			return false;
		}
		$step = Totp::verify( $secret, $code );
		if ( false === $step ) {
			return false;
		}
		$last = (int) get_user_meta( $user_id, self::META_LAST_STEP, true );
		if ( $step <= $last ) {
			return false; // código já usado nesta janela.
		}
		update_user_meta( $user_id, self::META_LAST_STEP, (int) $step );
		return true;
	}

	/**
	 * Nome do transient do código por e-mail.
	 *
	 * @param int    $user_id Usuário.
	 * @param string $purpose login | activation.
	 * @return string
	 */
	private static function code_key( $user_id, $purpose ) {
		return 'ebcr_2fa_code_' . (int) $user_id . '_' . ( 'activation' === $purpose ? 'act' : 'login' );
	}

	/**
	 * Há um código por e-mail ainda válido?
	 *
	 * @param int    $user_id Usuário.
	 * @param string $purpose login | activation.
	 * @return bool
	 */
	public static function has_email_code( $user_id, $purpose = 'login' ) {
		$row = get_transient( self::code_key( $user_id, $purpose ) );
		return is_array( $row ) && ! empty( $row['expires'] ) && (int) $row['expires'] > time();
	}

	/**
	 * Gera e envia um código de 6 dígitos por e-mail (vale 10 minutos; no máximo 3 envios por 10 minutos).
	 *
	 * @param int    $user_id Usuário.
	 * @param string $purpose login | activation.
	 * @return true|\WP_Error
	 */
	public static function send_email_code( $user_id, $purpose = 'login' ) {
		$user_id = (int) $user_id;
		$user    = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return new \WP_Error( 'no_email', __( 'Sua conta não tem um e-mail válido. Fale com o administrador.', 'eb-credito-rural' ) );
		}
		if ( ! RateLimiter::hit( '2fa_send', $user_id . ':' . $purpose, self::SEND_MAX, self::CODE_TTL ) ) {
			return new \WP_Error( 'too_many', __( 'Já enviamos alguns códigos. Aguarde alguns minutos antes de pedir outro.', 'eb-credito-rural' ) );
		}
		$code = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
		set_transient(
			self::code_key( $user_id, $purpose ),
			array(
				'hash'    => self::hash_code( $user_id, $purpose, $code ),
				'expires' => time() + self::CODE_TTL,
			),
			self::CODE_TTL
		);
		Mailer::send_event(
			'two_factor_code',
			$user->user_email,
			array(
				'nome'   => $user->display_name,
				'codigo' => $code,
			)
		);
		return true;
	}

	/**
	 * Confere (e consome) um código enviado por e-mail.
	 *
	 * @param int    $user_id Usuário.
	 * @param string $code    Código digitado.
	 * @param string $purpose login | activation.
	 * @return bool
	 */
	public static function check_email_code( $user_id, $code, $purpose = 'login' ) {
		$user_id = (int) $user_id;
		$code    = preg_replace( '/\D/', '', (string) $code );
		$key     = self::code_key( $user_id, $purpose );
		$row     = get_transient( $key );
		if ( 6 !== strlen( $code ) || ! is_array( $row ) || empty( $row['hash'] ) || empty( $row['expires'] ) || (int) $row['expires'] < time() ) {
			return false;
		}
		if ( ! hash_equals( (string) $row['hash'], self::hash_code( $user_id, $purpose, $code ) ) ) {
			return false;
		}
		delete_transient( $key );
		return true;
	}

	/**
	 * Hash do código por e-mail (com os sais do WordPress).
	 *
	 * @param int    $user_id Usuário.
	 * @param string $purpose Finalidade.
	 * @param string $code    Código.
	 * @return string
	 */
	private static function hash_code( $user_id, $purpose, $code ) {
		return wp_hash( (int) $user_id . '|' . $purpose . '|' . $code, 'auth' );
	}

	/**
	 * E-mail mascarado para exibição (j***@dominio.com).
	 *
	 * @param string $email E-mail.
	 * @return string
	 */
	public static function mask_email( $email ) {
		$email = (string) $email;
		$at    = strpos( $email, '@' );
		if ( false === $at ) {
			return '***';
		}
		return mb_substr( $email, 0, 1 ) . '***' . substr( $email, $at );
	}

	/* ---------------------------------------------------------------------
	 * Códigos de backup
	 * ------------------------------------------------------------------ */

	/**
	 * Gera 10 códigos de backup de uso único (guardados com hash de senha).
	 *
	 * @param int $user_id Usuário.
	 * @return string[] Códigos em claro no formato xxxx-xxxx.
	 */
	public static function generate_backup_codes( $user_id ) {
		$codes  = array();
		$hashes = array();
		$max    = strlen( self::BACKUP_ALPHABET ) - 1;
		for ( $i = 0; $i < self::BACKUP_COUNT; $i++ ) {
			$raw = '';
			for ( $j = 0; $j < 8; $j++ ) {
				$raw .= self::BACKUP_ALPHABET[ random_int( 0, $max ) ];
			}
			$codes[]  = substr( $raw, 0, 4 ) . '-' . substr( $raw, 4 );
			$hashes[] = wp_hash_password( $raw );
		}
		update_user_meta( (int) $user_id, self::META_BACKUP, $hashes );
		return $codes;
	}

	/**
	 * Quantos códigos de backup restam.
	 *
	 * @param int $user_id Usuário.
	 * @return int
	 */
	public static function backup_codes_left( $user_id ) {
		$hashes = get_user_meta( (int) $user_id, self::META_BACKUP, true );
		return is_array( $hashes ) ? count( $hashes ) : 0;
	}

	/**
	 * Confere e consome um código de backup.
	 *
	 * @param int    $user_id Usuário.
	 * @param string $code    Código digitado (espaços/hífens ignorados).
	 * @return bool
	 */
	public static function consume_backup_code( $user_id, $code ) {
		$user_id = (int) $user_id;
		$code    = strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $code ) );
		$hashes  = get_user_meta( $user_id, self::META_BACKUP, true );
		if ( 8 !== strlen( $code ) || ! is_array( $hashes ) ) {
			return false;
		}
		foreach ( $hashes as $i => $hash ) {
			if ( is_string( $hash ) && wp_check_password( $code, $hash ) ) {
				unset( $hashes[ $i ] );
				update_user_meta( $user_id, self::META_BACKUP, array_values( $hashes ) );
				return true;
			}
		}
		return false;
	}

	/* ---------------------------------------------------------------------
	 * Dispositivos confiáveis
	 * ------------------------------------------------------------------ */

	/**
	 * Lista de dispositivos confiáveis (sem os expirados).
	 *
	 * @param int $user_id Usuário.
	 * @return array[] Cada item: id, hash, created, expires, ua, ip, last_used.
	 */
	public static function trusted_devices( $user_id ) {
		$list = get_user_meta( (int) $user_id, self::META_TRUSTED, true );
		if ( ! is_array( $list ) ) {
			return array();
		}
		$now = time();
		return array_values(
			array_filter(
				$list,
				static function ( $d ) use ( $now ) {
					return is_array( $d ) && ! empty( $d['hash'] ) && ! empty( $d['expires'] ) && (int) $d['expires'] > $now;
				}
			)
		);
	}

	/**
	 * Cria um dispositivo confiável: cookie HttpOnly/Secure com token aleatório; só o hash fica no user meta.
	 *
	 * @param int $user_id Usuário.
	 * @return string Token (para testes).
	 */
	public static function trust_device( $user_id ) {
		$user_id = (int) $user_id;
		$token   = Helpers::random_hex( 20 );
		$list    = self::trusted_devices( $user_id );
		$list[]  = array(
			'id'        => Helpers::random_hex( 6 ),
			'hash'      => wp_hash( $token, 'auth' ),
			'created'   => time(),
			'expires'   => time() + self::TRUST_DAYS * DAY_IN_SECONDS,
			'ua'        => mb_substr( Ip::user_agent(), 0, 120 ),
			'ip'        => Ip::get(),
			'last_used' => 0,
		);
		update_user_meta( $user_id, self::META_TRUSTED, $list );
		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_TRUST,
				$user_id . ':' . $token,
				array(
					'expires'  => time() + self::TRUST_DAYS * DAY_IN_SECONDS,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
		AuditLog::log( '2fa_trusted_device', 'user', $user_id, array( 'event' => 'added' ), $user_id );
		return $token;
	}

	/**
	 * O cookie de dispositivo confiável do navegador vale para este usuário?
	 *
	 * @param int $user_id Usuário.
	 * @return bool
	 */
	public static function trusted_cookie_valid( $user_id ) {
		$user_id = (int) $user_id;
		if ( empty( $_COOKIE[ self::COOKIE_TRUST ] ) ) {
			return false;
		}
		$raw   = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_TRUST ] ) );
		$parts = explode( ':', $raw, 2 );
		if ( 2 !== count( $parts ) || (int) $parts[0] !== $user_id || ! preg_match( '/^[a-f0-9]{40}$/', $parts[1] ) ) {
			return false;
		}
		$hash = wp_hash( $parts[1], 'auth' );
		$list = self::trusted_devices( $user_id );
		foreach ( $list as $i => $device ) {
			if ( hash_equals( (string) $device['hash'], $hash ) ) {
				$list[ $i ]['last_used'] = time();
				update_user_meta( $user_id, self::META_TRUSTED, $list );
				return true;
			}
		}
		return false;
	}

	/**
	 * Revoga dispositivos confiáveis (todos ou por id).
	 *
	 * @param int      $user_id Usuário.
	 * @param string[] $ids     IDs a revogar; vazio = todos.
	 * @return int Quantidade revogada.
	 */
	public static function revoke_trusted( $user_id, array $ids = array() ) {
		$user_id = (int) $user_id;
		$list    = self::trusted_devices( $user_id );
		$keep    = array();
		foreach ( $list as $device ) {
			if ( $ids && ! in_array( $device['id'], $ids, true ) ) {
				$keep[] = $device;
			}
		}
		$revoked = count( $list ) - count( $keep );
		if ( $keep ) {
			update_user_meta( $user_id, self::META_TRUSTED, $keep );
		} else {
			delete_user_meta( $user_id, self::META_TRUSTED );
		}
		if ( $revoked > 0 ) {
			AuditLog::log(
				'2fa_trusted_device',
				'user',
				$user_id,
				array(
					'event' => 'revoked',
					'count' => $revoked,
				)
			);
		}
		return $revoked;
	}
}
