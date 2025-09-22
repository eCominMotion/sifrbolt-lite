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
			__NAMESPACE__ . '\\' => __DIR__,
			'SifrBolt\\Shared\\' => dirname( __DIR__ ) . '/shared',
		);

		foreach ( $prefixes as $prefix => $base_dir ) {
			if ( str_starts_with( $class_name, $prefix ) === false ) {
				continue;
			}

			$relative = substr( $class_name, strlen( $prefix ) );
			$segments = explode( '\\', $relative );
			$class    = array_pop( $segments );
			$subdir   = empty( $segments ) ? '' : implode( DIRECTORY_SEPARATOR, $segments ) . DIRECTORY_SEPARATOR;

			$filename_variants = array(
				$subdir . $class . '.php',
				$subdir . strtolower( $class ) . '.php',
				$subdir . 'class-' . to_kebab_case( $class ) . '.php',
			);

			foreach ( $filename_variants as $relative_path ) {
				$path = $base_dir . DIRECTORY_SEPARATOR . $relative_path;
				if ( file_exists( $path ) ) {
					require_once $path;
					return;
				}
			}
			return;
		}
	}
);

/**
 * Converts a CamelCase class name into kebab-case.
 *
 * @param string $class_name Class segment to convert.
 *
 * @return string Kebab case slug.
 */
function to_kebab_case( string $class_name ): string {
	$with_hyphen = preg_replace( '/(?<!^)[A-Z]/', '-$0', $class_name );
	if ( ! is_string( $with_hyphen ) ) {
		return strtolower( $class_name );
	}

	return strtolower( $with_hyphen );
}
