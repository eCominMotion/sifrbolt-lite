<?php

declare(strict_types=1);

namespace SifrBolt\Lite\Admin;

use SifrBolt\Lite\Features\AutoloadInspectorReader;
use SifrBolt\Lite\Features\AutoloadInspectorWriter;
use SifrBolt\Lite\Features\CalmSwitch;
use SifrBolt\Lite\Features\CronManager;
use SifrBolt\Lite\Features\RedisAdvisor;
use SifrBolt\Lite\Features\Telemetry;
use SifrBolt\Lite\Features\TransientsJanitor;

final class AdminUi
{

    public function __construct(
        private readonly AutoloadInspectorReader $autoload_reader,
        private readonly AutoloadInspectorWriter $autoload_writer,
        private readonly TransientsJanitor $transients_janitor,
        private readonly CronManager $cron_manager,
        private readonly Telemetry $telemetry,
        private readonly CalmSwitch $calm_switch,
        private readonly RedisAdvisor $redis_advisor
    ) {
    }

    private string $version = '0.0.0';

    /**
     * Canonical upgrade consoles mapped to the tier keys.
     */
    private function upgrade_consoles(): array
    {
        return [
            [
                'key' => 'surge',
                'title' => __('Surge', 'sifrbolt'),
                'description' => __('Edge-accelerated caching and CDN orchestration for scale.', 'sifrbolt'),
                'url' => 'https://console.sifrbolt.com/surge',
            ],
            [
                'key' => 'storm',
                'title' => __('Storm', 'sifrbolt'),
                'description' => __('Automation suite for flash sales, bursts, and traffic spikes.', 'sifrbolt'),
                'url' => 'https://console.sifrbolt.com/storm',
            ],
            [
                'key' => 'citadel',
                'title' => __('Citadel', 'sifrbolt'),
                'description' => __('Enterprise-grade resilience, isolation, and compliance tooling.', 'sifrbolt'),
                'url' => 'https://console.sifrbolt.com/citadel',
            ],
        ];
    }

    public function register(string $version): void
    {
        $this->version = $version;
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_notices', [$this, 'render_notices']);
        add_action('admin_post_sifrbolt_calm_toggle', [$this->calm_switch, 'handle_toggle']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('Command Deck', 'sifrbolt'),
            __('SifrBolt — Spark', 'sifrbolt'),
            'manage_options',
            'sifrbolt-command-deck',
            [$this, 'render_command_deck'],
            'dashicons-airplane'
        );

        add_submenu_page('sifrbolt-command-deck', __('Command Deck', 'sifrbolt'), __('Command Deck', 'sifrbolt'), 'manage_options', 'sifrbolt-command-deck', [$this, 'render_command_deck']);
        add_submenu_page('sifrbolt-command-deck', __('Runway', 'sifrbolt'), __('Runway', 'sifrbolt'), 'manage_options', 'sifrbolt-runway', [$this, 'render_runway']);
        add_submenu_page('sifrbolt-command-deck', __('Citadel Wall', 'sifrbolt'), __('Citadel Wall', 'sifrbolt'), 'manage_options', 'sifrbolt-citadel-wall', [$this, 'render_citadel_wall']);
        add_submenu_page('sifrbolt-command-deck', __('Black Box', 'sifrbolt'), __('Black Box', 'sifrbolt'), 'manage_options', 'sifrbolt-black-box', [$this, 'render_black_box']);
        add_submenu_page('sifrbolt-command-deck', __('Flight Recorder', 'sifrbolt'), __('Flight Recorder', 'sifrbolt'), 'manage_options', 'sifrbolt-flight-recorder', [$this, 'render_flight_recorder']);
    }

    public function render_notices(): void
    {
        settings_errors('sifrbolt-calm');
        settings_errors('sifrbolt-autoload');
        settings_errors('sifrbolt-cron');
        settings_errors('sifrbolt-telemetry');
    }

