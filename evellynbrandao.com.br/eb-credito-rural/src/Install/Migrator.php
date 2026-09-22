<?php
/**
 * Migrações incrementais do esquema.
 *
 * @package EBCR
 */

namespace EBCR\Install;

use EBCR\Roles\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Controle de versão do banco (opção ebcr_db_version).
 */
final class Migrator {

	const OPTION = 'ebcr_db_version';

	/**
	 * Executa migrações se a versão salva for anterior à atual.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$current = get_option( self::OPTION, '0' );
		if ( version_compare( $current, EBCR_DB_VERSION, '>=' ) ) {
			return;
		}
		self::run( $current );
	}

	/**
	 * Roda todas as migrações necessárias a partir de uma versão.
	 *
	 * @param string $from Versão salva.
	 * @return void
	 */
	public static function run( $from ) {
		// 1.0.0: esquema inicial. Futuras versões: adicionar blocos "if ( version_compare( $from, 'X', '<' ) ) { ... }".
		Schema::install();
		Capabilities::ensure_admin_caps();
		update_option( self::OPTION, EBCR_DB_VERSION, false );
		/**
		 * Após migração.
		 *
		 * @param string $from Versão anterior.
		 * @param string $to   Versão atual.
		 */
		do_action( 'ebcr_migrated', $from, EBCR_DB_VERSION );
	}
}
