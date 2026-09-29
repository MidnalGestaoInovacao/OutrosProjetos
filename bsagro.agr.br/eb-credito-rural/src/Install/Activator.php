<?php
/**
 * Rotina de ativação.
 *
 * @package EBCR
 */

namespace EBCR\Install;

use EBCR\Cron\Scheduler;
use EBCR\Files\FileGuard;
use EBCR\Roles\Capabilities;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Cria tabelas, papéis, pasta privada e agenda cron.
 */
final class Activator {

	/**
	 * Ativação.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( version_compare( PHP_VERSION, EBCR_MIN_PHP, '<' ) ) {
			deactivate_plugins( EBCR_BASENAME );
			wp_die( esc_html__( 'EB Crédito Rural requer PHP 8.1 ou superior.', 'eb-credito-rural' ) );
		}
		Schema::install();
		Capabilities::install();
		$fresh = false === get_option( Migrator::OPTION, false ) && false === get_option( Options::OPTION, false );
		update_option( Migrator::OPTION, EBCR_DB_VERSION, false );
		if ( false === get_option( Options::OPTION, false ) ) {
			add_option( Options::OPTION, array(), '', false );
		}
		// Instalação nova: nada a preservar das versões anteriores. Atualização: preserva os padrões antigos alterados.
		if ( $fresh ) {
			update_option( Migrator::SETTINGS_OPTION, Migrator::SETTINGS_VERSION, true );
		} else {
			Migrator::maybe_upgrade_settings();
		}
		// Pasta privada: cria (com .htaccess/index.php) e registra o resultado do teste para o admin.
		$guard = new FileGuard();
		$guard->ensure_base_dir();
		set_transient( 'ebcr_activation_notice', 1, 300 );
		Scheduler::schedule();
		\EBCR\Abilities\Abilities::enable_in_easy_mcp();
		// Endereço amigável da área do cliente (/area-do-cliente/), registrado antes de renovar as regras.
		\EBCR\Frontend\ClientArea::add_rewrite_rules();
		flush_rewrite_rules();
	}
}
