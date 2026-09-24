<?php
/**
 * Orquestra o carregamento dos módulos e hooks do plugin.
 *
 * @package EBCR
 */

namespace EBCR;

use EBCR\Abilities\Abilities;
use EBCR\Admin\Admin;
use EBCR\Cron\Scheduler;
use EBCR\Files\DownloadController;
use EBCR\Frontend\Frontend;
use EBCR\Install\Migrator;
use EBCR\Mail\Queue;
use EBCR\Rest\Routes;
use EBCR\Roles\Capabilities;
use EBCR\Security\AuditLog;
use EBCR\Security\LoginGuard;
use EBCR\Security\PrivacyIntegration;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton do plugin.
 */
final class Plugin {

	/**
	 * Instância.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Já inicializado?
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Instância única.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registra os hooks de todos os módulos.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		load_plugin_textdomain( 'eb-credito-rural', false, dirname( EBCR_BASENAME ) . '/languages' );

		// Migrações pendentes (por exemplo, após atualização do plugin sem reativar).
		add_action( 'init', array( Migrator::class, 'maybe_upgrade' ), 1 );

		// Papéis/capacidades: garante que o administrador tenha todas as capacidades.
		add_action( 'init', array( Capabilities::class, 'ensure_admin_caps' ), 2 );
		add_action( 'init', array( Capabilities::class, 'restrict_clients' ), 3 );

		// Segurança.
		( new LoginGuard() )->register();
		( new AuditLog() )->register();
		( new PrivacyIntegration() )->register();

		// Arquivos: download autenticado.
		( new DownloadController() )->register();

		// REST interno.
		( new Routes() )->register();

		// Abilities (WordPress Abilities API → Easy MCP AI).
		( new Abilities() )->register();

		// E-mails (fila).
		( new Queue() )->register();

		// Cron.
		( new Scheduler() )->register();

		// Front-end (shortcodes, portal, autenticação).
		( new Frontend() )->register();
		( new \EBCR\Frontend\FormRouter() )->register();
		( new \EBCR\Frontend\Simulator() )->register();
		( new \EBCR\Frontend\Team\Panel() )->register();
		( new \EBCR\Integrations\Lookup() )->register();
		( new \EBCR\Integrations\WhatsApp() )->register();
		( new \EBCR\Security\Turnstile() )->register();
		( new \EBCR\Security\TwoFactor() )->register();
		( new \EBCR\Esign\Esign() )->register();
		( new \EBCR\Admin\Crm() )->register();
		( new \EBCR\Admin\Reports() )->register();
		( new \EBCR\Admin\Branding() )->register();

		// Admin.
		if ( is_admin() ) {
			( new Admin() )->register();
		}

		/**
		 * Disparado após o carregamento completo do plugin.
		 *
		 * @param Plugin $plugin Instância.
		 */
		do_action( 'ebcr_loaded', $this );
	}
}
