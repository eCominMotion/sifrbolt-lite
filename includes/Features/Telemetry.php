<?php
/**
 * Telemetry feature manager.
 *
 * @package SifrBolt
 */

declare(strict_types=1);

namespace SifrBolt\Lite\Features;

/**
 * Captures and sends anonymous telemetry snapshots.
 */
final class Telemetry {

	private const OPTION_KEY = 'sifrbolt_lite_telemetry';
	private const BUCKET_KEY = 'sifrbolt_lite_cwv_buckets';
	private const CRON_HOOK  = 'sifrbolt_lite_send_telemetry';
	private const NONCE      = 'sifrbolt_toggle_telemetry';

	/**
	 * Sets up dependencies.
	 *
	 * @param string $version Plugin version identifier.
	 */
	public function __construct( private readonly string $version ) {
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( self::CRON_HOOK, array( $this, 'send_payload' ) );
		add_action( 'admin_post_sifrbolt_toggle_telemetry', array( $this, 'handle_toggle' ) );
		add_action( 'template_redirect', array( $this, 'maybe_capture_request' ) );
	}

	/**
	 * Determines whether telemetry is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$settings = (array) get_option( self::OPTION_KEY, array( 'enabled' => false ) );
		return (bool) ( $settings['enabled'] ?? false );
	}

	/**
	 * Handles the telemetry toggle form submission.
	 *
	 * @return void
	 */
	public function handle_toggle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change telemetry settings.', 'sifrbolt' ) );
		}

		check_admin_referer( self::NONCE );
		$enabled = isset( $_POST['enable_telemetry'] ) && '1' === $_POST['enable_telemetry'];
		update_option( self::OPTION_KEY, array( 'enabled' => $enabled ), true );

		if ( $enabled ) {
			$this->ensure_schedule();
			add_settings_error( 'sifrbolt-telemetry', 'telemetry-enabled', esc_html__( 'Telemetry enabled. Aggregated CWV data will be sent.', 'sifrbolt' ), 'updated' );
		} else {
			$this->clear_schedule();
			add_settings_error( 'sifrbolt-telemetry', 'telemetry-disabled', esc_html__( 'Telemetry disabled. No data will be sent.', 'sifrbolt' ), 'updated' );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'sifrbolt-flight-recorder' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Records a real user measurement bucket.
	 *
	 * @param string $metric Metric identifier.
	 * @param string $bucket Bucket label.
	 *
	 * @return void
	 */
	public function record_sample( string $metric, string $bucket ): void {
		$metric = sanitize_key( $metric );
		$bucket = sanitize_key( $bucket );
		if ( '' === $metric || '' === $bucket ) {
			return;
		}

		$data = (array) get_option( self::BUCKET_KEY, array() );
		if ( ! isset( $data[ $metric ] ) ) {
			$data[ $metric ] = array();
		}
		if ( ! isset( $data[ $metric ][ $bucket ] ) ) {
			$data[ $metric ][ $bucket ] = 0;
		}
		++$data[ $metric ][ $bucket ];
		update_option( self::BUCKET_KEY, $data, false );
	}

	/**
	 * Sends telemetry payload to the SifrBolt API.
	 *
	 * @return void
	 */
	public function send_payload(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$payload = $this->build_payload();
		if ( array() === $payload ) {
			return;
		}

		$body = wp_json_encode( $payload );
		if ( ! is_string( $body ) ) {
			return;
		}

		$signature = hash_hmac( 'sha256', $body, wp_salt( 'nonce' ) );
		$response  = wp_remote_post(
			'https://api.sifrbolt.com/v1/features',
			array(
				'timeout' => 5,
				'headers' => array(
					'Content-Type'         => 'application/json',
					'X-SifrBolt-Signature' => $signature,
					'X-SifrBolt-Agent'     => 'spark-lite/' . $this->version,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[sifrbolt-lite] Telemetry failed: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log failure to aid support.
			return;
		}

		delete_option( self::BUCKET_KEY );
	}

	/**
	 * Schedules the telemetry cron event.
	 *
	 * @return void
	 */
	public function ensure_schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Clears the telemetry cron schedule.
	 *
	 * @return void
	 */
	public function clear_schedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * Retrieves the nonce action name.
	 *
	 * @return string
	 */
	public function get_nonce_action(): string {
		return self::NONCE;
	}

	/**
	 * Provides the captured bucket snapshot.
	 *
	 * @return array<string, array<string, int>>
	 */
	public function get_bucket_snapshot(): array {
		$data = (array) get_option( self::BUCKET_KEY, array() );
		ksort( $data );
		foreach ( $data as $metric => &$buckets ) {
			if ( is_array( $buckets ) ) {
				ksort( $buckets );
			}
		}
		return $data;
	}

	/**
	 * Captures request timing for eligible traffic.
	 *
	 * @return void
	 */
	public function maybe_capture_request(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( is_admin() ) {
			return;
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '';
		if ( 'GET' !== $request_method ) {
			return;
		}

		$start = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true );
		add_action(
			'shutdown',
			function () use ( $start ): void {
				$duration = microtime( true ) - (float) $start;
				$bucket   = 'good';
				if ( $duration > 4.0 ) {
					$bucket = 'poor';
				} elseif ( $duration > 2.5 ) {
					$bucket = 'needs-improvement';
				}
				$this->record_sample( 'lcp', $bucket );
			}
		);
	}

	/**
	 * Builds the payload for the telemetry request.
	 *
	 * @return array<string, mixed>
	 */
	private function build_payload(): array {
		$buckets = $this->get_bucket_snapshot();
		if ( array() === $buckets ) {
			return array();
		}

		return array(
			'site'      => home_url(),
			'version'   => $this->version,
			'metrics'   => $buckets,
			'timestamp' => time(),
		);
	}
}
