<?php
/**
 * Gerador de PDF em PHP puro (sem dependências): páginas A4, fontes padrão Helvetica, quebra de linha por largura real,
 * títulos, pares chave/valor, tabelas com cabeçalho repetido e numeração de páginas. Usado pelo dossiê e pela
 * assinatura eletrônica.
 *
 * @package EBCR
 */

namespace EBCR\Pdf;

defined( 'ABSPATH' ) || exit;

/**
 * API fluente: (new Writer( $title ))->h1()->h2()->p()->kv( array )->table( $headers, $rows )->page_break()->output().
 *
 * Características do arquivo gerado:
 * - PDF 1.4, A4 retrato (595,28 × 841,89 pt), margens de ~18 mm;
 * - fontes Standard 14 Helvetica e Helvetica-Bold com /Encoding /WinAnsiEncoding (sem embutir);
 * - texto convertido de UTF-8 para Windows-1252 (iconv //TRANSLIT com fallback próprio que descarta o que não existe na tabela);
 * - cabeçalho com o título em todas as páginas e rodapé com "Página X de Y", data/hora de geração e "Confidencial";
 * - streams comprimidos com FlateDecode quando gzcompress() existir; tabela xref com offsets corretos; metadados no /Info.
 */
final class Writer {

	/**
	 * Largura da página A4 em pontos.
	 *
	 * @var float
	 */
	const PAGE_W = 595.28;

	/**
	 * Altura da página A4 em pontos.
	 *
	 * @var float
	 */
	const PAGE_H = 841.89;

	/**
	 * Margem (≈ 18 mm).
	 *
	 * @var float
	 */
	const MARGIN = 51.0;

	/**
	 * Altura reservada ao cabeçalho (abaixo da margem superior).
	 *
	 * @var float
	 */
	const HEADER_H = 20.0;

	/**
	 * Altura reservada ao rodapé (acima da margem inferior).
	 *
	 * @var float
	 */
	const FOOTER_H = 22.0;

	/**
	 * Fonte regular.
	 *
	 * @var string
	 */
	const F_REGULAR = 'F1';

	/**
	 * Fonte negrito.
	 *
	 * @var string
	 */
	const F_BOLD = 'F2';

	/**
	 * Título do documento.
	 *
	 * @var string
	 */
	private $title;

	/**
	 * Conteúdo (operadores PDF) de cada página, na ordem.
	 *
	 * @var string[]
	 */
	private $pages = array();

	/**
	 * Posição vertical atual medida a partir do topo da página (pt).
	 *
	 * @var float
	 */
	private $y = 0.0;

	/**
	 * Indica se a página atual já recebeu algum conteúdo.
	 *
	 * @var bool
	 */
	private $page_has_content = false;

	/**
	 * Data/hora de geração (rodapé), já no fuso do site.
	 *
	 * @var string
	 */
	private $generated_at;

	/**
	 * Larguras dos glifos (1/1000 em) por byte Windows-1252, fonte Helvetica.
	 * Fonte: métricas AFM públicas da Adobe (Core 14). Índices 0–31 e bytes sem glifo recebem 278/350 como na tabela padrão.
	 *
	 * @var int[]
	 */
	// phpcs:disable WordPress.Arrays.ArrayDeclarationSpacing -- tabela compacta (16 valores por linha = uma faixa de 16 bytes).
	private static $w_regular = array(
		278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278,
		278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278,
		278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
		556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
		1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
		667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
		333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
		556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584, 350,
		556, 350, 222, 556, 333, 1000, 556, 556, 333, 1000, 667, 333, 1000, 350, 611, 350,
		350, 222, 222, 333, 333, 350, 556, 1000, 333, 1000, 500, 333, 944, 350, 500, 667,
		278, 333, 556, 556, 556, 556, 260, 556, 333, 737, 370, 556, 584, 333, 737, 333,
		400, 584, 333, 333, 333, 556, 537, 278, 333, 333, 365, 556, 834, 834, 834, 611,
		667, 667, 667, 667, 667, 667, 1000, 722, 667, 667, 667, 667, 278, 278, 278, 278,
		722, 722, 778, 778, 778, 778, 778, 584, 778, 722, 722, 722, 722, 667, 667, 611,
		556, 556, 556, 556, 556, 556, 889, 500, 556, 556, 556, 556, 278, 278, 278, 278,
		556, 556, 556, 556, 556, 556, 556, 584, 611, 556, 556, 556, 556, 500, 556, 500,
	);
	// phpcs:enable WordPress.Arrays.ArrayDeclarationSpacing

