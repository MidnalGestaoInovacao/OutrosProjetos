<?php
/**
 * Bootstrap dos testes: carrega um WordPress instalado (WP_ROOT) com o plugin ativo.
 * Uso: WP_ROOT=/caminho/do/wordpress php phpunit.phar
 *
 * @package EBCR
 */

define( 'EBCR_TESTING', true );
$ebcr_wp_root = getenv( 'WP_ROOT' );
if ( ! $ebcr_wp_root || ! file_exists( $ebcr_wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Defina WP_ROOT apontando para uma instalação do WordPress (com o plugin em wp-content/plugins/eb-credito-rural).\n" );
	exit( 1 );
}
$_SERVER['HTTP_HOST']       = 'localhost';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['SERVER_NAME']     = 'localhost';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
require_once $ebcr_wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if ( ! is_plugin_active( 'eb-credito-rural/eb-credito-rural.php' ) ) {
	activate_plugin( 'eb-credito-rural/eb-credito-rural.php' );
}
EBCR\Install\Schema::install();
EBCR\Roles\Capabilities::install();
EBCR\Plugin::instance()->boot();
require_once __DIR__ . '/TestCase.php';
