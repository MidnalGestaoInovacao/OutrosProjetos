<?php
/**
 * Verificação antivírus opcional (ClamAV via clamdscan).
 *
 * @package EBCR
 */

namespace EBCR\Files;

use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Executa o comando configurado; qualquer resultado diferente de 0 rejeita o arquivo.
 */
final class Antivirus {

	/**
	 * O comando existe no servidor?
	 *
	 * @return bool
	 */
	public static function is_available() {
		if ( ! function_exists( 'exec' ) || ! function_exists( 'escapeshellarg' ) ) {
			return false;
		}
		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
		if ( in_array( 'exec', $disabled, true ) ) {
			return false;
		}
		$cmd = self::binary();
		if ( ! $cmd ) {
			return false;
		}
		$out = array();
		$rc  = 1;
		@exec( 'command -v ' . escapeshellarg( $cmd ) . ' 2>/dev/null', $out, $rc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec,WordPress.PHP.NoSilencedErrors.Discouraged -- verificação opcional de antivírus.
		return 0 === $rc;
	}

	/**
	 * Binário do comando configurado.
	 *
	 * @return string
	 */
	private static function binary() {
		$cmd   = trim( (string) Options::get( 'antivirus_command', '' ) );
		$parts = preg_split( '/\s+/', $cmd );
		return $parts && preg_match( '/^[a-zA-Z0-9_\/.-]+$/', $parts[0] ) ? $parts[0] : '';
	}

	/**
	 * Verifica um arquivo. true = limpo/não verificado; false = infectado ou erro.
	 *
	 * @param string $path Caminho.
	 * @return array{ok:bool,message:string}
	 */
	public static function scan( $path ) {
		if ( ! Options::bool( 'antivirus_enabled' ) ) {
			return array(
				'ok'      => true,
				'message' => 'disabled',
			);
		}
		if ( ! self::is_available() ) {
			return array(
				'ok'      => false,
				'message' => __( 'Antivírus habilitado, mas o comando não está disponível no servidor.', 'eb-credito-rural' ),
			);
		}
		$cmd  = trim( (string) Options::get( 'antivirus_command', '' ) );
		$args = array_slice( preg_split( '/\s+/', $cmd ), 1 );
		foreach ( $args as $a ) {
			if ( ! preg_match( '/^[a-zA-Z0-9_=.\/-]+$/', $a ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'Comando de antivírus inválido.', 'eb-credito-rural' ),
				);
			}
		}
		$full = escapeshellarg( self::binary() ) . ( $args ? ' ' . implode( ' ', array_map( 'escapeshellarg', $args ) ) : '' ) . ' ' . escapeshellarg( $path ) . ' 2>&1';
		$out  = array();
		$rc   = 1;
		@exec( $full, $out, $rc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec,WordPress.PHP.NoSilencedErrors.Discouraged -- antivírus opcional configurado pelo administrador.
		if ( 0 === $rc ) {
			return array(
				'ok'      => true,
				'message' => 'clean',
			);
		}
		return array(
			'ok'      => false,
			'message' => 1 === $rc ? __( 'O arquivo foi identificado como malicioso e rejeitado.', 'eb-credito-rural' ) : __( 'Falha ao executar o antivírus; o arquivo foi rejeitado por precaução.', 'eb-credito-rural' ),
		);
	}
}