	/**
	 * Larguras dos glifos (1/1000 em) por byte Windows-1252, fonte Helvetica-Bold (métricas AFM públicas da Adobe).
	 *
	 * @var int[]
	 */
	// phpcs:disable WordPress.Arrays.ArrayDeclarationSpacing -- tabela compacta (16 valores por linha = uma faixa de 16 bytes).
	private static $w_bold = array(
		278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278,
		278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278, 278,
		278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
		556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611,
		975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778,
		667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556,
		333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
		611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584, 350,
		556, 350, 278, 556, 500, 1000, 556, 556, 333, 1000, 667, 333, 1000, 350, 611, 350,
		350, 278, 278, 500, 500, 350, 556, 1000, 333, 1000, 556, 333, 944, 350, 500, 667,
		278, 333, 556, 556, 556, 556, 280, 556, 333, 737, 370, 556, 584, 333, 737, 333,
		400, 584, 333, 333, 333, 611, 556, 278, 333, 333, 365, 556, 834, 834, 834, 611,
		722, 722, 722, 722, 722, 722, 1000, 722, 667, 667, 667, 667, 278, 278, 278, 278,
		722, 722, 778, 778, 778, 778, 778, 584, 778, 722, 722, 722, 722, 667, 667, 611,
		556, 556, 556, 556, 556, 556, 889, 556, 556, 556, 556, 556, 278, 278, 278, 278,
		611, 611, 611, 611, 611, 611, 611, 584, 611, 611, 611, 611, 611, 556, 611, 556,
	);
	// phpcs:enable WordPress.Arrays.ArrayDeclarationSpacing

	/**
	 * Pontos de código Unicode dos bytes 0x80–0x9F do Windows-1252 (os demais coincidem com ISO-8859-1).
	 *
	 * @var int[]
	 */
	private static $cp1252_high = array(
		0x80 => 0x20AC,
		0x82 => 0x201A,
		0x83 => 0x0192,
		0x84 => 0x201E,
		0x85 => 0x2026,
		0x86 => 0x2020,
		0x87 => 0x2021,
		0x88 => 0x02C6,
		0x89 => 0x2030,
		0x8A => 0x0160,
		0x8B => 0x2039,
		0x8C => 0x0152,
		0x8E => 0x017D,
		0x91 => 0x2018,
		0x92 => 0x2019,
		0x93 => 0x201C,
		0x94 => 0x201D,
		0x95 => 0x2022,
		0x96 => 0x2013,
		0x97 => 0x2014,
		0x98 => 0x02DC,
		0x99 => 0x2122,
		0x9A => 0x0161,
		0x9B => 0x203A,
		0x9C => 0x0153,
		0x9E => 0x017E,
		0x9F => 0x0178,
	);

	/**
	 * Construtor.
	 *
	 * @param string $title Título (metadados e cabeçalho).
	 */
	public function __construct( $title = '' ) {
		$this->title        = (string) $title;
		$this->generated_at = function_exists( 'wp_date' ) ? wp_date( 'd/m/Y H:i' ) : gmdate( 'd/m/Y H:i' ) . ' UTC';
	}

	/**
	 * Título principal.
	 *
	 * @param string $text Texto.
	 * @return self
	 */
	public function h1( $text ) {
		$this->heading( $text, 15.0, 10.0, 5.0, true );
		return $this;
	}

	/**
	 * Subtítulo.
	 *
	 * @param string $text Texto.
	 * @return self
	 */
	public function h2( $text ) {
		$this->heading( $text, 11.5, 8.0, 3.0, false );
		return $this;
	}

	/**
	 * Parágrafo (quebra automática; "\n" força nova linha).
	 *
	 * @param string $text Texto.
	 * @return self
	 */
	public function p( $text ) {
		$size   = 9.5;
		$line_h = $size * 1.35;
		$lines  = $this->wrap( (string) $text, self::F_REGULAR, $size, $this->content_width() );
		if ( ! $lines ) {
			$lines = array( '' );
		}
		$this->ensure_page();
		foreach ( $lines as $line ) {
			if ( $this->y + $line_h > $this->bottom() ) {
				$this->new_page();
			}
			$this->text( self::MARGIN, $this->y + $size, self::F_REGULAR, $size, $line, 0.1 );
			$this->y               += $line_h;
			$this->page_has_content = true;
		}
		$this->y += 4.0;
		return $this;
	}

	/**
	 * Pares rótulo => valor em duas colunas alinhadas. Valores em array são unidos por quebra de linha.
	 *
	 * @param array $rows Pares.
	 * @return self
	 */
	public function kv( array $rows ) {
		if ( ! $rows ) {
			return $this;
		}
		$size    = 9.0;
		$padx    = 4.0;
		$width   = $this->content_width();
		$label_w = 0.0;
		$items   = array();
		foreach ( $rows as $label => $value ) {
			$label   = (string) $label;
			$value   = is_array( $value ) ? implode( "\n", array_map( 'strval', $value ) ) : (string) $value;
			$value   = '' === trim( $value ) ? "\xE2\x80\x94" : $value;
			$label_w = max( $label_w, $this->text_width( $this->encode( $label ), self::F_BOLD, $size ) + 2 * $padx );
			$items[] = array(
				'cells' => array( $label, $value ),
				'fonts' => array( self::F_BOLD, self::F_REGULAR ),
				'fill'  => null,
				'gray'  => array( 0.25, 0.1 ),
			);
		}
		$label_w = min( max( $label_w, 80.0 ), $width * 0.38 );
		$this->render_rows( array( $label_w, $width - $label_w ), $items, null, $size, 0.85 );
		$this->y += 4.0;
		return $this;
	}

