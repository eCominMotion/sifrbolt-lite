<?php
/**
 * License feature resolution utilities.
 *
 * @package SifrBolt
 */

declare(strict_types=1);

namespace SifrBolt\Lite\Infrastructure\License;

/**
 * Determines which premium features are available.
 */
final class LicenseFeatureResolver {

	private const TRANSIENT_KEY    = 'sifrbolt_lite_feature_manifest';
	private const DEFAULT_ENDPOINT = 'https://license.sifrbolt.com/v1/features';

	/**
	 * Plan feature manifest fallback keyed by tier slug.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const FALLBACK_FEATURES = array(
		'spark'   => array(),
		'surge'   => array(
			'autoload_inspector_write',
			'js_scheduler',
			'image_iq',
		),
		'storm'   => array(
			'autoload_inspector_write',
			'js_scheduler',
			'image_iq',
			'index_pack',
			'redis_advanced',
		),
		'citadel' => array(
			'autoload_inspector_write',
			'js_scheduler',
			'image_iq',
			'index_pack',
			'redis_advanced',
		),
	);

	/**
	 * Checks if the current plan allows a feature.
	 *
	 * @param string $feature Feature slug.
	 *
	 * @return bool
	 */
	public function allows( string $feature ): bool {
		$plan     = $this->current_plan();
		$features = $this->features_for_plan( $plan );
		return in_array( $feature, $features, true );
	}

	/**
	 * Clears the cached feature manifest.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		if ( function_exists( 'delete_site_transient' ) ) {
			delete_site_transient( self::TRANSIENT_KEY );
		}
	}

	/**
	 * Resolves the active plan.
	 *
	 * @return string
	 */
	private function current_plan(): string {
		$plan = null;
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'sifrbolt_lite/license_plan', null ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Slash namespace scopes filters per feature.
			if ( is_string( $filtered ) && '' !== $filtered ) {
				$plan = $filtered;
			}
		}

		if ( null === $plan && function_exists( 'get_option' ) ) {
			$stored = get_option( 'sifrbolt_lite_plan' );
			if ( is_string( $stored ) && '' !== $stored ) {
				$plan = $stored;
			}
		}

		if ( ! is_string( $plan ) || '' === $plan ) {
			$plan = 'spark';
		}

		return strtolower( (string) $plan );
	}

	/**
	 * Retrieves feature list for a plan.
	 *
	 * @param string $plan Plan slug.
	 *
	 * @return array<int, string>
	 */
	private function features_for_plan( string $plan ): array {
		$manifest = $this->load_manifest();
		$features = $manifest[ $plan ] ?? array();
		if ( ! is_array( $features ) ) {
			return array();
		}

		$normalised = array();
		foreach ( $features as $feature ) {
			if ( is_string( $feature ) && '' !== $feature ) {
				$normalised[] = $feature;
			}
		}

		return $normalised;
	}

	/**
	 * Loads the manifest from cache or remote.
	 *
	 * @return array<string, array<int, string>>
	 */
	private function load_manifest(): array {
		if ( ! function_exists( 'get_site_transient' ) || ! function_exists( 'set_site_transient' ) ) {
			return self::FALLBACK_FEATURES;
		}

		$cached = get_site_transient( self::TRANSIENT_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$manifest = $this->fetch_manifest();
		set_site_transient( self::TRANSIENT_KEY, $manifest, HOUR_IN_SECONDS );
		return $manifest;
	}

	/**
	 * Fetches the manifest from the remote endpoint.
	 *
	 * @return array<string, array<int, string>>
	 */
	private function fetch_manifest(): array {
		if ( ! function_exists( 'wp_remote_get' ) ) {
			return self::FALLBACK_FEATURES;
		}

		$endpoint = self::DEFAULT_ENDPOINT;
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'sifrbolt_lite/license_manifest_endpoint', $endpoint ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Slash namespace scopes filters per feature.
			if ( is_string( $filtered ) && '' !== $filtered ) {
				$endpoint = $filtered;
			}
		}

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout' => 5,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return self::FALLBACK_FEATURES;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return self::FALLBACK_FEATURES;
		}

		$body    = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return self::FALLBACK_FEATURES;
		}

		$plans = $decoded['manifest']['plans'] ?? array();
		if ( ! is_array( $plans ) ) {
			return self::FALLBACK_FEATURES;
		}

		$map = array();
		foreach ( $plans as $plan => $details ) {
			if ( ! is_array( $details ) ) {
				continue;
			}
			$features = $details['features'] ?? array();
			if ( ! is_array( $features ) ) {
				continue;
			}
			$normalised = array();
			foreach ( $features as $feature ) {
				if ( is_string( $feature ) && '' !== $feature ) {
					$normalised[] = $feature;
				}
			}
			$map[ strtolower( (string) $plan ) ] = $normalised;
		}

		return array() === $map ? self::FALLBACK_FEATURES : $map;
	}
}
