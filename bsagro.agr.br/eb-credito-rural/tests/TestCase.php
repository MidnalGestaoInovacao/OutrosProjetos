<?php
/**
 * Base dos testes: usuários, solicitações e arquivos de apoio.
 *
 * @package EBCR
 */

use EBCR\Database\DocumentRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Files\UploadHandler;
use EBCR\Roles\Capabilities;
use EBCR\Support\Options;

/**
 * Helpers comuns.
 */
abstract class EBCR_TestCase extends \PHPUnit\Framework\TestCase {

	/**
	 * IDs criados para limpeza.
	 *
	 * @var int[]
	 */
	protected $users = array();

	/**
	 * Arquivos temporários.
	 *
	 * @var string[]
	 */
	protected $tmp = array();

	/**
	 * Configuração padrão limpa.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		update_option( Options::OPTION, array( 'min_interval_days' => 0, 'block_if_in_progress' => false, 'min_fill_seconds' => 0 ), false );
		Options::flush();
		wp_set_current_user( 0 );
	}

	/**
	 * Limpeza.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->users as $uid ) {
			wp_delete_user( $uid );
		}
		foreach ( $this->tmp as $f ) {
			if ( is_file( $f ) ) {
				unlink( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Cria usuário com papel.
	 *
	 * @param string $role     Papel.
	 * @param bool   $verified E-mail confirmado.
	 * @return int
	 */
	protected function make_user( $role = Capabilities::ROLE_CLIENT, $verified = true ) {
		$id = wp_insert_user(
			array(
				'user_login'   => 'u' . wp_generate_password( 8, false, false ),
				'user_email'   => 'u' . wp_generate_password( 8, false, false ) . '@example.com',
				'user_pass'    => 'Senha-Forte-123!',
				'display_name' => 'Usuário Teste',
				'role'         => $role,
			)
		);
		$this->assertIsInt( $id, 'wp_insert_user falhou' );
		update_user_meta( $id, 'ebcr_email_verified', $verified ? time() : 0 );
		$this->users[] = $id;
		return $id;
	}

	/**
	 * Cria solicitação (rascunho ou com status).
	 *
	 * @param int    $user_id Usuário.
	 * @param string $status  Status.
	 * @param array  $extra   Campos.
	 * @return array
	 */
	protected function make_submission( $user_id, $status = 'rascunho', array $extra = array() ) {
		$repo = new SubmissionRepository();
		$s    = $repo->create_draft( $user_id );
		$data = array_merge( array( 'status' => $status ), $extra );
		if ( 'rascunho' !== $status && empty( $extra['submitted_at'] ) ) {
			$data['submitted_at'] = current_time( 'mysql', true );
			$data['protocol']     = 'T-' . wp_generate_password( 8, false, false );
		}
		$repo->update( (int) $s['id'], $data );
		return $repo->find( (int) $s['id'] );
	}

	/**
	 * Cria um arquivo temporário com conteúdo.
	 *
	 * @param string $content Conteúdo.
	 * @param string $ext     Extensão.
	 * @return string Caminho.
	 */
	protected function tmp_file( $content, $ext = 'pdf' ) {
		$path = tempnam( sys_get_temp_dir(), 'ebcr' ) . '.' . $ext;
		file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->tmp[] = $path;
		return $path;
	}

	/**
	 * PDF mínimo válido.
	 *
	 * @param int $pad Bytes extras.
	 * @return string
	 */
	protected function pdf_bytes( $pad = 0 ) {
		return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n" . str_repeat( '%', $pad ) . "\ntrailer<</Root 1 0 R>>\n%%EOF\n";
	}

	/**
	 * Simula $_FILES.
	 *
	 * @param string $path Caminho.
	 * @param string $name Nome original.
	 * @return array
	 */
	protected function files_item( $path, $name ) {
		return array(
			'name'     => $name,
			'type'     => 'application/octet-stream',
			'tmp_name' => $path,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $path ),
		);
	}

	/**
	 * Envia um PDF válido para a solicitação.
	 *
	 * @param int    $user_id    Usuário.
	 * @param array  $submission Solicitação.
	 * @param string $type       Tipo.
	 * @return array Documento.
	 */
	protected function upload_pdf( $user_id, array $submission, $type = 'comprovante_residencia' ) {
		$path = $this->tmp_file( $this->pdf_bytes( wp_rand( 1, 500 ) ) );
		$row  = ( new UploadHandler() )->handle( $user_id, $submission, $this->files_item( $path, 'comprovante.pdf' ), $type );
		$this->assertIsArray( $row, is_wp_error( $row ) ? $row->get_error_message() : 'upload falhou' );
		return $row;
	}
}