	/**
	 * Tabela: colunas com largura proporcional ao conteúdo/cabeçalho, quebra de linha dentro das células e cabeçalho
	 * repetido ao mudar de página.
	 *
	 * @param string[] $headers Cabeçalhos.
	 * @param array    $rows    Linhas (arrays de strings; valores em array são unidos por quebra de linha).
	 * @return self
	 */
	public function table( array $headers, array $rows ) {
		$headers = array_values( array_map( 'strval', $headers ) );
		$cols    = count( $headers );
		if ( 0 === $cols ) {
			return $this;
		}
		$size    = 8.5;
		$padx    = 4.0;
		$natural = array();
		$longest = array();
		foreach ( $headers as $i => $h ) {
			$natural[ $i ] = $this->text_width( $this->encode( $h ), self::F_BOLD, $size ) + 2 * $padx;
			$longest[ $i ] = $this->longest_word( $h, self::F_BOLD, $size );
		}
		$items = array();
		foreach ( $rows as $row ) {
			$cells = array();
			$row   = array_values( (array) $row );
			for ( $i = 0; $i < $cols; $i++ ) {
				$v = isset( $row[ $i ] ) ? $row[ $i ] : '';
				$v = is_array( $v ) ? implode( "\n", array_map( 'strval', $v ) ) : (string) $v;
				foreach ( preg_split( '/\r\n|\r|\n/', $v ) as $part ) {
					$natural[ $i ] = max( $natural[ $i ], $this->text_width( $this->encode( $part ), self::F_REGULAR, $size ) + 2 * $padx );
				}
				$longest[ $i ] = max( $longest[ $i ], $this->longest_word( $v, self::F_REGULAR, $size ) );
				$cells[]       = $v;
			}
			$items[] = array(
				'cells' => $cells,
				'fonts' => array_fill( 0, $cols, self::F_REGULAR ),
				'fill'  => null,
				'gray'  => array_fill( 0, $cols, 0.1 ),
			);
		}
		// Pesos limitados para colunas muito curtas não sumirem nem colunas longas dominarem; cada coluna recebe ao menos a
		// largura da sua palavra mais longa (até 35% da página) para não partir palavras sem necessidade.
		$width   = $this->content_width();
		$weights = array();
		$mins    = array();
		foreach ( $natural as $i => $n ) {
			$weights[] = min( max( $n, 36.0 ), 300.0 );
			$mins[]    = min( $longest[ $i ] + 2 * $padx, $width * 0.35 );
		}
		$widths = self::distribute( $weights, $mins, $width );
		$header = array(
			'cells' => $headers,
			'fonts' => array_fill( 0, $cols, self::F_BOLD ),
			'fill'  => 0.90,
			'gray'  => array_fill( 0, $cols, 0.0 ),
		);
		if ( ! $items ) {
			$items[] = array(
				'cells' => array_merge( array( "\xE2\x80\x94" ), array_fill( 0, max( 0, $cols - 1 ), '' ) ),
				'fonts' => array_fill( 0, $cols, self::F_REGULAR ),
				'fill'  => null,
				'gray'  => array_fill( 0, $cols, 0.4 ),
			);
		}
		$this->render_rows( $widths, $items, $header, $size, 0.75 );
		$this->y += 6.0;
		return $this;
	}

	/**
	 * Nova página (ignorada se a página atual ainda estiver vazia).
	 *
	 * @return self
	 */
	public function page_break() {
		if ( ! $this->pages || $this->page_has_content ) {
			$this->new_page();
		}
		return $this;
	}

