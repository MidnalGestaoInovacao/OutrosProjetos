<?php
/**
 * Motor de validação declarativa por campo.
 *
 * @package EBCR
 */

namespace EBCR\Forms\Validators;

use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Regras: required, max:N, min:N, cpf, cnpj, email, phone, cep, uf, date, past, future, min_age:N, amount:min,max, int:min,max, in:a|b, bool, car, key, upper.
 * Tipos: text (padrão), textarea, date, amount, int, bool, select, cpf, cnpj...
 */
final class Validator {

	/**
	 * Valida e sanitiza.
	 *
	 * @param array $fields Definições: nome => [label, rules(array), type].
	 * @param array $input  Entrada bruta.
	 * @return array [clean, errors] errors: nome => mensagem.
	 */
	public static function run( array $fields, array $input ) {
		$clean  = array();
		$errors = array();
		foreach ( $fields as $name => $def ) {
			$label = isset( $def['label'] ) ? $def['label'] : $name;
			$rules = isset( $def['rules'] ) ? (array) $def['rules'] : array();
			$type  = isset( $def['type'] ) ? $def['type'] : 'text';
			$raw   = isset( $input[ $name ] ) ? $input[ $name ] : '';
			if ( is_array( $raw ) ) {
				$raw = 'multi' === $type ? array_map( 'sanitize_text_field', array_map( 'strval', $raw ) ) : '';
			}
			$value = self::sanitize( $raw, $type );
			$empty = '' === $value || null === $value || array() === $value;
			if ( in_array( 'required', $rules, true ) && $empty ) {
				$errors[ $name ] = sprintf( /* translators: %s: rótulo do campo */ __( '%s é obrigatório.', 'eb-credito-rural' ), $label );
				$clean[ $name ]  = $value;
				continue;
			}
			if ( $empty ) {
				$clean[ $name ] = 'amount' === $type || 'int' === $type ? null : $value;
				continue;
			}
			$err = self::apply( $rules, $value, $label, $type );
			if ( null !== $err ) {
				$errors[ $name ] = $err;
				$clean[ $name ]  = $value;
				continue;
			}
			$clean[ $name ] = self::normalize( $value, $type );
		}
		return array( $clean, $errors );
	}

	/**
	 * Sanitização por tipo.
	 *
	 * @param mixed  $raw  Bruto.
	 * @param string $type Tipo.
	 * @return mixed
	 */
	private static function sanitize( $raw, $type ) {
		if ( 'multi' === $type ) {
			return is_array( $raw ) ? array_values( array_filter( array_map( 'sanitize_key', $raw ) ) ) : array();
		}
		if ( 'textarea' === $type ) {
			return sanitize_textarea_field( wp_unslash( (string) $raw ) );
		}
		if ( 'bool' === $type ) {
			$v = strtolower( trim( (string) $raw ) );
			return in_array( $v, array( '1', 'sim', 'true', 'on', 'yes' ), true ) ? '1' : ( '' === $v ? '' : '0' );
		}
		if ( 'key' === $type ) {
			return sanitize_key( (string) $raw );
		}
		return sanitize_text_field( wp_unslash( (string) $raw ) );
	}

