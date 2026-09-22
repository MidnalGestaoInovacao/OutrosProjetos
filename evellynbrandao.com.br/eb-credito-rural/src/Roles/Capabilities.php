<?php
/**
 * Papéis e capacidades do plugin.
 *
 * @package EBCR
 */

namespace EBCR\Roles;

use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Papéis: ebcr_cliente, ebcr_analista, ebcr_gestor (+ administrator com tudo).
 */
final class Capabilities {

	const ROLE_CLIENT  = 'ebcr_cliente';
	const ROLE_ANALYST = 'ebcr_analista';
	const ROLE_MANAGER = 'ebcr_gestor';

	const CAP_CLIENT       = 'ebcr_client_portal';
	const CAP_VIEW         = 'ebcr_view_submissions';
	const CAP_EDIT         = 'ebcr_edit_submissions';
	const CAP_DOWNLOAD     = 'ebcr_download_documents';
	const CAP_CRM          = 'ebcr_manage_crm';
	const CAP_FINAL_STATUS = 'ebcr_change_final_status';
	const CAP_DASHBOARD    = 'ebcr_view_dashboard';
	const CAP_EXPORT       = 'ebcr_export';
	const CAP_SETTINGS     = 'ebcr_manage_settings';
	const CAP_AUDIT        = 'ebcr_view_audit_log';

	/**
	 * Capacidades por papel.
	 *
	 * @return array
	 */
	public static function map() {
		$analyst = array( self::CAP_VIEW, self::CAP_EDIT, self::CAP_DOWNLOAD, self::CAP_CRM );
		$manager = array_merge( $analyst, array( self::CAP_FINAL_STATUS, self::CAP_DASHBOARD, self::CAP_EXPORT ) );
		$admin   = array_merge( $manager, array( self::CAP_SETTINGS, self::CAP_AUDIT ) );
		return array(
			self::ROLE_CLIENT  => array( self::CAP_CLIENT ),
			self::ROLE_ANALYST => $analyst,
			self::ROLE_MANAGER => $manager,
			'administrator'    => $admin,
		);
	}

	/**
	 * Todas as capacidades do plugin.
	 *
	 * @return string[]
	 */
	public static function all_caps() {
		return array_values( array_unique( array_merge( ...array_values( self::map() ) ) ) );
	}

	/**
	 * Cria/atualiza papéis na ativação.
	 *
	 * @return void
	 */
	public static function install() {
		$labels = array(
			self::ROLE_CLIENT  => __( 'Cliente (produtor rural)', 'eb-credito-rural' ),
			self::ROLE_ANALYST => __( 'Analista de crédito', 'eb-credito-rural' ),
			self::ROLE_MANAGER => __( 'Gestor de crédito', 'eb-credito-rural' ),
		);
		foreach ( $labels as $role => $label ) {
			$caps = array( 'read' => true );
			foreach ( self::map()[ $role ] as $cap ) {
				$caps[ $cap ] = true;
			}
			if ( get_role( $role ) ) {
				remove_role( $role );
			}
			add_role( $role, $label, $caps );
		}
		self::ensure_admin_caps();
	}

	/**
	 * Remove papéis (desinstalação). Usuários com esses papéis passam a "subscriber".
	 *
	 * @return void
	 */
	public static function uninstall() {
		foreach ( array( self::ROLE_CLIENT, self::ROLE_ANALYST, self::ROLE_MANAGER ) as $role ) {
			$users = get_users(
				array(
					'role'   => $role,
					'fields' => 'ID',
				)
			);
			foreach ( $users as $uid ) {
				$u = new \WP_User( $uid );
				$u->remove_role( $role );
				if ( empty( $u->roles ) ) {
					$u->add_role( 'subscriber' );
				}
			}
			remove_role( $role );
		}
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::all_caps() as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
	}

	/**
	 * Garante que o administrador tenha todas as capacidades (também após atualização).
	 *
	 * @return void
	 */
	public static function ensure_admin_caps() {
		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			return;
		}
		foreach ( self::map()['administrator'] as $cap ) {
			if ( ! $admin->has_cap( $cap ) ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * Clientes não acessam o wp-admin (redirecionados ao portal) e não veem a admin bar.
	 *
	 * @return void
	 */
	public static function restrict_clients() {
		add_filter(
			'show_admin_bar',
			static function ( $show ) {
				if ( is_user_logged_in() && self::is_client_only() ) {
					return false;
				}
				return $show;
			}
		);
		add_action(
			'admin_init',
			static function () {
				if ( wp_doing_ajax() || ! is_user_logged_in() || ! self::is_client_only() ) {
					return;
				}
				wp_safe_redirect( Helpers::portal_url() );
				exit;
			}
		);
	}

	/**
	 * O usuário atual é apenas cliente (sem capacidades de equipe)?
	 *
	 * @return bool
	 */
	public static function is_client_only() {
		return current_user_can( self::CAP_CLIENT ) && ! current_user_can( self::CAP_VIEW ) && ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' );
	}
}
