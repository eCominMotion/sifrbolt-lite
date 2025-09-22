# SifrBolt — Spark

A lightweight WordPress command deck exposing core SifrBolt operational tooling:

- Page cache drop-in (`advanced-cache.php`) with WooCommerce-aware bypasses and CalmSwitch integration.
- Autoload Inspector to review heavy `autoload=yes` options, adjust flags, and backup/restore snapshots.
- Transients Janitor scheduled weekly clean using WordPress APIs.
- Cron Manager to toggle `DISABLE_WP_CRON` through an MU-plugin with guidance for real cron.
- Redis capability hints with upgrade paths to SifrBolt — Surge Pack, SifrBolt — Storm, and SifrBolt — Citadel.
- Telemetry controls (off by default) that send aggregated CWV buckets only when enabled.

## Requirements

- WordPress 6.2+
- PHP 8.1+

## Development

PHPCS baseline lives in `phpcs.xml.dist` and targets the WordPress Coding Standards.

To lint locally:

1. Install the toolchain with `composer install -d tools/phpcs --no-interaction --no-progress`.
2. Run `tools/phpcs/vendor/bin/phpcs --standard=plugins/sifrbolt-lite/phpcs.xml.dist plugins/sifrbolt-lite`.
3. Auto-fix safe sniffs with `tools/phpcs/vendor/bin/phpcbf --standard=plugins/sifrbolt-lite/phpcs.xml.dist plugins/sifrbolt-lite`.

## Publishing

This plugin is mirrored to https://github.com/eCominMotion/sifrbolt-lite by the `Sync SifrBolt Spark` GitHub Action.

Manual mirroring is rarely needed, but `./scripts/sync-sifrbolt-lite.sh` remains available for maintenance tasks and can target a custom remote URL when required.
