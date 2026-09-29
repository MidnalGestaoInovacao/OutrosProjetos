<?php
/**
 * Validação e gravação de uploads.
 *
 * @package EBCR
 */

namespace EBCR\Files;

use EBCR\Database\DocumentRepository;
use EBCR\Database\DocumentRequestRepository;
use EBCR\Forms\DocumentMatrix;
use EBCR\Security\AuditLog;
use EBCR\Security\Authorization;
use EBCR\Support\Helpers;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Regras: extensão permitida, MIME real (finfo + wp_check_filetype_and_ext), tamanho, quantidade, cota, hash, EXIF, antivírus.
 */
final class UploadHandler {

	/**
	 * MIME esperados por extensão permitida.
	 *
	 * @var array
	 */
	const MIMES = array(
		'pdf'  => array( 'application/pdf' ),
		'jpg'  => array( 'image/jpeg' ),
		'jpeg' => array( 'image/jpeg' ),
		'png'  => array( 'image/png' ),
		'webp' => array( 'image/webp' ),
		'heic' => array( 'image/heic', 'image/heif' ),
		'doc'  => array( 'application/msword' ),
		'docx' => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ),
		'xls'  => array( 'application/vnd.ms-excel' ),
		'xlsx' => array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ),
	);

	/**
	 * Processa um item de $_FILES para uma solicitação.
	 *
	 * @param int    $user_id    Usuário (deve ser o dono).
	 * @param array  $submission Linha da solicitação.
	 * @param array  $file       Item de $_FILES (name, type, tmp_name, error, size).
	 * @param string $doc_type   Tipo (matriz).
	 * @param string $ref_key    Referência (ex.: p:12) ou ''.
	 * @param int    $request_id Pedido da equipe atendido (opcional).
	 * @return array|\WP_Error Linha do documento.
	 */
	public function handle( $user_id, array $submission, array $file, $doc_type, $ref_key = '', $request_id = 0 ) {
		if ( ! Authorization::client_can_upload( $user_id, $submission ) ) {
			AuditLog::log( 'access_denied', 'submission', $submission['public_id'], array( 'op' => 'upload' ), $user_id );
			return new \WP_Error( 'forbidden', __( 'Você não pode enviar documentos para esta solicitação.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		$request = null;
		if ( $request_id ) {
			$request = ( new DocumentRequestRepository() )->find( (int) $request_id );
			if ( ! $request || (int) $request['submission_id'] !== (int) $submission['id'] || ! empty( $request['fulfilled_at'] ) ) {
				return new \WP_Error( 'bad_request', __( 'Pedido de documento inválido ou já atendido.', 'eb-credito-rural' ), array( 'status' => 400 ) );
			}
			$doc_type = $request['doc_type'];
		} elseif ( ! \EBCR\Domain\Status::client_can_edit( $submission['status'] ) ) {
			// Fora de rascunho/pendência, só é permitido atender pedidos abertos.
			$open = ( new DocumentRequestRepository() )->for_submission( (int) $submission['id'], true );
			if ( ! $open ) {
				return new \WP_Error( 'locked', __( 'Esta solicitação não aceita novos documentos no momento.', 'eb-credito-rural' ), array( 'status' => 403 ) );
			}
		}
		$doc_type = sanitize_key( $doc_type );
		if ( ! DocumentMatrix::is_valid_type( $doc_type ) ) {
			return new \WP_Error( 'bad_type', __( 'Tipo de documento inválido.', 'eb-credito-rural' ), array( 'status' => 400 ) );
		}
		$ref_key = preg_replace( '/[^a-z0-9:_-]/', '', strtolower( (string) $ref_key ) );

		$check = $this->validate_file( $file, $user_id, $submission );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		list( $ext, $mime, $tmp ) = $check;

		if ( Options::bool( 'strip_exif' ) ) {
			Exif::strip( $tmp, $mime );
		}
		$av = Antivirus::scan( $tmp );
		if ( ! $av['ok'] ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'antivirus', $av['message'], array( 'status' => 422 ) );
		}
		$sha  = hash_file( 'sha256', $tmp );
		$size = (int) filesize( $tmp );
		$docs = new DocumentRepository();
		$dup  = $docs->find_duplicate( (int) $submission['id'], $sha );
		if ( $dup ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'duplicate', sprintf( /* translators: %s: nome do arquivo */ __( 'Este arquivo já foi enviado nesta solicitação (%s).', 'eb-credito-rural' ), $dup['original_name'] ), array( 'status' => 409 ) );
		}
		try {
			list( $dir, $stored, $enc ) = ( new Storage() )->store( $user_id, $tmp );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'storage', __( 'Não foi possível gravar o arquivo. Tente novamente ou contate o suporte.', 'eb-credito-rural' ), array( 'status' => 500 ) );
		}
		$original = sanitize_file_name( wp_unslash( (string) $file['name'] ) );
		$original = $original ? mb_substr( $original, 0, 200 ) : 'documento.' . $ext;
		$matrix   = DocumentMatrix::all();
		$expires  = null;
		if ( isset( $matrix[ $doc_type ] ) && $matrix[ $doc_type ]['validity_days'] > 0 ) {
			$expires = gmdate( 'Y-m-d', strtotime( '+' . (int) $matrix[ $doc_type ]['validity_days'] . ' days' ) );
		}
		$row = $docs->create(
			array(
				'submission_id' => (int) $submission['id'],
				'user_id'       => (int) $user_id,
				'doc_type'      => $doc_type,
				'ref_key'       => $ref_key,
				'request_id'    => $request ? (int) $request['id'] : null,
				'original_name' => $original,
				'stored_name'   => $stored,
				'storage_dir'   => $dir,
				'mime'          => $mime,
				'size'          => $size,
				'sha256'        => $sha,
				'encrypted'     => $enc,
				'expires_at'    => $expires,
			)
		);
		if ( $request ) {
			( new DocumentRequestRepository() )->fulfill( (int) $request['id'], (int) $row['id'] );
		}
		AuditLog::log(
			'document_uploaded',
			'document',
			$row['public_id'],
			array(
				'submission' => $submission['public_id'],
				'type'       => $doc_type,
				'size'       => $size,
				'mime'       => $mime,
			),
			$user_id
		);
		/**
		 * Documento enviado.
		 *
		 * @param array $row        Documento.
		 * @param array $submission Solicitação.
		 * @param array $request    Pedido atendido ou null.
		 */
		do_action( 'ebcr_document_uploaded', $row, $submission, $request );
		return $row;
	}

	/**
	 * Valida o arquivo (erros de upload, extensão, MIME real, tamanho, quantidade, cota).
	 *
	 * @param array $file       $_FILES item.
	 * @param int   $user_id    Usuário.
	 * @param array $submission Solicitação.
	 * @return array|\WP_Error [ext, mime, tmp]
	 */
	public function validate_file( array $file, $user_id, array $submission ) {
		$err = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $err ) {
			$map = array(
				UPLOAD_ERR_INI_SIZE   => __( 'O arquivo excede o limite do servidor.', 'eb-credito-rural' ),
				UPLOAD_ERR_FORM_SIZE  => __( 'O arquivo excede o limite do formulário.', 'eb-credito-rural' ),
				UPLOAD_ERR_PARTIAL    => __( 'O envio foi interrompido. Tente novamente.', 'eb-credito-rural' ),
				UPLOAD_ERR_NO_FILE    => __( 'Nenhum arquivo foi enviado.', 'eb-credito-rural' ),
				UPLOAD_ERR_NO_TMP_DIR => __( 'Erro do servidor (pasta temporária).', 'eb-credito-rural' ),
				UPLOAD_ERR_CANT_WRITE => __( 'Erro do servidor ao gravar.', 'eb-credito-rural' ),
			);
			return new \WP_Error( 'upload_error', isset( $map[ $err ] ) ? $map[ $err ] : __( 'Falha no envio.', 'eb-credito-rural' ), array( 'status' => 400 ) );
		}
		$tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		if ( ! $tmp || ! is_file( $tmp ) ) {
			return new \WP_Error( 'upload_error', __( 'Arquivo temporário inválido.', 'eb-credito-rural' ), array( 'status' => 400 ) );
		}
		$name = sanitize_file_name( wp_unslash( (string) $file['name'] ) );
		$ext  = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		$ok   = Options::allowed_extensions();
		if ( ! $ext || ! in_array( $ext, $ok, true ) ) {
			return new \WP_Error( 'bad_extension', sprintf( /* translators: %s: lista de extensões */ __( 'Tipo de arquivo não permitido. Envie: %s.', 'eb-credito-rural' ), strtoupper( implode( ', ', $ok ) ) ), array( 'status' => 415 ) );
		}
		// MIME real (finfo) e checagem cruzada do WordPress.
		$real = '';
		if ( function_exists( 'finfo_open' ) ) {
			$fi = finfo_open( FILEINFO_MIME_TYPE );
			if ( $fi ) {
				$real = (string) finfo_file( $fi, $tmp );
				finfo_close( $fi );
			}
		}
		$expected = isset( self::MIMES[ $ext ] ) ? self::MIMES[ $ext ] : array();
		if ( ! $real || ! in_array( $real, $expected, true ) ) {
			return new \WP_Error( 'mime_mismatch', __( 'O conteúdo do arquivo não corresponde à extensão. Envie o arquivo original (PDF ou imagem).', 'eb-credito-rural' ), array( 'status' => 415 ) );
		}
		$wp_check = wp_check_filetype_and_ext(
			$tmp,
			$name,
			array_combine(
				$ok,
				array_map(
					static function ( $e ) {
						return self::MIMES[ $e ][0] ?? 'application/octet-stream';
					},
					$ok
				)
			)
		);
		if ( empty( $wp_check['ext'] ) || empty( $wp_check['type'] ) || ! in_array( $wp_check['type'], $expected, true ) ) {
			return new \WP_Error( 'mime_mismatch', __( 'O conteúdo do arquivo não corresponde à extensão.', 'eb-credito-rural' ), array( 'status' => 415 ) );
		}
		if ( 'application/pdf' === $real ) {
			$head = file_get_contents( $tmp, false, null, 0, 1024 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- leitura curta do temporário.
			if ( false === $head || 0 !== strpos( ltrim( $head ), '%PDF' ) ) {
				return new \WP_Error( 'mime_mismatch', __( 'PDF inválido.', 'eb-credito-rural' ), array( 'status' => 415 ) );
			}
		}
		$size = (int) filesize( $tmp );
		$max  = max( 1, Options::int( 'max_file_size_mb' ) ) * MB_IN_BYTES;
		if ( $size <= 0 || $size > $max ) {
			return new \WP_Error( 'too_large', sprintf( /* translators: %s: tamanho máximo */ __( 'O arquivo excede o tamanho máximo de %s.', 'eb-credito-rural' ), size_format( $max ) ), array( 'status' => 413 ) );
		}
		$docs = new DocumentRepository();
		if ( $docs->count_for_submission( (int) $submission['id'] ) >= max( 1, Options::int( 'max_files_per_submission' ) ) ) {
			return new \WP_Error( 'too_many', __( 'Limite de arquivos por solicitação atingido.', 'eb-credito-rural' ), array( 'status' => 422 ) );
		}
		$quota = max( 1, Options::int( 'user_quota_mb' ) ) * MB_IN_BYTES;
		if ( $docs->bytes_for_user( $user_id ) + $size > $quota ) {
			return new \WP_Error( 'quota', sprintf( /* translators: %s: cota */ __( 'Sua cota de armazenamento (%s) foi atingida. Remova arquivos antigos ou fale com a equipe.', 'eb-credito-rural' ), size_format( $quota ) ), array( 'status' => 422 ) );
		}
		return array( $ext, $real, $tmp );
	}

	/**
	 * Registra um arquivo gerado pelo próprio plugin (ex.: PDF de assinatura eletrônica) como documento da solicitação.
	 * O conteúdo já é confiável, então não passa pelas checagens de extensão/MIME do upload; aplica as mesmas regras de
	 * armazenamento (pasta privada, nome aleatório, criptografia opcional), hash, validade da matriz, auditoria e hooks.
	 *
	 * @param int    $user_id       Usuário dono.
	 * @param array  $submission    Linha da solicitação.
	 * @param string $tmp_path      Arquivo temporário (será movido/removido).
	 * @param string $doc_type      Tipo (matriz).
	 * @param string $original_name Nome exibido ao cliente/equipe.
	 * @param string $mime          MIME do conteúdo.
	 * @param string $ref_key       Referência (ex.: p:12) ou ''.
	 * @param int    $request_id    Pedido da equipe atendido (opcional, já validado pelo chamador).
	 * @param array  $meta          Dados extras para a auditoria (ex.: source => esign).
	 * @return array|\WP_Error Linha do documento.
	 */
	public function register_generated( $user_id, array $submission, $tmp_path, $doc_type, $original_name, $mime, $ref_key = '', $request_id = 0, array $meta = array() ) {
		if ( ! Authorization::client_can_upload( $user_id, $submission ) ) {
			wp_delete_file( $tmp_path );
			return new \WP_Error( 'forbidden', __( 'Você não pode enviar documentos para esta solicitação.', 'eb-credito-rural' ), array( 'status' => 403 ) );
		}
		$doc_type = sanitize_key( $doc_type );
		if ( ! DocumentMatrix::is_valid_type( $doc_type ) ) {
			wp_delete_file( $tmp_path );
			return new \WP_Error( 'bad_type', __( 'Tipo de documento inválido.', 'eb-credito-rural' ), array( 'status' => 400 ) );
		}
		if ( ! is_file( $tmp_path ) || filesize( $tmp_path ) <= 0 ) {
			return new \WP_Error( 'storage', __( 'Não foi possível gerar o arquivo.', 'eb-credito-rural' ), array( 'status' => 500 ) );
		}
		$ref_key = preg_replace( '/[^a-z0-9:_-]/', '', strtolower( (string) $ref_key ) );
		$size    = (int) filesize( $tmp_path );
		$docs    = new DocumentRepository();
		if ( $docs->count_for_submission( (int) $submission['id'] ) >= max( 1, Options::int( 'max_files_per_submission' ) ) ) {
			wp_delete_file( $tmp_path );
			return new \WP_Error( 'too_many', __( 'Limite de arquivos por solicitação atingido.', 'eb-credito-rural' ), array( 'status' => 422 ) );
		}
		$quota = max( 1, Options::int( 'user_quota_mb' ) ) * MB_IN_BYTES;
		if ( $docs->bytes_for_user( $user_id ) + $size > $quota ) {
			wp_delete_file( $tmp_path );
			return new \WP_Error( 'quota', sprintf( /* translators: %s: cota */ __( 'Sua cota de armazenamento (%s) foi atingida. Remova arquivos antigos ou fale com a equipe.', 'eb-credito-rural' ), size_format( $quota ) ), array( 'status' => 422 ) );
		}
		$sha = hash_file( 'sha256', $tmp_path );
		try {
			list( $dir, $stored, $enc ) = ( new Storage() )->store( $user_id, $tmp_path );
		} catch ( \RuntimeException $e ) {
			wp_delete_file( $tmp_path );
			return new \WP_Error( 'storage', __( 'Não foi possível gravar o arquivo. Tente novamente ou contate o suporte.', 'eb-credito-rural' ), array( 'status' => 500 ) );
		}
		$original = sanitize_file_name( (string) $original_name );
		$original = $original ? mb_substr( $original, 0, 200 ) : 'documento.pdf';
		$matrix   = DocumentMatrix::all();
		$expires  = null;
		if ( isset( $matrix[ $doc_type ] ) && $matrix[ $doc_type ]['validity_days'] > 0 ) {
			$expires = gmdate( 'Y-m-d', strtotime( '+' . (int) $matrix[ $doc_type ]['validity_days'] . ' days' ) );
		}
		$request = $request_id ? ( new DocumentRequestRepository() )->find( (int) $request_id ) : null;
		$row     = $docs->create(
			array(
				'submission_id' => (int) $submission['id'],
				'user_id'       => (int) $user_id,
				'doc_type'      => $doc_type,
				'ref_key'       => $ref_key,
				'request_id'    => $request ? (int) $request['id'] : null,
				'original_name' => $original,
				'stored_name'   => $stored,
				'storage_dir'   => $dir,
				'mime'          => sanitize_mime_type( $mime ),
				'size'          => $size,
				'sha256'        => $sha,
				'encrypted'     => $enc,
				'expires_at'    => $expires,
			)
		);
		if ( $request ) {
			( new DocumentRequestRepository() )->fulfill( (int) $request['id'], (int) $row['id'] );
		}
		AuditLog::log(
			'document_uploaded',
			'document',
			$row['public_id'],
			array_merge(
				array(
					'submission' => $submission['public_id'],
					'type'       => $doc_type,
					'size'       => $size,
					'mime'       => $row['mime'],
				),
				$meta
			),
			$user_id
		);
		/** This action is documented in src/Files/UploadHandler.php */
		do_action( 'ebcr_document_uploaded', $row, $submission, $request );
		return $row;
	}

	/**
	 * Limites do PHP vs. configuração (para o admin).
	 *
	 * @return array{php_upload:int,php_post:int,configured:int,ok:bool}
	 */
	public static function php_limits() {
		$upload = wp_convert_hr_to_bytes( (string) ini_get( 'upload_max_filesize' ) );
		$post   = wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) );
		$conf   = max( 1, Options::int( 'max_file_size_mb' ) ) * MB_IN_BYTES;
		return array(
			'php_upload' => $upload,
			'php_post'   => $post,
			'configured' => $conf,
			'ok'         => $upload >= $conf && $post >= $conf,
		);
	}

	/**
	 * Uso da cota do usuário.
	 *
	 * @param int $user_id Usuário.
	 * @return array{used:int,quota:int,percent:int}
	 */
	public static function quota_usage( $user_id ) {
		$used  = ( new DocumentRepository() )->bytes_for_user( $user_id );
		$quota = max( 1, Options::int( 'user_quota_mb' ) ) * MB_IN_BYTES;
		return array(
			'used'    => $used,
			'quota'   => $quota,
			'percent' => min( 100, (int) round( 100 * $used / $quota ) ),
		);
	}
}
