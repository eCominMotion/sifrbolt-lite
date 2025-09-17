<?php

declare(strict_types=1);

namespace SifrBolt\Lite\Features;

final class Telemetry
{
    private const OPTION_KEY = 'sifrbolt_lite_telemetry';
    private const BUCKET_KEY = 'sifrbolt_lite_cwv_buckets';
    private const CRON_HOOK = 'sifrbolt_lite_send_telemetry';
    private const NONCE = 'sifrbolt_toggle_telemetry';

    public function __construct(private readonly string $version)
    {
    }

    public function register(): void
    {
        add_action(self::CRON_HOOK, [$this, 'send_payload']);
        add_action('admin_post_sifrbolt_toggle_telemetry', [$this, 'handle_toggle']);
        add_action('template_redirect', [$this, 'maybe_capture_request']);
    }

    public function is_enabled(): bool
    {
        $settings = (array) get_option(self::OPTION_KEY, ['enabled' => false]);
        return (bool) ($settings['enabled'] ?? false);
    }

    public function handle_toggle(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have permission to change telemetry settings.', 'sifrbolt'));
        }

        check_admin_referer(self::NONCE);
        $enabled = isset($_POST['enable_telemetry']) && $_POST['enable_telemetry'] === '1';
        update_option(self::OPTION_KEY, ['enabled' => $enabled], true);

        if ($enabled) {
            $this->ensure_schedule();
            add_settings_error('sifrbolt-telemetry', 'telemetry-enabled', __('Telemetry enabled. Aggregated CWV data will be sent.', 'sifrbolt'), 'updated');
        } else {
            $this->clear_schedule();
            add_settings_error('sifrbolt-telemetry', 'telemetry-disabled', __('Telemetry disabled. No data will be sent.', 'sifrbolt'), 'updated');
        }

        wp_safe_redirect(add_query_arg(['page' => 'sifrbolt-flight-recorder'], admin_url('admin.php')));
        exit;
    }

    public function record_sample(string $metric, string $bucket): void
    {
        $metric = sanitize_key($metric);
        $bucket = sanitize_key($bucket);
        if ($metric === '' || $bucket === '') {
            return;
        }

        $data = (array) get_option(self::BUCKET_KEY, []);
        if (! isset($data[$metric])) {
            $data[$metric] = [];
        }
        if (! isset($data[$metric][$bucket])) {
            $data[$metric][$bucket] = 0;
        }
        $data[$metric][$bucket]++;
        update_option(self::BUCKET_KEY, $data, false);
    }

    public function send_payload(): void
    {
        if (! $this->is_enabled()) {
            return;
        }

        $payload = $this->build_payload();
        if ($payload === []) {
            return;
        }

        $body = wp_json_encode($payload);
        if (! is_string($body)) {
            return;
        }

        $signature = hash_hmac('sha256', $body, wp_salt('nonce'));
        $response = wp_remote_post('https://api.sifrbolt.com/v1/features', [
            'timeout' => 5,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-SifrBolt-Signature' => $signature,
                'X-SifrBolt-Agent' => 'spark-lite/' . $this->version,
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            error_log('[sifrbolt-lite] Telemetry failed: ' . $response->get_error_message());
            return;
        }

        delete_option(self::BUCKET_KEY);
    }

    public function ensure_schedule(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public function clear_schedule(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        while ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
            $timestamp = wp_next_scheduled(self::CRON_HOOK);
        }
    }

    public function get_nonce_action(): string
    {
        return self::NONCE;
    }

    public function get_bucket_snapshot(): array
    {
        $data = (array) get_option(self::BUCKET_KEY, []);
        ksort($data);
        foreach ($data as $metric => &$buckets) {
            if (is_array($buckets)) {
                ksort($buckets);
            }
        }
        return $data;
    }

    public function maybe_capture_request(): void
    {
        if (! $this->is_enabled()) {
            return;
        }

        if (is_admin()) {
            return;
        }

        if (! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
            return;
        }

        $start = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        add_action('shutdown', function () use ($start): void {
            $duration = microtime(true) - (float) $start;
            $bucket = 'good';
            if ($duration > 4.0) {
                $bucket = 'poor';
            } elseif ($duration > 2.5) {
                $bucket = 'needs-improvement';
            }
            $this->record_sample('lcp', $bucket);
        });
    }

    private function build_payload(): array
    {
        $buckets = $this->get_bucket_snapshot();
        if ($buckets === []) {
            return [];
        }

        return [
            'site' => home_url(),
            'version' => $this->version,
            'metrics' => $buckets,
            'timestamp' => time(),
        ];
    }
}
