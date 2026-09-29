<?php
/**
 * Anonimização e retenção.
 *
 * @package EBCR
 */

namespace EBCR\Security;

use EBCR\Database\DocumentRepository;
use EBCR\Database\GuaranteeRepository;
use EBCR\Database\MessageRepository;
use EBCR\Database\PropertyRepository;
use EBCR\Database\SubmissionDataRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Files\Storage;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Remove documentos e dados pessoais, mantendo estatísticas agregadas.
 */
final class Retention {

	/**
	 * Anonimiza uma solicitação.
	 *
	 * @param array  $submission Linha.
	 * @param string $reason     Motivo (retention|privacy_eraser|lgpd_request).
	 * @return void
	 */
	public static function anonymize( array $submission, $reason = 'retention' ) {
		$id      = (int) $submission['id'];
		$docs    = new DocumentRepository();
		$storage = new Storage();
		foreach ( $docs->for_submission( $id ) as $d ) {
			$storage->delete( $d );
			$docs->update(
				(int) $d['id'],
				array(
					'original_name' => 'anonimizado',
					'deleted_at'    => current_time( 'mysql', true ),
				)
			);
		}
		( new SubmissionDataRepository() )->delete_all( $id );
		( new PropertyRepository() )->delete_all( $id );
		( new GuaranteeRepository() )->delete_all( $id );
		global $wpdb;
		$wpdb->delete( \EBCR\Database\Db::table( 'messages' ), array( 'submission_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- tabela própria.
		( new SubmissionRepository() )->update( $id, array( 'anonymized_at' => current_time( 'mysql', true ) ) );
		AuditLog::log( 'retention_anonymized', 'submission', $submission['public_id'], array( 'reason' => $reason ), 0 );
	}

	/**
	 * Rotina de retenção: reprovadas/canceladas após X meses; aprovadas/concluídas após Y meses.
	 *
	 * @return int Quantidade anonimizada.
	 */
	public static function run() {
		$repo = new SubmissionRepository();
		$n    = 0;
		$sets = array(
			array( array( Status::REJECTED, Status::CANCELLED ), Options::int( 'retention_months_rejected' ) ),
			array( array( Status::DONE, Status::APPROVED ), Options::int( 'retention_months_approved' ) ),
		);
		foreach ( $sets as $set ) {
			foreach ( $repo->finished_before( $set[0], $set[1] ) as $s ) {
				self::anonymize( $s, 'retention' );
				++$n;
			}
		}
		return $n;
	}
}
