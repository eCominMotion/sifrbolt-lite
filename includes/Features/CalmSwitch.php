<?php
/**
 * CalmSwitch feature toggles.
 *
 * @package SifrBolt
 */

declare(strict_types=1);

namespace SifrBolt\Lite\Features;

/**
 * Coordinates runtime toggle for cache writes.
 */
final class CalmSwitch {

	private const OPTION_KEY = 'sifrbolt_lite_state';
	private const CALM_KEY   = 'calm_mode';
	private const NONCE      = 'sifrbolt_calm_toggle';

	/**
	 * Reports whether CalmSwitch is active.
	 *
	 * @return bool
	 */
	public function is_calm(): bool {
		$settings = (array) get_option( self::OPTION_KEY, array() );
		return (bool) ( $settings[ self::CALM_KEY ] ?? false );
	}

	/**
	 * Enables CalmSwitch mode.
	 *
	 * @return void
	 */
	public function enable_calm(): void {
		$this->persist( true );
	}

	/**
	 * Disables CalmSwitch mode.
	 *
	 * @return void
	 */
	public function disable_calm(): void {
		$this->persist( false );
	}

	/**
	 * Toggles CalmSwitch state.
	 *
	 * @return bool True when CalmSwitch is engaged.
	 */
	public function toggle(): bool {
		$new_state = ! $this->is_calm();
		$this->persist( $new_state );
		return $new_state;
	}

	/**
	 * Handles CalmSwitch toggle requests.
	 *
	 * @return void
	 */
	public function handle_toggle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to toggle CalmSwitch.', 'sifrbolt' ) );
		}

		check_admin_referer( self::NONCE );
		$calm    = $this->toggle();
		$message = $calm ? esc_html__( 'CalmSwitch engaged. Runtime transforms paused.', 'sifrbolt' ) : esc_html__( 'CalmSwitch disengaged. Runtime transforms restored.', 'sifrbolt' );
		add_settings_error( 'sifrbolt-calm', 'calm-switch', $message, 'updated' );
		wp_safe_redirect( add_query_arg( array( 'page' => 'sifrbolt-runway' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Returns the nonce action used for toggling.
	 *
	 * @return string
	 */
	public function get_nonce_action(): string {
		return self::NONCE;
	}

	/**
	 * Retrieves the cache config file path.
	 *
	 * @return string
	 */
	public function get_config_path(): string {
		return WP_CONTENT_DIR . '/sifrbolt-cache-config.php';
	}

	/**
	 * Ensures the config file reflects the active state.
	 *
	 * @return void
	 */
	public function ensure_config_file(): void {
		$this->write_config( ! $this->is_calm() );
	}

	/**
	 * Persists the CalmSwitch state.
	 *
	 * @param bool $calm Whether CalmSwitch is engaged.
	 *
	 * @return void
	 */
	private function persist( bool $calm ): void {
		$settings                   = (array) get_option( self::OPTION_KEY, array() );
		$settings[ self::CALM_KEY ] = $calm;
		update_option( self::OPTION_KEY, $settings, true );
		$this->write_config( ! $calm );
	}

	/**
	 * Writes the cache configuration file.
	 *
	 * @param bool $cache_enabled Whether cache should remain enabled.
	 *
	 * @return void
	 */
	private function write_config( bool $cache_enabled ): void {
		$config = '<?php return [' . "'enabled' => " . ( $cache_enabled ? 'true' : 'false' ) . '];';
		$bytes  = file_put_contents( $this->get_config_path(), $config, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Configuration file must be written before WordPress filesystem APIs are loaded.
		if ( false === $bytes ) {
			error_log( '[sifrbolt-lite] Failed to write cache config file.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log failure for site operators.
		}
	}
}
