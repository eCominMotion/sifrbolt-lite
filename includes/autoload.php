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
		$prefix = __NAMESPACE__ . '\\';
		if ( str_starts_with( $class_name, $prefix ) === false ) {
			return;
		}

		$relative      = substr( $class_name, strlen( $prefix ) );
		$relative_path = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$path          = __DIR__ . DIRECTORY_SEPARATOR . $relative_path . '.php';

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);
