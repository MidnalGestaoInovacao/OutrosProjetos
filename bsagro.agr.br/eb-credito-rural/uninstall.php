<?php
/**
 * Desinstalação: remove dados apenas se "Manter dados ao desinstalar" estiver desligado.
 *
 * @package EBCR
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$ebcr_settings = get_option( 'ebcr_settings', array() );
$ebcr_keep     = ! is_array( $ebcr_settings ) || ! array_key_exists( 'keep_data_on_uninstall', $ebcr_settings ) || ! empty( $ebcr_settings['keep_data_on_uninstall'] );

if ( $ebcr_keep ) {
	return;
}

require_once __DIR__ . '/src/Autoloader.php';
EBCR\Autoloader::register( __DIR__ . '/src/' );

global $wpdb;

// Arquivos privados (apenas se estiverem dentro de uploads, para não apagar uma pasta externa por engano).
$ebcr_uploads = wp_upload_dir();
$ebcr_private = trailingslashit( $ebcr_uploads['basedir'] ) . 'ebcr-private';
if ( is_dir( $ebcr_private ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	global $wp_filesystem;
	if ( $wp_filesystem ) {
		$wp_filesystem->rmdir( $ebcr_private, true );
	}
}

foreach ( EBCR\Install\Schema::tables() as $ebcr_table ) {
	$ebcr_name = EBCR\Database\Db::table( $ebcr_table );
	$wpdb->query( "DROP TABLE IF EXISTS `{$ebcr_name}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
}

EBCR\Roles\Capabilities::uninstall();

foreach ( array( 'ebcr_settings', 'ebcr_db_version', 'ebcr_protocol_seq' ) as $ebcr_option ) {
	delete_option( $ebcr_option );
}
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ebcr\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- limpeza na desinstalação.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_ebcr\_%' OR option_name LIKE '\_transient\_timeout\_ebcr\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- limpeza na desinstalação.
wp_clear_scheduled_hook( 'ebcr_daily' );
wp_clear_scheduled_hook( 'ebcr_process_mail_queue' );