	/**
	 * Bytes do PDF.
	 *
	 * @return string
	 */
	public function output() {
		$this->ensure_page();
		$total   = count( $this->pages );
		$objects = array();
		// 1 catálogo, 2 páginas, 3 e 4 fontes, 5 informações; depois pares (página, conteúdo).
		$kids = array();
		for ( $i = 0; $i < $total; $i++ ) {
			$kids[] = ( 6 + 2 * $i ) . ' 0 R';
		}
		$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[2] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . $total . ' >>';
		$objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		$objects[5] = $this->info_dictionary();
		for ( $i = 0; $i < $total; $i++ ) {
			$content                 = $this->pages[ $i ] . $this->chrome( $i + 1, $total );
			$page_no                 = 6 + 2 * $i;
			$objects[ $page_no ]     = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::num( self::PAGE_W ) . ' ' . self::num( self::PAGE_H ) . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> /ProcSet [/PDF /Text] >> /Contents ' . ( $page_no + 1 ) . ' 0 R >>';
			$objects[ $page_no + 1 ] = $this->stream_object( $content );
		}
		$out     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		$count   = count( $objects );
		for ( $n = 1; $n <= $count; $n++ ) {
			$offsets[ $n ] = strlen( $out );
			$out          .= $n . " 0 obj\n" . $objects[ $n ] . "\nendobj\n";
		}
		$xref = strlen( $out );
		$out .= "xref\n0 " . ( $count + 1 ) . "\n0000000000 65535 f \n";
		for ( $n = 1; $n <= $count; $n++ ) {
			$out .= sprintf( '%010d 00000 n ', $offsets[ $n ] ) . "\n";
		}
		$id   = md5( $this->title . '|' . $xref . '|' . $out );
		$out .= 'trailer << /Size ' . ( $count + 1 ) . ' /Root 1 0 R /Info 5 0 R /ID [<' . $id . '> <' . $id . '>] >>' . "\nstartxref\n" . $xref . "\n%%EOF";
		return $out;
	}

	/**
	 * Título (h1/h2) com "keep with next": garante espaço para o título e duas linhas de texto.
	 *
	 * @param string $text   Texto.
	 * @param float  $size   Tamanho da fonte.
	 * @param float  $before Espaço antes.
	 * @param float  $after  Espaço depois.
	 * @param bool   $rule   Desenhar linha abaixo.
	 * @return void
	 */
	private function heading( $text, $size, $before, $after, $rule ) {
		$line_h = $size * 1.25;
		$lines  = $this->wrap( (string) $text, self::F_BOLD, $size, $this->content_width() );
		if ( ! $lines ) {
			return;
		}
		$this->ensure_page();
		if ( $this->page_has_content ) {
			$this->y += $before;
		}
		$needed = count( $lines ) * $line_h + $after + 2 * 13.0;
		if ( $this->y + $needed > $this->bottom() && $this->page_has_content ) {
			$this->new_page();
		}
		foreach ( $lines as $line ) {
			if ( $this->y + $line_h > $this->bottom() ) {
				$this->new_page();
			}
			$this->text( self::MARGIN, $this->y + $size, self::F_BOLD, $size, $line, 0.0 );
			$this->y += $line_h;
		}
		if ( $rule ) {
			$this->line( self::MARGIN, $this->y + 1.5, self::MARGIN + $this->content_width(), $this->y + 1.5, 0.6, 0.5 );
			$this->y += 3.0;
		}
		$this->y               += $after;
		$this->page_has_content = true;
	}

	/**
	 * Desenha linhas de células (kv e tabelas) com quebra de página; linhas altas são divididas entre páginas e o
	 * cabeçalho (quando houver) é repetido no topo de cada nova página.
	 *
	 * @param float[]    $widths    Larguras das colunas.
	 * @param array      $rows      Linhas: cells (string[]), fonts (string[]), fill (float|null), gray (float[]).
	 * @param array|null $header    Linha de cabeçalho no mesmo formato, ou null.
	 * @param float      $size      Tamanho da fonte.
	 * @param float      $rule_gray Cinza da régua inferior (0 = preto, 1 = branco).
	 * @return void
	 */
	private function render_rows( array $widths, array $rows, $header, $size, $rule_gray ) {
		$line_h = $size * 1.3;
		$pad    = 2.5;
		$padx   = 4.0;
		$this->ensure_page();
		$header_lines = null;
		$header_h     = 0.0;
		if ( $header ) {
			$header_lines = $this->wrap_cells( $header, $widths, $size, $padx );
			$header_h     = $this->lines_count( $header_lines ) * $line_h + 2 * $pad;
		}
		$header_drawn = false;
		foreach ( $rows as $row ) {
			$lines = $this->wrap_cells( $row, $widths, $size, $padx );
			$first = true;
			while ( $this->lines_count( $lines ) > 0 ) {
				$max   = $this->lines_count( $lines );
				$avail = (int) floor( ( $this->bottom() - $this->y - 2 * $pad - ( $header_lines && ! $header_drawn ? $header_h : 0 ) ) / $line_h );
				$need  = $first ? min( 2, $max ) : 1;
				if ( $avail < $need ) {
					if ( ! $this->page_has_content ) {
						// Página vazia e ainda assim não cabe: desenha o que der (evita laço infinito).
						$avail = max( 1, $avail );
					} else {
						$this->new_page();
						$header_drawn = false;
						continue;
					}
				}
				if ( $header_lines && ! $header_drawn ) {
					$this->draw_chunk( $widths, $header, $header_lines, $this->lines_count( $header_lines ), $size, $line_h, $pad, $padx, 0.6 );
					$header_drawn = true;
				}
				$n = min( $max, $avail );
				$this->draw_chunk( $widths, $row, $lines, $n, $size, $line_h, $pad, $padx, $rule_gray );
				foreach ( $lines as $i => $cell_lines ) {
					$lines[ $i ] = array_slice( $cell_lines, $n );
				}
				$first = false;
			}
		}
		if ( $header_lines && ! $header_drawn ) {
			// Tabela sem linhas: só o cabeçalho.
			if ( $this->y + $header_h > $this->bottom() && $this->page_has_content ) {
				$this->new_page();
			}
			$this->draw_chunk( $widths, $header, $header_lines, $this->lines_count( $header_lines ), $size, $line_h, $pad, $padx, 0.6 );
		}
	}

