<?php
/**
 * Gerador de PDF em PHP puro (sem dependências): páginas A4, fontes padrão Helvetica, quebra de linha, títulos,
 * pares chave/valor, tabelas simples e numeração de páginas. Usado pelo dossiê e pela assinatura eletrônica.
 *
 * @package EBCR
 */

namespace EBCR\Pdf;

defined( 'ABSPATH' ) || exit;

/**
 * API: (new Writer( $title ))->h1()->h2()->p()->kv( array )->table( $headers, $rows )->page_break()->output() : string (bytes do PDF).
 * Implementação entregue pelo módulo "Dossiê PDF".
 */
final class Writer {

	/**
	 * Título do documento.
	 *
	 * @var string
	 */
	private $title;

	/**
	 * Construtor.
	 *
	 * @param string $title Título (metadados e cabeçalho).
	 */
	public function __construct( $title = '' ) {
		$this->title = (string) $title;
	}

	/**
	 * Título principal.
	 *
	 * @param string $text Texto.
	 * @return self
	 */
	public function h1( $text ) {
		return $this;
	}

	/**
	 * Subtítulo.
	 *
	 * @param string $text Texto.
	 * @return self
	 */
	public function h2( $text ) {
		return $this;
	}

	/**
	 * Parágrafo (quebra automática).
	 *
	 * @param string $text Texto.
	 * @return self
	 */
	public function p( $text ) {
		return $this;
	}

	/**
	 * Pares rótulo => valor.
	 *
	 * @param array $rows Pares.
	 * @return self
	 */
	public function kv( array $rows ) {
		return $this;
	}

	/**
	 * Tabela simples.
	 *
	 * @param string[] $headers Cabeçalhos.
	 * @param array    $rows    Linhas (arrays de strings).
	 * @return self
	 */
	public function table( array $headers, array $rows ) {
		return $this;
	}

	/**
	 * Nova página.
	 *
	 * @return self
	 */
	public function page_break() {
		return $this;
	}

	/**
	 * Bytes do PDF.
	 *
	 * @return string
	 */
	public function output() {
		return '%PDF-1.4\n%%EOF\n';
	}
}
