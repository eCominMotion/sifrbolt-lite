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
	private const OPTION_KEY  = 'sifrbolt_lite_dropin';
	private const OPTION_FLAG = 'enabled';
	private const NONCE       = 'sifrbolt_dropin_toggle';
	private const ACTION      = 'sifrbolt_toggle_dropin';

	/**
	 * Sets dependencies.
	 *
	 * @param CalmSwitch $calm_switch CalmSwitch controller.
	 */
	public function __construct( private readonly CalmSwitch $calm_switch ) {
	}

	/**
	 * Registers admin post handlers.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_toggle' ) );
	}

	/**
	 * Returns the nonce action for toggling the drop-in.
	 *
	 * @return string
	 */
	public function get_nonce_action(): string {
		return self::NONCE;
	}

	/**
	 * Determines whether the drop-in is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$settings = (array) get_option( self::OPTION_KEY, array( self::OPTION_FLAG => false ) );
		return (bool) ( $settings[ self::OPTION_FLAG ] ?? false );
	}

	/**
	 * Enables the drop-in by writing it to disk.
	 *
	 * @return bool True when the drop-in was written successfully.
	 */
	public function install(): bool {
		$this->calm_switch->ensure_config_file();
		$template = $this->get_composed_dropin();
		$target   = $this->get_dropin_path();
		$success  = $this->write_dropin( $template, $target );

		if ( $success ) {
			update_option( self::OPTION_KEY, array( self::OPTION_FLAG => true ), true );
		}

		return $success;
	}

	/**
	 * Disables the drop-in and updates stored state.
	 *
	 * @return void
	 */
	public function disable(): void {
		$this->remove_dropin();
		update_option( self::OPTION_KEY, array( self::OPTION_FLAG => false ), true );
	}

	/**
	 * Handles the admin toggle request for the drop-in.
	 *
	 * @return void
	 */
	public function handle_toggle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the cache drop-in.', 'sifrbolt' ) );
		}

		check_admin_referer( self::NONCE );
		$enable = isset( $_POST['enable_cache_dropin'] ) && '1' === $_POST['enable_cache_dropin']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Request validated via check_admin_referer().

		if ( $enable ) {
			$success = $this->install();
			if ( $success ) {
				add_settings_error( 'sifrbolt-dropin', 'dropin-enabled', esc_html__( 'Page cache drop-in enabled.', 'sifrbolt' ), 'updated' );
			} else {
				update_option( self::OPTION_KEY, array( self::OPTION_FLAG => false ), true );
				add_settings_error( 'sifrbolt-dropin', 'dropin-error', esc_html__( 'Failed to write the page cache drop-in. Check file permissions.', 'sifrbolt' ), 'error' );
			}
		} else {
			$this->disable();
			add_settings_error( 'sifrbolt-dropin', 'dropin-disabled', esc_html__( 'Page cache drop-in disabled.', 'sifrbolt' ), 'updated' );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'sifrbolt-runway' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Installs and refreshes the drop-in if enabled.
	 *
	 * @return void
	 */
	public function maybe_refresh(): void {
		if ( ! $this->is_enabled() ) {
			$this->remove_dropin();
			return;
		}

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
		$this->remove_dropin();
	}

	/**
	 * Provides the target drop-in path.
	 *
	 * @return string
	 */
	public function get_dropin_path(): string {
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
	 * @return bool True when the drop-in was written.
	 */
	private function write_dropin( string $contents, string $target ): bool {
		if ( ! is_dir( WP_CONTENT_DIR ) ) {
			return false;
		}

		$bytes = file_put_contents( $target, $contents, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Drop-in must be written before WP_Filesystem APIs.
		if ( false === $bytes ) {
			error_log( '[sifrbolt-lite] Failed to write advanced-cache drop-in.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log failure to aid troubleshooting.
			return false;
		}

		@chmod( $target, 0644 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Maintain drop-in permissions without depending on WP_Filesystem.
		return true;
	}

	/**
	 * Removes the managed drop-in if present.
	 *
	 * @return void
	 */
	private function remove_dropin(): void {
		$target = $this->get_dropin_path();
		if ( ! file_exists( $target ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists -- Drop-in management requires filesystem checks.
			return;
		}

		$contents = (string) file_get_contents( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Drop-in managed before WP_Filesystem.
		if ( str_contains( $contents, self::SIGNATURE ) ) {
			unlink( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Drop-in removal uses direct filesystem operations.
		}
	}
}
