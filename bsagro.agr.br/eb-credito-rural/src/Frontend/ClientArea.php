<?php
/**
 * Botão "Área do Cliente": shortcode [ebcr_client_area_button], bloco dinâmico ebcr/client-area-button, barra fixa no
 * topo, item em menus clássicos e no bloco Navegação, auxiliar JS (a[data-ebcr-client-area] + window.EBCR_CLIENT_AREA)
 * e endereço amigável /area-do-cliente/ (com fallback ?ebcr_client_area=1 para links permanentes simples).
 *
 * @package EBCR
 */

namespace EBCR\Frontend;

use EBCR\Admin\Branding;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Tudo é opcional e desligado por padrão, exceto o shortcode, o bloco e o auxiliar JS (inofensivos se não usados).
 */
final class ClientArea {

	const QUERY_VAR = 'ebcr_client_area';
	const BLOCK     = 'ebcr/client-area-button';
	const STYLE     = 'ebcr-client-area';
	const SIG_OPT   = 'ebcr_rewrite_sig';
	const SLUG_TR   = 'ebcr_ca_slug_page';

	/**
	 * Botão já inserido em um bloco Navegação nesta requisição?
	 *
	 * @var bool
	 */
	private static $nav_done = false;

	/**
	 * Barra já impressa nesta requisição?
	 *
	 * @var bool
	 */
	private static $bar_done = false;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'ebcr_client_area_button', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_assets' ), 9 );
		add_action( 'init', array( __CLASS__, 'register_block' ), 10 );
		add_action( 'init', array( __CLASS__, 'rewrite' ), 20 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'plain_alias' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_when_needed' ), 20 );
		add_action( 'wp_body_open', array( __CLASS__, 'print_bar' ) );
		add_action( 'wp_footer', array( __CLASS__, 'footer' ), 5 );
		add_filter( 'wp_nav_menu_items', array( __CLASS__, 'menu_items' ), 10, 2 );
		add_filter( 'render_block_core/navigation', array( __CLASS__, 'navigation_block' ), 10, 2 );
		add_action(
			'save_post_page',
			static function () {
				delete_transient( self::SLUG_TR );
			}
		);
	}

	// ------------------------------------------------------------------ dados

	/**
	 * URL da área do cliente (página do portal; sem página configurada, a que contém [ebcr_portal]).
	 * Filtro: ebcr_client_area_url (string $url, array $context).
	 *
	 * @return string
	 */
	public static function url() {
		$url = Helpers::portal_url();
		/**
		 * URL de destino do botão "Área do Cliente".
		 *
		 * @param string $url     URL (get_permalink da página do portal; funciona com links permanentes simples).
		 * @param array  $context logged_in (bool), portal_page_id (int).
		 */
		return (string) apply_filters(
			'ebcr_client_area_url',
			$url,
			array(
				'logged_in'      => is_user_logged_in(),
				'portal_page_id' => Helpers::portal_page_id(),
			)
		);
	}

	/**
	 * Rótulo para visitantes.
	 *
	 * @return string
	 */
	public static function label() {
		$l = trim( (string) Options::get( 'client_area_label', '' ) );
		return '' !== $l ? $l : __( 'Área do Cliente', 'eb-credito-rural' );
	}

	/**
	 * Rótulo para quem está logado.
	 *
	 * @return string
	 */
	public static function label_logged() {
		$l = trim( (string) Options::get( 'client_area_label_logged', '' ) );
		return '' !== $l ? $l : __( 'Minha área', 'eb-credito-rural' );
	}

