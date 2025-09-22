<?php
/**
 * Lightweight blueprint library placeholder for Spark tier.
 */

declare(strict_types=1);

namespace SifrBolt\Shared\Blueprints;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

final class Library {

	/**
	 * Returns a placeholder Storm baseline blueprint.
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
	 */
	public static function storm_baseline_json(): string {
		$data = self::storm_baseline();

		if ( function_exists( 'wp_json_encode' ) ) {
			$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		} else {
			$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		}

		return is_string( $json ) ? $json : '{}';
	}

	/**
	 * Returns the verifying key hint.
	 */
	public static function verifying_key(): string {
		return self::translate( 'Blueprint verifying keys are provided with a Storm subscription.' );
	}

	/**
	 * Helper that defers to WordPress translation if available.
	 */
	private static function translate( string $text ): string {
		if ( function_exists( '__' ) ) {
			return __( $text, 'sifrbolt' );
		}

		return $text;
	}
}
