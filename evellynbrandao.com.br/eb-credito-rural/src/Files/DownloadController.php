<?php
/**
 * Download/visualização de documentos somente via PHP autenticado.
 *
 * @package EBCR
 */

namespace EBCR\Files;

use EBCR\Database\DocumentRepository;
use EBCR\Security\AuditLog;
use EBCR\Security\Authorization;
use EBCR\Support\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * URL: ?ebcr_download={public_id}&ebcr_nonce=… (&inline=1 para visualização no admin).
 */
final class DownloadController {

	/**
	 * Hook cedo (antes de qualquer saída do tema).
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'maybe_serve' ), 20 );
	}

	/**
	 * URL assinada para o usuário atual.
	 *
	 * @param array $document Linha.
	 * @param bool  $inline   Visualizar inline.
	 * @return string
	 */
	public static function url( array $document, $inline = false ) {
		$args = array( 'ebcr_download' => $document['public_id'] );
		if ( $inline ) {
			$args['inline'] = '1';
		}
		return wp_nonce_url( add_query_arg( $args, home_url( '/' ) ), 'ebcr_download_' . $document['public_id'], 'ebcr_nonce' );
	}

	/**
	 * Atende a requisição, se for de download.
	 *
	 * @return void
	 */
	public function maybe_serve() {
		if ( ! isset( $_GET['ebcr_download'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verificado abaixo.
			return;
		}
		$public_id = sanitize_text_field( wp_unslash( $_GET['ebcr_download'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idem.
		$inline    = ! empty( $_GET['inline'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idem.
		$this->serve( $public_id, $inline );
	}

	/**
	 * Verifica login, nonce e autorização; registra auditoria; envia o arquivo.
	 *
	 * @param string $public_id UUID do documento.
	 * @param bool   $inline    Inline.
	 * @return void
	 */
	public function serve( $public_id, $inline = false ) {
		nocache_headers();
		if ( ! is_user_logged_in() ) {
			if ( wp_is_json_request() || ( defined( 'EBCR_TESTING' ) && EBCR_TESTING ) ) {
				$this->deny( 401 );
			}
			wp_safe_redirect( wp_login_url( home_url( add_query_arg( array() ) ) ) );
			exit;
		}
		$nonce = isset( $_GET['ebcr_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['ebcr_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ebcr_download_' . $public_id ) ) {
			$this->deny( 403, __( 'Link expirado. Volte à sua área e clique novamente no documento.', 'eb-credito-rural' ) );
		}
		$user_id = get_current_user_id();
		$doc     = ( new DocumentRepository() )->find_by_public_id( $public_id );
		if ( ! $doc ) {
			$this->deny( 404 );
		}
		if ( ! Authorization::can_access_document( $user_id, $doc ) ) {
			AuditLog::log( 'access_denied', 'document', $doc['public_id'], array( 'op' => 'download' ), $user_id );
			$this->deny( 403 );
		}
		$storage = new Storage();
		if ( ! $storage->exists( $doc ) ) {
			$this->deny( 404 );
		}
		AuditLog::log( $inline ? 'document_viewed' : 'document_downloaded', 'document', $doc['public_id'], array( 'name' => $doc['original_name'] ), $user_id );

		$mime = $doc['mime'] ? $doc['mime'] : 'application/octet-stream';
		$name = $this->safe_name( $doc['original_name'] );
		// Inline apenas para tipos seguros de renderizar (PDF/imagens) e para a equipe; demais sempre como anexo.
		$inline = $inline && in_array( $mime, array( 'application/pdf', 'image/jpeg', 'image/png', 'image/webp' ), true ) && Authorization::is_team( $user_id );

		if ( ob_get_level() ) {
			ob_end_clean();
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'Pragma: no-cache' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; sandbox' );
		header( 'Content-Type: ' . $mime );
		header( sprintf( 'Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s', $inline ? 'inline' : 'attachment', $name, rawurlencode( $name ) ) );

		if ( ! empty( $doc['encrypted'] ) ) {
			$bytes = $storage->read( $doc );
			if ( null === $bytes ) {
				$this->deny( 500, __( 'Não foi possível abrir o arquivo (chave de criptografia indisponível).', 'eb-credito-rural' ) );
			}
			header( 'Content-Length: ' . strlen( $bytes ) );
			echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- conteúdo binário do arquivo.
			exit;
		}
		$path = $storage->path( $doc );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		$fh = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming do arquivo privado.
		if ( $fh ) {
			while ( ! feof( $fh ) ) {
				echo fread( $fh, 512 * 1024 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.AlternativeFunctions.file_system_operations_fread -- conteúdo binário.
				flush();
			}
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- idem.
		}
		exit;
	}

	/**
	 * Nome de arquivo seguro para o cabeçalho.
	 *
	 * @param string $name Nome original.
	 * @return string
	 */
	private function safe_name( $name ) {
		$name = sanitize_file_name( (string) $name );
		$name = str_replace( array( '"', "\r", "\n", ';' ), '', $name );
		return $name ? $name : 'documento';
	}

	/**
	 * Encerra com código HTTP.
	 *
	 * @param int    $code    Código.
	 * @param string $message Mensagem.
	 * @return void
	 */
	private function deny( $code, $message = '' ) {
		$messages = array(
			401 => __( 'É necessário estar autenticado.', 'eb-credito-rural' ),
			403 => __( 'Você não tem permissão para acessar este arquivo.', 'eb-credito-rural' ),
			404 => __( 'Arquivo não encontrado.', 'eb-credito-rural' ),
			500 => __( 'Erro ao ler o arquivo.', 'eb-credito-rural' ),
		);
		$message  = $message ? $message : ( isset( $messages[ $code ] ) ? $messages[ $code ] : '' );
		if ( defined( 'EBCR_TESTING' ) && EBCR_TESTING ) {
			throw new \EBCR\Files\DownloadDenied( esc_html( $message ), (int) $code ); // testes.
		}
		wp_die( esc_html( $message ), (int) $code, array( 'response' => (int) $code ) );
	}
}