	/**
	 * Primeiro nome do usuário logado (first_name ou primeira palavra do nome de exibição).
	 *
	 * @return string
	 */
	public static function first_name() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$u     = wp_get_current_user();
		$first = trim( (string) get_user_meta( $u->ID, 'first_name', true ) );
		if ( '' === $first ) {
			$parts = preg_split( '/\s+/', trim( (string) $u->display_name ) );
			$first = $parts ? (string) $parts[0] : '';
		}
		return $first;
	}

	/**
	 * Slug do endereço amigável.
	 *
	 * @return string
	 */
	public static function slug() {
		$slug = sanitize_title( (string) Options::get( 'client_area_slug', 'area-do-cliente' ) );
		return '' !== $slug ? $slug : 'area-do-cliente';
	}

	/**
	 * Existe página publicada com o slug do endereço amigável? (cache de 12 h; limpo ao salvar páginas.)
	 *
	 * @return bool
	 */
	public static function slug_page_exists() {
		$cached = get_transient( self::SLUG_TR );
		if ( is_array( $cached ) && isset( $cached['slug'], $cached['exists'] ) && self::slug() === $cached['slug'] ) {
			return (bool) $cached['exists'];
		}
		$page   = get_page_by_path( self::slug(), OBJECT, 'page' );
		$exists = $page instanceof \WP_Post && 'publish' === $page->post_status;
		set_transient(
			self::SLUG_TR,
			array(
				'slug'   => self::slug(),
				'exists' => $exists,
			),
			12 * HOUR_IN_SECONDS
		);
		return $exists;
	}

	/**
	 * O endereço amigável está em uso (ligado e sem página própria com o mesmo slug)?
	 *
	 * @return bool
	 */
	public static function alias_active() {
		return Options::bool( 'client_area_alias' ) && ! self::slug_page_exists();
	}

	/**
	 * URL amigável (ou, com links permanentes simples, ?ebcr_client_area=1). Se houver página com o slug, a URL dela.
	 *
	 * @return string
	 */
	public static function alias_url() {
		if ( self::slug_page_exists() ) {
			$page = get_page_by_path( self::slug(), OBJECT, 'page' );
			return $page ? (string) get_permalink( $page ) : self::url();
		}
		if ( ! Options::bool( 'client_area_alias' ) ) {
			return self::url();
		}
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			return add_query_arg( self::QUERY_VAR, '1', home_url( '/' ) );
		}
		return home_url( user_trailingslashit( self::slug() ) );
	}

	/**
	 * Dados do auxiliar JS (window.EBCR_CLIENT_AREA).
	 *
	 * @return array
	 */
	public static function js_data() {
		$data = array(
			'url'             => self::url(),
			'label'           => self::label(),
			'labelLogged'     => self::label_logged(),
			'loggedIn'        => is_user_logged_in(),
			'userFirstName'   => self::first_name(),
			// Registro de consentimento de cookies (POST JSON); rest_url() funciona com links permanentes simples.
			'consentEndpoint' => esc_url_raw( rest_url( \EBCR\Rest\Routes::NS . '/cookie-consent' ) ),
		);
		if ( is_user_logged_in() ) {
			// Com o nonce (cabeçalho X-WP-Nonce), o registro fica vinculado ao usuário conectado.
			$data['consentNonce'] = wp_create_nonce( 'wp_rest' );
		}
		return $data;
	}

	/**
	 * Resumo para a tela de configurações e as abilities.
	 *
	 * @return array
	 */
	public static function info() {
		return array(
			'url'          => self::url(),
			'alias_url'    => self::alias_url(),
			'alias_active' => self::alias_active(),
			'slug'         => self::slug(),
			'plain'        => '' === (string) get_option( 'permalink_structure' ),
			'bar'          => Options::bool( 'client_area_bar' ),
			'menu'         => (string) Options::get( 'client_area_menu_location', '' ),
			'nav_block'    => Options::bool( 'client_area_nav_block' ),
		);
	}

	/**
	 * Estilos aceitos.
	 *
	 * @return array
	 */
	public static function styles() {
		return array(
			'primary' => __( 'Principal (cor da marca)', 'eb-credito-rural' ),
			'outline' => __( 'Contorno', 'eb-credito-rural' ),
			'ghost'   => __( 'Discreto (sem borda)', 'eb-credito-rural' ),
			'link'    => __( 'Link', 'eb-credito-rural' ),
		);
	}

	// ------------------------------------------------------------------ renderização

	/**
	 * Ícone (pessoa) em SVG inline.
	 *
	 * @return string
	 */
	private static function icon_svg() {
		return '<svg class="ebcr-ca-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>';
	}

	/**
	 * Valor booleano de atributo de shortcode (yes/no, sim/não, 1/0, true/false).
	 *
	 * @param mixed $v Valor.
	 * @return bool
	 */
	private static function truthy( $v ) {
		if ( is_bool( $v ) ) {
			return $v;
		}
		return ! in_array( strtolower( trim( (string) $v ) ), array( 'no', 'nao', 'não', '0', 'false', 'off', '' ), true );
	}

	/**
	 * HTML do botão.
	 *
	 * @param array $args label, label_logged, style (primary|outline|ghost|link), icon (bool|yes|no), class.
	 * @return string
	 */
	public static function button( array $args = array() ) {
		$a       = array_merge(
			array(
				'label'        => '',
				'label_logged' => '',
				'style'        => 'primary',
				'icon'         => Options::bool( 'client_area_icon' ),
				'class'        => '',
			),
			$args
		);
		$style   = isset( self::styles()[ $a['style'] ] ) ? $a['style'] : 'primary';
		$label   = '' !== trim( (string) $a['label'] ) ? trim( (string) $a['label'] ) : self::label();
		$logged  = '' !== trim( (string) $a['label_logged'] ) ? trim( (string) $a['label_logged'] ) : self::label_logged();
		$classes = array( 'ebcr-ca-btn', 'ebcr-ca-btn--' . $style );
		foreach ( preg_split( '/\s+/', (string) $a['class'] ) as $c ) {
			$c = sanitize_html_class( $c );
			if ( '' !== $c ) {
				$classes[] = $c;
			}
		}
		$is_logged = is_user_logged_in();
		$first     = self::first_name();
		if ( $is_logged ) {
			$text = ( '' !== $first ? '<span class="ebcr-ca-user">' . esc_html( $first ) . '</span><span class="ebcr-ca-sep" aria-hidden="true">·</span>' : '' ) . '<span class="ebcr-ca-label">' . esc_html( $logged ) . '</span>';
			/* translators: 1: rótulo (Minha área), 2: nome do usuário */
			$aria = '' !== $first ? sprintf( __( '%1$s — conectado como %2$s', 'eb-credito-rural' ), $logged, $first ) : $logged;
		} else {
			$text = '<span class="ebcr-ca-label">' . esc_html( $label ) . '</span>';
			/* translators: %s: rótulo (Área do Cliente) */
			$aria = sprintf( __( '%s: entrar ou criar conta', 'eb-credito-rural' ), $label );
		}
		self::enqueue_style();
		return sprintf(
			'<a class="%1$s" href="%2$s" aria-label="%3$s"%4$s>%5$s%6$s</a>',
			esc_attr( implode( ' ', array_unique( $classes ) ) ),
			esc_url( self::url() ),
			esc_attr( $aria ),
			self::is_portal_page() ? ' aria-current="page"' : '',
			self::truthy( $a['icon'] ) ? self::icon_svg() : '',
			$text
		);
	}

	/**
	 * [ebcr_client_area_button label="" label_logged="" style="primary|outline|ghost|link" icon="yes|no" class=""].
	 *
	 * @param array|string $atts Atributos.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$a = shortcode_atts(
			array(
				'label'        => '',
				'label_logged' => '',
				'style'        => 'primary',
				'icon'         => Options::bool( 'client_area_icon' ) ? 'yes' : 'no',
				'class'        => '',
			),
			is_array( $atts ) ? $atts : array(),
			'ebcr_client_area_button'
		);
		return self::button( $a );
	}

	/**
	 * Estamos na página do portal?
	 *
	 * @return bool
	 */
	public static function is_portal_page() {
		if ( is_admin() ) {
			return false;
		}
		$id = Helpers::portal_page_id();
		if ( $id && function_exists( 'is_page' ) && did_action( 'wp' ) && is_page( $id ) ) {
			return true;
		}
		$post = get_post();
		return $post instanceof \WP_Post && did_action( 'wp' ) && is_singular() && has_shortcode( (string) $post->post_content, 'ebcr_portal' );
	}

	// ------------------------------------------------------------------ assets e bloco

	/**
	 * Registra o CSS do botão (depende das variáveis da marca) e o script do bloco.
	 *
	 * @return void
	 */
	public static function register_assets() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style( self::STYLE, EBCR_URL . 'assets/css/client-area.css', array( Branding::register_front_handle() ), EBCR_VERSION );
		}
		if ( ! wp_script_is( 'ebcr-client-area-block', 'registered' ) ) {
			wp_register_script( 'ebcr-client-area-block', EBCR_URL . 'assets/js/client-area-block.js', array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ), EBCR_VERSION, true );
		}
	}

	/**
	 * Enfileira o CSS do botão (também tarde, durante o conteúdo).
	 *
	 * @return void
	 */
	public static function enqueue_style() {
		self::register_assets();
		Branding::attach_inline();
		wp_enqueue_style( self::STYLE );
	}

	/**
	 * Bloco dinâmico (sem build: editor em JS puro com wp.serverSideRender).
	 *
	 * @return void
	 */
	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		self::register_assets();
		register_block_type(
			self::BLOCK,
			array(
				'api_version'     => 3,
				'title'           => __( 'Botão Área do Cliente', 'eb-credito-rural' ),
				'description'     => __( 'Botão que leva à área do cliente (portal). Mostra o primeiro nome e "Minha área" para quem está conectado.', 'eb-credito-rural' ),
				'category'        => 'widgets',
				'icon'            => 'admin-users',
				'keywords'        => array( 'cliente', 'login', 'portal', 'área' ),
				'editor_script'   => 'ebcr-client-area-block',
				'style'           => self::STYLE,
				'attributes'      => array(
					'label'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'labelLogged' => array(
						'type'    => 'string',
						'default' => '',
					),
					'buttonStyle' => array(
						'type'    => 'string',
						'default' => 'primary',
						'enum'    => array_keys( self::styles() ),
					),
					'showIcon'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'supports'        => array(
					'html'      => false,
					'className' => true,
					'align'     => array( 'left', 'center', 'right' ),
				),
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);
	}

	/**
	 * Renderização do bloco.
	 *
	 * @param array $attributes Atributos.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$button     = self::button(
			array(
				'label'        => isset( $attributes['label'] ) ? (string) $attributes['label'] : '',
				'label_logged' => isset( $attributes['labelLogged'] ) ? (string) $attributes['labelLogged'] : '',
				'style'        => isset( $attributes['buttonStyle'] ) ? (string) $attributes['buttonStyle'] : 'primary',
				'icon'         => ! isset( $attributes['showIcon'] ) || (bool) $attributes['showIcon'],
			)
		);
		$wrapper    = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes( array( 'class' => 'ebcr-ca' ) ) : '';
		if ( '' === $wrapper ) {
			$wrapper = 'class="ebcr-ca' . ( ! empty( $attributes['className'] ) ? ' ' . esc_attr( (string) $attributes['className'] ) : '' ) . '"';
		}
		return '<div ' . $wrapper . '>' . $button . '</div>';
	}

	/**
	 * Enfileira o CSS cedo quando algum recurso automático está ligado ou a página usa o shortcode/bloco.
	 *
	 * @return void
	 */
	public static function enqueue_when_needed() {
		$post = get_post();
		if ( Options::bool( 'client_area_bar' ) || '' !== (string) Options::get( 'client_area_menu_location', '' ) || Options::bool( 'client_area_nav_block' )
			|| ( $post instanceof \WP_Post && ( has_shortcode( (string) $post->post_content, 'ebcr_client_area_button' ) || ( function_exists( 'has_block' ) && has_block( self::BLOCK, $post ) ) ) ) ) {
			self::enqueue_style();
		}
	}

	// ------------------------------------------------------------------ injeções automáticas

	/**
	 * A barra fixa deve aparecer nesta página?
	 *
	 * @return bool
	 */
	private static function bar_enabled() {
		if ( ! Options::bool( 'client_area_bar' ) || is_admin() || is_feed() || ( function_exists( 'is_embed' ) && is_embed() ) || wp_doing_ajax() ) {
			return false;
		}
		/**
		 * Exibir a barra fixa do botão "Área do Cliente" nesta página.
		 *
		 * @param bool $show Exibir (padrão: fora da página do portal).
		 */
		return (bool) apply_filters( 'ebcr_client_area_bar_visible', ! self::is_portal_page() );
	}

	/**
	 * HTML da barra fixa.
	 *
	 * @return string
	 */
	public static function bar_html() {
		$pos    = 'top-left' === Options::get( 'client_area_bar_position', 'top-right' ) ? 'top-left' : 'top-right';
		$offset = max( 0, min( 200, (int) Options::get( 'client_area_bar_offset', 16 ) ) );
		return sprintf(
			'<nav class="ebcr-ca-bar ebcr-ca-bar--%1$s" style="--ebcr-ca-offset:%2$dpx" aria-label="%3$s">%4$s</nav>',
			esc_attr( $pos ),
			$offset,
			esc_attr( self::label() ),
			self::button( array( 'style' => (string) Options::get( 'client_area_bar_style', 'primary' ) ) )
		);
	}

	/**
	 * Imprime a barra (wp_body_open).
	 *
	 * @return void
	 */
	public static function print_bar() {
		if ( self::$bar_done || ! self::bar_enabled() ) {
			return;
		}
		self::$bar_done = true;
		echo self::bar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapado em bar_html()/button().
	}

	/**
	 * Rodapé: barra (se o tema não chama wp_body_open) e auxiliar JS.
	 *
	 * @return void
	 */
	public static function footer() {
		if ( ! did_action( 'wp_body_open' ) ) {
			self::print_bar();
		}
		/**
		 * Imprime o auxiliar JS (window.EBCR_CLIENT_AREA + a[data-ebcr-client-area]).
		 *
		 * @param bool $enabled Ativo.
		 */
		if ( is_admin() || ! apply_filters( 'ebcr_client_area_helper_enabled', true ) ) {
			return;
		}
		$json = wp_json_encode( self::js_data(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES );
		$js   = '(function(){var d=window.EBCR_CLIENT_AREA=' . $json . ';function run(){var l=document.querySelectorAll("a[data-ebcr-client-area]");for(var i=0;i<l.length;i++){var a=l[i];a.setAttribute("href",d.url);if(d.loggedIn){var t=a.getAttribute("data-label-logged")||d.labelLogged;a.textContent=d.userFirstName?d.userFirstName+" · "+t:t;}}}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",run);}else{run();}})();';
		if ( function_exists( 'wp_print_inline_script_tag' ) ) {
			wp_print_inline_script_tag( $js, array( 'id' => 'ebcr-client-area-helper' ) );
		} else {
			echo '<script id="ebcr-client-area-helper">' . $js . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON com JSON_HEX_*.
		}
	}

	/**
	 * Item no menu clássico escolhido.
	 *
	 * @param string    $items HTML dos itens.
	 * @param \stdClass $args  Argumentos do wp_nav_menu.
	 * @return string
	 */
	public static function menu_items( $items, $args ) {
		$location = (string) Options::get( 'client_area_menu_location', '' );
		if ( '' === $location || ! is_object( $args ) || empty( $args->theme_location ) || $location !== $args->theme_location ) {
			return $items;
		}
		return $items . '<li class="menu-item menu-item-type-custom ebcr-ca-menu-item">' . self::button( array( 'style' => (string) Options::get( 'client_area_menu_style', 'primary' ) ) ) . '</li>';
	}

	/**
	 * Item no primeiro bloco Navegação da página (temas de blocos).
	 *
	 * @param string $content HTML do bloco.
	 * @param array  $block   Bloco.
	 * @return string
	 */
	public static function navigation_block( $content, $block = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- assinatura do filtro.
		if ( self::$nav_done || ! Options::bool( 'client_area_nav_block' ) || is_admin() ) {
			return $content;
		}
		$pos = strrpos( (string) $content, '</ul>' );
		if ( false === $pos ) {
			return $content;
		}
		self::$nav_done = true;
		$item           = '<li class="wp-block-navigation-item ebcr-ca-nav-item">' . self::button( array( 'style' => (string) Options::get( 'client_area_menu_style', 'primary' ) ) ) . '</li>';
		return substr( $content, 0, $pos ) . $item . substr( $content, $pos );
	}

	// ------------------------------------------------------------------ endereço amigável

	/**
	 * Regra /area-do-cliente/ → portal (só se não houver página com esse slug). Renova as regras uma vez quando muda.
	 *
	 * @return void
	 */
	public static function rewrite() {
		self::add_rewrite_rules();
		$sig = md5( wp_json_encode( array( self::alias_active(), self::slug(), EBCR_VERSION ) ) );
		if ( get_option( self::SIG_OPT ) !== $sig ) {
			update_option( self::SIG_OPT, $sig, true );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Adiciona a regra (também chamada na ativação, antes de flush_rewrite_rules()).
	 *
	 * @return void
	 */
	public static function add_rewrite_rules() {
		if ( self::alias_active() ) {
			// O slug já passou por sanitize_title(); o hífen não precisa de escape na regex.
			add_rewrite_rule( '^' . str_replace( '\\-', '-', preg_quote( self::slug(), '/' ) ) . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
		}
	}

	/**
	 * Registra a query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Links permanentes simples: /area-do-cliente/ chega ao WordPress sem regra de reescrita; redireciona pelo caminho.
	 *
	 * @param \WP $wp Requisição.
	 * @return void
	 */
	public static function plain_alias( $wp ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- assinatura da ação.
		if ( '' !== (string) get_option( 'permalink_structure' ) || ! self::alias_active() || empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$path = (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $home && 0 === strpos( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		if ( trim( $path, '/' ) === self::slug() ) {
			self::redirect();
		}
	}

	/**
	 * ?ebcr_client_area=1 ou /area-do-cliente/ → portal.
	 *
	 * @return void
	 */
	public static function maybe_redirect() {
		if ( get_query_var( self::QUERY_VAR ) ) {
			self::redirect();
		}
	}

	/**
	 * Redireciona à área do cliente.
	 *
	 * @return void
	 */
	private static function redirect() {
		nocache_headers();
		wp_safe_redirect( self::url(), 302, 'EB Credito Rural' );
		exit;
	}
}
