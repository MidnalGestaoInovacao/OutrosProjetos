<?php
/**
 * Roteador dos formulários do portal: os POSTs vão para a própria página do portal (front-end), não para wp-admin/admin-post.php.
 * Motivo: firewalls de hospedagem (ModSecurity/Imunify) costumam bloquear POSTs a /wp-admin/ de visitantes e clientes; o admin-post.php
 * continua aceito como fallback.
 *
 * @package EBCR
 */

namespace EBCR\Frontend;

use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Despacha ações ebcr_* recebidas por POST na página do portal para os mesmos hooks admin_post_*.
 */
final class FormRouter {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'dispatch' ), 30 );
	}

	/**
	 * URL de destino dos formulários do portal.
	 *
	 * @param array $args Parâmetros extras.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return Helpers::portal_url( $args );
	}

	/**
	 * Executa o handler da ação (mesmos hooks do admin-post.php).
	 *
	 * @return void
	 */
	public function dispatch() {
		// phpcs:disable WordPress.Security.NonceVerification -- cada handler verifica o próprio nonce (Nonces::verify) antes de agir.
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['action'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['action'] ) );
		// phpcs:enable
		if ( 0 !== strpos( $action, 'ebcr_' ) ) {
			return;
		}
		$hook = ( is_user_logged_in() ? 'admin_post_' : 'admin_post_nopriv_' ) . $action;
		if ( ! has_action( $hook ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		nocache_headers();
		do_action( $hook ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- mesmos hooks admin_post_ebcr_* do admin-post.php; os handlers redirecionam e encerram.
	}
}
