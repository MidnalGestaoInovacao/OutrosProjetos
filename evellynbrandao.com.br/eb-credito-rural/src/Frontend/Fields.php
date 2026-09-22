<?php
/**
 * Helpers de campos de formulário acessíveis (rótulo, erro associado, ajuda).
 *
 * @package EBCR
 */

namespace EBCR\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Gera HTML escapado.
 */
final class Fields {

	/**
	 * ID HTML a partir do nome.
	 *
	 * @param string $name Nome.
	 * @return string
	 */
	public static function id( $name ) {
		return 'ebcr-' . preg_replace( '/[^a-z0-9]+/', '-', strtolower( $name ) );
	}

	/**
	 * Valor em array aninhado a partir de "a[b][c]" / "a.0.b".
	 *
	 * @param array  $data Dados.
	 * @param string $name Nome.
	 * @return mixed
	 */
	public static function value( array $data, $name ) {
		$path = preg_split( '/[\[\]\.]+/', $name, -1, PREG_SPLIT_NO_EMPTY );
		$cur  = $data;
		foreach ( $path as $p ) {
			if ( ! is_array( $cur ) || ! array_key_exists( $p, $cur ) ) {
				return '';
			}
			$cur = $cur[ $p ];
		}
		return is_bool( $cur ) ? ( $cur ? '1' : '0' ) : $cur;
	}

	/**
	 * Erro do campo (chaves "a.0.b").
	 *
	 * @param array  $errors Erros.
	 * @param string $name   Nome.
	 * @return string
	 */
	public static function error( array $errors, $name ) {
		$key = str_replace( array( '][', '[', ']' ), array( '.', '.', '' ), $name );
		return isset( $errors[ $key ] ) ? (string) $errors[ $key ] : '';
	}

	/**
	 * Abre o wrapper do campo.
	 *
	 * @param string $id    ID.
	 * @param string $label Rótulo.
	 * @param string $error Erro.
	 * @param string $help  Ajuda.
	 * @param bool   $required Obrigatório.
	 * @return string
	 */
	private static function open( $id, $label, $error, $help, $required ) {
		return sprintf(
			'<div class="ebcr-field%s"><label for="%s">%s%s</label>',
			$error ? ' has-error' : '',
			esc_attr( $id ),
			esc_html( $label ),
			$required ? ' <span class="ebcr-req" aria-hidden="true">*</span>' : ''
		);
	}

	/**
	 * Fecha o wrapper.
	 *
	 * @param string $id    ID.
	 * @param string $error Erro.
	 * @param string $help  Ajuda.
	 * @return string
	 */
	private static function close( $id, $error, $help ) {
		$out = '';
		if ( $help ) {
			$out .= sprintf( '<span id="%s-help" class="ebcr-help">%s</span>', esc_attr( $id ), esc_html( $help ) );
		}
		if ( $error ) {
			$out .= sprintf( '<span id="%s-error" class="ebcr-error" role="alert">%s</span>', esc_attr( $id ), esc_html( $error ) );
		}
		return $out . '</div>';
	}

	/**
	 * Atributos aria.
	 *
	 * @param string $id    ID.
	 * @param string $error Erro.
	 * @param string $help  Ajuda.
	 * @return string
	 */
	private static function aria( $id, $error, $help ) {
		$desc = array();
		if ( $help ) {
			$desc[] = $id . '-help';
		}
		if ( $error ) {
			$desc[] = $id . '-error';
		}
		return ( $desc ? ' aria-describedby="' . esc_attr( implode( ' ', $desc ) ) . '"' : '' ) . ( $error ? ' aria-invalid="true"' : '' );
	}

