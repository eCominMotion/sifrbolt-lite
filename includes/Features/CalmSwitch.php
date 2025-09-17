<?php

declare(strict_types=1);

namespace SifrBolt\Lite\Features;

final class CalmSwitch
{
    private const OPTION_KEY = 'sifrbolt_lite_state';
    private const CALM_KEY = 'calm_mode';
    private const NONCE = 'sifrbolt_calm_toggle';

    public function is_calm(): bool
    {
        $settings = (array) get_option(self::OPTION_KEY, []);
        return (bool) ($settings[self::CALM_KEY] ?? false);
    }

    public function enable_calm(): void
    {
        $this->persist(true);
    }

    public function disable_calm(): void
    {
        $this->persist(false);
    }

    public function toggle(): bool
    {
        $new_state = ! $this->is_calm();
        $this->persist($new_state);
        return $new_state;
    }

    public function handle_toggle(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have permission to toggle CalmSwitch.', 'sifrbolt'));
        }

        check_admin_referer(self::NONCE);
        $calm = $this->toggle();
        $message = $calm ? __('CalmSwitch engaged. Runtime transforms paused.', 'sifrbolt') : __('CalmSwitch disengaged. Runtime transforms restored.', 'sifrbolt');
        add_settings_error('sifrbolt-calm', 'calm-switch', $message, 'updated');
        wp_safe_redirect(add_query_arg(['page' => 'sifrbolt-runway'], admin_url('admin.php')));
        exit;
    }

    public function get_nonce_action(): string
    {
        return self::NONCE;
    }

    public function get_config_path(): string
    {
        return WP_CONTENT_DIR . '/sifrbolt-cache-config.php';
    }

    public function ensure_config_file(): void
    {
        $this->write_config(! $this->is_calm());
    }

    private function persist(bool $calm): void
    {
        $settings = (array) get_option(self::OPTION_KEY, []);
        $settings[self::CALM_KEY] = $calm;
        update_option(self::OPTION_KEY, $settings, true);
        $this->write_config(! $calm);
    }

    private function write_config(bool $cache_enabled): void
    {
        $config = '<?php return [' . "'enabled' => " . ($cache_enabled ? 'true' : 'false') . '];';
        $bytes = file_put_contents($this->get_config_path(), $config, LOCK_EX);
        if ($bytes === false) {
            error_log('[sifrbolt-lite] Failed to write cache config file.');
        }
    }
}
