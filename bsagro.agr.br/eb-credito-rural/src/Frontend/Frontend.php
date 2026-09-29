<?php
/**
 * Shortcodes, assets e registro dos handlers do front-end.
 *
 * @package EBCR
 */

namespace EBCR\Frontend;

use EBCR\Forms\Steps;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * [ebcr_portal] [ebcr_login] [ebcr_register] [ebcr_form] [ebcr_cta]
 */
final class Frontend {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		( new Auth() )->register();
		( new Portal() )->register();
		add_shortcode( 'ebcr_portal', array( $this, 'sc_portal' ) );
		add_shortcode( 'ebcr_login', array( $this, 'sc_login' ) );
		add_shortcode( 'ebcr_register', array( $this, 'sc_register' ) );
		add_shortcode( 'ebcr_form', array( $this, 'sc_form' ) );
		add_shortcode( 'ebcr_cta', array( $this, 'sc_cta' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'init', array( $this, 'form_cookie' ) );
		add_action( 'init', array( __CLASS__, 'disable_emoji' ), 20 );
		add_filter( 'logout_redirect', array( $this, 'logout_redirect' ), 10, 3 );
		add_action(
			'save_post_page',
			static function () {
				delete_transient( 'ebcr_portal_page_auto' );
			}
		);
	}

	/**
	 * Remove o script de detecção e os estilos de emoji do WordPress no site público (configuração disable_wp_emoji).
	 * O wp-admin não é alterado.
	 *
	 * @return bool Removido.
	 */
	public static function disable_emoji() {
		if ( is_admin() || ! Options::bool( 'disable_wp_emoji' ) ) {
			return false;
		}
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'embed_head', 'print_emoji_detection_script' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'enqueue_embed_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		add_filter( 'emoji_svg_url', '__return_false' ); // Sem dns-prefetch para o CDN de emojis.
		return true;
	}

