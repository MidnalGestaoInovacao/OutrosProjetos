<?php
/**
 * Identidade visual.
 * - Painel (1.2.2): logotipo no login, na barra de administração e no topo do menu lateral, ícone do menu do plugin,
 *   rodapé do painel e ícone do site (favicon) no wp-admin e no login.
 * - Área do cliente (1.3.0): nome e logotipo da marca, cores, arredondamentos e fontes aplicados ao portal, telas de acesso,
 *   formulário, simulador, assinatura eletrônica, painel da equipe no site, verificação em duas etapas, botão "Área do Cliente"
 *   e e-mails — via variáveis CSS --ebcr-* limitadas aos invólucros do plugin (o restante do tema não é afetado).
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Support\Color;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Aplica a marca da operação ao ambiente de administração (opcional, Configurações → Geral) e à área do cliente
 * (Configurações → Identidade visual).
 */
final class Branding {

	/**
	 * Handle do estilo (sem arquivo) que carrega as variáveis da marca e as fontes.
	 */
	const HANDLE = 'ebcr-brand';

	/**
	 * Seletores que recebem as variáveis (invólucros do plugin).
	 */
	const SCOPE = '.ebcr-portal,.ebcr-cta,.ebcr-sim,.ebcr-2fa-page,.ebcr-ca-btn,.ebcr-ca-bar';

	/**
	 * CSS inline já anexado nesta requisição?
	 *
	 * @var bool
	 */
	private static $inline_done = false;

