=== SifrBolt — Spark (Lite) ===
Contributors: sifrbolt
Tags: cache, performance, redis, cron, admin, tools
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight WordPress command deck: page cache drop-in, autoload inspector, transients janitor, cron manager, Redis hints — telemetry OFF by default.

== Description ==
SifrBolt — Spark gives operators a clean control deck:
- Page cache drop-in (`advanced-cache.php`) with WooCommerce-aware bypasses and CalmSwitch integration. Opt-in from the Runway screen.
- Autoload Inspector to review heavy `autoload=yes` options, with JSON backup/restore.
- Transients Janitor (weekly clean via WordPress APIs).
- Cron Manager (toggle `DISABLE_WP_CRON` and guidance for real cron).
- Redis capability hints (no client required).
- Telemetry controls that are **OFF by default**. When enabled, only **aggregated Core Web Vitals buckets** are sent; no URLs or content are collected.

This plugin does **not** install software from outside WordPress.org. Upgrade links point to external documentation only.

== Privacy ==
By default, this plugin does **not** transmit any data. If you explicitly enable telemetry, it sends only anonymized, bucketed performance counters (no URLs/HTML/user data). You can disable telemetry at any time from the Command Deck.

== Installation ==
1. Upload the `sifrbolt` folder to `/wp-content/plugins/` or install via “Add New Plugin”.
2. Activate the plugin.
3. (Optional) From **SifrBolt — Spark → Runway**, enable the page cache drop-in (advanced-cache). This writes `wp-content/advanced-cache.php` and can be turned off at any time.

== Frequently Asked Questions ==
= Does this plugin install or update code from outside WordPress.org? =
No. It uses WordPress APIs and offers links to documentation for optional upgrades.

= What happens on deactivation/uninstall? =
If the advanced-cache drop-in was created by SifrBolt, it’s removed on deactivate/uninstall. Scheduled events, mu-plugin bridges, cache directories, and plugin options are also cleaned up.

== Screenshots ==
1. Command Deck overview
2. Autoload Inspector
3. Transients Janitor
4. Cron Manager

== Changelog ==
= 0.1.1 =
* First public submission: cache drop-in (opt-in), autoload inspector, transients janitor, cron manager, Redis hints, telemetry (off by default).

== Upgrade Notice ==
= 0.1.1 =
Initial release.
