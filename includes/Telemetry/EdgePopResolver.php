<?php
/**
 * Cloudflare edge POP detection utilities.
 *
 * @package SifrBolt
 */

declare(strict_types=1);

namespace SifrBolt\Lite\Telemetry;

/**
 * Provides coarse Cloudflare edge cluster metadata.
 */
final class EdgePopResolver {

	/**
	 * Map of Cloudflare POP codes to coarse cluster identifiers.
	 *
	 * @var array<string, array{cluster_id: string, region: string}>
	 */
	private const POP_MAP = array(
		'iad' => array(
			'cluster_id' => 'iad-1',
			'region'     => 'us-east',
		),
		'pdx' => array(
			'cluster_id' => 'pdx-1',
			'region'     => 'us-west',
		),
		'lax' => array(
			'cluster_id' => 'lax-1',
			'region'     => 'us-west',
		),
		'dfw' => array(
			'cluster_id' => 'dfw-1',
			'region'     => 'us-central',
		),
		'mia' => array(
			'cluster_id' => 'mia-1',
			'region'     => 'us-south',
		),
		'yyz' => array(
			'cluster_id' => 'yyz-1',
			'region'     => 'north-america',
		),
		'fra' => array(
			'cluster_id' => 'fra-1',
			'region'     => 'eu-central',
		),
		'ams' => array(
			'cluster_id' => 'ams-1',
			'region'     => 'eu-west',
		),
		'dub' => array(
			'cluster_id' => 'dub-1',
			'region'     => 'eu-west',
		),
		'arn' => array(
			'cluster_id' => 'arn-1',
			'region'     => 'eu-north',
		),
		'cdg' => array(
			'cluster_id' => 'cdg-1',
			'region'     => 'eu-west',
		),
		'mad' => array(
			'cluster_id' => 'mad-1',
			'region'     => 'eu-south',
		),
		'sin' => array(
			'cluster_id' => 'sin-1',
			'region'     => 'ap-southeast',
		),
		'nrt' => array(
			'cluster_id' => 'nrt-1',
			'region'     => 'ap-northeast',
		),
		'syd' => array(
			'cluster_id' => 'syd-1',
			'region'     => 'ap-southeast',
		),
		'gru' => array(
			'cluster_id' => 'gru-1',
			'region'     => 'sa-east',
		),
		'eze' => array(
			'cluster_id' => 'eze-1',
			'region'     => 'sa-south',
		),
	);

	/**
	 * Fallback cluster heuristics derived from country codes.
	 *
	 * @var array<string, array{cluster_id: string, region: string}>
	 */
	private const COUNTRY_FALLBACK = array(
		'us' => array(
			'cluster_id' => 'us-unknown',
			'region'     => 'us',
		),
		'ca' => array(
			'cluster_id' => 'ca-unknown',
			'region'     => 'north-america',
		),
		'gb' => array(
			'cluster_id' => 'gb-unknown',
			'region'     => 'eu-west',
		),
		'ie' => array(
			'cluster_id' => 'ie-unknown',
			'region'     => 'eu-west',
		),
		'de' => array(
			'cluster_id' => 'de-unknown',
			'region'     => 'eu-central',
		),
		'fr' => array(
			'cluster_id' => 'fr-unknown',
			'region'     => 'eu-west',
		),
		'es' => array(
			'cluster_id' => 'es-unknown',
			'region'     => 'eu-south',
		),
		'it' => array(
			'cluster_id' => 'it-unknown',
			'region'     => 'eu-south',
		),
		'se' => array(
			'cluster_id' => 'se-unknown',
			'region'     => 'eu-north',
		),
		'br' => array(
			'cluster_id' => 'br-unknown',
			'region'     => 'sa-east',
		),
		'ar' => array(
			'cluster_id' => 'ar-unknown',
			'region'     => 'sa-south',
		),
		'cl' => array(
			'cluster_id' => 'cl-unknown',
			'region'     => 'sa-south',
		),
		'sg' => array(
			'cluster_id' => 'sg-unknown',
			'region'     => 'ap-southeast',
		),
		'au' => array(
			'cluster_id' => 'au-unknown',
			'region'     => 'ap-southeast',
		),
		'jp' => array(
			'cluster_id' => 'jp-unknown',
			'region'     => 'ap-northeast',
		),
		'in' => array(
			'cluster_id' => 'in-unknown',
			'region'     => 'ap-south',
		),
	);

	/**
	 * Static-only utility class.
	 */
	private function __construct() {}

	/**
	 * Resolve edge cluster hints from a server environment.
	 *
	 * @param array<string, mixed> $server Server array (typically $_SERVER).
	 * @return array{cluster_id: string, region: string}|null
	 */
	public static function resolve( array $server ): ?array {
		$pop_code = self::extract_pop_from_ray( $server['HTTP_CF_RAY'] ?? null );
		if ( null !== $pop_code && isset( self::POP_MAP[ $pop_code ] ) ) {
			return self::POP_MAP[ $pop_code ];
		}

		$country = self::normalize_country( $server['HTTP_CF_ORIGIN_COUNTRY'] ?? null );
		if ( '' === $country ) {
			$country = self::normalize_country( $server['HTTP_CF_IPCOUNTRY'] ?? null );
		}

		if ( '' !== $country && isset( self::COUNTRY_FALLBACK[ $country ] ) ) {
			return self::COUNTRY_FALLBACK[ $country ];
		}

		return null;
	}

	/**
	 * Extract lowercase POP code from the Cloudflare Ray header.
	 *
	 * @param mixed $ray_header Raw header value.
	 * @return string|null Three-letter POP code or null when unavailable.
	 */
	private static function extract_pop_from_ray( $ray_header ): ?string {
		if ( ! is_string( $ray_header ) ) {
			return null;
		}

		$ray_header = trim( $ray_header );
		if ( '' === $ray_header ) {
			return null;
		}

		if ( ! preg_match( '/-([A-Za-z]{3})$/', $ray_header, $matches ) ) {
			return null;
		}

		return strtolower( $matches[1] );
	}

	/**
	 * Normalise a Cloudflare country hint.
	 *
	 * @param mixed $country Header value to normalise.
	 * @return string Lowercased country code or empty string when missing.
	 */
	private static function normalize_country( $country ): string {
		if ( ! is_string( $country ) ) {
			return '';
		}

		return strtolower( trim( $country ) );
	}
}
