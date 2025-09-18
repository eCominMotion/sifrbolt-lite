<?php
/**
 * Cache drop-in manager.
 *
 * @package SifrBolt
 */

declare(strict_types=1);

namespace SifrBolt\Lite\Features;

/**
 * Installs and maintains the advanced-cache drop-in.
 */
final class CacheDropInManager {

	private const DROPIN_PATH = 'advanced-cache.php';
	private const SIGNATURE   = 'SIFRBOLT_SPARK_LITE_DROPIN v1';

	/**
	 * Sets dependencies.
	 *
	 * @param CalmSwitch $calm_switch CalmSwitch controller.
	 */
	public function __construct( private readonly CalmSwitch $calm_switch ) {
	}

	/**
	 * Installs and refreshes the drop-in.
	 *
	 * @return void
	 */
	public function install(): void {
		$this->calm_switch->ensure_config_file();
		$this->maybe_refresh();
	}

	/**
	 * Refreshes the drop-in when stale or missing signature.
	 *
	 * @return void
	 */
	public function maybe_refresh(): void {
		$template = $this->get_composed_dropin();
		$target   = $this->get_dropin_path();

		$target_exists     = file_exists( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists -- Drop-in management requires filesystem checks.
		$existing_contents = $target_exists ? (string) file_get_contents( $target ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Drop-in managed before WP_Filesystem.

		if ( ! $target_exists || ! str_contains( $existing_contents, self::SIGNATURE ) ) {
			$this->write_dropin( $template, $target );
			return;
		}

		$current_hash = hash( 'sha256', $existing_contents );
		$new_hash     = hash( 'sha256', $template );
		if ( $current_hash !== $new_hash ) {
			$this->write_dropin( $template, $target );
		}
	}

	/**
	 * Removes the drop-in when the plugin deactivates.
	 *
	 * @return void
	 */
	public function maybe_remove_on_deactivate(): void {
		$target        = $this->get_dropin_path();
		$target_exists = file_exists( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists -- Drop-in management requires filesystem checks.
		if ( $target_exists && str_contains( (string) file_get_contents( $target ), self::SIGNATURE ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Drop-in managed before WP_Filesystem.
			unlink( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Drop-in removal uses direct filesystem operations.
		}
	}

	/**
	 * Provides the target drop-in path.
	 *
	 * @return string
	 */
	private function get_dropin_path(): string {
		return WP_CONTENT_DIR . '/' . self::DROPIN_PATH;
	}

	/**
	 * Returns the bundled drop-in template path.
	 *
	 * @return string
	 */
	private function get_template_path(): string {
		return SIFRBOLT_LITE_PATH . '/dropins/advanced-cache.php';
	}

	/**
	 * Generates the drop-in contents with config substitutions.
	 *
	 * @return string
	 */
	private function get_composed_dropin(): string {
		$template    = (string) file_get_contents( $this->get_template_path() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Template bundled within plugin.
		$config_path = $this->calm_switch->get_config_path();
		return str_replace( '%%CONFIG_PATH%%', addslashes( $config_path ), $template );
	}

	/**
	 * Writes the drop-in to disk.
	 *
	 * @param string $contents Drop-in contents.
	 * @param string $target   Destination path.
	 *
	 * @return void
	 */
	private function write_dropin( string $contents, string $target ): void {
		if ( ! is_dir( WP_CONTENT_DIR ) ) {
			return;
		}

		$bytes = file_put_contents( $target, $contents, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Drop-in must be written before WP_Filesystem APIs.
		if ( false === $bytes ) {
			error_log( '[sifrbolt-lite] Failed to write advanced-cache drop-in.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log failure to aid troubleshooting.
			return;
		}

		@chmod( $target, 0644 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Maintain drop-in permissions without depending on WP_Filesystem.
	}
}
