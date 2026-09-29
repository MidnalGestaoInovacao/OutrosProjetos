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
	 * Versão das configurações (migrações só de opções, sem mudança no banco).
	 */
	const SETTINGS_OPTION  = 'ebcr_settings_version';
	const SETTINGS_VERSION = '1.3.0';

	/**
	 * Padrões anteriores que mudaram e devem ser preservados em instalações existentes (chave => valor antigo).
	 *
	 * @return array
	 */
	public static function legacy_defaults() {
		return array(
			// Até a 1.2.2 o nome da operação vinha fixo; a partir da 1.3.0 o padrão usa o nome do site.
			'operation_name' => 'Crédito Rural — Évellyn Brandão',
			// Até a 1.2.2 o encarregado (DPO) padrão era o do site original; a partir da 1.3.0 o padrão é vazio.
			'dpo_name'       => 'Sanclé Albuquerque',
		);
	}

	/**
	 * Migração de configurações: em sites que já usavam o plugin, grava explicitamente os padrões antigos que mudaram
	 * (só quando a chave nunca foi salva), para que nada mude na atualização.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_settings() {
		$current = (string) get_option( self::SETTINGS_OPTION, '' );
		if ( '' !== $current && version_compare( $current, self::SETTINGS_VERSION, '>=' ) ) {
			return;
		}
		$saved = get_option( \EBCR\Support\Options::OPTION, false );
		if ( '' === $current && ( false !== $saved || false !== get_option( self::OPTION, false ) ) ) {
			$saved  = is_array( $saved ) ? $saved : array();
			$freeze = array();
			foreach ( self::legacy_defaults() as $k => $v ) {
				if ( ! array_key_exists( $k, $saved ) ) {
					$freeze[ $k ] = $v;
				}
			}
			if ( $freeze ) {
				\EBCR\Support\Options::update( $freeze );
			}
		}
		update_option( self::SETTINGS_OPTION, self::SETTINGS_VERSION, true );
	}

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
		// 1.1.0: abilities do plugin habilitadas no Easy MCP AI (se instalado).
		\EBCR\Abilities\Abilities::enable_in_easy_mcp();
		// 1.3.0: tabela ebcr_cookie_consents (criada pelo Schema::install() acima) e padrões antigos preservados.
		if ( '0' === (string) $from && false === get_option( \EBCR\Support\Options::OPTION, false ) ) {
			update_option( self::SETTINGS_OPTION, self::SETTINGS_VERSION, true ); // instalação nova: nada a preservar.
		} else {
			self::maybe_upgrade_settings();
		}
		/**
		 * Após migração.
		 *
		 * @param string $from Versão anterior.
		 * @param string $to   Versão atual.
		 */
		do_action( 'ebcr_migrated', $from, EBCR_DB_VERSION );
	}
}
