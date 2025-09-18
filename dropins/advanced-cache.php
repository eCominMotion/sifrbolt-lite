<?php
/**
 * SifrBolt — Spark advanced cache drop-in.
 * Signature: SIFRBOLT_SPARK_LITE_DROPIN v1
 *
 * @package SifrBolt
 */

declare(strict_types=1);

if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
	return;
}

if ( function_exists( 'sifrbolt_spark_lite_bootstrap_cache' ) ) {
	return;
}

/**
 * Boots the cache layer drop-in.
 *
 * @return void
 */
function sifrbolt_spark_lite_bootstrap_cache(): void {
	$config      = array( 'enabled' => true );
	$config_path = '%%CONFIG_PATH%%';
	if ( is_readable( $config_path ) ) {
		$loaded = include $config_path;
		if ( is_array( $loaded ) ) {
			$config = array_merge( $config, $loaded );
		}
	}

	if ( empty( $config['enabled'] ) ) {
		return;
	}

	if ( sifrbolt_spark_lite_should_bypass_cache() ) {
		return;
	}

	$cache_dir = __DIR__ . '/cache/sifrbolt';
	if ( ! is_dir( $cache_dir ) && ! mkdir( $cache_dir, 0755, true ) && ! is_dir( $cache_dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Drop-in runs before WP_Filesystem is available.
		return;
	}

	$key        = sifrbolt_spark_lite_cache_key();
	$cache_file = $cache_dir . '/' . $key . '.html';
	$ttl        = 600;

	if ( is_file( $cache_file ) && ( time() - filemtime( $cache_file ) ) < $ttl ) {
		$contents = file_get_contents( $cache_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Drop-in runs before WP_Filesystem is available.
		if ( false !== $contents ) {
			if ( ! headers_sent() ) {
				header( 'X-SifrBolt-Cache: HIT' );
			}
			echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}
	}

	if ( ! headers_sent() ) {
		header( 'X-SifrBolt-Cache: MISS' );
	}

	ob_start(
		static function ( string $buffer ) use ( $cache_file ): string {
			$status = function_exists( 'http_response_code' ) ? http_response_code() : 200;
			if ( 200 === $status && ! headers_sent() ) {
				$dir = dirname( $cache_file );
				if ( ! is_dir( $dir ) ) {
					mkdir( $dir, 0755, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Drop-in runs before WP_Filesystem is available.
				}
				file_put_contents( $cache_file, $buffer, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Drop-in runs before WP_Filesystem is available.
				header( 'X-SifrBolt-Cache: STORE' );
			}
			return $buffer;
		}
	);
}

/**
 * Determines if caching should be bypassed for current request.
 *
 * @return bool
 */
function sifrbolt_spark_lite_should_bypass_cache(): bool {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return true;
	}

	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
		return true;
	}

	$uri          = $_SERVER['REQUEST_URI'] ?? '';
	$query_string = $_SERVER['QUERY_STRING'] ?? '';

	if ( '' !== $query_string ) {
		$query_args = array();
		parse_str( $query_string, $query_args );
		$nocache_keys = array( 'preview', 'customize_changeset_uuid', 'wc-ajax', 'doing_wp_cron', 'sifrbolt_nocache' );
		foreach ( $nocache_keys as $key ) {
			if ( array_key_exists( $key, $query_args ) ) {
				return true;
			}
		}
		if ( isset( $query_args['add-to-cart'] ) ) {
			return true;
		}
	}

	$woo_paths = array( '/cart', '/checkout', '/my-account' );
	foreach ( $woo_paths as $path ) {
		if ( false !== stripos( $uri, $path ) ) {
			return true;
		}
	}

	$bypass_paths = array( '/wp-login.php', '/wp-admin' );
	foreach ( $bypass_paths as $path ) {
		if ( false !== stripos( $uri, $path ) ) {
			return true;
		}
	}

	foreach ( $_COOKIE as $name => $value ) {
		if ( str_starts_with( $name, 'wordpress_logged_in_' ) ) {
			return true;
		}
		$woo_cookies = array( 'woocommerce_items_in_cart', 'woocommerce_cart_hash' );
		if ( in_array( $name, $woo_cookies, true ) ) {
			return true;
		}
		if ( str_starts_with( $name, 'wp_woocommerce_session_' ) ) {
			return true;
		}
		if ( 'sifrbolt_cache_bypass' === $name ) {
			return true;
		}
	}

	return false;
}

/**
 * Builds a cache key for the current request.
 *
 * @return string
 */
function sifrbolt_spark_lite_cache_key(): string {
	$host             = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$uri              = $_SERVER['REQUEST_URI'] ?? '/';
	$https            = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== strtolower( (string) $_SERVER['HTTPS'] ) ) ? 'https' : 'http';
	$relevant_cookies = array();

	foreach ( $_COOKIE as $name => $value ) {
		if ( str_starts_with( $name, 'woocommerce_cart_hash' ) || str_starts_with( $name, 'wp_woocommerce_session_' ) ) {
			$relevant_cookies[ $name ] = $value;
		}
		if ( str_starts_with( $name, 'wordpress_logged_in_' ) ) {
			$relevant_cookies[ $name ] = $value;
		}
	}

	ksort( $relevant_cookies );
	$cookie_string = http_build_query( $relevant_cookies, '', '&' );

	return md5( $https . '://' . $host . $uri . '|' . $cookie_string );
}

sifrbolt_spark_lite_bootstrap_cache();