	/**
	 * Largura da palavra mais longa de um texto (para a largura mínima das colunas).
	 *
	 * @param string $text Texto UTF-8.
	 * @param string $font F1|F2.
	 * @param float  $size Tamanho.
	 * @return float
	 */
	private function longest_word( $text, $font, $size ) {
		$max = 0.0;
		foreach ( preg_split( '/\s+/', $this->encode( (string) $text ) ) as $word ) {
			$max = max( $max, $this->text_width( $word, $font, $size ) );
		}
		return $max;
	}

	/**
	 * Distribui a largura total proporcionalmente aos pesos, respeitando a largura mínima de cada coluna
	 * (as colunas que ficariam abaixo do mínimo são fixadas nele e o restante é redistribuído).
	 *
	 * @param float[] $weights Pesos.
	 * @param float[] $mins    Mínimos.
	 * @param float   $total   Largura total.
	 * @return float[]
	 */
	private static function distribute( array $weights, array $mins, $total ) {
		$n      = count( $weights );
		$widths = array_fill( 0, $n, 0.0 );
		$sum    = array_sum( $mins );
		if ( $sum >= $total ) {
			foreach ( $mins as $i => $m ) {
				$widths[ $i ] = $total * $m / $sum;
			}
			return $widths;
		}
		$fixed = array_fill( 0, $n, false );
		for ( $round = 0; $round < $n; $round++ ) {
			$free = $total;
			$wsum = 0.0;
			foreach ( $weights as $i => $w ) {
				if ( $fixed[ $i ] ) {
					$free -= $mins[ $i ];
				} else {
					$wsum += $w;
				}
			}
			$changed = false;
			foreach ( $weights as $i => $w ) {
				if ( $fixed[ $i ] ) {
					continue;
				}
				$widths[ $i ] = $wsum > 0 ? $free * $w / $wsum : 0.0;
				if ( $widths[ $i ] < $mins[ $i ] ) {
					$fixed[ $i ]  = true;
					$widths[ $i ] = $mins[ $i ];
					$changed      = true;
				}
			}
			if ( ! $changed ) {
				break;
			}
		}
		return $widths;
	}

	/**
	 * Quebra o texto de cada célula de uma linha.
	 *
	 * @param array   $row    Linha.
	 * @param float[] $widths Larguras.
	 * @param float   $size   Tamanho da fonte.
	 * @param float   $padx   Recuo horizontal.
	 * @return array Lista de listas de linhas (já em Windows-1252).
	 */
	private function wrap_cells( array $row, array $widths, $size, $padx ) {
		$out = array();
		foreach ( $widths as $i => $w ) {
			$text      = isset( $row['cells'][ $i ] ) ? (string) $row['cells'][ $i ] : '';
			$font      = isset( $row['fonts'][ $i ] ) ? $row['fonts'][ $i ] : self::F_REGULAR;
			$out[ $i ] = $this->wrap( $text, $font, $size, max( 1.0, $w - 2 * $padx ) );
		}
		return $out;
	}

	/**
	 * Maior quantidade de linhas entre as células.
	 *
	 * @param array $lines Linhas por célula.
	 * @return int
	 */
	private function lines_count( array $lines ) {
		$max = 0;
		foreach ( $lines as $cell ) {
			$max = max( $max, count( $cell ) );
		}
		return $max;
	}

