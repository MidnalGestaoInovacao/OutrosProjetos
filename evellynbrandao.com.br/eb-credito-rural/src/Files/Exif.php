<?php
/**
 * Remoção de metadados EXIF de imagens (re-encode com GD/Imagick).
 *
 * @package EBCR
 */

namespace EBCR\Files;

defined( 'ABSPATH' ) || exit;

/**
 * Re-encoda JPEG/PNG para descartar EXIF/XMP/GPS.
 */
final class Exif {

	/**
	 * Remove metadados no próprio arquivo. Retorna true se processou (ou não era necessário).
	 *
	 * @param string $path Caminho.
	 * @param string $mime MIME real.
	 * @return bool
	 */
	public static function strip( $path, $mime ) {
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
			return true;
		}
		if ( class_exists( '\Imagick' ) ) {
			try {
				$im = new \Imagick( $path );
				$im->stripImage();
				$ok = $im->writeImage( $path );
				$im->clear();
				return (bool) $ok;
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- cai para o GD.
				// Tenta GD.
			}
		}
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return false;
		}
		$data = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- temporário local.
		if ( false === $data ) {
			return false;
		}
		$img = @imagecreatefromstring( $data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- imagem inválida retorna false.
		if ( ! $img ) {
			return false;
		}
		if ( 'image/jpeg' === $mime ) {
			$ok = imagejpeg( $img, $path, 90 );
		} else {
			imagesavealpha( $img, true );
			$ok = imagepng( $img, $path, 6 );
		}
		imagedestroy( $img );
		return (bool) $ok;
	}
}
