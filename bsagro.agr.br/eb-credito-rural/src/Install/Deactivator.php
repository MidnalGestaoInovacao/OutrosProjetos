<?php
/**
 * Rotina de desativação (não remove dados).
 *
 * @package EBCR
 */

namespace EBCR\Install;

use EBCR\Cron\Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Desagenda cron.
 */
final class Deactivator {

	/**
	 * Desativação.
	 *
	 * @return void
	 */
	public static function deactivate() {
		Scheduler::unschedule();
		// Na reativação, a regra /area-do-cliente/ é recalculada e as regras renovadas.
		delete_option( \EBCR\Frontend\ClientArea::SIG_OPT );
		flush_rewrite_rules();
	}
}
