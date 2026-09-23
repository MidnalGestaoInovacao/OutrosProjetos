<?php
/**
 * Plugin Name:       EB Crédito Rural
 * Plugin URI:        https://evellynbrandao.com.br/
 * Description:       Captação de solicitações de crédito rural: cadastro do produtor, formulário em etapas, documentos protegidos, notificações, área do cliente e painel da equipe.
 * Version:           1.1.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Évellyn Brandão
 * Author URI:        https://evellynbrandao.com.br/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eb-credito-rural
 * Domain Path:       /languages
 *
 * @package EBCR
 */

defined( 'ABSPATH' ) || exit;

define( 'EBCR_VERSION', '1.1.1' );
define( 'EBCR_DB_VERSION', '1.1.0' );
define( 'EBCR_FILE', __FILE__ );
define( 'EBCR_DIR', plugin_dir_path( __FILE__ ) );
define( 'EBCR_URL', plugin_dir_url( __FILE__ ) );
define( 'EBCR_BASENAME', plugin_basename( __FILE__ ) );
define( 'EBCR_MIN_PHP', '8.1' );
define( 'EBCR_MIN_WP', '6.4' );

/**
 * Verificação mínima de ambiente antes de carregar qualquer classe.
 *
 * @return bool
 */
function ebcr_environment_ok() {
	global $wp_version;
	if ( version_compare( PHP_VERSION, EBCR_MIN_PHP, '<' ) || version_compare( $wp_version, EBCR_MIN_WP, '<' ) ) {
		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-error"><p>' . esc_html(
					sprintf(
						/* translators: 1: versão mínima do PHP, 2: versão mínima do WordPress */
						__( 'EB Crédito Rural requer PHP %1$s ou superior e WordPress %2$s ou superior. O plugin não foi carregado.', 'eb-credito-rural' ),
						EBCR_MIN_PHP,
						EBCR_MIN_WP
					)
				) . '</p></div>';
			}
		);
		return false;
	}
	return true;
}

if ( ! ebcr_environment_ok() ) {
	return;
}

// Autoloader próprio (PSR-4: EBCR\ => src/). Composer é opcional: se existir vendor/autoload.php, é usado também.
require_once EBCR_DIR . 'src/Autoloader.php';
EBCR\Autoloader::register( EBCR_DIR . 'src/' );
require_once EBCR_DIR . 'src/functions.php';
if ( file_exists( EBCR_DIR . 'vendor/autoload.php' ) ) {
	require_once EBCR_DIR . 'vendor/autoload.php';
}

register_activation_hook( __FILE__, array( 'EBCR\Install\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'EBCR\Install\Deactivator', 'deactivate' ) );

/**
 * Instância única do plugin.
 *
 * @return \EBCR\Plugin
 */
function ebcr() {
	return \EBCR\Plugin::instance();
}

add_action( 'plugins_loaded', array( ebcr(), 'boot' ), 5 );