	/**
	 * Desenha até $n linhas de cada célula da linha, com fundo opcional e régua inferior.
	 *
	 * @param float[] $widths    Larguras.
	 * @param array   $row       Linha (fonts, fill, gray).
	 * @param array   $lines     Linhas por célula.
	 * @param int     $n         Quantidade de linhas a desenhar.
	 * @param float   $size      Tamanho da fonte.
	 * @param float   $line_h    Altura da linha.
	 * @param float   $pad       Recuo vertical.
	 * @param float   $padx      Recuo horizontal.
	 * @param float   $rule_gray Cinza da régua inferior.
	 * @return void
	 */
	private function draw_chunk( array $widths, array $row, array $lines, $n, $size, $line_h, $pad, $padx, $rule_gray ) {
		$h = $n * $line_h + 2 * $pad;
		$x = self::MARGIN;
		if ( null !== $row['fill'] ) {
			$this->rect( self::MARGIN, $this->y, array_sum( $widths ), $h, (float) $row['fill'] );
		}
		foreach ( $widths as $i => $w ) {
			$font = isset( $row['fonts'][ $i ] ) ? $row['fonts'][ $i ] : self::F_REGULAR;
			$gray = isset( $row['gray'][ $i ] ) ? (float) $row['gray'][ $i ] : 0.1;
			$cell = isset( $lines[ $i ] ) ? array_slice( $lines[ $i ], 0, $n ) : array();
			foreach ( $cell as $k => $line ) {
				$this->text( $x + $padx, $this->y + $pad + $k * $line_h + $size, $font, $size, $line, $gray );
			}
			$x += $w;
		}
		$this->y += $h;
		$this->line( self::MARGIN, $this->y, self::MARGIN + array_sum( $widths ), $this->y, 0.4, $rule_gray );
		$this->page_has_content = true;
	}

