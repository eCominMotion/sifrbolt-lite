<?php
/**
 * Plugin bootstrapper.
 *
 * @package SifrBolt
 */

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
use SifrBolt\Shared\Blueprints\Journal as BlueprintJournal;

/**
 * Coordinates plugin bootstrapping and lifecycle hooks.
 */
final class Plugin {

	/**
	 * Tracks whether boot() has already executed.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Boots the plugin infrastructure.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @param string $version     Plugin version string.
	 *
	 * @return void
	 */
	public static function boot( string $plugin_file, string $version ): void {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;
		$instance     = new self( $plugin_file, $version );
		$instance->register_hooks();
	}

	// phpcs:disable Squiz.Commenting.VariableComment.Missing -- Typed members are self-descriptive.
	private CacheDropInManager $cache_dropin;

	private CalmSwitch $calm_switch;

	private AutoloadInspectorReader $autoload_reader;

	private AutoloadInspectorWriter $autoload_writer;

	private TransientsJanitor $transients_janitor;

	private CronManager $cron_manager;

	private Telemetry $telemetry;

	private RedisAdvisor $redis_advisor;

	private AdminUi $admin_ui;

	private BlueprintJournal $blueprint_journal;

	private string $plugin_file;

	private string $version;

	private LicenseFeatureResolver $license_features;
	// phpcs:enable Squiz.Commenting.VariableComment.Missing

	/**
	 * Sets up services used across the plugin.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @param string $version     Plugin version string.
	 */
	private function __construct( string $plugin_file, string $version ) {
		$this->plugin_file = $plugin_file;
		$this->version     = $version;

		$base_dir = \dirname( $plugin_file );
		if ( ! \defined( 'SIFRBOLT_LITE_FILE' ) ) {
			\define( 'SIFRBOLT_LITE_FILE', $plugin_file );
		}
		if ( ! \defined( 'SIFRBOLT_LITE_PATH' ) ) {
			\define( 'SIFRBOLT_LITE_PATH', $base_dir );
		}
		if ( ! \defined( 'SIFRBOLT_LITE_URL' ) ) {
			\define( 'SIFRBOLT_LITE_URL', plugins_url( '', $plugin_file ) );
		}

		$this->calm_switch        = new CalmSwitch();
		$this->cache_dropin       = new CacheDropInManager( $this->calm_switch );
		$this->license_features   = new LicenseFeatureResolver();
		$this->autoload_reader    = new AutoloadInspectorReader();
		$this->autoload_writer    = new AutoloadInspectorWriter( $this->license_features );
		$this->transients_janitor = new TransientsJanitor();
		$this->cron_manager       = new CronManager();
		$this->telemetry          = new Telemetry( $this->version );
		$this->redis_advisor      = new RedisAdvisor();
		$this->blueprint_journal  = new BlueprintJournal();
		$this->admin_ui           = new AdminUi(
			$this->autoload_reader,
			$this->autoload_writer,
			$this->transients_janitor,
			$this->cron_manager,
			$this->telemetry,
			$this->calm_switch,
			$this->redis_advisor,
			$this->blueprint_journal
		);
	}

	/**
	 * Registers WordPress hooks for the plugin.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		register_activation_hook( $this->plugin_file, array( $this, 'on_activate' ) );
		register_deactivation_hook( $this->plugin_file, array( $this, 'on_deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
	}

	/**
	 * Performs setup tasks when the plugin activates.
	 *
	 * @internal
	 *
	 * @return void
	 */
	public function on_activate(): void {
		$this->calm_switch->ensure_config_file();
		$this->cache_dropin->install();
		$this->transients_janitor->ensure_schedule();
		$this->cron_manager->ensure_directory();
	}

	/**
	 * Cleans up plugin resources on deactivation.
	 *
	 * @internal
	 *
	 * @return void
	 */
	public function on_deactivate(): void {
		$this->cache_dropin->maybe_remove_on_deactivate();
		$this->transients_janitor->clear_schedule();
		$this->telemetry->clear_schedule();
	}

	/**
	 * Completes runtime registration after plugins load.
	 *
	 * @internal
	 *
	 * @return void
	 */
	public function on_plugins_loaded(): void {
		load_plugin_textdomain( 'sifrbolt', false, basename( SIFRBOLT_LITE_PATH ) . '/languages' );
		$this->calm_switch->ensure_config_file();
		$this->cache_dropin->maybe_refresh();
		$this->transients_janitor->register();
		$this->cron_manager->register();
		$this->telemetry->register();
		$this->admin_ui->register( $this->version );
	}
}
