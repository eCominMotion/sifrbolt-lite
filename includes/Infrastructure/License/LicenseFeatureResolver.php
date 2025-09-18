<?php

declare(strict_types=1);

namespace SifrBolt\Lite\Infrastructure\License;

final class LicenseFeatureResolver
{
    private const TRANSIENT_KEY = 'sifrbolt_lite_feature_manifest';
    private const DEFAULT_ENDPOINT = 'https://license.sifrbolt.com/v1/features';

    /**
     * @var array<string, array<int, string>>
     */
    private const FALLBACK_FEATURES = [
        'spark' => [],
        'surge' => ['autoload_inspector_write'],
        'storm' => ['autoload_inspector_write'],
        'citadel' => ['autoload_inspector_write'],
    ];

    public function allows(string $feature): bool
    {
        $plan = $this->current_plan();
        $features = $this->features_for_plan($plan);
        return in_array($feature, $features, true);
    }

    public function clear_cache(): void
    {
        if (function_exists('delete_site_transient')) {
            delete_site_transient(self::TRANSIENT_KEY);
        }
    }

    private function current_plan(): string
    {
        $plan = null;
        if (function_exists('apply_filters')) {
            $filtered = apply_filters('sifrbolt_lite/license_plan', null);
            if (is_string($filtered) && $filtered !== '') {
                $plan = $filtered;
            }
        }

        if ($plan === null && function_exists('get_option')) {
            $stored = get_option('sifrbolt_lite_plan');
            if (is_string($stored) && $stored !== '') {
                $plan = $stored;
            }
        }

        if (! is_string($plan) || $plan === '') {
            $plan = 'spark';
        }

        return strtolower($plan);
    }

    /**
     * @return array<int, string>
     */
    private function features_for_plan(string $plan): array
    {
        $manifest = $this->load_manifest();
        $features = $manifest[$plan] ?? [];
        if (! is_array($features)) {
            return [];
        }

        $normalised = [];
        foreach ($features as $feature) {
            if (is_string($feature) && $feature !== '') {
                $normalised[] = $feature;
            }
        }

        return $normalised;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function load_manifest(): array
    {
        if (! function_exists('get_site_transient') || ! function_exists('set_site_transient')) {
            return self::FALLBACK_FEATURES;
        }

        $cached = get_site_transient(self::TRANSIENT_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $manifest = $this->fetch_manifest();
        set_site_transient(self::TRANSIENT_KEY, $manifest, HOUR_IN_SECONDS);
        return $manifest;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function fetch_manifest(): array
    {
        if (! function_exists('wp_remote_get')) {
            return self::FALLBACK_FEATURES;
        }

        $endpoint = self::DEFAULT_ENDPOINT;
        if (function_exists('apply_filters')) {
            $filtered = apply_filters('sifrbolt_lite/license_manifest_endpoint', $endpoint);
            if (is_string($filtered) && $filtered !== '') {
                $endpoint = $filtered;
            }
        }

        $response = wp_remote_get($endpoint, ['timeout' => 5, 'headers' => ['Accept' => 'application/json']]);
        if (is_wp_error($response)) {
            return self::FALLBACK_FEATURES;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return self::FALLBACK_FEATURES;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return self::FALLBACK_FEATURES;
        }

        $plans = $decoded['manifest']['plans'] ?? [];
        if (! is_array($plans)) {
            return self::FALLBACK_FEATURES;
        }

        $map = [];
        foreach ($plans as $plan => $details) {
            if (! is_array($details)) {
                continue;
            }
            $features = $details['features'] ?? [];
            if (! is_array($features)) {
                continue;
            }
            $normalised = [];
            foreach ($features as $feature) {
                if (is_string($feature) && $feature !== '') {
                    $normalised[] = $feature;
                }
            }
            $map[strtolower((string) $plan)] = $normalised;
        }

        return $map === [] ? self::FALLBACK_FEATURES : $map;
    }
}
