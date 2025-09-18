<?php

declare(strict_types=1);

namespace SifrBolt\Lite\Features;

use SifrBolt\Lite\Infrastructure\License\LicenseFeatureResolver;

final class AutoloadInspectorWriter
{
    private const FEATURE_FLAG = 'autoload_inspector_write';

    public function __construct(private readonly LicenseFeatureResolver $features)
    {
    }

    public function can_write(): bool
    {
        return $this->features->allows(self::FEATURE_FLAG);
    }

    public function handle_post(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        $action = sanitize_text_field($_POST['sifrbolt_autoload_action'] ?? '');
        if ($action === '' || ! in_array($action, ['toggle', 'bulk_toggle'], true)) {
            return;
        }

        if (! $this->can_write()) {
            return;
        }

        check_admin_referer(AutoloadInspectorReader::NONCE_ACTION);

        if ($action === 'toggle') {
            $this->handle_single_toggle();
            return;
        }

        $this->handle_bulk_toggle();
    }

    public function toggle(string $option, bool $autoload): void
    {
        if (! $this->can_write()) {
            return;
        }

        $option = sanitize_text_field($option);
        if ($option === '') {
            return;
        }

        $value = get_option($option);
        update_option($option, $value, $autoload);
    }

    /**
     * @param array<string, bool> $changes
     */
    public function bulk_toggle(array $changes): int
    {
        if (! $this->can_write()) {
            return 0;
        }

        $updated = 0;
        foreach ($changes as $option => $flag) {
            $name = sanitize_text_field((string) $option);
            if ($name === '') {
                continue;
            }
            $value = get_option($name);
            update_option($name, $value, (bool) $flag);
            ++$updated;
        }

        return $updated;
    }

    private function handle_single_toggle(): void
    {
        $option = sanitize_text_field($_POST['option_name'] ?? '');
        $autoload = sanitize_text_field($_POST['set_autoload'] ?? 'no');

        if ($option === '') {
            return;
        }

        $this->toggle($option, $autoload === 'yes');
        add_settings_error('sifrbolt-autoload', 'autoload-updated', __('Autoload flag updated.', 'sifrbolt'), 'updated');
    }

    private function handle_bulk_toggle(): void
    {
        $payload = wp_unslash($_POST['bulk_payload'] ?? '');
        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            add_settings_error('sifrbolt-autoload', 'autoload-bulk-invalid', __('Bulk payload invalid.', 'sifrbolt'), 'error');
            return;
        }

        $changes = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = sanitize_text_field($item['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $flag = $item['autoload'] ?? 'no';
            $changes[$name] = $flag === 'yes' || $flag === true || $flag === '1';
        }

        $updated = $this->bulk_toggle($changes);
        if ($updated === 0) {
            return;
        }

        add_settings_error(
            'sifrbolt-autoload',
            'autoload-bulk-updated',
            sprintf(
                /* translators: %d: number of options updated */
                __('Updated autoload flags for %d options.', 'sifrbolt'),
                $updated
            ),
            'updated'
        );
    }
}