	/**
	 * Cookie anônimo para reexibir erros de login/cadastro.
	 *
	 * @return void
	 */
	public function form_cookie() {
		if ( is_admin() || is_user_logged_in() || ! empty( $_COOKIE['ebcr_fid'] ) || headers_sent() ) {
			return;
		}
		setcookie(
			'ebcr_fid',
			Helpers::random_hex( 8 ),
			array(
				'expires'  => time() + DAY_IN_SECONDS,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Após logout, volta ao portal.
	 *
	 * @param string   $redirect  Destino.
	 * @param string   $requested Pedido.
	 * @param \WP_User $user      Usuário.
	 * @return string
	 */
	public function logout_redirect( $redirect, $requested, $user ) {
		if ( $user instanceof \WP_User && ( user_can( $user, \EBCR\Roles\Capabilities::CAP_CLIENT ) || user_can( $user, \EBCR\Roles\Capabilities::CAP_VIEW ) ) && ! $requested ) {
			return Helpers::portal_url( array( 'ebcr_msg' => 'logged_out' ) );
		}
		return $redirect;
	}

	/**
	 * Registra assets (enfileirados sob demanda).
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style( 'ebcr-portal', EBCR_URL . 'assets/css/portal.css', array( \EBCR\Admin\Branding::style_handle() ), EBCR_VERSION );
		wp_register_script( 'ebcr-portal', EBCR_URL . 'assets/js/portal.js', array(), EBCR_VERSION, true );
		wp_register_script( 'ebcr-wizard', EBCR_URL . 'assets/js/wizard.js', array( 'ebcr-portal' ), EBCR_VERSION, true );
		global $post;
		if ( $post instanceof \WP_Post && has_shortcode( $post->post_content, 'ebcr_portal' ) ) {
			self::enqueue_portal();
		}
	}

	/**
	 * Enfileira CSS/JS do portal.
	 *
	 * @return void
	 */
	public static function enqueue_portal() {
		wp_enqueue_style( 'ebcr-portal' );
		wp_enqueue_script( 'ebcr-portal' );
		wp_localize_script(
			'ebcr-portal',
			'EBCR',
			array(
				'rest'  => esc_url_raw( rest_url( 'ebcr/v1/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'  => array(
					'saving'         => __( 'Salvando…', 'eb-credito-rural' ),
					'saved'          => __( 'Rascunho salvo automaticamente.', 'eb-credito-rural' ),
					'sending'        => __( 'Enviando…', 'eb-credito-rural' ),
					'error'          => __( 'Ocorreu um erro. Tente novamente.', 'eb-credito-rural' ),
					'invalid'        => __( 'Valor inválido.', 'eb-credito-rural' ),
					'remove'         => __( 'Remover', 'eb-credito-rural' ),
					'confirm_delete' => __( 'Remover este documento?', 'eb-credito-rural' ),
					'weak'           => __( 'Fraca', 'eb-credito-rural' ),
					'medium'         => __( 'Média', 'eb-credito-rural' ),
					'strong'         => __( 'Forte', 'eb-credito-rural' ),
					'choose'         => __( 'escolha uma opção.', 'eb-credito-rural' ),
				),
			)
		);
	}

	/**
	 * Enfileira o JS do formulário com o contexto.
	 *
	 * @param array    $submission Solicitação.
	 * @param int      $step       Etapa.
	 * @param int|null $next       Próxima etapa ativa (padrão: a seguinte).
	 * @return void
	 */
	public static function enqueue_wizard( array $submission, $step, $next = null ) {
		self::enqueue_portal();
		wp_enqueue_script( 'ebcr-wizard' );
		wp_localize_script(
			'ebcr-wizard',
			'EBCR_WIZARD',
			array(
				'id'         => $submission['public_id'],
				'step'       => (int) $step,
				'autosave'   => 40,
				'next_url'   => Helpers::portal_url(
					array(
						'ebcr_view' => 'formulario',
						'id'        => $submission['public_id'],
						'etapa'     => null === $next ? min( 7, $step + 1 ) : (int) $next,
					)
				),
				'max_mb'     => max( 1, Options::int( 'max_file_size_mb' ) ),
				'extensions' => Options::allowed_extensions(),
			)
		);
	}

	/**
	 * [ebcr_portal].
	 *
	 * @return string
	 */
	public function sc_portal() {
		return ( new Portal() )->render();
	}

	/**
	 * [ebcr_login].
	 *
	 * @return string
	 */
	public function sc_login() {
		self::enqueue_portal();
		if ( is_user_logged_in() ) {
			return sprintf( '<div class="ebcr-portal"><p><a class="ebcr-btn" href="%s">%s</a></p></div>', esc_url( Helpers::portal_url() ), esc_html__( 'Acessar minha área', 'eb-credito-rural' ) );
		}
		return '<div class="ebcr-portal">' . ( new Portal() )->render_auth( 'login' ) . '</div>';
	}

	/**
	 * [ebcr_register].
	 *
	 * @return string
	 */
	public function sc_register() {
		self::enqueue_portal();
		if ( is_user_logged_in() ) {
			return sprintf( '<div class="ebcr-portal"><p><a class="ebcr-btn" href="%s">%s</a></p></div>', esc_url( Helpers::portal_url() ), esc_html__( 'Acessar minha área', 'eb-credito-rural' ) );
		}
		return '<div class="ebcr-portal">' . ( new Portal() )->render_auth( 'register' ) . '</div>';
	}

	/**
	 * [ebcr_form] — redireciona ao formulário (login se necessário).
	 *
	 * @return string
	 */
	public function sc_form() {
		if ( ! is_user_logged_in() ) {
			return sprintf( '<p class="ebcr-alert ebcr-alert--info">%s <a href="%s">%s</a></p>', esc_html__( 'Para preencher a solicitação, entre na sua conta ou cadastre-se.', 'eb-credito-rural' ), esc_url( Helpers::portal_url( array( 'redirect_to' => get_permalink() ) ) ), esc_html__( 'Entrar', 'eb-credito-rural' ) );
		}
		$_GET['ebcr_view'] = isset( $_GET['ebcr_view'] ) ? $_GET['ebcr_view'] : 'painel'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput -- reaproveita o roteamento do portal.
		return ( new Portal() )->render();
	}

	/**
	 * [ebcr_cta titulo="" texto="" botao="" url=""].
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_cta( $atts ) {
		$a = shortcode_atts(
			array(
				'titulo' => __( 'Solicite crédito rural com estrutura', 'eb-credito-rural' ),
				'texto'  => __( 'Cadastre-se, envie seus dados e documentos com segurança e acompanhe a análise pela sua área.', 'eb-credito-rural' ),
				'botao'  => __( 'Iniciar solicitação', 'eb-credito-rural' ),
				'url'    => Helpers::portal_url( array( 'ebcr_view' => 'cadastro' ) ),
			),
			$atts,
			'ebcr_cta'
		);
		self::enqueue_portal();
		return sprintf(
			'<div class="ebcr-cta"><h3>%s</h3><p>%s</p><a class="ebcr-btn ebcr-btn--primary" href="%s">%s</a></div>',
			esc_html( $a['titulo'] ),
			esc_html( $a['texto'] ),
			esc_url( $a['url'] ),
			esc_html( $a['botao'] )
		);
	}
}