	/**
	 * Aplica regras. Retorna mensagem de erro ou null.
	 *
	 * @param array  $rules Regras.
	 * @param mixed  $value Valor sanitizado.
	 * @param string $label Rótulo.
	 * @param string $type  Tipo.
	 * @return string|null
	 */
	private static function apply( array $rules, $value, $label, $type ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- reservado para regras por tipo.
		foreach ( $rules as $rule ) {
			if ( 'required' === $rule ) {
				continue;
			}
			$parts = explode( ':', (string) $rule, 2 );
			$r     = $parts[0];
			$arg   = isset( $parts[1] ) ? $parts[1] : '';
			switch ( $r ) {
				case 'max':
					if ( is_string( $value ) && mb_strlen( $value ) > (int) $arg ) {
						/* translators: 1: rótulo, 2: máximo */
						return sprintf( __( '%1$s deve ter no máximo %2$d caracteres.', 'eb-credito-rural' ), $label, (int) $arg );
					}
					break;
				case 'min':
					if ( is_string( $value ) && mb_strlen( $value ) < (int) $arg ) {
						/* translators: 1: rótulo, 2: mínimo */
						return sprintf( __( '%1$s deve ter pelo menos %2$d caracteres.', 'eb-credito-rural' ), $label, (int) $arg );
					}
					break;
				case 'cpf':
					if ( ! Rules::cpf( $value ) ) {
						return sprintf( /* translators: %s: rótulo */ __( '%s inválido. Verifique os dígitos.', 'eb-credito-rural' ), $label );
					}
					break;
				case 'cnpj':
					if ( ! Rules::cnpj( $value ) ) {
						return sprintf( /* translators: %s: rótulo */ __( '%s inválido. Verifique os dígitos.', 'eb-credito-rural' ), $label );
					}
					break;
				case 'email':
					if ( ! Rules::email( $value ) ) {
						return __( 'Informe um e-mail válido.', 'eb-credito-rural' );
					}
					break;
				case 'phone':
					if ( ! Rules::phone( $value ) ) {
						return sprintf( /* translators: %s: rótulo */ __( '%s deve ter DDD e número (10 ou 11 dígitos).', 'eb-credito-rural' ), $label );
					}
					break;
				case 'cep':
					if ( ! Rules::cep( $value ) ) {
						return __( 'CEP deve ter 8 dígitos.', 'eb-credito-rural' );
					}
					break;
				case 'uf':
					if ( ! Rules::uf( $value ) ) {
						return __( 'Selecione uma UF válida.', 'eb-credito-rural' );
					}
					break;
				case 'car':
					if ( ! Rules::car( $value ) ) {
						return __( 'Código do CAR fora do padrão UF-XXXXXXX-XXXX.XXXX.XXXX.XXXX.XXXX.XXXX.XXXX.XXXX.', 'eb-credito-rural' );
					}
					break;
				case 'date':
					if ( null === Rules::date( $value ) ) {
						return sprintf( /* translators: %s: rótulo */ __( '%s deve ser uma data válida (dd/mm/aaaa).', 'eb-credito-rural' ), $label );
					}
					break;
				case 'past':
					$d = Rules::date( $value );
					if ( null === $d || strtotime( $d ) > time() ) {
						return sprintf( /* translators: %s: rótulo */ __( '%s deve ser uma data passada.', 'eb-credito-rural' ), $label );
					}
					break;
				case 'future':
					$d = Rules::date( $value );
					if ( null === $d || ! Rules::future( $d ) ) {
						return sprintf( /* translators: %s: rótulo */ __( '%s deve ser uma data futura.', 'eb-credito-rural' ), $label );
					}
					break;
				case 'min_age':
					$d = Rules::date( $value );
					if ( null === $d || ! Rules::min_age( $d, (int) $arg ) ) {
						return sprintf( /* translators: %d: idade */ __( 'É necessário ter pelo menos %d anos.', 'eb-credito-rural' ), (int) $arg );
					}
					break;
				case 'amount':
					list( $min, $max ) = array_pad( explode( ',', $arg ), 2, '' );
					$v                 = Rules::amount( $value, '' === $min ? null : (float) $min, '' === $max ? null : (float) $max );
					if ( null === $v ) {
						if ( '' !== $min || '' !== $max ) {
							/* translators: 1: rótulo, 2: mínimo, 3: máximo */
							return sprintf( __( '%1$s deve ser um número entre %2$s e %3$s.', 'eb-credito-rural' ), $label, '' === $min ? '0' : number_format( (float) $min, 2, ',', '.' ), '' === $max ? '∞' : number_format( (float) $max, 2, ',', '.' ) );
						}
						return sprintf( /* translators: %s: rótulo */ __( '%s deve ser um número positivo.', 'eb-credito-rural' ), $label );
					}
					break;
				case 'int':
					list( $min, $max ) = array_pad( explode( ',', $arg ), 2, '' );
					if ( ! preg_match( '/^-?\d+$/', (string) $value ) || ( '' !== $min && (int) $value < (int) $min ) || ( '' !== $max && (int) $value > (int) $max ) ) {
						/* translators: 1: rótulo, 2: mínimo, 3: máximo */
						return sprintf( __( '%1$s deve ser um número inteiro entre %2$s e %3$s.', 'eb-credito-rural' ), $label, '' === $min ? '0' : $min, '' === $max ? '∞' : $max );
					}
					break;
				case 'in':
					$allowed = explode( '|', $arg );
					$vals    = is_array( $value ) ? $value : array( $value );
					foreach ( $vals as $v ) {
						if ( ! in_array( (string) $v, $allowed, true ) ) {
							return sprintf( /* translators: %s: rótulo */ __( '%s tem um valor inválido.', 'eb-credito-rural' ), $label );
						}
					}
					break;
				case 'bool':
					if ( ! in_array( $value, array( '0', '1' ), true ) ) {
						return sprintf( /* translators: %s: rótulo */ __( '%s tem um valor inválido.', 'eb-credito-rural' ), $label );
					}
					break;
				default:
					break;
			}
		}
		return null;
	}

	/**
	 * Normaliza valor final.
	 *
	 * @param mixed  $value Valor.
	 * @param string $type  Tipo.
	 * @return mixed
	 */
	private static function normalize( $value, $type ) {
		switch ( $type ) {
			case 'amount':
				return Rules::amount( $value );
			case 'int':
				return (int) $value;
			case 'date':
				return Rules::date( $value );
			case 'bool':
				return '1' === $value;
			case 'cpf':
			case 'cnpj':
			case 'cep':
			case 'phone':
				return Helpers::digits( $value );
			case 'upper':
				return strtoupper( $value );
			default:
				return $value;
		}
	}
}
