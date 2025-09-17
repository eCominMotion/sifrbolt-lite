<?php

declare(strict_types=1);

namespace SifrBolt\Lite\Features;

final class CronManager
{
    private const NONCE = 'sifrbolt_cron_toggle';

    public function register(): void
    {
        add_action('admin_post_sifrbolt_cron_toggle', [$this, 'handle_toggle']);
    }

    public function ensure_directory(): void
    {
        wp_mkdir_p($this->get_mu_dir());
    }

    public function is_wp_cron_disabled(): bool
    {
        if (defined('DISABLE_WP_CRON')) {
            return (bool) DISABLE_WP_CRON;
        }

        return is_readable($this->get_mu_plugin_path());
    }

    public function handle_toggle(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have permission to change cron settings.', 'sifrbolt'));
        }

        check_admin_referer(self::NONCE);
        $disable = isset($_POST['disable_wp_cron']) && $_POST['disable_wp_cron'] === '1';

        if (defined('DISABLE_WP_CRON')) {
            add_settings_error(
                'sifrbolt-cron',
                'cron-hardcoded',
                __('DISABLE_WP_CRON is already defined in wp-config.php. Update it there to make changes.', 'sifrbolt'),
                'error'
            );
            wp_safe_redirect(add_query_arg(['page' => 'sifrbolt-black-box'], admin_url('admin.php')));
            exit;
        }

        if ($disable) {
            $this->write_mu_plugin();
            add_settings_error('sifrbolt-cron', 'cron-disabled', __('WP-Cron disabled. Configure a real cron job for wp-cron.php.', 'sifrbolt'), 'updated');
        } else {
            $this->remove_mu_plugin();
            add_settings_error('sifrbolt-cron', 'cron-enabled', __('WP-Cron re-enabled.', 'sifrbolt'), 'updated');
        }

        wp_safe_redirect(add_query_arg(['page' => 'sifrbolt-black-box'], admin_url('admin.php')));
        exit;
    }

    public function get_nonce_action(): string
    {
        return self::NONCE;
    }

    public function get_mu_plugin_path(): string
    {
        return $this->get_mu_dir() . '/sifrbolt-cron-bridge.php';
    }

    private function get_mu_dir(): string
    {
        return WP_CONTENT_DIR . '/mu-plugins';
    }

    private function write_mu_plugin(): void
    {
        $contents = <<<'PHP'
<?php
/**
 * Plugin Name: SifrBolt Cron Bridge
 * Description: Managed by SifrBolt — Spark (Lite) to control WP-Cron.
 */
if (! defined('DISABLE_WP_CRON')) {
    define('DISABLE_WP_CRON', true);
}
PHP;
        file_put_contents($this->get_mu_plugin_path(), $contents, LOCK_EX);
    }

    private function remove_mu_plugin(): void
    {
        $path = $this->get_mu_plugin_path();
        if (is_file($path)) {
            unlink($path);
        }
    }
}
