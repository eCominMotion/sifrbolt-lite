<?php
/**
 * Cron management utilities.
 *
 * @package SifrBolt
 */

declare(strict_types=1);

namespace SifrBolt\Lite\Features;

/**
 * Handles the opt-in cron bridge workflow.
 */
final class CronManager {

	private const NONCE = 'sifrbolt_cron_toggle';

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_sifrbolt_cron_toggle', array( $this, 'handle_toggle' ) );
	}

	/**
	 * Ensures the mu-plugins directory exists.
	 *
	 * @return void
	 */
	public function ensure_directory(): void {
		wp_mkdir_p( $this->get_mu_dir() );
	}

	/**
	 * Checks whether WP-Cron is disabled via bridge or constant.
	 *
	 * @return bool
	 */
	public function is_wp_cron_disabled(): bool {
		if ( defined( 'DISABLE_WP_CRON' ) ) {
			return (bool) DISABLE_WP_CRON;
		}

		return is_readable( $this->get_mu_plugin_path() );
	}

	/**
	 * Handles the cron toggle request.
	 *
	 * @return void
	 */
	public function handle_toggle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change cron settings.', 'sifrbolt' ) );
		}

		check_admin_referer( self::NONCE );
		$disable = isset( $_POST['disable_wp_cron'] ) && '1' === $_POST['disable_wp_cron']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Request validated via check_admin_referer().

		if ( defined( 'DISABLE_WP_CRON' ) ) {
			add_settings_error(
				'sifrbolt-cron',
				'cron-hardcoded',
				esc_html__( 'DISABLE_WP_CRON is already defined in wp-config.php. Update it there to make changes.', 'sifrbolt' ),
				'error'
			);
			wp_safe_redirect( add_query_arg( array( 'page' => 'sifrbolt-black-box' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( $disable ) {
			$this->write_mu_plugin();
			add_settings_error( 'sifrbolt-cron', 'cron-disabled', esc_html__( 'WP-Cron disabled. Configure a real cron job for wp-cron.php.', 'sifrbolt' ), 'updated' );
		} else {
			$this->remove_mu_plugin();
			add_settings_error( 'sifrbolt-cron', 'cron-enabled', esc_html__( 'WP-Cron re-enabled.', 'sifrbolt' ), 'updated' );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'sifrbolt-black-box' ), admin_url( 'admin.php' ) ) );
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

	/**
	 * Provides the MU plugin path.
	 *
	 * @return string
	 */
	public function get_mu_plugin_path(): string {
		return $this->get_mu_dir() . '/sifrbolt-cron-bridge.php';
	}

	/**
	 * Gets the mu-plugins directory.
	 *
	 * @return string
	 */
	private function get_mu_dir(): string {
		return WP_CONTENT_DIR . '/mu-plugins';
	}

	/**
	 * Writes the cron bridge MU plugin.
	 *
	 * @return void
	 */
	private function write_mu_plugin(): void {
		$contents = <<<'PHP'
<?php
/**
 * Plugin Name: SifrBolt Cron Bridge
 * Description: Managed by SifrBolt — Spark to control WP-Cron.
 */
if (! defined('DISABLE_WP_CRON')) {
    define('DISABLE_WP_CRON', true);
}
PHP;
		file_put_contents( $this->get_mu_plugin_path(), $contents, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- MU plugin must be written directly.
	}

	/**
	 * Removes the cron bridge MU plugin.
	 *
	 * @return void
	 */
	private function remove_mu_plugin(): void {
		$path = $this->get_mu_plugin_path();
		if ( is_file( $path ) ) {
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- MU plugin removal requires filesystem access.
		}
	}
}
