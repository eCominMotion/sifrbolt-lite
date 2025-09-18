<?php
/**
 * Redis advisor helpers.
 *
 * @package SifrBolt
 */

declare(strict_types=1);

namespace SifrBolt\Lite\Features;

/**
 * Provides guidance about Redis availability.
 */
final class RedisAdvisor {

	/**
	 * Detects Redis integration status.
	 *
	 * @return array<string, bool>
	 */
	public function detect(): array {
		$extension = extension_loaded( 'redis' ) || class_exists( '\\Redis' );
		$dropin    = defined( 'WP_REDIS_PLUGIN_VERSION' ) || file_exists( WP_CONTENT_DIR . '/object-cache.php' );

		return array(
			'extension' => $extension,
			'dropin'    => $dropin,
		);
	}

	/**
	 * Returns the recommended plugin link.
	 *
	 * @return string
	 */
	public function get_recommendation_url(): string {
		return 'https://wordpress.org/plugins/redis-cache/';
	}
}
