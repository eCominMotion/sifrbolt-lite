<?php

declare(strict_types=1);

namespace SifrBolt\Lite\Infrastructure;

use SifrBolt\Lite\Admin\AdminUi;
use SifrBolt\Lite\Features\AutoloadInspectorReader;
use SifrBolt\Lite\Features\AutoloadInspectorWriter;
use SifrBolt\Lite\Features\CacheDropInManager;
use SifrBolt\Lite\Features\CalmSwitch;
use SifrBolt\Lite\Features\CronManager;
use SifrBolt\Lite\Features\RedisAdvisor;
use SifrBolt\Lite\Features\Telemetry;
use SifrBolt\Lite\Features\TransientsJanitor;
use SifrBolt\Lite\Infrastructure\License\LicenseFeatureResolver;

final class Plugin
{
    private static bool $booted = false;

    public static function boot(string $plugin_file, string $version): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;
        $instance = new self($plugin_file, $version);
        $instance->register_hooks();
    }

    private CacheDropInManager $cache_dropin;

    private CalmSwitch $calm_switch;

    private AutoloadInspectorReader $autoload_reader;

    private AutoloadInspectorWriter $autoload_writer;

    private TransientsJanitor $transients_janitor;

    private CronManager $cron_manager;

    private Telemetry $telemetry;

    private RedisAdvisor $redis_advisor;

    private AdminUi $admin_ui;

    private string $plugin_file;

    private string $version;

    private LicenseFeatureResolver $license_features;

    private function __construct(string $plugin_file, string $version)
    {
        $this->plugin_file = $plugin_file;
        $this->version = $version;

        $base_dir = \dirname($plugin_file);
        if (! \defined('SIFRBOLT_LITE_FILE')) {
            \define('SIFRBOLT_LITE_FILE', $plugin_file);
        }
        if (! \defined('SIFRBOLT_LITE_PATH')) {
            \define('SIFRBOLT_LITE_PATH', $base_dir);
        }
        if (! \defined('SIFRBOLT_LITE_URL')) {
            \define('SIFRBOLT_LITE_URL', plugins_url('', $plugin_file));
        }

        $this->calm_switch = new CalmSwitch();
        $this->cache_dropin = new CacheDropInManager($this->calm_switch);
        $this->license_features = new LicenseFeatureResolver();
        $this->autoload_reader = new AutoloadInspectorReader();
        $this->autoload_writer = new AutoloadInspectorWriter($this->license_features);
        $this->transients_janitor = new TransientsJanitor();
        $this->cron_manager = new CronManager();
        $this->telemetry = new Telemetry($this->version);
        $this->redis_advisor = new RedisAdvisor();
        $this->admin_ui = new AdminUi(
            $this->autoload_reader,
            $this->autoload_writer,
            $this->transients_janitor,
            $this->cron_manager,
            $this->telemetry,
            $this->calm_switch,
            $this->redis_advisor
        );
    }

    private function register_hooks(): void
    {
        register_activation_hook($this->plugin_file, [$this, 'on_activate']);
        register_deactivation_hook($this->plugin_file, [$this, 'on_deactivate']);

        add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
    }

    /**
     * @internal
     */
    public function on_activate(): void
    {
        $this->calm_switch->ensure_config_file();
        $this->cache_dropin->install();
        $this->transients_janitor->ensure_schedule();
        $this->cron_manager->ensure_directory();
    }

    /**
     * @internal
     */
    public function on_deactivate(): void
    {
        $this->cache_dropin->maybe_remove_on_deactivate();
        $this->transients_janitor->clear_schedule();
        $this->telemetry->clear_schedule();
    }

    /**
     * @internal
     */
    public function on_plugins_loaded(): void
    {
        load_plugin_textdomain('sifrbolt', false, basename(SIFRBOLT_LITE_PATH) . '/languages');
        $this->calm_switch->ensure_config_file();
        $this->cache_dropin->maybe_refresh();
        $this->transients_janitor->register();
        $this->cron_manager->register();
        $this->telemetry->register();
        $this->admin_ui->register($this->version);
    }
}
