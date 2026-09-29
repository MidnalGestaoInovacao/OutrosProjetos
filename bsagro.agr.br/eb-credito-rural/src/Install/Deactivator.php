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
		flush_rewrite_rules();
	}
}
