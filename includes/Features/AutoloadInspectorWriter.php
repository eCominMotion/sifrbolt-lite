<?php
/**
 * Autoload inspector write operations.
 *
 * @package SifrBolt
 */

declare(strict_types=1);

namespace SifrBolt\Lite\Features;

use SifrBolt\Lite\Infrastructure\License\LicenseFeatureResolver;

/**
 * Applies autoload flag adjustments when permitted.
 */
final class AutoloadInspectorWriter {

	private const FEATURE_FLAG = 'autoload_inspector_write';

	/**
	 * Sets dependencies.
	 *
	 * @param LicenseFeatureResolver $features License feature resolver.
	 */
	public function __construct( private readonly LicenseFeatureResolver $features ) {
	}

	/**
	 * Checks whether writes are allowed for the current license.
	 *
	 * @return bool
	 */
	public function can_write(): bool {
		return $this->features->allows( self::FEATURE_FLAG );
	}

	/**
	 * Handles admin post submissions.
	 *
	 * @return void
	 */
	public function handle_post(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '';
		if ( 'POST' !== $request_method ) {
			return;
		}

		$action = sanitize_text_field( $_POST['sifrbolt_autoload_action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified via check_admin_referer().
		if ( '' === $action || ! in_array( $action, array( 'toggle', 'bulk_toggle' ), true ) ) {
			return;
		}

		if ( ! $this->can_write() ) {
			return;
		}

		check_admin_referer( AutoloadInspectorReader::NONCE_ACTION );

		if ( 'toggle' === $action ) {
			$this->handle_single_toggle();
			return;
		}

		$this->handle_bulk_toggle();
	}

	/**
	 * Toggles autoload flag for a single option.
	 *
	 * @param string $option   Option name.
	 * @param bool   $autoload Autoload value.
	 *
	 * @return void
	 */
	public function toggle( string $option, bool $autoload ): void {
		if ( ! $this->can_write() ) {
			return;
		}

		$option = sanitize_text_field( $option );
		if ( '' === $option ) {
			return;
		}

		$value = get_option( $option );
		update_option( $option, $value, $autoload );
	}

	/**
	 * Applies bulk autoload flag changes.
	 *
	 * @param array<string, bool> $changes Autoload flags keyed by option name.
	 *
	 * @return int Number of options updated.
	 */
	public function bulk_toggle( array $changes ): int {
		if ( ! $this->can_write() ) {
			return 0;
		}

		$updated = 0;
		foreach ( $changes as $option => $flag ) {
			$name = sanitize_text_field( (string) $option );
			if ( '' === $name ) {
				continue;
			}
			$value = get_option( $name );
			update_option( $name, $value, (bool) $flag );
			++$updated;
		}

		return $updated;
	}

	/**
	 * Handles single option toggle submissions.
	 *
	 * @return void
	 */
	private function handle_single_toggle(): void {
		$option   = sanitize_text_field( $_POST['option_name'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified via check_admin_referer().
		$autoload = sanitize_text_field( $_POST['set_autoload'] ?? 'no' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified via check_admin_referer().

		if ( '' === $option ) {
			return;
		}

		$this->toggle( $option, 'yes' === $autoload );
		add_settings_error( 'sifrbolt-autoload', 'autoload-updated', esc_html__( 'Autoload flag updated.', 'sifrbolt' ), 'updated' );
	}

	/**
	 * Handles bulk toggle submissions.
	 *
	 * @return void
	 */
	private function handle_bulk_toggle(): void {
		$payload = wp_unslash( $_POST['bulk_payload'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified via check_admin_referer().
		$decoded = json_decode( $payload, true );
		if ( ! is_array( $decoded ) ) {
			add_settings_error( 'sifrbolt-autoload', 'autoload-bulk-invalid', esc_html__( 'Bulk payload invalid.', 'sifrbolt' ), 'error' );
			return;
		}

		$changes = array();
		foreach ( $decoded as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$name = sanitize_text_field( $item['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$flag             = $item['autoload'] ?? 'no';
			$changes[ $name ] = 'yes' === $flag || true === $flag || '1' === $flag;
		}

		$updated = $this->bulk_toggle( $changes );
		if ( 0 === $updated ) {
			return;
		}

		add_settings_error(
			'sifrbolt-autoload',
			'autoload-bulk-updated',
			sprintf(
				/* translators: %d: number of options updated */
				esc_html__( 'Updated autoload flags for %d options.', 'sifrbolt' ),
				$updated
			),
			'updated'
		);
	}
}
