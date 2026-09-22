<?php
/**
 * Painel inicial (cartões e listas). Gráficos ficam para a Fase 2.
 *
 * @package EBCR
 */

namespace EBCR\Admin;

use EBCR\Database\CrmActivityRepository;
use EBCR\Database\DocumentRepository;
use EBCR\Database\DocumentRequestRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Roles\Capabilities;
use EBCR\Support\Options;
use EBCR\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Cartões: novas (7 dias), em análise, com pendência, aprovadas no mês, volume.
 */
final class Dashboard {

	/**
	 * Renderiza.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Capabilities::CAP_VIEW ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'eb-credito-rural' ), 403 );
		}
		global $wpdb;
		$t          = \EBCR\Database\Db::table( 'submissions' );
		$week       = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$month      = gmdate( 'Y-m-01 00:00:00' );
		$cards      = array(
			array( __( 'Novas (7 dias)', 'eb-credito-rural' ), (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE deleted_at IS NULL AND submitted_at >= %s", $week ) ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
			array( __( 'Em análise', 'eb-credito-rural' ), (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE deleted_at IS NULL AND status IN (%s,%s,%s)", Status::PRE_ANALYSIS, Status::CREDIT, Status::COMMITTEE ) ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- idem.
			array( __( 'Com pendência', 'eb-credito-rural' ), (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE deleted_at IS NULL AND status = %s", Status::PENDING_DOCS ) ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- idem.
			array( __( 'Aprovadas no mês', 'eb-credito-rural' ), (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE deleted_at IS NULL AND status IN (%s,%s,%s) AND updated_at >= %s", Status::APPROVED, Status::FORMALIZATION, Status::DONE, $month ) ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- idem.
			array( __( 'Volume solicitado (em andamento)', 'eb-credito-rural' ), \EBCR\Support\Helpers::money( (float) $wpdb->get_var( "SELECT COALESCE(SUM(requested_amount),0) FROM `{$t}` WHERE deleted_at IS NULL AND status NOT IN ('rascunho','reprovada','cancelada','concluida')" ) ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- idem.
			array( __( 'Volume aprovado (mês)', 'eb-credito-rural' ), \EBCR\Support\Helpers::money( (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(requested_amount),0) FROM `{$t}` WHERE deleted_at IS NULL AND status IN (%s,%s,%s) AND updated_at >= %s", Status::APPROVED, Status::FORMALIZATION, Status::DONE, $month ) ) ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- idem.
		);
		$days       = max( 1, Options::int( 'pending_reminder_days' ) );
		$stale      = ( new DocumentRequestRepository() )->stale_open( $days );
		$subs       = new SubmissionRepository();
		$stale_rows = array();
		foreach ( $stale as $r ) {
			$s = $subs->find( (int) $r['submission_id'] );
			if ( $s && ! Status::is_final( $s['status'] ) ) {
				$stale_rows[ $s['public_id'] ] = array(
					's'     => $s,
					'label' => $r['label'],
					'since' => $r['requested_at'],
				);
			}
		}
		View::show(
			'admin/dashboard',
			array(
				'cards'     => $cards,
				'by_status' => $subs->count_by_status(),
				'tasks'     => current_user_can( Capabilities::CAP_CRM ) ? ( new CrmActivityRepository() )->open_tasks( 10 ) : array(),
				'stale'     => $stale_rows,
				'expiring'  => ( new DocumentRepository() )->expiring_until( gmdate( 'Y-m-d', strtotime( '+15 days' ) ), Status::in_progress() ),
				'days'      => $days,
			)
		);
	}
}