	/**
	 * Cache dos tokens desta requisição.
	 *
	 * @var array|null
	 */
	private static $tokens = null;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'get_site_icon_url', array( __CLASS__, 'site_icon_fallback' ), 10, 3 );
		// Identidade da área do cliente (independe da identidade do painel).
		add_action( 'init', array( __CLASS__, 'register_front_handle' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'attach_inline' ), 5 );
		add_action( 'enqueue_block_assets', array( __CLASS__, 'attach_inline' ), 5 );
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
		$t    = self::tokens();
		if ( ! self::is_legacy_palette( $t ) ) {
			$css = self::login_css_from_tokens( $t );
			if ( '' !== $logo ) {
				$css .= 'body.login h1 a{background-image:url(' . esc_url_raw( $logo ) . ')}';
			}
			wp_register_style( 'ebcr-login', false, array(), EBCR_VERSION );
			wp_enqueue_style( 'ebcr-login' );
			wp_add_inline_style( 'ebcr-login', $css );
			return;
		}
		$css = '
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
	 * CSS da tela de login a partir da identidade configurada (quando não é a paleta original dourado/preto).
	 *
	 * @param array $t Tokens.
	 * @return string
	 */
	private static function login_css_from_tokens( array $t ) {
		$v    = self::css_vars( $t );
		$dark = Color::hex( $v['--ebcr-dark'] );
		$link = Color::contrast( $t['accent'], $dark ) >= 4.5 ? $t['accent'] : $v['--ebcr-dark-contrast'];
		$bg   = str_replace( array( '{', '}', ';', '<', '>' ), '', $v['--ebcr-primary-bg'] );
		return 'body.login{background:' . $dark . ';color:' . $v['--ebcr-dark-contrast'] . '}
body.login #login{padding-top:6vh}
body.login h1 a{background-size:contain;background-position:center;width:280px;height:110px;margin:0 auto 18px;text-indent:-9999px;outline:0}
body.login #loginform,body.login #registerform,body.login #lostpasswordform,body.login .login form{background:' . $t['surface'] . ';border:1px solid ' . $t['border'] . ';border-radius:' . (int) $t['radius'] . 'px;box-shadow:0 18px 50px rgba(0,0,0,.35)}
body.login .message,body.login .notice,body.login #login_error{border-left-color:' . $t['accent'] . ';border-radius:10px}
body.login.wp-core-ui .button-primary{background:' . $bg . ';border-color:' . $v['--ebcr-primary-border'] . ';color:' . $t['primary_contrast'] . ';text-shadow:none;font-weight:600;border-radius:' . (int) $t['btn_radius'] . 'px;padding:0 22px}
body.login.wp-core-ui .button-primary:hover,body.login.wp-core-ui .button-primary:focus{background:' . str_replace( array( '{', '}', ';', '<', '>' ), '', $v['--ebcr-primary-bg-hover'] ) . ';border-color:' . $v['--ebcr-primary-border'] . ';color:' . $t['primary_contrast'] . '}
body.login input[type=text]:focus,body.login input[type=password]:focus,body.login input[type=email]:focus{border-color:' . $t['primary'] . ';box-shadow:0 0 0 1px ' . $t['primary'] . '}
body.login #nav a,body.login #backtoblog a,body.login .privacy-policy-page-link a{color:' . $link . '}
body.login #nav a:hover,body.login #backtoblog a:hover{color:' . $v['--ebcr-dark-contrast'] . '}
body.login form label,body.login .forgetmenot label,body.login form .description{color:' . $t['text'] . '}
body.login .wp-hide-pw{color:' . $v['--ebcr-link'] . '}
body.login .language-switcher{display:none}';
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
#wpadminbar #wp-admin-bar-ebcr-brand>.ab-item{color:' . ( self::is_legacy_palette( self::tokens() ) ? '#f3d77c' : Color::hex( self::tokens()['accent'] ) ) . '}';
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

	// ================================================================== área do cliente (1.3.0)

	/**
	 * Chaves das configurações de aparência (tokens manuais).
	 *
	 * @return string[]
	 */
	public static function token_keys() {
		return array(
			'brand_primary',
			'brand_primary_hover',
			'brand_primary_contrast',
			'brand_accent',
			'brand_accent_strong',
			'brand_bg',
			'brand_surface',
			'brand_text',
			'brand_muted',
			'brand_border',
			'brand_radius',
			'brand_btn_radius',
			'brand_button_style',
			'brand_font_heading',
			'brand_heading_weight',
			'brand_font_body',
			'brand_font_urls',
		);
	}

	/**
	 * Predefinições de identidade (aplicadas pelo botão "Aplicar" na tela, por update-settings com brand_preset ou por
	 * run-tool apply_brand_preset).
	 *
	 * @return array chave => [label, description, values]
	 */
	public static function presets() {
		return array(
			'evellyn' => array(
				'label'       => 'Évellyn Brandão',
				'description' => __( 'Dourado e preto (visual original do plugin).', 'eb-credito-rural' ),
				'values'      => array(
					'brand_primary'          => '#d4af37',
					'brand_primary_hover'    => '#b8860b',
					'brand_primary_contrast' => '#111111',
					'brand_accent'           => '#d4af37',
					'brand_accent_strong'    => '#b8860b',
					'brand_bg'               => '',
					'brand_surface'          => '#ffffff',
					'brand_text'             => '#111827',
					'brand_muted'            => '#4b5563',
					'brand_border'           => '#e5e7eb',
					'brand_radius'           => 12,
					'brand_btn_radius'       => 999,
					'brand_button_style'     => 'gradient',
					'brand_font_heading'     => '',
					'brand_heading_weight'   => '',
					'brand_font_body'        => '',
					'brand_font_urls'        => '',
				),
			),
			'bsagro'  => array(
				'label'       => 'BS Agro Capital',
				'description' => __( 'Verde-oliva, dourado e papel; Raleway nos títulos e Roboto no texto; botões em pílula.', 'eb-credito-rural' ),
				'values'      => array(
					'brand_primary'          => '#0d3527',
					'brand_primary_hover'    => '#124a35',
					'brand_primary_contrast' => '#fbf9f4',
					'brand_accent'           => '#d4af37',
					'brand_accent_strong'    => '#b8860b',
					'brand_bg'               => '#fbf9f4',
					'brand_surface'          => '#ffffff',
					'brand_text'             => '#171b24',
					'brand_muted'            => '#4b5262',
					'brand_border'           => 'rgba(23,27,36,0.15)',
					'brand_radius'           => 20,
					'brand_btn_radius'       => 999,
					'brand_button_style'     => 'solid',
					'brand_font_heading'     => '"Raleway", ui-sans-serif, system-ui, sans-serif',
					'brand_heading_weight'   => '600',
					'brand_font_body'        => '"Roboto", ui-sans-serif, system-ui, sans-serif',
					'brand_font_urls'        => "https://cdn.jsdelivr.net/npm/@fontsource/raleway@5/latin-600.css\nhttps://cdn.jsdelivr.net/npm/@fontsource/roboto@5/latin-400.css",
				),
			),
			'neutro'  => array(
				'label'       => __( 'Neutro', 'eb-credito-rural' ),
				'description' => __( 'Grafite e azul, cantos discretos e fontes do sistema.', 'eb-credito-rural' ),
				'values'      => array(
					'brand_primary'          => '#1f2937',
					'brand_primary_hover'    => '#111827',
					'brand_primary_contrast' => '#ffffff',
					'brand_accent'           => '#2563eb',
					'brand_accent_strong'    => '#1d4ed8',
					'brand_bg'               => '',
					'brand_surface'          => '#ffffff',
					'brand_text'             => '#111827',
					'brand_muted'            => '#4b5563',
					'brand_border'           => '#e5e7eb',
					'brand_radius'           => 10,
					'brand_btn_radius'       => 8,
					'brand_button_style'     => 'solid',
					'brand_font_heading'     => '',
					'brand_heading_weight'   => '',
					'brand_font_body'        => 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
					'brand_font_urls'        => '',
				),
			),
		);
	}

	/**
	 * Valores de uma predefinição (vazio se não existir).
	 *
	 * @param string $key Chave.
	 * @return array
	 */
	public static function preset_values( $key ) {
		$p = self::presets();
		return isset( $p[ $key ] ) ? $p[ $key ]['values'] : array();
	}

	/**
	 * Opções do seletor de predefinição.
	 *
	 * @return array
	 */
	public static function preset_labels() {
		$out = array( 'custom' => __( 'Personalizada', 'eb-credito-rural' ) );
		foreach ( self::presets() as $k => $p ) {
			$out[ $k ] = $p['label'];
		}
		return $out;
	}

	/**
	 * Expande brand_preset em uma entrada de configurações: os valores da predefinição preenchem as chaves de aparência
	 * que não vieram explicitamente (valores explícitos prevalecem).
	 *
	 * @param array $input Entrada bruta (chave => valor).
	 * @return array
	 */
	public static function expand_preset( array $input ) {
		if ( empty( $input['brand_preset'] ) || ! is_string( $input['brand_preset'] ) ) {
			return $input;
		}
		foreach ( self::preset_values( sanitize_key( $input['brand_preset'] ) ) as $k => $v ) {
			if ( ! array_key_exists( $k, $input ) ) {
				$input[ $k ] = $v;
			}
		}
		return $input;
	}

	/**
	 * Nome da marca exibido na área do cliente, e-mails e PDFs (configurado ou o nome do site).
	 *
	 * @return string
	 */
	public static function brand_name() {
		$name = trim( (string) Options::get( 'brand_name', '' ) );
		if ( '' === $name ) {
			$name = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		}
		/**
		 * Nome da marca na área do cliente.
		 *
		 * @param string $name Nome.
		 */
		return (string) apply_filters( 'ebcr_brand_name', $name );
	}

	/**
	 * Logotipo da área do cliente: imagem escolhida em Identidade visual → logotipo personalizado do tema → ícone do site.
	 *
	 * @param string $size Tamanho registrado.
	 * @return string URL ou vazio.
	 */
	public static function front_logo_url( $size = 'medium' ) {
		$url = '';
		$id  = (int) Options::get( 'brand_logo_id', 0 );
		if ( $id ) {
			$url = (string) wp_get_attachment_image_url( $id, $size );
		}
		if ( '' === $url ) {
			$custom = (int) get_theme_mod( 'custom_logo' );
			if ( $custom ) {
				$url = (string) wp_get_attachment_image_url( $custom, $size );
			}
		}
		if ( '' === $url && function_exists( 'get_site_icon_url' ) ) {
			$url = (string) get_site_icon_url( 192 );
		}
		/**
		 * URL do logotipo da área do cliente.
		 *
		 * @param string $url URL (vazia = só o nome).
		 */
		return (string) apply_filters( 'ebcr_brand_logo_url', $url );
	}

	/**
	 * Tokens manuais (configurações) já sanitizados.
	 *
	 * @return array
	 */
	public static function manual_tokens() {
		$legacy = self::preset_values( 'evellyn' );
		$t      = array();
		foreach ( array( 'primary', 'primary_hover', 'primary_contrast', 'accent', 'accent_strong', 'surface', 'text', 'muted', 'border' ) as $k ) {
			$v       = Color::sanitize( Options::get( 'brand_' . $k, $legacy[ 'brand_' . $k ] ) );
			$t[ $k ] = '' !== $v ? $v : $legacy[ 'brand_' . $k ];
		}
		$bg                  = trim( (string) Options::get( 'brand_bg', '' ) );
		$t['bg']             = '' === $bg ? '' : Color::sanitize( $bg );
		$t['radius']         = max( 0, min( 40, (int) Options::get( 'brand_radius', 12 ) ) );
		$t['btn_radius']     = max( 0, min( 999, (int) Options::get( 'brand_btn_radius', 999 ) ) );
		$t['button_style']   = 'solid' === Options::get( 'brand_button_style', 'gradient' ) ? 'solid' : 'gradient';
		$t['font_heading']   = self::sanitize_font( Options::get( 'brand_font_heading', '' ) );
		$t['heading_weight'] = self::sanitize_weight( Options::get( 'brand_heading_weight', '' ) );
		$t['font_body']      = self::sanitize_font( Options::get( 'brand_font_body', '' ) );
		$t['font_urls']      = self::font_urls();
		return $t;
	}

	/**
	 * Tokens efetivos: manuais ou, com "Herdar do tema", os do tema de blocos (o que o tema não definir vem dos manuais).
	 * Filtro: ebcr_brand_tokens.
	 *
	 * @return array
	 */
	public static function tokens() {
		if ( null !== self::$tokens ) {
			return self::$tokens;
		}
		$t = self::manual_tokens();
		if ( Options::bool( 'brand_inherit_theme' ) ) {
			foreach ( self::theme_tokens() as $k => $v ) {
				if ( '' !== $v && null !== $v ) {
					$t[ $k ] = $v;
				}
			}
		}
		/**
		 * Tokens da identidade visual da área do cliente.
		 *
		 * @param array $tokens primary, primary_hover, primary_contrast, accent, accent_strong, bg, surface, text, muted, border,
		 *                      radius, btn_radius, button_style, font_heading, heading_weight, font_body, font_urls.
		 */
		self::$tokens = (array) apply_filters( 'ebcr_brand_tokens', $t );
		return self::$tokens;
	}

	/**
	 * Limpa o cache dos tokens (após salvar configurações; testes).
	 *
	 * @return void
	 */
	public static function flush() {
		self::$tokens      = null;
		self::$inline_done = false;
	}

	/**
	 * Lê paleta, cores e tipografia do tema (theme.json / Estilos globais). Vazio quando o tema não define.
	 *
	 * @return array Tokens parciais.
	 */
	public static function theme_tokens() {
		if ( ! function_exists( 'wp_get_global_settings' ) || ! function_exists( 'wp_get_global_styles' ) ) {
			return array();
		}
		$palette = array();
		$colors  = wp_get_global_settings( array( 'color', 'palette' ) );
		foreach ( array( 'default', 'theme', 'custom' ) as $origin ) {
			if ( ! empty( $colors[ $origin ] ) && is_array( $colors[ $origin ] ) ) {
				foreach ( $colors[ $origin ] as $c ) {
					if ( isset( $c['slug'], $c['color'] ) ) {
						$hex = Color::sanitize( $c['color'] );
						if ( '' !== $hex ) {
							$palette[ sanitize_key( $c['slug'] ) ] = $hex;
						}
					}
				}
			}
		}
		$fonts    = array();
		$families = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
		foreach ( array( 'default', 'theme', 'custom' ) as $origin ) {
			if ( ! empty( $families[ $origin ] ) && is_array( $families[ $origin ] ) ) {
				foreach ( $families[ $origin ] as $f ) {
					if ( isset( $f['slug'], $f['fontFamily'] ) ) {
						$fonts[ sanitize_key( $f['slug'] ) ] = self::sanitize_font( $f['fontFamily'] );
					}
				}
			}
		}
		$styles = (array) wp_get_global_styles();
		$get    = static function ( array $path ) use ( $styles ) {
			$cur = $styles;
			foreach ( $path as $p ) {
				if ( ! is_array( $cur ) || ! isset( $cur[ $p ] ) ) {
					return '';
				}
				$cur = $cur[ $p ];
			}
			return is_scalar( $cur ) ? (string) $cur : '';
		};
		$color  = static function ( $value, array $fallback_slugs = array() ) use ( $palette ) {
			$value = trim( (string) $value );
			if ( preg_match( '/^var(?::preset\|color\||\(--wp--preset--color--)([a-z0-9-]+)\)?$/i', $value, $m ) ) {
				$slug = sanitize_key( $m[1] );
				if ( isset( $palette[ $slug ] ) ) {
					return $palette[ $slug ];
				}
			} elseif ( '' !== $value ) {
				$hex = Color::sanitize( $value );
				if ( '' !== $hex && 'transparent' !== $hex ) {
					return $hex;
				}
			}
			foreach ( $fallback_slugs as $slug ) {
				if ( isset( $palette[ $slug ] ) ) {
					return $palette[ $slug ];
				}
			}
			return '';
		};
		$font   = static function ( $value, array $fallback_slugs = array() ) use ( $fonts ) {
			$value = trim( (string) $value );
			if ( preg_match( '/^var(?::preset\|font-family\||\(--wp--preset--font-family--)([a-z0-9-]+)\)?$/i', $value, $m ) ) {
				$slug = sanitize_key( $m[1] );
				return isset( $fonts[ $slug ] ) ? $fonts[ $slug ] : '';
			}
			if ( '' !== $value ) {
				return self::sanitize_font( $value );
			}
			foreach ( $fallback_slugs as $slug ) {
				if ( isset( $fonts[ $slug ] ) ) {
					return $fonts[ $slug ];
				}
			}
			return '';
		};
		$out = array(
			'primary'          => $color( $get( array( 'elements', 'button', 'color', 'background' ) ), array( 'primary', 'brand', 'accent-1', 'accent' ) ),
			'primary_contrast' => $color( $get( array( 'elements', 'button', 'color', 'text' ) ), array( 'on-primary', 'base', 'background', 'white' ) ),
			'accent'           => $color( '', array( 'secondary', 'accent', 'accent-2', 'tertiary' ) ),
			'accent_strong'    => $color( $get( array( 'elements', 'link', 'color', 'text' ) ), array( 'secondary', 'accent-3' ) ),
			'bg'               => $color( $get( array( 'color', 'background' ) ), array( 'base', 'background' ) ),
			'text'             => $color( $get( array( 'color', 'text' ) ), array( 'contrast', 'foreground', 'text' ) ),
			'muted'            => $color( '', array( 'contrast-2', 'muted', 'secondary-text' ) ),
			'surface'          => $color( '', array( 'surface', 'base-2' ) ),
			'font_body'        => $font( $get( array( 'typography', 'fontFamily' ) ), array( 'body', 'system-sans-serif', 'system-font' ) ),
			'font_heading'     => $font( $get( array( 'elements', 'heading', 'typography', 'fontFamily' ) ), array( 'heading' ) ),
			'heading_weight'   => self::sanitize_weight( $get( array( 'elements', 'heading', 'typography', 'fontWeight' ) ) ),
		);
		if ( '' !== $out['primary'] && '' === $out['primary_contrast'] ) {
			$out['primary_contrast'] = Color::contrast( '#ffffff', $out['primary'] ) >= Color::contrast( '#111111', $out['primary'] ) ? '#ffffff' : '#111111';
		}
		if ( '' !== $out['primary'] ) {
			$out['primary_hover'] = Color::mix( $out['primary'], Color::is_dark( $out['primary'] ) ? '#ffffff' : '#000000', 0.12 );
		}
		$radius = $get( array( 'elements', 'button', 'border', 'radius' ) );
		if ( preg_match( '/^(\d+(?:\.\d+)?)px$/', $radius, $m ) ) {
			$out['btn_radius'] = (int) round( (float) $m[1] );
		}
		return array_filter(
			$out,
			static function ( $v ) {
				return '' !== $v && null !== $v;
			}
		);
	}

	/**
	 * Sanitiza uma pilha de fontes CSS (sem ; { } < > nem url()).
	 *
	 * @param mixed $value Valor.
	 * @return string
	 */
	public static function sanitize_font( $value ) {
		$v = trim( preg_replace( '/[^\p{L}\p{N}\s"\',\-_.]/u', '', (string) $value ) );
		return mb_substr( preg_replace( '/\s+/', ' ', $v ), 0, 300 );
	}

	/**
	 * Peso de fonte (100–900) ou vazio.
	 *
	 * @param mixed $value Valor.
	 * @return string
	 */
	public static function sanitize_weight( $value ) {
		$v = (string) $value;
		return preg_match( '/^[1-9]00$/', $v ) ? $v : '';
	}

	/**
	 * URLs das folhas de estilo das fontes (https, até 6).
	 *
	 * @param mixed $raw Texto (uma por linha) ou lista; padrão: configuração salva.
	 * @return string[]
	 */
	public static function font_urls( $raw = null ) {
		$raw  = null === $raw ? Options::get( 'brand_font_urls', '' ) : $raw;
		$list = is_array( $raw ) ? $raw : preg_split( '/[\r\n]+/', (string) $raw );
		$out  = array();
		foreach ( $list as $url ) {
			$url = esc_url_raw( trim( (string) $url ), array( 'https' ) );
			if ( '' !== $url && 0 === strpos( $url, 'https://' ) ) {
				$out[] = $url;
			}
		}
		return array_slice( array_values( array_unique( $out ) ), 0, 6 );
	}

	/**
	 * A paleta é a original (Évellyn Brandão, com degradê)? Nesse caso as variáveis derivadas não são impressas e os
	 * valores de reserva dos arquivos CSS reproduzem exatamente o visual das versões anteriores.
	 *
	 * @param array $t Tokens.
	 * @return bool
	 */
	public static function is_legacy_palette( array $t ) {
		$legacy = self::preset_values( 'evellyn' );
		foreach ( array( 'primary', 'primary_hover', 'primary_contrast', 'accent', 'accent_strong', 'surface', 'text', 'muted', 'border' ) as $k ) {
			if ( strtolower( (string) $t[ $k ] ) !== strtolower( (string) $legacy[ 'brand_' . $k ] ) ) {
				return false;
			}
		}
		return '' === (string) $t['bg'] && 'gradient' === $t['button_style'];
	}

	/**
	 * Primeira cor com contraste AA (4,5:1) sobre o fundo; senão a última.
	 *
	 * @param array  $candidates Cores.
	 * @param string $bg         Fundo.
	 * @return string
	 */
	private static function first_readable( array $candidates, $bg ) {
		foreach ( $candidates as $c ) {
			if ( '' !== (string) $c && Color::contrast( $c, $bg ) >= 4.5 ) {
				return $c;
			}
		}
		return (string) end( $candidates );
	}

	/**
	 * Variáveis CSS (nome => valor) para os tokens informados.
	 *
	 * @param array|null $t Tokens (padrão: efetivos).
	 * @return array
	 */
	public static function css_vars( $t = null ) {
		$t    = is_array( $t ) ? $t : self::tokens();
		$vars = array(
			'--ebcr-primary'          => $t['primary'],
			'--ebcr-primary-hover'    => $t['primary_hover'],
			'--ebcr-primary-contrast' => $t['primary_contrast'],
			'--ebcr-accent'           => $t['accent'],
			'--ebcr-accent-strong'    => $t['accent_strong'],
			'--ebcr-surface'          => $t['surface'],
			'--ebcr-text'             => $t['text'],
			'--ebcr-muted'            => $t['muted'],
			'--ebcr-border'           => $t['border'],
			'--ebcr-radius'           => (int) $t['radius'] . 'px',
			'--ebcr-btn-radius'       => (int) $t['btn_radius'] . 'px',
		);
		if ( '' !== (string) $t['bg'] ) {
			$vars['--ebcr-bg'] = $t['bg'];
		}
		if ( '' !== (string) $t['font_heading'] ) {
			$vars['--ebcr-font-heading'] = $t['font_heading'];
		}
		if ( '' !== (string) $t['heading_weight'] ) {
			$vars['--ebcr-heading-weight'] = $t['heading_weight'];
		}
		if ( '' !== (string) $t['font_body'] ) {
			$vars['--ebcr-font-body'] = $t['font_body'];
		}
		if ( self::is_legacy_palette( $t ) ) {
			return $vars;
		}
		// Derivadas (só fora da paleta original).
		$surface  = $t['surface'];
		$page_bg  = '' !== (string) $t['bg'] ? $t['bg'] : $surface;
		$gradient = 'gradient' === $t['button_style'];
		$dark_p   = Color::is_dark( $t['primary'] );
		$dark     = $dark_p ? $t['primary'] : Color::hex( $t['text'] );
		$dark_fg  = $dark_p ? $t['primary_contrast'] : self::first_readable( array( $t['primary'], $t['accent'], $surface, '#ffffff' ), $dark );
		$vars    += array(
			'--ebcr-primary-bg'           => $gradient ? sprintf( 'linear-gradient(135deg,%1$s,%2$s 45%%,%3$s)', Color::mix( $t['primary'], '#ffffff', 0.45 ), Color::hex( $t['primary'] ), Color::mix( $t['primary'], '#000000', 0.35 ) ) : $t['primary'],
			'--ebcr-primary-bg-hover'     => $gradient ? sprintf( 'linear-gradient(135deg,%1$s,%2$s 45%%,%3$s)', Color::mix( $t['primary_hover'], '#ffffff', 0.45 ), Color::hex( $t['primary_hover'] ), Color::mix( $t['primary_hover'], '#000000', 0.35 ) ) : $t['primary_hover'],
			'--ebcr-primary-border'       => $gradient ? Color::mix( $t['primary'], '#000000', 0.35 ) : $t['primary'],
			'--ebcr-primary-hover-filter' => 'none',
			'--ebcr-dark'                 => $dark,
			'--ebcr-dark-contrast'        => $dark_fg,
			'--ebcr-dark-accent'          => Color::contrast( $t['accent'], $dark ) >= 3 ? $t['accent'] : $dark_fg,
			'--ebcr-soft-bg'              => '' !== (string) $t['bg'] && strtolower( $t['bg'] ) !== strtolower( $surface ) ? $t['bg'] : Color::mix( $surface, $t['text'], 0.035 ),
			'--ebcr-link'                 => self::first_readable( array( $t['accent_strong'], $t['primary'], $t['text'] ), $surface ),
			'--ebcr-progress'             => sprintf( 'linear-gradient(90deg,%1$s,%2$s)', $t['accent'], $t['accent_strong'] ),
			'--ebcr-page-bg'              => $page_bg,
		);
		// Botão principal dentro de fundos escuros (CTA): se o fundo escuro é a própria cor primária, usa o destaque.
		if ( strtolower( Color::hex( $dark ) ) === strtolower( Color::hex( $t['primary'] ) ) ) {
			$vars['--ebcr-cta-btn-bg']     = $t['accent'];
			$vars['--ebcr-cta-btn-border'] = $t['accent_strong'];
			$vars['--ebcr-cta-btn-fg']     = self::first_readable( array( $t['text'], '#111111', '#ffffff' ), $t['accent'] );
		}
		return $vars;
	}

	/**
	 * CSS inline da marca, limitado aos invólucros do plugin. Filtro: ebcr_brand_css.
	 *
	 * @param array|null $t Tokens (padrão: efetivos).
	 * @return string
	 */
	public static function inline_css( $t = null ) {
		$t    = is_array( $t ) ? $t : self::tokens();
		$decl = '';
		foreach ( self::css_vars( $t ) as $name => $value ) {
			// Os valores já são normalizados; a limpeza abaixo é defesa extra contra fechamento de bloco/tag.
			$decl .= $name . ':' . str_replace( array( '{', '}', ';', '<', '>' ), '', (string) $value ) . ';';
		}
		$css = self::SCOPE . '{' . $decl . '}';
		if ( '' !== (string) $t['bg'] ) {
			$css .= '.ebcr-portal{background:var(--ebcr-bg);padding:clamp(16px,3vw,32px);border-radius:var(--ebcr-radius)}';
		}
		if ( '' !== (string) $t['font_body'] ) {
			$css .= '.ebcr-portal,.ebcr-cta,.ebcr-sim,.ebcr-2fa-page,.ebcr-ca-btn,.ebcr-portal :is(input,select,textarea,button),.ebcr-sim :is(input,select,button){font-family:var(--ebcr-font-body)}';
		}
		if ( '' !== (string) $t['font_heading'] || '' !== (string) $t['heading_weight'] ) {
			$rule = '';
			if ( '' !== (string) $t['font_heading'] ) {
				$rule .= 'font-family:var(--ebcr-font-heading);';
			}
			if ( '' !== (string) $t['heading_weight'] ) {
				$rule .= 'font-weight:var(--ebcr-heading-weight);';
			}
			$css .= '.ebcr-portal :is(h1,h2,h3,h4),.ebcr-cta h3,.ebcr-sim__title,.ebcr-2fa-title,.ebcr-brandbar-name{' . $rule . '}';
		}
		/**
		 * CSS inline da identidade visual (variáveis --ebcr-* e regras de fonte/fundo).
		 *
		 * @param string $css    CSS.
		 * @param array  $tokens Tokens.
		 */
		return (string) apply_filters( 'ebcr_brand_css', $css, $t );
	}

	/**
	 * Registra o handle da marca (sem arquivo) e as folhas de estilo das fontes como dependências.
	 *
	 * @return string Handle.
	 */
	public static function register_front_handle() {
		if ( ! wp_style_is( self::HANDLE, 'registered' ) ) {
			$deps = array();
			foreach ( self::font_urls() as $i => $url ) {
				$h = 'ebcr-brand-font-' . $i;
				wp_register_style( $h, $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- URL externa versionada pelo próprio CDN.
				$deps[] = $h;
			}
			wp_register_style( self::HANDLE, false, $deps, EBCR_VERSION );
		}
		return self::HANDLE;
	}

	/**
	 * Handle da marca pronto para ser dependência (registrado e com o CSS inline).
	 *
	 * @return string
	 */
	public static function style_handle() {
		self::register_front_handle();
		self::attach_inline();
		return self::HANDLE;
	}

	/**
	 * Anexa o CSS inline ao handle da marca (uma vez por requisição).
	 *
	 * @return void
	 */
	public static function attach_inline() {
		if ( self::$inline_done ) {
			return;
		}
		self::register_front_handle();
		self::$inline_done = true;
		wp_add_inline_style( self::HANDLE, self::inline_css() );
	}

	/**
	 * Verificação de contraste (WCAG AA 4,5:1) dos pares de cores usados em texto.
	 *
	 * @param array|null $t Tokens (padrão: efetivos).
	 * @return array Lista de [key, label, fg, bg, ratio, pass].
	 */
	public static function contrast_checks( $t = null ) {
		$t     = is_array( $t ) ? $t : self::tokens();
		$bg    = '' !== (string) $t['bg'] ? $t['bg'] : $t['surface'];
		$pairs = array(
			'primary'       => array( __( 'Texto sobre a cor primária (botões principais)', 'eb-credito-rural' ), $t['primary_contrast'], $t['primary'] ),
			'text_bg'       => array( '' !== (string) $t['bg'] ? __( 'Texto sobre o fundo da área', 'eb-credito-rural' ) : __( 'Texto sobre o fundo (fundo do tema não verificado; usando a superfície)', 'eb-credito-rural' ), $t['text'], $bg ),
			'text_surface'  => array( __( 'Texto sobre os cartões', 'eb-credito-rural' ), $t['text'], $t['surface'] ),
			'muted_surface' => array( __( 'Texto secundário sobre os cartões', 'eb-credito-rural' ), $t['muted'], $t['surface'] ),
			'link_surface'  => array( __( 'Destaque forte (links) sobre os cartões', 'eb-credito-rural' ), $t['accent_strong'], $t['surface'] ),
		);
		$out   = array();
		foreach ( $pairs as $key => $p ) {
			$ratio = Color::contrast( $p[1], $p[2] );
			$out[] = array(
				'key'   => $key,
				'label' => $p[0],
				'fg'    => $p[1],
				'bg'    => $p[2],
				'ratio' => $ratio,
				'pass'  => $ratio >= 4.5,
			);
		}
		return $out;
	}

	/**
	 * Avisos de contraste (texto) para o salvamento/abilities.
	 *
	 * @param array|null $t Tokens.
	 * @return string[]
	 */
	public static function contrast_warnings( $t = null ) {
		$out = array();
		foreach ( self::contrast_checks( $t ) as $c ) {
			if ( ! $c['pass'] ) {
				/* translators: 1: par de cores, 2: razão de contraste */
				$out[] = sprintf( __( 'Contraste abaixo do mínimo WCAG AA (4,5:1) em "%1$s": %2$s:1.', 'eb-credito-rural' ), $c['label'], number_format_i18n( $c['ratio'], 2 ) );
			}
		}
		return $out;
	}

	/**
	 * Tokens a partir de valores de configuração arbitrários (para verificar antes de salvar).
	 *
	 * @param array $values Configurações brand_* (as ausentes vêm das salvas).
	 * @return array
	 */
	public static function tokens_from( array $values ) {
		$t = self::manual_tokens();
		foreach ( array( 'primary', 'primary_hover', 'primary_contrast', 'accent', 'accent_strong', 'surface', 'text', 'muted', 'border' ) as $k ) {
			if ( isset( $values[ 'brand_' . $k ] ) && '' !== Color::sanitize( $values[ 'brand_' . $k ] ) ) {
				$t[ $k ] = Color::sanitize( $values[ 'brand_' . $k ] );
			}
		}
		if ( array_key_exists( 'brand_bg', $values ) ) {
			$t['bg'] = Color::sanitize( $values['brand_bg'] );
		}
		return $t;
	}

	/**
	 * Cores do cabeçalho dos e-mails: as originais (preto/dourado) na paleta original; senão derivadas da marca.
	 *
	 * @return array{header_bg:string,header_fg:string,link:string}
	 */
	public static function email_colors() {
		$t = self::tokens();
		if ( self::is_legacy_palette( $t ) ) {
			return array(
				'header_bg' => '#0b0b0b',
				'header_fg' => '#d4af37',
				'link'      => '#8a6a1c',
			);
		}
		$vars = self::css_vars( $t );
		$bg   = Color::hex( $vars['--ebcr-dark'] );
		return array(
			'header_bg' => $bg,
			'header_fg' => Color::hex( Color::contrast( $t['accent'], $bg ) >= 4.5 ? $t['accent'] : $vars['--ebcr-dark-contrast'] ),
			'link'      => Color::hex( $vars['--ebcr-link'] ),
		);
	}
}