	/**
	 * Quebra de linha por largura real (palavras; palavras maiores que a largura são partidas por caractere).
	 *
	 * @param string $text  Texto UTF-8.
	 * @param string $font  F1|F2.
	 * @param float  $size  Tamanho.
	 * @param float  $max_w Largura máxima.
	 * @return string[] Linhas em Windows-1252.
	 */
	private function wrap( $text, $font, $size, $max_w ) {
		$text = str_replace( "\t", '    ', (string) $text );
		$enc  = $this->encode( $text );
		$out  = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $enc ) as $paragraph ) {
			$paragraph = trim( $paragraph );
			if ( '' === $paragraph ) {
				$out[] = '';
				continue;
			}
			$words = preg_split( '/ +/', $paragraph );
			$line  = '';
			foreach ( $words as $word ) {
				$try = '' === $line ? $word : $line . ' ' . $word;
				if ( $this->text_width( $try, $font, $size ) <= $max_w ) {
					$line = $try;
					continue;
				}
				if ( '' !== $line ) {
					$out[] = $line;
					$line  = '';
				}
				// A palavra sozinha não cabe: parte por caractere.
				while ( '' !== $word && $this->text_width( $word, $font, $size ) > $max_w ) {
					$len = strlen( $word );
					$cut = 0;
					for ( $i = 1; $i <= $len; $i++ ) {
						if ( $this->text_width( substr( $word, 0, $i ), $font, $size ) > $max_w ) {
							break;
						}
						$cut = $i;
					}
					$cut   = max( 1, $cut );
					$out[] = substr( $word, 0, $cut );
					$word  = substr( $word, $cut );
				}
				$line = $word;
			}
			$out[] = $line;
		}
		// Remove linhas vazias finais.
		while ( $out && '' === end( $out ) ) {
			array_pop( $out );
		}
		return $out;
	}

	/**
	 * Largura de um texto já em Windows-1252.
	 *
	 * @param string $text Texto (bytes cp1252).
	 * @param string $font F1|F2.
	 * @param float  $size Tamanho.
	 * @return float
	 */
	private function text_width( $text, $font, $size ) {
		$table = self::F_BOLD === $font ? self::$w_bold : self::$w_regular;
		$w     = 0;
		$len   = strlen( $text );
		for ( $i = 0; $i < $len; $i++ ) {
			$w += $table[ ord( $text[ $i ] ) ];
		}
		return $w * $size / 1000;
	}

	/**
	 * Converte UTF-8 em Windows-1252: iconv com //TRANSLIT e, se indisponível, mapeamento próprio descartando os
	 * caracteres fora da tabela.
	 *
	 * @param string $text Texto UTF-8.
	 * @return string
	 */
	private function encode( $text ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return '';
		}
		if ( ! preg_match( '/[\x80-\xFF]/', $text ) ) {
			return $text;
		}
		if ( function_exists( 'iconv' ) ) {
			$converted = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- fallback abaixo.
			if ( false !== $converted ) {
				return $converted;
			}
		}
		$map = array_flip( self::$cp1252_high );
		$out = '';
		$len = strlen( $text );
		$i   = 0;
		while ( $i < $len ) {
			$b = ord( $text[ $i ] );
			if ( $b < 0x80 ) {
				$out .= $text[ $i ];
				++$i;
				continue;
			}
			if ( $b >= 0xF0 ) {
				$cp = ( ( $b & 0x07 ) << 18 ) | ( ( ord( $text[ $i + 1 ] ?? "\0" ) & 0x3F ) << 12 ) | ( ( ord( $text[ $i + 2 ] ?? "\0" ) & 0x3F ) << 6 ) | ( ord( $text[ $i + 3 ] ?? "\0" ) & 0x3F );
				$i += 4;
			} elseif ( $b >= 0xE0 ) {
				$cp = ( ( $b & 0x0F ) << 12 ) | ( ( ord( $text[ $i + 1 ] ?? "\0" ) & 0x3F ) << 6 ) | ( ord( $text[ $i + 2 ] ?? "\0" ) & 0x3F );
				$i += 3;
			} elseif ( $b >= 0xC0 ) {
				$cp = ( ( $b & 0x1F ) << 6 ) | ( ord( $text[ $i + 1 ] ?? "\0" ) & 0x3F );
				$i += 2;
			} else {
				++$i; // byte de continuação solto.
				continue;
			}
			if ( $cp >= 0xA0 && $cp <= 0xFF ) {
				$out .= chr( $cp );
			} elseif ( isset( $map[ $cp ] ) ) {
				$out .= chr( $map[ $cp ] );
			}
			// Fora da tabela: descartado.
		}
		return $out;
	}

	/**
	 * Escapa uma string para literal PDF: "(", ")" e "\".
	 *
	 * @param string $text Texto (bytes cp1252).
	 * @return string
	 */
	private function escape( $text ) {
		return str_replace( array( '\\', '(', ')', "\r" ), array( '\\\\', '\\(', '\\)', '\\r' ), $text );
	}

	/**
	 * Emite um texto na página atual.
	 *
	 * @param float  $x    Posição X (pt).
	 * @param float  $y    Linha de base medida a partir do topo (pt).
	 * @param string $font F1|F2.
	 * @param float  $size Tamanho.
	 * @param string $text Texto (bytes cp1252).
	 * @param float  $gray Cinza (0 = preto).
	 * @return void
	 */
	private function text( $x, $y, $font, $size, $text, $gray ) {
		if ( '' === $text ) {
			return;
		}
		$this->pages[ count( $this->pages ) - 1 ] .= 'BT ' . self::num( $gray ) . ' g /' . $font . ' ' . self::num( $size ) . ' Tf ' . self::num( $x ) . ' ' . self::num( self::PAGE_H - $y ) . ' Td (' . $this->escape( $text ) . ") Tj ET\n";
	}

	/**
	 * Linha reta.
	 *
	 * @param float $x1    X inicial.
	 * @param float $y1    Y inicial (do topo).
	 * @param float $x2    X final.
	 * @param float $y2    Y final (do topo).
	 * @param float $width Espessura.
	 * @param float $gray  Cinza.
	 * @return void
	 */
	private function line( $x1, $y1, $x2, $y2, $width, $gray ) {
		$this->pages[ count( $this->pages ) - 1 ] .= self::num( $gray ) . ' G ' . self::num( $width ) . ' w ' . self::num( $x1 ) . ' ' . self::num( self::PAGE_H - $y1 ) . ' m ' . self::num( $x2 ) . ' ' . self::num( self::PAGE_H - $y2 ) . " l S\n";
	}

	/**
	 * Retângulo preenchido.
	 *
	 * @param float $x    X.
	 * @param float $y    Y (do topo).
	 * @param float $w    Largura.
	 * @param float $h    Altura.
	 * @param float $gray Cinza do preenchimento.
	 * @return void
	 */
	private function rect( $x, $y, $w, $h, $gray ) {
		$this->pages[ count( $this->pages ) - 1 ] .= self::num( $gray ) . ' g ' . self::num( $x ) . ' ' . self::num( self::PAGE_H - $y - $h ) . ' ' . self::num( $w ) . ' ' . self::num( $h ) . " re f\n";
	}

	/**
	 * Cabeçalho e rodapé de uma página (gerados no output, quando o total de páginas é conhecido).
	 *
	 * @param int $number Número da página.
	 * @param int $total  Total de páginas.
	 * @return string Operadores PDF.
	 */
	private function chrome( $number, $total ) {
		$saved_pages = $this->pages;
		$this->pages = array( '' );
		$size        = 8.0;
		$width       = $this->content_width();
		$right       = self::MARGIN + $width;
		$title       = $this->fit( $this->encode( $this->title ), self::F_BOLD, $size, $width );
		$header_base = self::MARGIN + $size;
		$this->text( self::MARGIN, $header_base, self::F_BOLD, $size, $title, 0.35 );
		$this->line( self::MARGIN, self::MARGIN + self::HEADER_H - 6.0, $right, self::MARGIN + self::HEADER_H - 6.0, 0.5, 0.75 );
		$footer_top  = self::PAGE_H - self::MARGIN - self::FOOTER_H;
		$footer_base = $footer_top + 12.0 + $size;
		$this->line( self::MARGIN, $footer_top + 6.0, $right, $footer_top + 6.0, 0.5, 0.75 );
		$left_text   = $this->encode( __( 'Confidencial — uso interno', 'eb-credito-rural' ) );
		$center_text = $this->encode( sprintf( /* translators: %s: data e hora */ __( 'Gerado em %s', 'eb-credito-rural' ), $this->generated_at ) );
		$page_text   = $this->encode( sprintf( /* translators: 1: página atual; 2: total de páginas */ __( 'Página %1$d de %2$d', 'eb-credito-rural' ), $number, $total ) );
		$this->text( self::MARGIN, $footer_base, self::F_REGULAR, $size, $left_text, 0.4 );
		$this->text( self::MARGIN + ( $width - $this->text_width( $center_text, self::F_REGULAR, $size ) ) / 2, $footer_base, self::F_REGULAR, $size, $center_text, 0.4 );
		$this->text( $right - $this->text_width( $page_text, self::F_REGULAR, $size ), $footer_base, self::F_REGULAR, $size, $page_text, 0.4 );
		$ops         = $this->pages[0];
		$this->pages = $saved_pages;
		return $ops;
	}

	/**
	 * Recorta um texto (cp1252) para caber na largura, acrescentando reticências.
	 *
	 * @param string $text  Texto.
	 * @param string $font  Fonte.
	 * @param float  $size  Tamanho.
	 * @param float  $max_w Largura.
	 * @return string
	 */
	private function fit( $text, $font, $size, $max_w ) {
		if ( $this->text_width( $text, $font, $size ) <= $max_w ) {
			return $text;
		}
		$ellipsis = "\x85";
		while ( '' !== $text && $this->text_width( $text . $ellipsis, $font, $size ) > $max_w ) {
			$text = substr( $text, 0, -1 );
		}
		return $text . $ellipsis;
	}

	/**
	 * Dicionário /Info (strings Unicode em UTF-16BE com BOM, formato hexadecimal, como manda a especificação).
	 *
	 * @return string
	 */
	private function info_dictionary() {
		$date = 'D:' . gmdate( 'YmdHis' ) . 'Z';
		return '<< /Title ' . $this->text_string( $this->title ) . ' /Producer ' . $this->text_string( 'EB Crédito Rural' ) . ' /Creator ' . $this->text_string( 'EB Crédito Rural (WordPress)' ) . ' /CreationDate (' . $date . ') /ModDate (' . $date . ') >>';
	}

	/**
	 * String de texto para dicionários (fora de streams): ASCII puro como literal; caso contrário UTF-16BE com BOM em hex.
	 *
	 * @param string $text Texto UTF-8.
	 * @return string
	 */
	private function text_string( $text ) {
		$text = (string) $text;
		if ( ! preg_match( '/[\x80-\xFF]/', $text ) ) {
			return '(' . $this->escape( $text ) . ')';
		}
		$utf16 = false;
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$utf16 = mb_convert_encoding( $text, 'UTF-16BE', 'UTF-8' );
		} elseif ( function_exists( 'iconv' ) ) {
			$utf16 = @iconv( 'UTF-8', 'UTF-16BE//IGNORE', $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- fallback abaixo.
		}
		if ( false === $utf16 || null === $utf16 ) {
			return '(' . $this->escape( $this->encode( $text ) ) . ')';
		}
		return '<FEFF' . strtoupper( bin2hex( $utf16 ) ) . '>';
	}

	/**
	 * Objeto de stream (comprimido quando possível).
	 *
	 * @param string $content Conteúdo.
	 * @return string
	 */
	private function stream_object( $content ) {
		$filter = '';
		if ( function_exists( 'gzcompress' ) ) {
			$compressed = gzcompress( $content, 6 );
			if ( false !== $compressed ) {
				$content = $compressed;
				$filter  = ' /Filter /FlateDecode';
			}
		}
		return '<< /Length ' . strlen( $content ) . $filter . " >>\nstream\n" . $content . "\nendstream";
	}

	/**
	 * Garante que exista uma página aberta.
	 *
	 * @return void
	 */
	private function ensure_page() {
		if ( ! $this->pages ) {
			$this->new_page();
		}
	}

	/**
	 * Abre uma nova página.
	 *
	 * @return void
	 */
	private function new_page() {
		$this->pages[]          = '';
		$this->y                = self::MARGIN + self::HEADER_H;
		$this->page_has_content = false;
	}

	/**
	 * Limite inferior da área de conteúdo (do topo).
	 *
	 * @return float
	 */
	private function bottom() {
		return self::PAGE_H - self::MARGIN - self::FOOTER_H;
	}

	/**
	 * Largura útil.
	 *
	 * @return float
	 */
	private function content_width() {
		return self::PAGE_W - 2 * self::MARGIN;
	}

	/**
	 * Número em formato PDF (ponto decimal, sem zeros inúteis).
	 *
	 * @param float $n Número.
	 * @return string
	 */
	private static function num( $n ) {
		$s = number_format( (float) $n, 3, '.', '' );
		$s = rtrim( rtrim( $s, '0' ), '.' );
		return '' === $s || '-0' === $s ? '0' : $s;
	}
}
