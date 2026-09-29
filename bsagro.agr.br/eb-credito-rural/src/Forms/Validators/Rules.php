<?php
/**
 * Validadores de formato (CPF, CNPJ, CAR, CEP, telefone, datas, valores).
 *
 * @package EBCR
 */

namespace EBCR\Forms\Validators;

use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Funções puras, testáveis.
 */
final class Rules {

	/**
	 * CPF com dígitos verificadores; rejeita sequências repetidas.
	 *
	 * @param string $cpf CPF.
	 * @return bool
	 */
	public static function cpf( $cpf ) {
		$d = Helpers::digits( $cpf );
		if ( 11 !== strlen( $d ) || preg_match( '/^(\d)\1{10}$/', $d ) ) {
			return false;
		}
		for ( $t = 9; $t < 11; $t++ ) {
			$sum = 0;
			for ( $i = 0; $i < $t; $i++ ) {
				$sum += (int) $d[ $i ] * ( $t + 1 - $i );
			}
			$digit = ( ( 10 * $sum ) % 11 ) % 10;
			if ( (int) $d[ $t ] !== $digit ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * CNPJ com dígitos verificadores; rejeita sequências repetidas.
	 *
	 * @param string $cnpj CNPJ.
	 * @return bool
	 */
	public static function cnpj( $cnpj ) {
		$d = Helpers::digits( $cnpj );
		if ( 14 !== strlen( $d ) || preg_match( '/^(\d)\1{13}$/', $d ) ) {
			return false;
		}
		$weights1 = array( 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 );
		$weights2 = array( 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 );
		$calc     = static function ( $digits, $weights ) {
			$sum = 0;
			foreach ( $weights as $i => $w ) {
				$sum += (int) $digits[ $i ] * $w;
			}
			$r = $sum % 11;
			return $r < 2 ? 0 : 11 - $r;
		};
		if ( (int) $d[12] !== $calc( $d, $weights1 ) ) {
			return false;
		}
		return (int) $d[13] === $calc( $d, $weights2 );
	}

	/**
	 * Código do CAR: UF-XXXXXXX-XXXX.XXXX.XXXX.XXXX.XXXX.XXXX.XXXX.XXXX (7 dígitos do município + 8 grupos hex de 4).
	 *
	 * @param string $car Código.
	 * @return bool
	 */
	public static function car( $car ) {
		$car = strtoupper( trim( (string) $car ) );
		if ( ! preg_match( '/^([A-Z]{2})-(\d{7})-((?:[A-F0-9]{4}\.){7}[A-F0-9]{4})$/', $car, $m ) ) {
			return false;
		}
		return array_key_exists( $m[1], Helpers::ufs() );
	}

	/**
	 * CEP com 8 dígitos.
	 *
	 * @param string $cep CEP.
	 * @return bool
	 */
	public static function cep( $cep ) {
		$d = Helpers::digits( $cep );
		return 8 === strlen( $d ) && ! preg_match( '/^0{8}$/', $d );
	}

	/**
	 * Telefone BR com 10 ou 11 dígitos (com DDD).
	 *
	 * @param string $phone Telefone.
	 * @return bool
	 */
	public static function phone( $phone ) {
		$d = Helpers::digits( $phone );
		if ( 0 === strpos( $d, '55' ) && strlen( $d ) > 11 ) {
			$d = substr( $d, 2 );
		}
		return in_array( strlen( $d ), array( 10, 11 ), true ) && '0' !== $d[0] && ! preg_match( '/^(\d)\1{9,10}$/', $d );
	}

	/**
	 * E-mail.
	 *
	 * @param string $email E-mail.
	 * @return bool
	 */
	public static function email( $email ) {
		return (bool) is_email( (string) $email );
	}

	/**
	 * UF oficial.
	 *
	 * @param string $uf UF.
	 * @return bool
	 */
	public static function uf( $uf ) {
		return array_key_exists( strtoupper( trim( (string) $uf ) ), Helpers::ufs() );
	}

	/**
	 * Data válida (Y-m-d ou d/m/Y). Retorna Y-m-d ou null.
	 *
	 * @param string $value Data.
	 * @return string|null
	 */
	public static function date( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			list( , $y, $mo, $d ) = $m;
		} elseif ( preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m ) ) {
			list( , $d, $mo, $y ) = $m;
		} else {
			return null;
		}
		return checkdate( (int) $mo, (int) $d, (int) $y ) ? sprintf( '%04d-%02d-%02d', $y, $mo, $d ) : null;
	}

	/**
	 * Idade mínima a partir da data de nascimento (Y-m-d).
	 *
	 * @param string $ymd     Nascimento.
	 * @param int    $min_age Idade mínima.
	 * @return bool
	 */
	public static function min_age( $ymd, $min_age = 18 ) {
		$ts = strtotime( $ymd );
		if ( false === $ts ) {
			return false;
		}
		return $ts <= strtotime( "-{$min_age} years" );
	}

	/**
	 * Data futura (estritamente após hoje).
	 *
	 * @param string $ymd Data.
	 * @return bool
	 */
	public static function future( $ymd ) {
		$ts = strtotime( $ymd );
		return false !== $ts && $ts > strtotime( wp_date( 'Y-m-d' ) );
	}

	/**
	 * Valor numérico positivo dentro de limites.
	 *
	 * @param mixed      $value Valor (aceita formato BR).
	 * @param float|null $min   Mínimo.
	 * @param float|null $max   Máximo.
	 * @return float|null Valor normalizado ou null se inválido.
	 */
	public static function amount( $value, $min = null, $max = null ) {
		$v = Helpers::parse_decimal( $value );
		if ( null === $v || $v < 0 ) {
			return null;
		}
		if ( null !== $min && $v < $min ) {
			return null;
		}
		if ( null !== $max && $v > $max ) {
			return null;
		}
		return round( $v, 2 );
	}
}
