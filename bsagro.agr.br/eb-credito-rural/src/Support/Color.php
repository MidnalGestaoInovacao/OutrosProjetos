<?php
/**
 * Utilitários de cor: leitura/sanitização de cores CSS, mistura e contraste WCAG.
 *
 * @package EBCR
 */

namespace EBCR\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Aceita #rgb, #rgba, #rrggbb, #rrggbbaa, rgb()/rgba() e as palavras transparent/white/black.
 * Tudo o que sai daqui é normalizado (nunca o texto bruto), para ser impresso com segurança em CSS.
 */
final class Color {

	/**
	 * Converte uma cor CSS em [r, g, b, a] (0–255, 0–255, 0–255, 0–1) ou null se inválida.
	 *
	 * @param mixed $value Cor.
	 * @return array|null
	 */
	public static function parse( $value ) {
		if ( is_array( $value ) && 4 === count( $value ) ) {
			return array_values( $value );
		}
		$v = strtolower( trim( (string) $value ) );
		if ( '' === $v ) {
			return null;
		}
		$named = array(
			'transparent' => array( 0, 0, 0, 0.0 ),
			'white'       => array( 255, 255, 255, 1.0 ),
			'black'       => array( 0, 0, 0, 1.0 ),
		);
		if ( isset( $named[ $v ] ) ) {
			return $named[ $v ];
		}
		if ( preg_match( '/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $v, $m ) ) {
			$h = $m[1];
			if ( strlen( $h ) <= 4 ) {
				$h = preg_replace( '/(.)/', '$1$1', $h );
			}
			$a = 8 === strlen( $h ) ? round( hexdec( substr( $h, 6, 2 ) ) / 255, 3 ) : 1.0;
			return array( hexdec( substr( $h, 0, 2 ) ), hexdec( substr( $h, 2, 2 ) ), hexdec( substr( $h, 4, 2 ) ), (float) $a );
		}
		if ( preg_match( '/^rgba?\(\s*([\d.]+%?)\s*[,\s]\s*([\d.]+%?)\s*[,\s]\s*([\d.]+%?)\s*(?:[,\/]\s*([\d.]+%?)\s*)?\)$/', $v, $m ) ) {
			$rgb = array();
			for ( $i = 1; $i <= 3; $i++ ) {
				$n     = '%' === substr( $m[ $i ], -1 ) ? (float) $m[ $i ] * 2.55 : (float) $m[ $i ];
				$rgb[] = (int) round( max( 0, min( 255, $n ) ) );
			}
			$a = 1.0;
			if ( isset( $m[4] ) && '' !== $m[4] ) {
				$a = '%' === substr( $m[4], -1 ) ? (float) $m[4] / 100 : (float) $m[4];
				$a = max( 0.0, min( 1.0, $a ) );
			}
			$rgb[] = round( $a, 3 );
			return $rgb;
		}
		return null;
	}

	/**
	 * Sanitiza uma cor: devolve a forma normalizada (#rrggbb, #rrggbbaa → rgba(), rgba(...)) ou '' se inválida.
	 *
	 * @param mixed $value Cor.
	 * @return string
	 */
	public static function sanitize( $value ) {
		$v = strtolower( trim( (string) $value ) );
		if ( 'transparent' === $v ) {
			return 'transparent';
		}
		$c = self::parse( $v );
		if ( null === $c ) {
			return '';
		}
		if ( $c[3] >= 1.0 ) {
			return self::hex( $c );
		}
		return sprintf( 'rgba(%d,%d,%d,%s)', $c[0], $c[1], $c[2], rtrim( rtrim( number_format( $c[3], 3, '.', '' ), '0' ), '.' ) );
	}

	/**
	 * #rrggbb (ignora a transparência).
	 *
	 * @param mixed $value Cor ou [r,g,b,a].
	 * @return string
	 */
	public static function hex( $value ) {
		$c = self::parse( $value );
		if ( null === $c ) {
			return '';
		}
		return sprintf( '#%02x%02x%02x', (int) $c[0], (int) $c[1], (int) $c[2] );
	}

	/**
	 * Compõe uma cor com transparência sobre um fundo opaco.
	 *
	 * @param mixed $color    Cor.
	 * @param mixed $backdrop Fundo (padrão branco).
	 * @return array|null [r,g,b,1]
	 */
	public static function flatten( $color, $backdrop = '#ffffff' ) {
		$c = self::parse( $color );
		$b = self::parse( $backdrop );
		if ( null === $c ) {
			return null;
		}
		if ( null === $b || $b[3] < 1.0 ) {
			$b = array( 255, 255, 255, 1.0 );
		}
		$a = (float) $c[3];
		return array(
			(int) round( $c[0] * $a + $b[0] * ( 1 - $a ) ),
			(int) round( $c[1] * $a + $b[1] * ( 1 - $a ) ),
			(int) round( $c[2] * $a + $b[2] * ( 1 - $a ) ),
			1.0,
		);
	}

	/**
	 * Mistura duas cores (peso de $b entre 0 e 1) → #rrggbb.
	 *
	 * @param mixed $a      Cor A.
	 * @param mixed $b      Cor B.
	 * @param float $weight Peso de B.
	 * @return string
	 */
	public static function mix( $a, $b, $weight ) {
		$x = self::flatten( $a );
		$y = self::flatten( $b );
		if ( null === $x || null === $y ) {
			return self::hex( $a );
		}
		$w = max( 0.0, min( 1.0, (float) $weight ) );
		return self::hex(
			array(
				(int) round( $x[0] * ( 1 - $w ) + $y[0] * $w ),
				(int) round( $x[1] * ( 1 - $w ) + $y[1] * $w ),
				(int) round( $x[2] * ( 1 - $w ) + $y[2] * $w ),
				1.0,
			)
		);
	}

	/**
	 * Luminância relativa (WCAG 2.x).
	 *
	 * @param mixed $color    Cor.
	 * @param mixed $backdrop Fundo para cores translúcidas.
	 * @return float
	 */
	public static function luminance( $color, $backdrop = '#ffffff' ) {
		$c = self::flatten( $color, $backdrop );
		if ( null === $c ) {
			return 1.0;
		}
		$lin = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$s       = $c[ $i ] / 255;
			$lin[ $i ] = $s <= 0.04045 ? $s / 12.92 : pow( ( $s + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
	}

	/**
	 * Razão de contraste WCAG entre texto e fundo (1–21). O fundo translúcido é composto sobre $backdrop.
	 *
	 * @param mixed $fg       Texto.
	 * @param mixed $bg       Fundo.
	 * @param mixed $backdrop Fundo do fundo (padrão branco).
	 * @return float
	 */
	public static function contrast( $fg, $bg, $backdrop = '#ffffff' ) {
		$bg_flat = self::flatten( $bg, $backdrop );
		if ( null === $bg_flat ) {
			$bg_flat = self::parse( $backdrop );
		}
		$l1 = self::luminance( $fg, self::hex( $bg_flat ) );
		$l2 = self::luminance( $bg_flat );
		$hi = max( $l1, $l2 );
		$lo = min( $l1, $l2 );
		return round( ( $hi + 0.05 ) / ( $lo + 0.05 ), 2 );
	}

	/**
	 * Cor escura (luminância baixa)?
	 *
	 * @param mixed $color Cor.
	 * @return bool
	 */
	public static function is_dark( $color ) {
		return self::luminance( $color ) < 0.2;
	}
}
