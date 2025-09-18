<?php
/**
 * Transients Janitor scheduler.
 *
 * @package SifrBolt
 */

declare(strict_types=1);

namespace SifrBolt\Lite\Features;

/**
 * Orchestrates periodic cleanup of expired transients.
 */
final class TransientsJanitor {

	private const HOOK  = 'sifrbolt_transients_janitor_event';
	private const NONCE = 'sifrbolt_transients_janitor';

	/**
	 * Hooks the janitor into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'maybe_add_weekly_schedule' ) );
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'admin_post_sifrbolt_run_janitor', array( $this, 'handle_manual_run' ) );
	}

	/**
	 * Ensures the recurring schedule exists.
	 *
	 * @return void
	 */
	public function ensure_schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::HOOK );
		}
	}

	/**
	 * Clears the recurring schedule.
	 *
	 * @return void
	 */
	public function clear_schedule(): void {
		$timestamp = wp_next_scheduled( self::HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
			$timestamp = wp_next_scheduled( self::HOOK );
		}
	}

	/**
	 * Adds a weekly schedule if missing.
	 *
	 * @param array<string, array<string, mixed>> $schedules Cron schedules.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function maybe_add_weekly_schedule( array $schedules ): array {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => 7 * DAY_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'sifrbolt' ),
			);
		}

		return $schedules;
	}

	/**
	 * Executes transient cleanup routines.
	 *
	 * @return void
	 */
	public function run(): void {
		if ( function_exists( 'delete_expired_transients' ) ) {
			delete_expired_transients();
		}

		if ( function_exists( 'delete_expired_site_transients' ) ) {
			delete_expired_site_transients();
		}
	}

	/**
	 * Handles the manual cleanup request.
	 *
	 * @return void
	 */
	public function handle_manual_run(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run the janitor.', 'sifrbolt' ) );
		}

		check_admin_referer( self::NONCE );
		$this->run();
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'sifrbolt-black-box',
					'janitor' => 'done',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Returns the nonce action string.
	 *
	 * @return string
	 */
	public function get_nonce_action(): string {
		return self::NONCE;
	}
}
