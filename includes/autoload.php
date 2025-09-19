<?php
/**
 * Plugin autoloader.
 *
 * @package SifrBolt
 */

declare(strict_types=1);

namespace SifrBolt\Lite;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefixes = array(
			__NAMESPACE__ . '\\'    => __DIR__,
			'SifrBolt\\Shared\\' => dirname( __DIR__ ) . '/shared',
		);

		foreach ( $prefixes as $prefix => $base_dir ) {
			if ( str_starts_with( $class_name, $prefix ) === false ) {
				continue;
			}

			$relative      = substr( $class_name, strlen( $prefix ) );
			$relative_path = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
			$path          = $base_dir . DIRECTORY_SEPARATOR . $relative_path . '.php';

			if ( file_exists( $path ) ) {
				require_once $path;
			}
			return;
		}
	}
);