	/**
	 * Input.
	 *
	 * @param string $name  Nome.
	 * @param string $label Rótulo.
	 * @param mixed  $value Valor.
	 * @param array  $attrs Atributos (type, required, placeholder, inputmode, data-*, maxlength, autocomplete, class).
	 * @param string $error Erro.
	 * @param string $help  Ajuda.
	 * @return string
	 */
	public static function input( $name, $label, $value = '', array $attrs = array(), $error = '', $help = '' ) {
		$id    = isset( $attrs['id'] ) ? $attrs['id'] : self::id( $name );
		$attrs = array_merge( array( 'type' => 'text' ), $attrs );
		unset( $attrs['id'] );
		$required = ! empty( $attrs['required'] );
		$html     = self::open( $id, $label, $error, $help, $required );
		$html    .= sprintf( '<input id="%s" name="%s" value="%s" class="ebcr-input%s"%s%s>', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), isset( $attrs['class'] ) ? ' ' . esc_attr( $attrs['class'] ) : '', self::attrs( $attrs, array( 'class' ) ), self::aria( $id, $error, $help ) );
		return $html . self::close( $id, $error, $help );
	}

	/**
	 * Textarea.
	 *
	 * @param string $name  Nome.
	 * @param string $label Rótulo.
	 * @param mixed  $value Valor.
	 * @param array  $attrs Atributos.
	 * @param string $error Erro.
	 * @param string $help  Ajuda.
	 * @return string
	 */
	public static function textarea( $name, $label, $value = '', array $attrs = array(), $error = '', $help = '' ) {
		$id       = self::id( $name );
		$required = ! empty( $attrs['required'] );
		$html     = self::open( $id, $label, $error, $help, $required );
		$html    .= sprintf( '<textarea id="%s" name="%s" class="ebcr-input" rows="%d"%s%s>%s</textarea>', esc_attr( $id ), esc_attr( $name ), isset( $attrs['rows'] ) ? (int) $attrs['rows'] : 4, self::attrs( $attrs, array( 'rows' ) ), self::aria( $id, $error, $help ), esc_textarea( (string) $value ) );
		return $html . self::close( $id, $error, $help );
	}

	/**
	 * Select.
	 *
	 * @param string $name    Nome.
	 * @param string $label   Rótulo.
	 * @param array  $options Opções valor => rótulo.
	 * @param mixed  $value   Valor.
	 * @param array  $attrs   Atributos.
	 * @param string $error   Erro.
	 * @param string $help    Ajuda.
	 * @return string
	 */
	public static function select( $name, $label, array $options, $value = '', array $attrs = array(), $error = '', $help = '' ) {
		$id       = self::id( $name );
		$required = ! empty( $attrs['required'] );
		$html     = self::open( $id, $label, $error, $help, $required );
		$html    .= sprintf( '<select id="%s" name="%s" class="ebcr-input"%s%s>', esc_attr( $id ), esc_attr( $name ), self::attrs( $attrs ), self::aria( $id, $error, $help ) );
		$html    .= '<option value="">' . esc_html__( 'Selecione…', 'eb-credito-rural' ) . '</option>';
		foreach ( $options as $k => $v ) {
			$html .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $k ), (string) $k === (string) $value ? ' selected' : '', esc_html( $v ) );
		}
		return $html . '</select>' . self::close( $id, $error, $help );
	}

	/**
	 * Grupo de rádio.
	 *
	 * @param string $name    Nome.
	 * @param string $label   Rótulo.
	 * @param array  $options Opções.
	 * @param mixed  $value   Valor.
	 * @param string $error   Erro.
	 * @param string $help    Ajuda.
	 * @param bool   $required Obrigatório.
	 * @return string
	 */
	public static function radios( $name, $label, array $options, $value = '', $error = '', $help = '', $required = true ) {
		$id   = self::id( $name );
		$html = sprintf( '<fieldset class="ebcr-field ebcr-radios%s" id="%s"%s><legend>%s%s</legend>', $error ? ' has-error' : '', esc_attr( $id ), self::aria( $id, $error, $help ), esc_html( $label ), $required ? ' <span class="ebcr-req" aria-hidden="true">*</span>' : '' );
		foreach ( $options as $k => $v ) {
			$oid   = $id . '-' . sanitize_key( $k );
			$html .= sprintf( '<label class="ebcr-radio" for="%s"><input type="radio" id="%s" name="%s" value="%s"%s%s> %s</label>', esc_attr( $oid ), esc_attr( $oid ), esc_attr( $name ), esc_attr( $k ), (string) $k === (string) $value ? ' checked' : '', $required ? ' required' : '', esc_html( $v ) );
		}
		return $html . self::close( $id, $error, $help ) . '</fieldset>';
	}

	/**
	 * Checkboxes múltiplos.
	 *
	 * @param string $name    Nome (sem []).
	 * @param string $label   Rótulo.
	 * @param array  $options Opções.
	 * @param array  $values  Valores marcados.
	 * @param string $error   Erro.
	 * @param string $help    Ajuda.
	 * @return string
	 */
	public static function checkboxes( $name, $label, array $options, $values = array(), $error = '', $help = '' ) {
		$id   = self::id( $name );
		$html = sprintf( '<fieldset class="ebcr-field ebcr-checks%s" id="%s"><legend>%s</legend>', $error ? ' has-error' : '', esc_attr( $id ), esc_html( $label ) );
		foreach ( $options as $k => $v ) {
			$oid   = $id . '-' . sanitize_key( $k );
			$html .= sprintf( '<label class="ebcr-check" for="%s"><input type="checkbox" id="%s" name="%s[]" value="%s"%s> %s</label>', esc_attr( $oid ), esc_attr( $oid ), esc_attr( $name ), esc_attr( $k ), in_array( (string) $k, array_map( 'strval', (array) $values ), true ) ? ' checked' : '', esc_html( $v ) );
		}
		return $html . self::close( $id, $error, $help ) . '</fieldset>';
	}

	/**
	 * Checkbox único (aceites) — nunca pré-marcado.
	 *
	 * @param string $name  Nome.
	 * @param string $label Rótulo (HTML permitido: link).
	 * @param bool   $required Obrigatório.
	 * @param string $error Erro.
	 * @return string
	 */
	public static function consent( $name, $label, $required = true, $error = '' ) {
		$id = self::id( $name );
		return sprintf(
			'<div class="ebcr-field ebcr-consent%s"><label class="ebcr-check" for="%s"><input type="checkbox" id="%s" name="%s" value="1"%s> <span>%s%s</span></label>%s</div>',
			$error ? ' has-error' : '',
			esc_attr( $id ),
			esc_attr( $id ),
			esc_attr( $name ),
			$required ? ' required' : '',
			wp_kses(
				$label,
				array(
					'a'      => array(
						'href'   => true,
						'target' => true,
						'rel'    => true,
					),
					'strong' => array(),
				)
			),
			$required ? ' <span class="ebcr-req" aria-hidden="true">*</span>' : '',
			$error ? sprintf( '<span id="%s-error" class="ebcr-error" role="alert">%s</span>', esc_attr( $id ), esc_html( $error ) ) : ''
		);
	}

	/**
	 * Atributos HTML.
	 *
	 * @param array $attrs Atributos.
	 * @param array $skip  Chaves ignoradas.
	 * @return string
	 */
	private static function attrs( array $attrs, array $skip = array() ) {
		$out = '';
		foreach ( $attrs as $k => $v ) {
			if ( in_array( $k, $skip, true ) || false === $v || null === $v ) {
				continue;
			}
			$k = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $k ) );
			if ( true === $v ) {
				$out .= ' ' . $k;
			} else {
				$out .= sprintf( ' %s="%s"', $k, esc_attr( (string) $v ) );
			}
		}
		return $out;
	}

	/**
	 * Resumo de erros no topo do formulário.
	 *
	 * @param array $errors Erros.
	 * @return string
	 */
	public static function error_summary( array $errors ) {
		if ( ! $errors ) {
			return '';
		}
		$html = '<div class="ebcr-alert ebcr-alert--error" role="alert" tabindex="-1" id="ebcr-error-summary"><strong>' . esc_html__( 'Corrija os itens abaixo:', 'eb-credito-rural' ) . '</strong><ul>';
		foreach ( $errors as $k => $msg ) {
			$html .= '<li>' . esc_html( $msg ) . '</li>';
		}
		return $html . '</ul></div>';
	}
}
