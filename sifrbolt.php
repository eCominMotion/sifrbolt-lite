<?php
/**
 * Plugin Name: SifrBolt — Spark (Lite)
 * Plugin URI: https://sifrbolt.com
 * Description: WordPress command deck for SifrBolt Spark (Lite) features.
 * Version: 0.1.0
 * Author: SifrBolt
 * Author URI: https://sifrbolt.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Text Domain: sifrbolt
 */

declare(strict_types=1);

namespace SifrBolt\Lite;

use SifrBolt\Lite\Infrastructure\Plugin;

if (! \defined('ABSPATH')) {
    exit;
}

const VERSION = '0.1.0';

require_once __DIR__ . '/includes/autoload.php';

Plugin::boot(__FILE__, VERSION);
