<?php
/**
 * Identidade visual do painel: logotipo no login, na barra de administração e no topo do menu lateral,
 * ícone do menu do plugin, rodapé do painel e ícone do site (favicon) no wp-admin e no login.
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Aplica a marca da operação ao ambiente de administração (opcional, Configurações → Geral).
 */
final class Branding {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'get_site_icon_url', array( __CLASS__, 'site_icon_fallback' ), 10, 3 );
		if ( ! self::enabled() ) {
			return;
		}
		add_action( 'login_enqueue_scripts', array( $this, 'login_styles' ) );
		add_filter( 'login_headerurl', array( __CLASS__, 'login_url' ) );
		add_filter( 'login_headertext', array( __CLASS__, 'login_text' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 11 );
		add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ) );
	}

	/**
	 * Identidade visual ligada?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return Options::bool( 'admin_branding' );
	}

	/**
	 * URL do logotipo (marca completa); usa o logotipo dos e-mails como reserva.
	 *
	 * @return string
	 */
	public static function logo_url() {
		$url = (string) Options::get( 'brand_logo_url', '' );
		return '' !== $url ? $url : (string) Options::get( 'email_logo_url', '' );
	}

	/**
	 * URL do ícone quadrado (monograma/favicon).
	 *
	 * @return string
	 */
	public static function icon_url() {
		return (string) Options::get( 'brand_icon_url', '' );
	}

	/**
	 * Ícone do menu "Crédito Rural": o ícone da marca quando configurado; senão, o dashicon padrão.
	 *
	 * @return string
	 */
	public static function menu_icon() {
		$icon = self::enabled() ? self::icon_url() : '';
		return '' !== $icon ? $icon : 'dashicons-carrot';
	}

	/**
	 * Quando o WordPress não tem "ícone do site" definido, usa o ícone da marca no wp-admin e no login
	 * (o front-end já recebe o favicon pelo tema/cabeçalho).
	 *
	 * @param string $url     URL atual (vazia quando não há ícone).
	 * @param int    $size    Tamanho.
	 * @param int    $blog_id Site.
	 * @return string
	 */
	public static function site_icon_fallback( $url, $size, $blog_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- assinatura do filtro.
		if ( '' !== (string) $url || ! self::enabled() ) {
			return $url;
		}
		if ( ! is_admin() && ! did_action( 'login_init' ) ) {
			return $url;
		}
		return self::icon_url();
	}

	/**
	 * Define o "ícone do site" do WordPress a partir do ícone da marca (precisa estar na biblioteca de mídia).
	 *
	 * @return array{applied:bool,attachment_id:int,reason:string}
	 */
	public static function apply_site_icon() {
		$url = self::icon_url();
		if ( '' === $url ) {
			return array(
				'applied'       => false,
				'attachment_id' => 0,
				'reason'        => 'no_icon_url',
			);
		}
		$id = (int) attachment_url_to_postid( $url );
		if ( ! $id ) {
			$id = (int) attachment_url_to_postid( preg_replace( '/-\d+x\d+(\.[a-z]+)$/i', '$1', $url ) );
		}
		if ( ! $id ) {
			return array(
				'applied'       => false,
				'attachment_id' => 0,
				'reason'        => 'not_in_media_library',
			);
		}
		update_option( 'site_icon', $id );
		return array(
			'applied'       => true,
			'attachment_id' => $id,
			'reason'        => (int) get_option( 'site_icon' ) === $id ? 'set' : 'not_saved',
		);
	}

	/**
	 * CSS da tela de login (fundo escuro, logotipo da marca, botão dourado).
	 *
	 * @return void
	 */
	public function login_styles() {
		$logo = self::logo_url();
		$css  = '
body.login{background:#0b0b0b;background-image:radial-gradient(ellipse at top,rgba(212,175,55,.16),transparent 55%);color:#e8e2d0}
body.login #login{padding-top:6vh}
body.login h1 a{background-size:contain;background-position:center;width:280px;height:110px;margin:0 auto 18px;text-indent:-9999px;outline:0}
body.login #loginform,body.login #registerform,body.login #lostpasswordform,body.login .login form{background:#fff;border:1px solid rgba(212,175,55,.35);border-radius:14px;box-shadow:0 18px 50px rgba(0,0,0,.55)}
body.login .message,body.login .notice,body.login #login_error{border-left-color:#d4af37;border-radius:10px}
body.login.wp-core-ui .button-primary{background:linear-gradient(135deg,#f3d77c,#d4af37 55%,#b8860b);border-color:#b8860b;color:#111;text-shadow:none;font-weight:600;border-radius:999px;padding:0 22px}
body.login.wp-core-ui .button-primary:hover,body.login.wp-core-ui .button-primary:focus{background:linear-gradient(135deg,#f6dd8a,#d9b640 55%,#c4910f);border-color:#b8860b;color:#111}
body.login input[type=text]:focus,body.login input[type=password]:focus,body.login input[type=email]:focus{border-color:#d4af37;box-shadow:0 0 0 1px #d4af37}
body.login #nav a,body.login #backtoblog a{color:#e0c67a}
body.login #nav a:hover,body.login #backtoblog a:hover{color:#fff}
body.login .privacy-policy-page-link a{color:#e0c67a}
body.login form label,body.login .forgetmenot label,body.login form .description{color:#1d2327}
body.login .wp-hide-pw{color:#8a6a1c}
body.login.wp-core-ui .button.wp-hide-pw:focus{border-color:#d4af37;box-shadow:0 0 0 1px #d4af37}
body.login .language-switcher{display:none}';
		if ( '' !== $logo ) {
			$css .= 'body.login h1 a{background-image:url(' . esc_url_raw( $logo ) . ')}';
		}
		wp_register_style( 'ebcr-login', false, array(), EBCR_VERSION );
		wp_enqueue_style( 'ebcr-login' );
		wp_add_inline_style( 'ebcr-login', $css );
	}

	/**
	 * Link do logotipo do login: a página inicial.
	 *
	 * @return string
	 */
	public static function login_url() {
		return home_url( '/' );
	}

	/**
	 * Texto alternativo do logotipo do login.
	 *
	 * @return string
	 */
	public static function login_text() {
		return (string) Options::get( 'operation_name', get_bloginfo( 'name' ) );
	}

	/**
	 * CSS do painel: logotipo no topo do menu lateral, ícone do plugin e monograma na barra.
	 *
	 * @return void
	 */
	public function admin_styles() {
		$logo = self::logo_url();
		$icon = self::icon_url();
		$css  = '';
		if ( '' !== $logo ) {
			$css .= '#adminmenu::before{content:"";display:block;height:64px;margin:10px 12px 6px;background:url(' . esc_url_raw( $logo ) . ') center/contain no-repeat}
.folded #adminmenu::before{height:34px;margin:8px 4px 2px' . ( '' !== $icon ? ';background-image:url(' . esc_url_raw( $icon ) . ')' : '' ) . '}
@media (max-width:960px){#adminmenu::before{height:34px;margin:8px 4px 2px' . ( '' !== $icon ? ';background-image:url(' . esc_url_raw( $icon ) . ')' : '' ) . '}}';
		}
		if ( '' !== $icon ) {
			$css .= '#adminmenu .toplevel_page_ebcr .wp-menu-image img{width:18px;height:18px;padding:8px 0 0;opacity:.85}
#adminmenu .toplevel_page_ebcr:hover .wp-menu-image img,#adminmenu .toplevel_page_ebcr.current .wp-menu-image img,#adminmenu .toplevel_page_ebcr.wp-has-current-submenu .wp-menu-image img{opacity:1}
#wpadminbar #wp-admin-bar-ebcr-brand>.ab-item{padding:0 8px 0 6px}
#wpadminbar #wp-admin-bar-ebcr-brand .ebcr-brand-icon{display:inline-block;width:20px;height:20px;margin:6px 6px 0 0;vertical-align:top;background:url(' . esc_url_raw( $icon ) . ') center/contain no-repeat}
#wpadminbar #wp-admin-bar-ebcr-brand>.ab-item{color:#f3d77c}';
		}
		if ( '' === $css ) {
			return;
		}
		wp_register_style( 'ebcr-branding', false, array(), EBCR_VERSION );
		wp_enqueue_style( 'ebcr-branding' );
		wp_add_inline_style( 'ebcr-branding', $css );
	}

	/**
	 * Substitui o logotipo do WordPress na barra pelo nome da operação com o monograma, levando ao site.
	 *
	 * @param \WP_Admin_Bar $bar Barra.
	 * @return void
	 */
	public function admin_bar( $bar ) {
		$bar->remove_node( 'wp-logo' );
		$icon = self::icon_url();
		$bar->add_node(
			array(
				'id'    => 'ebcr-brand',
				'title' => ( '' !== $icon ? '<span class="ebcr-brand-icon" aria-hidden="true"></span>' : '' ) . esc_html( self::login_text() ),
				'href'  => home_url( '/' ),
				'meta'  => array( 'title' => __( 'Ver o site', 'eb-credito-rural' ) ),
			)
		);
	}

	/**
	 * Rodapé do painel.
	 *
	 * @param string $text Texto original.
	 * @return string
	 */
	public static function footer_text( $text ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- assinatura do filtro.
		return esc_html( self::login_text() ) . ' · ' . esc_html__( 'painel de operações', 'eb-credito-rural' ) . ' · EB Crédito Rural ' . esc_html( EBCR_VERSION );
	}
}
