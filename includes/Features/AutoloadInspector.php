<?php

declare(strict_types=1);

namespace SifrBolt\Lite\Features;

use wpdb;

final class AutoloadInspector
{
    private const NONCE_ACTION = 'sifrbolt_autoload_action';

    public function get_top_autoloads(int $limit = 20): array
    {
        global $wpdb;
        if (! $wpdb instanceof wpdb) {
            return [];
        }

        $limit = max(1, $limit);
        $table = $wpdb->options;
        $query = $wpdb->prepare(
            "SELECT option_name, LENGTH(option_value) AS size_bytes, autoload FROM {$table} WHERE autoload = 'yes' ORDER BY size_bytes DESC LIMIT %d",
            $limit
        );

        /** @var array<int,array<string,mixed>> $results */
        $results = $wpdb->get_results($query, ARRAY_A) ?: [];
        return array_map(static function (array $row): array {
            return [
                'name' => $row['option_name'],
                'size' => (int) $row['size_bytes'],
                'autoload' => $row['autoload'],
            ];
        }, $results);
    }

    public function get_total_autoload_bytes(): int
    {
        global $wpdb;
        if (! $wpdb instanceof wpdb) {
            return 0;
        }

        $table = $wpdb->options;
        $query = "SELECT SUM(LENGTH(option_value)) FROM {$table} WHERE autoload = 'yes'";
        return (int) $wpdb->get_var($query);
    }

    public function handle_post(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $action = sanitize_text_field($_POST['sifrbolt_autoload_action'] ?? '');
        if ($action === '') {
            return;
        }

        check_admin_referer(self::NONCE_ACTION);

        switch ($action) {
            case 'toggle':
                $option = sanitize_text_field($_POST['option_name'] ?? '');
                $autoload = sanitize_text_field($_POST['set_autoload'] ?? 'no');
                if ($option !== '') {
                    $this->set_autoload($option, $autoload === 'yes');
                    add_settings_error('sifrbolt-autoload', 'autoload-updated', __('Autoload flag updated.', 'sifrbolt'), 'updated');
                }
                break;
            case 'export':
                $this->stream_backup();
                break;
            case 'import':
                $payload = wp_unslash($_POST['autoload_payload'] ?? '');
                $this->restore_from_json($payload);
                break;
        }
    }

    private function set_autoload(string $option_name, bool $autoload): void
    {
        $value = get_option($option_name);
        update_option($option_name, $value, $autoload);
    }

    private function stream_backup(): void
    {
        $json = $this->generate_backup_json();
        nocache_headers();
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="sifrbolt-autoload-backup.json"');
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private function generate_backup_json(): string
    {
        global $wpdb;
        if (! $wpdb instanceof wpdb) {
            return wp_json_encode([]);
        }

        $table = $wpdb->options;
        $rows = $wpdb->get_results("SELECT option_name, option_value, autoload FROM {$table} WHERE autoload = 'yes'", ARRAY_A) ?: [];
        $payload = [];
        foreach ($rows as $row) {
            $payload[] = [
                'name' => $row['option_name'],
                'autoload' => $row['autoload'],
                'value' => base64_encode((string) $row['option_value']),
            ];
        }

        return wp_json_encode($payload, JSON_PRETTY_PRINT);
    }

    private function restore_from_json(string $json): void
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            add_settings_error('sifrbolt-autoload', 'autoload-import-invalid', __('Invalid JSON payload.', 'sifrbolt'), 'error');
            return;
        }

        $imported = 0;
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = sanitize_text_field($item['name'] ?? '');
            $raw_value = is_string($item['value'] ?? null) ? base64_decode((string) $item['value'], true) : false;
            $autoload = ($item['autoload'] ?? 'yes') === 'yes';

            if ($name === '' || $raw_value === false) {
                continue;
            }

            $value = maybe_unserialize($raw_value);
            update_option($name, $value, $autoload);
            ++$imported;
        }

        add_settings_error(
            'sifrbolt-autoload',
            'autoload-import-complete',
            sprintf( /* translators: %d: number of options restored */ __('Restored %d autoload options.', 'sifrbolt'), $imported ),
            'updated'
        );
    }
}
