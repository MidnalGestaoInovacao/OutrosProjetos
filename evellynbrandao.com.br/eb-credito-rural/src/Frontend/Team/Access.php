<?php
/**
 * Regras do modo "só no site" (team_portal_mode = portal_only): decide, sem efeitos colaterais, quando um membro da
 * equipe deve ser levado ao painel do portal em vez do wp-admin. Funções puras, testáveis sem HTTP.
 *
 * @package EBCR
 */

namespace EBCR\Frontend\Team;

use EBCR\Roles\Capabilities;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Modo de operação da equipe: both (painel no site e wp-admin) ou portal_only (só o painel no site).
 */
final class Access {

	const MODE_BOTH        = 'both';
	const MODE_PORTAL_ONLY = 'portal_only';

	/**
	 * Modo configurado (sempre um dos dois valores conhecidos).
	 *
	 * @return string
	 */
	public static function mode() {
		$mode = (string) Options::get( 'team_portal_mode', self::MODE_BOTH );
		return self::MODE_PORTAL_ONLY === $mode ? self::MODE_PORTAL_ONLY : self::MODE_BOTH;
	}

	/**
	 * O modo "só no site" está ativo?
	 *
	 * @return bool
	 */
	public static function portal_only() {
		return self::MODE_PORTAL_ONLY === self::mode();
	}

	/**
	 * Administrador do WordPress (nunca é bloqueado).
	 *
	 * @param int $user_id Usuário.
	 * @return bool
	 */
	public static function is_admin_user( $user_id ) {
		return $user_id > 0 && user_can( (int) $user_id, 'manage_options' );
	}

	/**
	 * Membro da equipe sujeito ao bloqueio do wp-admin (analista/gestor sem manage_options) no modo informado.
	 *
	 * @param int    $user_id Usuário.
	 * @param string $mode    Modo (both|portal_only).
	 * @return bool
	 */
	public static function is_restricted( $user_id, $mode ) {
		if ( self::MODE_PORTAL_ONLY !== $mode || $user_id <= 0 ) {
			return false;
		}
		return user_can( (int) $user_id, Capabilities::CAP_VIEW ) && ! self::is_admin_user( $user_id );
	}

	/**
	 * URL para onde uma requisição ao wp-admin deve ser redirecionada, ou '' para deixá-la passar.
	 * admin-ajax, REST e admin-post.php nunca são redirecionados (são chamadas de API, não telas).
	 *
	 * @param int    $user_id Usuário logado (0 = anônimo).
	 * @param string $mode    Modo (both|portal_only).
	 * @param array  $context pagenow (string), ajax (bool), rest (bool).
	 * @return string
	 */
	public static function admin_redirect_url( $user_id, $mode, array $context = array() ) {
		$pagenow = isset( $context['pagenow'] ) ? (string) $context['pagenow'] : '';
		if ( ! empty( $context['ajax'] ) || ! empty( $context['rest'] ) || in_array( $pagenow, array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php' ), true ) ) {
			return '';
		}
		if ( ! self::is_restricted( $user_id, $mode ) ) {
			return '';
		}
		return Panel::url();
	}

	/**
	 * Destino após o login (wp-login.php): no modo "só no site", a equipe vai ao painel do portal.
	 *
	 * @param string $redirect_to Destino atual.
	 * @param int    $user_id     Usuário.
	 * @param string $mode        Modo.
	 * @return string
	 */
	public static function login_destination( $redirect_to, $user_id, $mode ) {
		if ( self::is_restricted( $user_id, $mode ) ) {
			return Panel::url();
		}
		return $redirect_to;
	}

	/**
	 * Exibir a barra de administração? No modo "só no site" a equipe (não administradores) não a vê.
	 *
	 * @param bool   $show    Valor atual do filtro.
	 * @param int    $user_id Usuário.
	 * @param string $mode    Modo.
	 * @return bool
	 */
	public static function show_admin_bar( $show, $user_id, $mode ) {
		if ( self::is_restricted( $user_id, $mode ) ) {
			return false;
		}
		return (bool) $show;
	}
}
