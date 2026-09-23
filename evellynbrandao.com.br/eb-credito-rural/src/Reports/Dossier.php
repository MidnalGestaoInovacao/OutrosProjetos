<?php
/**
 * Dossiê em PDF para o comitê: resumo, tomador, imóveis, produção, financeiro (com indicadores), garantias, documentos,
 * checklist de conferência, histórico e pareceres. Entregue pelo módulo "Dossiê PDF".
 *
 * @package EBCR
 */

namespace EBCR\Reports;

defined( 'ABSPATH' ) || exit;

/**
 * Monta e entrega o dossiê.
 */
final class Dossier {

	/**
	 * Gera os bytes do PDF de uma solicitação.
	 *
	 * @param array $submission Linha da solicitação.
	 * @return string
	 */
	public static function build( array $submission ) {
		return ( new \EBCR\Pdf\Writer( 'Dossiê' ) )->output();
	}

	/**
	 * Envia o PDF ao navegador (download) e encerra.
	 *
	 * @param array $submission Linha da solicitação.
	 * @return void
	 */
	public static function download( array $submission ) {
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="dossie-' . sanitize_file_name( (string) $submission['protocol'] ) . '.pdf"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo self::build( $submission ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binário PDF.
		exit;
	}
}
