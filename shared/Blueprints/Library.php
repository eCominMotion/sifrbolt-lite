<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * Lightweight blueprint library placeholder for Spark tier.
 *
 * @package SifrBolt\Shared\Blueprints
 */
declare(strict_types=1);

namespace SifrBolt\Shared\Blueprints;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides placeholder blueprint metadata for Spark tier installs.
 */
final class Library {

	/**
	 * Returns a placeholder Storm baseline blueprint.
	 *
	 * @return array<string, mixed>
	 */
	public static function storm_baseline(): array {
		return array(
			'id'          => 'storm-baseline',
			'fleet'       => 'storm',
			'version'     => '0.0.0',
			'description' => self::translate( 'Storm blueprints are available with SifrBolt Storm.' ),
			'note'        => self::translate( 'Upgrade to Storm to access signed blueprint exports and rollback automation.' ),
		);
	}

	/**
	 * Returns the placeholder blueprint JSON payload.
	 *
	 * @return string JSON encoded placeholder payload.
	 */
	public static function storm_baseline_json(): string {
		$data = self::storm_baseline();

		if ( function_exists( 'wp_json_encode' ) ) {
			$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		} else {
			$json = self::fallback_json_encode( $data );
		}

		return is_string( $json ) ? $json : '{}';
	}

	/**
	 * Fallback encoder when WordPress helper is unavailable.
	 *
	 * @param array<string, mixed> $data Data to be encoded.
	 *
	 * @return string|false
	 */
	private static function fallback_json_encode( array $data ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Used only outside WP bootstrap.
		return json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Returns the verifying key hint.
	 *
	 * @return string Translated verifying key hint.
	 */
	public static function verifying_key(): string {
		return self::translate( 'Blueprint verifying keys are provided with a Storm subscription.' );
	}

	/**
	 * Helper that defers to WordPress translation if available.
	 *
	 * @param string $text Text to translate.
	 *
	 * @return string Possibly translated string.
	 */
	private static function translate( string $text ): string {
		if ( function_exists( '__' ) ) {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.NonSingularStringLiteralDomain -- Variable string passed through translations intentionally.
			return __( $text, 'sifrbolt' );
		}

		return $text;
	}
}
