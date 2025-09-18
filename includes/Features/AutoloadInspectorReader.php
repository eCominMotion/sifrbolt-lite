<?php
/**
 * Autoload inspector read operations.
 *
 * @package SifrBolt
 */

declare(strict_types=1);

namespace SifrBolt\Lite\Features;

use wpdb;

/**
 * Provides read-only insights into autoloaded options.
 */
final class AutoloadInspectorReader {

	public const NONCE_ACTION = 'sifrbolt_autoload_action';

	/**
	 * Retrieves the heaviest autoloaded options.
	 *
	 * @param int $limit Number of entries to fetch.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_top_autoloads( int $limit = 20 ): array {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return array();
		}

		$limit = max( 1, $limit );

		/**
		 * Query results.
		 *
		 * @var array<int, array<string, mixed>> $results
		 */
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, LENGTH(option_value) AS size_bytes, autoload FROM {$wpdb->options} WHERE autoload = %s ORDER BY size_bytes DESC LIMIT %d",
				'yes',
				$limit
			),
			ARRAY_A
		);
		if ( ! is_array( $results ) ) {
			$results = array();
		}
		return array_map(
			static function ( array $row ): array {
				return array(
					'name'     => $row['option_name'],
					'size'     => (int) $row['size_bytes'],
					'autoload' => $row['autoload'],
				);
			},
			$results
		);
	}

	/**
	 * Calculates total autoload footprint in bytes.
	 *
	 * @return int
	 */
	public function get_total_autoload_bytes(): int {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = %s",
				'yes'
			)
		);
	}

	/**
	 * Handles import/export requests from admin screens.
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
		if ( '' === $action ) {
			return;
		}

		if ( ! in_array( $action, array( 'export', 'import' ), true ) ) {
			return;
		}

		check_admin_referer( self::NONCE_ACTION );

		if ( 'export' === $action ) {
			$this->stream_backup();
			return;
		}

		$payload = wp_unslash( $_POST['autoload_payload'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified via check_admin_referer().
		$this->restore_from_json( $payload );
	}

	/**
	 * Streams the current autoload snapshot as JSON.
	 *
	 * @return void
	 */
	private function stream_backup(): void {
		$json = $this->generate_backup_json();
		nocache_headers();
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="sifrbolt-autoload-backup.json"' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Generates the backup JSON payload.
	 *
	 * @return string
	 */
	private function generate_backup_json(): string {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return wp_json_encode( array() );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE autoload = %s",
				'yes'
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}
		$payload = array();
		foreach ( $rows as $row ) {
			$payload[] = array(
				'name'     => $row['option_name'],
				'autoload' => $row['autoload'],
				'value'    => base64_encode( (string) $row['option_value'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding payload to preserve binary data in JSON export.
			);
		}

		return wp_json_encode( $payload, JSON_PRETTY_PRINT );
	}

	/**
	 * Restores autoload flags from JSON snapshot.
	 *
	 * @param string $json JSON payload from import.
	 *
	 * @return void
	 */
	private function restore_from_json( string $json ): void {
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			add_settings_error( 'sifrbolt-autoload', 'autoload-import-invalid', esc_html__( 'Invalid JSON payload.', 'sifrbolt' ), 'error' );
			return;
		}

		$imported = 0;
		foreach ( $decoded as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$name      = sanitize_text_field( $item['name'] ?? '' );
			$raw_value = is_string( $item['value'] ?? null ) ? base64_decode( (string) $item['value'], true ) : false; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding plugin-generated JSON export payload.
			$autoload  = 'yes' === ( $item['autoload'] ?? 'yes' );

			if ( '' === $name || false === $raw_value ) {
				continue;
			}

			$value = maybe_unserialize( $raw_value );
			update_option( $name, $value, $autoload );
			++$imported;
		}

		add_settings_error(
			'sifrbolt-autoload',
			'autoload-import-complete',
			sprintf(
				/* translators: %d: number of options restored */
				esc_html__( 'Restored %d autoload options.', 'sifrbolt' ),
				$imported
			),
			'updated'
		);
	}
}
