<?php
/**
 * Tarefas agendadas: lembretes, certidões a vencer, retenção, limpezas.
 *
 * @package EBCR
 */

namespace EBCR\Cron;

use EBCR\Database\AuditLogRepository;
use EBCR\Database\DocumentRepository;
use EBCR\Database\DocumentRequestRepository;
use EBCR\Database\MailQueueRepository;
use EBCR\Database\SubmissionRepository;
use EBCR\Domain\Status;
use EBCR\Forms\DocumentMatrix;
use EBCR\Mail\Notifier;
use EBCR\Security\Retention;
use EBCR\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_daily (diário) e ebcr_mail_tick (a cada 5 min).
 */
final class Scheduler {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- 5 minutos é adequado para a fila de e-mails.
		add_action( 'ebcr_daily', array( __CLASS__, 'daily' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 30 );
	}

	/**
	 * Intervalo de 5 minutos.
	 *
	 * @param array $schedules Agendas.
	 * @return array
	 */
	public function schedules( $schedules ) {
		$schedules['ebcr_5min'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'A cada 5 minutos (EB Crédito Rural)', 'eb-credito-rural' ),
		);
		return $schedules;
	}

	/**
	 * Agenda (idempotente).
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( 'ebcr_daily' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 06:00' ), 'daily', 'ebcr_daily' );
		}
		if ( ! wp_next_scheduled( 'ebcr_mail_tick' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'ebcr_5min', 'ebcr_mail_tick' );
		}
	}

	/**
	 * Desagenda.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( 'ebcr_daily' );
		wp_clear_scheduled_hook( 'ebcr_mail_tick' );
		wp_clear_scheduled_hook( 'ebcr_process_mail_queue' );
	}

	/**
	 * Rotina diária.
	 *
	 * @return array Resumo.
	 */
	public static function daily() {
		$summary = array(
			'reminders'    => self::pending_reminders(),
			'certificates' => self::certificates_expiring(),
			'anonymized'   => Retention::run(),
			'audit_purged' => ( new AuditLogRepository() )->purge_older_than( max( 30, Options::int( 'audit_retention_days' ) ) ),
			'mail_purged'  => ( new MailQueueRepository() )->purge_sent( 30 ),
		);
		update_option( 'ebcr_last_daily', array_merge( $summary, array( 'at' => current_time( 'mysql', true ) ) ), false );
		return $summary;
	}

	/**
	 * Lembretes de pendências sem resposta há X dias; cancelamento automático opcional.
	 *
	 * @return int
	 */
	public static function pending_reminders() {
		$days = Options::int( 'pending_reminder_days' );
		if ( $days < 1 ) {
			return 0;
		}
		$requests = new DocumentRequestRepository();
		$subs     = new SubmissionRepository();
		$groups   = array();
		foreach ( $requests->stale_open( $days ) as $r ) {
			$groups[ (int) $r['submission_id'] ][] = $r;
		}
		$n      = 0;
		$cancel = Options::int( 'pending_cancel_days' );
		foreach ( $groups as $sid => $list ) {
			$s = $subs->find( $sid );
			if ( ! $s || Status::is_final( $s['status'] ) ) {
				continue;
			}
			$oldest = min(
				array_map(
					static function ( $r ) {
						return strtotime( $r['requested_at'] . ' UTC' );
					},
					$list
				)
			);
			if ( $cancel > 0 && $oldest < time() - $cancel * DAY_IN_SECONDS && Status::can_transition( $s['status'], Status::CANCELLED ) ) {
				( new SubmissionRepository() )->update( $sid, array( 'status' => Status::CANCELLED ) );
				( new \EBCR\Database\StatusHistoryRepository() )->add( $sid, $s['status'], Status::CANCELLED, null, __( 'Cancelamento automático: pendências sem resposta.', 'eb-credito-rural' ), __( 'Sua solicitação foi cancelada por falta de resposta às pendências. Você pode iniciar uma nova quando quiser.', 'eb-credito-rural' ) );
				Notifier::status_changed( $subs->find( $sid ), $s['status'], Status::CANCELLED, __( 'Cancelada por falta de resposta às pendências.', 'eb-credito-rural' ), '' );
				continue;
			}
			Notifier::pending_reminder( $s, $list );
			foreach ( $list as $r ) {
				$requests->mark_reminded( (int) $r['id'] );
			}
			++$n;
		}
		return $n;
	}

	/**
	 * Alerta de certidões que vencem nos próximos 15 dias.
	 *
	 * @return int
	 */
	public static function certificates_expiring() {
		$docs  = ( new DocumentRepository() )->expiring_until( gmdate( 'Y-m-d', strtotime( '+15 days' ) ), Status::in_progress() );
		$lines = array();
		foreach ( $docs as $d ) {
			$lines[] = sprintf( '• %s — %s (%s): vence em %s', $d['protocol'] ? $d['protocol'] : $d['submission_public_id'], DocumentMatrix::label( $d['doc_type'] ), $d['original_name'], wp_date( get_option( 'date_format' ), strtotime( $d['expires_at'] ) ) );
		}
		if ( $lines ) {
			Notifier::certificates_expiring( $lines );
		}
		return count( $lines );
	}
}
