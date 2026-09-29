<?php
/**
 * Armazenamento físico: subpasta aleatória por usuário, nomes aleatórios, criptografia opcional.
 *
 * @package EBCR
 */

namespace EBCR\Files;

use EBCR\Security\Crypto;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Nunca expõe o caminho real; o nome original fica só no banco.
 */
final class Storage {

	/**
	 * Guarda.
	 *
	 * @var FileGuard
	 */
	private $guard;

	/**
	 * Construtor.
	 */
	public function __construct() {
		$this->guard = new FileGuard();
	}

	/**
	 * Nome da subpasta do usuário (aleatório, persistido em user meta).
	 *
	 * @param int $user_id Usuário.
	 * @return string
	 */
	public function user_dir_name( $user_id ) {
		$name = (string) get_user_meta( $user_id, 'ebcr_storage_dir', true );
		if ( ! preg_match( '/^u[a-f0-9]{24}$/', $name ) ) {
			$name = 'u' . Helpers::random_hex( 12 );
			update_user_meta( $user_id, 'ebcr_storage_dir', $name );
		}
		return $name;
	}

	/**
	 * Caminho absoluto da pasta do usuário (criando e protegendo).
	 *
	 * @param string $dir_name Nome da subpasta.
	 * @return string
	 */
	public function user_dir_path( $dir_name ) {
		$this->guard->ensure_base_dir();
		$path = $this->guard->base_dir() . $dir_name . '/';
		if ( ! is_dir( $path ) ) {
			wp_mkdir_p( $path );
			$this->guard->protect_dir( $path );
		}
		return $path;
	}

	/**
	 * Grava o conteúdo de um upload validado. Retorna [storage_dir, stored_name, encrypted].
	 *
	 * @param int    $user_id  Usuário dono.
	 * @param string $tmp_path Arquivo temporário (já validado).
	 * @return array
	 * @throws \RuntimeException Em falha de gravação.
	 */
	public function store( $user_id, $tmp_path ) {
		$dir_name = $this->user_dir_name( $user_id );
		$dir      = $this->user_dir_path( $dir_name );
		$stored   = Helpers::random_hex( 20 ) . '.dat';
		$encrypt  = Options::bool( 'encrypt_files' ) && Crypto::is_available();
		if ( $encrypt ) {
			$bytes = file_get_contents( $tmp_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- arquivo temporário local.
			if ( false === $bytes || false === file_put_contents( $dir . $stored, Crypto::encrypt_bytes( $bytes ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- gravação em pasta privada.
				throw new \RuntimeException( esc_html( 'write_failed' ) );
			}
			wp_delete_file( $tmp_path );
		} elseif ( ! ( ( is_uploaded_file( $tmp_path ) && move_uploaded_file( $tmp_path, $dir . $stored ) ) || ( ! is_uploaded_file( $tmp_path ) && rename( $tmp_path, $dir . $stored ) ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- movimentação local.
			throw new \RuntimeException( esc_html( 'write_failed' ) );
		}
		chmod( $dir . $stored, 0640 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- restringe permissão.
		return array( $dir_name, $stored, $encrypt ? 1 : 0 );
	}

	/**
	 * Caminho absoluto de um documento.
	 *
	 * @param array $document Linha.
	 * @return string
	 */
	public function path( array $document ) {
		$dir  = preg_replace( '/[^a-z0-9]/', '', (string) $document['storage_dir'] );
		$name = preg_replace( '/[^a-z0-9.]/', '', (string) $document['stored_name'] );
		return $this->guard->base_dir() . $dir . '/' . $name;
	}

	/**
	 * Conteúdo em claro (descriptografa se necessário). Null se indisponível.
	 *
	 * @param array $document Linha.
	 * @return string|null
	 */
	public function read( array $document ) {
		$path = $this->path( $document );
		if ( ! is_file( $path ) ) {
			return null;
		}
		$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- arquivo privado local.
		if ( false === $bytes ) {
			return null;
		}
		if ( ! empty( $document['encrypted'] ) ) {
			return Crypto::decrypt_bytes( $bytes );
		}
		return $bytes;
	}

	/**
	 * Existe no disco?
	 *
	 * @param array $document Linha.
	 * @return bool
	 */
	public function exists( array $document ) {
		return is_file( $this->path( $document ) );
	}

	/**
	 * Remove o arquivo físico.
	 *
	 * @param array $document Linha.
	 * @return void
	 */
	public function delete( array $document ) {
		$path = $this->path( $document );
		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