    public function render_command_deck(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap sifrbolt-command-deck">
            <h1><?php esc_html_e('SifrBolt — Spark Command Deck', 'sifrbolt'); ?></h1>
            <p><?php echo esc_html(sprintf(__('Version %s ready on deck. Choose a console to deploy deeper capabilities.', 'sifrbolt'), $this->version)); ?></p>
            <p style="opacity:0.75;">Spark → Surge → Storm → Citadel</p>
            <div class="sifrbolt-upgrade-grid" style="display:flex;gap:16px;flex-wrap:wrap;">
                <?php foreach ($this->upgrade_consoles() as $card) : ?>
                    <div data-tier="<?php echo esc_attr($card['key']); ?>" style="background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:16px;flex:1 1 200px;">
                        <h2><?php echo esc_html($card['title']); ?></h2>
                        <p><?php echo esc_html($card['description']); ?></p>
                        <a class="button button-primary" href="<?php echo esc_url($card['url']); ?>" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Open Console', 'sifrbolt'); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (has_action('sifrbolt/command_deck/widgets')) : ?>
                <div class="sifrbolt-command-deck-widgets" style="margin-top:24px;display:flex;gap:16px;flex-wrap:wrap;">
                    <?php do_action('sifrbolt/command_deck/widgets'); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_runway(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $calm = $this->calm_switch->is_calm();
        $dropin_path = WP_CONTENT_DIR . '/advanced-cache.php';
        $dropin_exists = file_exists($dropin_path);
        $redis = $this->redis_advisor->detect();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Runway', 'sifrbolt'); ?></h1>
            <p><?php esc_html_e('Manage cache posture and CalmSwitch status.', 'sifrbolt'); ?></p>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th><?php esc_html_e('Page Cache Drop-in', 'sifrbolt'); ?></th>
                        <td>
                            <?php echo $dropin_exists ? esc_html__('Installed', 'sifrbolt') : esc_html__('Missing', 'sifrbolt'); ?>
                            <code><?php echo esc_html($dropin_path); ?></code>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('CalmSwitch State', 'sifrbolt'); ?></th>
                        <td><?php echo $calm ? esc_html__('Engaged — runtime transforms paused', 'sifrbolt') : esc_html__('Disengaged — runtime transforms active', 'sifrbolt'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Redis Detection', 'sifrbolt'); ?></th>
                        <td>
                            <?php if ($redis['dropin']) : ?>
                                <?php esc_html_e('Object cache drop-in detected.', 'sifrbolt'); ?>
                            <?php elseif ($redis['extension']) : ?>
                                <?php esc_html_e('Redis extension present. Install an object cache drop-in to leverage it.', 'sifrbolt'); ?>
                            <?php else : ?>
                                <?php esc_html_e('No Redis signals detected. Consider installing the Redis Object Cache plugin.', 'sifrbolt'); ?>
                                <a href="<?php echo esc_url($this->redis_advisor->get_recommendation_url()); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Learn more', 'sifrbolt'); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:20px;">
                <?php wp_nonce_field($this->calm_switch->get_nonce_action()); ?>
                <input type="hidden" name="action" value="sifrbolt_calm_toggle" />
                <input type="submit" class="button button-<?php echo $calm ? 'secondary' : 'primary'; ?>" value="<?php echo $calm ? esc_attr__('Disengage CalmSwitch', 'sifrbolt') : esc_attr__('Engage CalmSwitch', 'sifrbolt'); ?>" />
            </form>
        </div>
        <?php
    }

    public function render_citadel_wall(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $this->autoload_reader->handle_post();
        $this->autoload_writer->handle_post();

        $write_enabled = $this->autoload_writer->can_write();
        $top = $this->autoload_reader->get_top_autoloads();
        $total_bytes = $this->autoload_reader->get_total_autoload_bytes();
        $nonce_action = AutoloadInspectorReader::NONCE_ACTION;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Citadel Wall — Autoload Inspector', 'sifrbolt'); ?></h1>
            <p><?php esc_html_e('Identify heavy autoloaded options and rebalance memory pressure.', 'sifrbolt'); ?></p>
            <p><?php echo esc_html(sprintf(__('Total autoload footprint: %s KB', 'sifrbolt'), number_format_i18n($total_bytes / 1024, 2))); ?></p>

            <?php if (! $write_enabled) : ?>
                <p class="description" style="margin-top:1em;margin-bottom:1em;"><?php esc_html_e('Write operations require Surge. Upgrade to change autoload flags directly.', 'sifrbolt'); ?></p>
            <?php endif; ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Option', 'sifrbolt'); ?></th>
                        <th><?php esc_html_e('Size (KB)', 'sifrbolt'); ?></th>
                        <th><?php esc_html_e('Autoload', 'sifrbolt'); ?></th>
                        <th><?php esc_html_e('Actions', 'sifrbolt'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($top as $row) : ?>
                    <tr>
                        <td><code><?php echo esc_html($row['name']); ?></code></td>
                        <td><?php echo esc_html(number_format_i18n($row['size'] / 1024, 2)); ?></td>
                        <td><?php echo esc_html($row['autoload']); ?></td>
                        <td>
                            <?php if ($write_enabled) : ?>
                                <form method="post" action="">
                                    <?php wp_nonce_field($nonce_action); ?>
                                    <input type="hidden" name="sifrbolt_autoload_action" value="toggle" />
                                    <input type="hidden" name="option_name" value="<?php echo esc_attr($row['name']); ?>" />
                                    <input type="hidden" name="set_autoload" value="<?php echo $row['autoload'] === 'yes' ? 'no' : 'yes'; ?>" />
                                    <input type="submit" class="button" value="<?php echo $row['autoload'] === 'yes' ? esc_attr__('Set to no', 'sifrbolt') : esc_attr__('Set to yes', 'sifrbolt'); ?>" />
                                </form>
                            <?php else : ?>
                                <span style="opacity:0.65;"><?php esc_html_e('Requires Surge', 'sifrbolt'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e('Backup & Restore', 'sifrbolt'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field($nonce_action); ?>
                <input type="hidden" name="sifrbolt_autoload_action" value="export" />
                <p><input type="submit" class="button" value="<?php esc_attr_e('Download Autoload Snapshot', 'sifrbolt'); ?>" /></p>
            </form>

            <form method="post" action="">
                <?php wp_nonce_field($nonce_action); ?>
                <input type="hidden" name="sifrbolt_autoload_action" value="import" />
                <p>
                    <textarea name="autoload_payload" rows="6" style="width:100%;"></textarea>
                </p>
                <p><input type="submit" class="button button-primary" value="<?php esc_attr_e('Restore from JSON', 'sifrbolt'); ?>" /></p>
            </form>
        </div>
        <?php
    }

    public function render_black_box(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $cron_disabled = $this->cron_manager->is_wp_cron_disabled();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Black Box — Operations Console', 'sifrbolt'); ?></h1>
            <p><?php esc_html_e('Control WordPress cron behavior and schedule janitorial passes.', 'sifrbolt'); ?></p>

            <h2><?php esc_html_e('WP-Cron Switch', 'sifrbolt'); ?></h2>
            <p><?php echo $cron_disabled ? esc_html__('WP-Cron is currently disabled. Ensure a real cron job calls wp-cron.php.', 'sifrbolt') : esc_html__('WP-Cron is enabled and running within requests.', 'sifrbolt'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field($this->cron_manager->get_nonce_action()); ?>
                <input type="hidden" name="action" value="sifrbolt_cron_toggle" />
                <input type="hidden" name="disable_wp_cron" value="<?php echo $cron_disabled ? '0' : '1'; ?>" />
                <input type="submit" class="button button-primary" value="<?php echo $cron_disabled ? esc_attr__('Re-enable WP-Cron', 'sifrbolt') : esc_attr__('Disable WP-Cron', 'sifrbolt'); ?>" />
            </form>
            <p><?php esc_html_e('Guidance: configure a system cron to hit /wp-cron.php every minute when WP-Cron is disabled.', 'sifrbolt'); ?></p>
            <pre style="background:#f6f7f7;padding:10px;border-radius:4px;">* * * * * curl -s https://example.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1</pre>

            <h2><?php esc_html_e('Transients Janitor', 'sifrbolt'); ?></h2>
            <p><?php esc_html_e('Runs weekly to clear expired transients via the WordPress API.', 'sifrbolt'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field($this->transients_janitor->get_nonce_action()); ?>
                <input type="hidden" name="action" value="sifrbolt_run_janitor" />
                <input type="submit" class="button" value="<?php esc_attr_e('Run Now', 'sifrbolt'); ?>" />
            </form>
        </div>
        <?php
    }

    public function render_flight_recorder(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $enabled = $this->telemetry->is_enabled();
        $buckets = $this->telemetry->get_bucket_snapshot();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Flight Recorder — Telemetry', 'sifrbolt'); ?></h1>
            <p><?php esc_html_e('Telemetry is off by default. When enabled, only aggregated Core Web Vital buckets are sent to SifrBolt.', 'sifrbolt'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:20px;">
                <?php wp_nonce_field($this->telemetry->get_nonce_action()); ?>
                <input type="hidden" name="action" value="sifrbolt_toggle_telemetry" />
                <input type="hidden" name="enable_telemetry" value="<?php echo $enabled ? '0' : '1'; ?>" />
                <input type="submit" class="button button-<?php echo $enabled ? 'secondary' : 'primary'; ?>" value="<?php echo $enabled ? esc_attr__('Disable Telemetry', 'sifrbolt') : esc_attr__('Enable Telemetry', 'sifrbolt'); ?>" />
            </form>

            <h2><?php esc_html_e('CWV Buckets', 'sifrbolt'); ?></h2>
            <?php if ($buckets === []) : ?>
                <p><?php esc_html_e('No samples captured yet.', 'sifrbolt'); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Metric', 'sifrbolt'); ?></th>
                            <th><?php esc_html_e('Bucket', 'sifrbolt'); ?></th>
                            <th><?php esc_html_e('Count', 'sifrbolt'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buckets as $metric => $groups) : ?>
                            <?php foreach ($groups as $bucket => $count) : ?>
                                <tr>
                                    <td><?php echo esc_html($metric); ?></td>
                                    <td><?php echo esc_html($bucket); ?></td>
                                    <td><?php echo esc_html((string) $count); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
