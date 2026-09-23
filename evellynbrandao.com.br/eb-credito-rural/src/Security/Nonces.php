<?php
/**
 * Nonces com nomes padronizados.
 *
 * @package EBCR
 */

namespace EBCR\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers de nonce.
 */
final class Nonces {

	/**
	 * Campo oculto.
	 *
	 * @param string $action Ação.
	 * @return string
	 */
	public static function field( $action ) {
		return wp_nonce_field( 'ebcr_' . $action, 'ebcr_nonce', true, false );
	}

	/**
	 * Verifica nonce de formulário ($_POST['ebcr_nonce']).
	 *
	 * @param string $action Ação.
	 * @param array  $input  Dados (padrão $_POST).
	 * @return bool
	 */
	public static function verify( $action, ?array $input = null ) {
		if ( null === $input ) {
			$input = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- é exatamente a verificação do nonce.
		}
		$nonce = isset( $input['ebcr_nonce'] ) ? sanitize_text_field( wp_unslash( $input['ebcr_nonce'] ) ) : '';
		return (bool) wp_verify_nonce( $nonce, 'ebcr_' . $action );
	}

	/**
	 * URL com nonce.
	 *
	 * @param string $url    URL.
	 * @param string $action Ação.
	 * @return string
	 */
	public static function url( $url, $action ) {
		return wp_nonce_url( $url, 'ebcr_' . $action, 'ebcr_nonce' );
	}

	/**
	 * Verifica nonce vindo por GET (ebcr_nonce).
	 *
	 * @param string $action Ação.
	 * @return bool
	 */
	public static function verify_get( $action ) {
		$nonce = isset( $_GET['ebcr_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['ebcr_nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- é a verificação.
		return (bool) wp_verify_nonce( $nonce, 'ebcr_' . $action );
	}
}
