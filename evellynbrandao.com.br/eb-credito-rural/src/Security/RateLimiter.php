<?php
/**
 * Limite de requisições por chave (IP, usuário) com bloqueio progressivo.
 *
 * @package EBCR
 */

namespace EBCR\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Baseado em transients (funciona sem cache externo).
 */
final class RateLimiter {

	/**
	 * Registra uma tentativa e informa se o limite foi excedido.
	 *
	 * @param string $scope   Escopo (ex.: login, register, upload).
	 * @param string $key     Identificador (IP ou usuário).
	 * @param int    $max     Máximo de tentativas na janela.
	 * @param int    $window  Janela em segundos.
	 * @return bool true se ainda permitido.
	 */
	public static function hit( $scope, $key, $max, $window ) {
		$name = self::name( $scope, $key );
		$data = get_transient( $name );
		if ( ! is_array( $data ) || empty( $data['reset'] ) || $data['reset'] < time() ) {
			$data = array(
				'count' => 0,
				'reset' => time() + $window,
			);
		}
		++$data['count'];
		set_transient( $name, $data, max( 1, $data['reset'] - time() ) );
		return $data['count'] <= $max;
	}

	/**
	 * Está bloqueado? (sem registrar tentativa)
	 *
	 * @param string $scope Escopo.
	 * @param string $key   Chave.
	 * @return int Segundos restantes de bloqueio (0 = livre).
	 */
	public static function blocked_for( $scope, $key ) {
		$lock = get_transient( self::name( $scope, $key ) . '_lock' );
		if ( is_array( $lock ) && ! empty( $lock['until'] ) && $lock['until'] > time() ) {
			return (int) ( $lock['until'] - time() );
		}
		return 0;
	}

	/**
	 * Aplica bloqueio progressivo: cada novo bloqueio dobra a duração (até 24 h).
	 *
	 * @param string $scope Escopo.
	 * @param string $key   Chave.
	 * @param int    $base  Duração base em segundos.
	 * @return int Duração aplicada.
	 */
	public static function lock( $scope, $key, $base ) {
		$name  = self::name( $scope, $key ) . '_lock';
		$lock  = get_transient( $name );
		$level = is_array( $lock ) && isset( $lock['level'] ) ? (int) $lock['level'] + 1 : 1;
		$dur   = min( DAY_IN_SECONDS, $base * ( 2 ** ( $level - 1 ) ) );
		set_transient(
			$name,
			array(
				'until' => time() + $dur,
				'level' => $level,
			),
			DAY_IN_SECONDS
		);
		return (int) $dur;
	}

	/**
	 * Limpa contadores e bloqueio.
	 *
	 * @param string $scope Escopo.
	 * @param string $key   Chave.
	 * @return void
	 */
	public static function clear( $scope, $key ) {
		delete_transient( self::name( $scope, $key ) );
		delete_transient( self::name( $scope, $key ) . '_lock' );
	}

	/**
	 * Nome do transient.
	 *
	 * @param string $scope Escopo.
	 * @param string $key   Chave.
	 * @return string
	 */
	private static function name( $scope, $key ) {
		return 'ebcr_rl_' . sanitize_key( $scope ) . '_' . md5( (string) $key );
	}
}
