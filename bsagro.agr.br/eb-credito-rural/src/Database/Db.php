<?php
/**
 * Acesso ao banco: nomes de tabela e helpers comuns.
 *
 * @package EBCR
 */

namespace EBCR\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers de $wpdb.
 */
class Db {

	/**
	 * Nome completo da tabela.
	 *
	 * @param string $name Sufixo.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'ebcr_' . $name;
	}

	/**
	 * Data/hora atual UTC (MySQL).
	 *
	 * @return string
	 */
	protected static function now() {
		return current_time( 'mysql', true );
	}

	/**
	 * Insere e retorna o ID.
	 *
	 * @param string $table Sufixo da tabela.
	 * @param array  $data  Dados.
	 * @return int
	 */
	protected static function insert_row( $table, array $data ) {
		global $wpdb;
		$wpdb->insert( self::table( $table ), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabela própria.
		return (int) $wpdb->insert_id;
	}

	/**
	 * Atualiza por ID.
	 *
	 * @param string $table Sufixo.
	 * @param int    $id    ID.
	 * @param array  $data  Dados.
	 * @return bool
	 */
	protected static function update_row( $table, $id, array $data ) {
		global $wpdb;
		$r = $wpdb->update( self::table( $table ), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabela própria.
		return false !== $r;
	}

	/**
	 * Linha por ID.
	 *
	 * @param string $table Sufixo.
	 * @param int    $id    ID.
	 * @return array|null
	 */
	protected static function row_by_id( $table, $id ) {
		global $wpdb;
		$t   = self::table( $table );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
		return $row ? $row : null;
	}

	/**
	 * Placeholder list para IN().
	 *
	 * @param array $values Valores.
	 * @return string
	 */
	protected static function in_placeholders( array $values ) {
		return implode( ',', array_fill( 0, count( $values ), '%s' ) );
	}
}
