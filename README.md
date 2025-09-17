# SifrBolt — Spark (Lite)

A lightweight WordPress command deck exposing core SifrBolt operational tooling:

- Page cache drop-in (`advanced-cache.php`) with WooCommerce-aware bypasses and CalmSwitch integration.
- Autoload Inspector to review heavy `autoload=yes` options, adjust flags, and backup/restore snapshots.
- Transients Janitor scheduled weekly clean using WordPress APIs.
- Cron Manager to toggle `DISABLE_WP_CRON` through an MU-plugin with guidance for real cron.
- Redis capability hints with upgrade paths to SifrBolt Surge, Storm, and Citadel.
- Telemetry controls (off by default) that send aggregated CWV buckets only when enabled.

## Requirements

- WordPress 6.2+
- PHP 8.1+

## Development

PHPCS baseline lives in `phpcs.xml.dist` and targets the WordPress Coding Standards.
