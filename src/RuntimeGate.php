<?php

namespace Rixetbd\FluxCore;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Exception;

class RuntimeGate
{
    public function getDomain(): string
    {
        return str_replace(["http://", "https://", "www."], "", url('/'));
    }

    public function getSystemAddonCacheKey(string|null $app = 'default'): string
    {
        return str_replace('-', '_', Str::slug('cache_system_addons_for_' . $app . '_' . $this->getDomain()));
    }

    public function getAddonsConfig(): array
    {
        return [
            'admin_panel' => [
                "active" => "0",
                "username" => config(base64_decode('YXBwLmFwcF9lbmdpbmVfdW4=')),
                "purchase_key" => config(base64_decode('YXBwLmFwcF9lbmdpbmVfcGs=')),
                "software_id" => config(base64_decode('YXBwLmFwcF9lbmdpbmVfc2k=')),
                "domain" => $this->getDomain(),
                "software_type" => "product",
            ]
        ];
    }

    public function getCacheTimeoutByDays(int $days = 3): int
    {
        return 60 * 60 * 24 * $days;
    }

    public function getRequestConfig(string|null $app, string|null $username = null, string|null $purchaseKey = null, string|null $softwareId = null, string|null $softwareType = null): array
    {
        
        $config = $this->getAddonsConfig();
        if (!isset($config[$app])) {
            return [
                "active" => false,
                "username" => trim((string)$username),
                "purchase_key" => $purchaseKey,
                "software_id" => $softwareId,
                "domain" => $this->getDomain(),
                "software_type" => $softwareType,
                "expires_at" => null,
                "errors" => ["Invalid addon configuration."],
                ];
        }
                
        $appConfig = $config[$app];
        $cacheKey = $this->getSystemAddonCacheKey(app: $app);
        $cacheTtl = $this->getCacheTimeoutByDays(days: 1);

        try {

            $cachedResponse = Cache::remember($cacheKey, $cacheTtl, function () use ($appConfig, $username, $purchaseKey, $softwareId, $softwareType) {
                return $this->buildRuntimePayload($appConfig, $username, $purchaseKey, $softwareId, $softwareType);
            });


            if (is_array($cachedResponse)) {
                return $cachedResponse;
            }

            Cache::forget($cacheKey);
            $freshResponse = $this->buildRuntimePayload($appConfig, $username, $purchaseKey, $softwareId, $softwareType);
            Cache::put($cacheKey, $freshResponse, $cacheTtl);

            return $freshResponse;
        } catch (Exception $exception) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }

            return [
                "active" => false,
                "username" => trim((string)$username),
                "purchase_key" => $purchaseKey,
                "software_id" => $softwareId,
                "domain" => $this->getDomain(),
                "software_type" => $softwareType,
                "expires_at" => Carbon::now()->addMinutes(20)->toDateTimeString(),
                "errors" => ["Validation server is unreachable."],
            ];
        }
    }

    public function checkActivationCache(string|null $app)
    {
        $config = $this->getAddonsConfig();
        if (!isset($config[$app])) {
            return false;
        }

        $appConfig = $config[$app];

        $response = $this->getRequestConfig(app: $app, username: $appConfig['username'], purchaseKey: $appConfig['purchase_key'], softwareId: $appConfig['software_id'], softwareType: $appConfig['software_type'] ?? base64_decode('cHJvZHVjdA=='));

        $isActive = (bool)($response['active'] ?? false);
        if (!$isActive) {
            return false;
        }

        $expiresAt = $response['expires_at'] ?? null;
        if (empty($expiresAt)) {
            return $isActive;
        }

        try {
            return Carbon::parse($expiresAt)->greaterThan(Carbon::now());
        } catch (Exception $exception) {
            return false;
        }
    }

    private function normalizeActiveStatus(mixed $active): bool
    {
        if (is_bool($active)) {
            return $active;
        }

        if (is_int($active)) {
            return $active === 1;
        }

        if (is_string($active)) {
            $value = strtolower(trim($active));
            if (in_array($value, ['1', 'true', 'yes'], true)) {
                return true;
            }

            $decoded = base64_decode($active, true);
            if ($decoded !== false) {
                $decodedValue = strtolower(trim($decoded));
                return in_array($decodedValue, ['1', 'true', 'yes'], true);
            }
        }

        return false;
    }

    private function buildRuntimePayload(array $appConfig, string|null $username, string|null $purchaseKey, string|null $softwareId, string|null $softwareType): array
    {
        $response = Http::post(base64_decode('aHR0cHM6Ly9yaXhldGJkLmNvbS9hcGkvdjEvY2xpZW50LWxpY2Vuc2UtY2hlY2s='), [
            base64_decode('dXNlcm5hbWU=') => trim($username),
            base64_decode('cHVyY2hhc2Vfa2V5') => $purchaseKey,
            base64_decode('c29mdHdhcmVfaWQ=') => $softwareId,
            base64_decode('ZG9tYWlu') => $this->getDomain(),
            base64_decode('c29mdHdhcmVfdHlwZQ==') => $softwareType,
            base64_decode('aXBfYWRkcmVzcw==') => request()->ip(),
        ])->json();

        $active = $this->normalizeActiveStatus($response['active'] ?? $appConfig['active']);
        $expiresAt = $this->resolveExpiresAt($response);

        if ($active && $expiresAt->lessThanOrEqualTo(Carbon::now())) {
            $active = false;
        }

        $errors = [];
        if (!$active && !empty($response['errors']) && is_array($response['errors'])) {
            $errors = $response['errors'];
        }

        return [
            "active" => $active,
            "username" => trim((string)$username),
            "purchase_key" => $purchaseKey,
            "software_id" => $softwareId,
            "domain" => $this->getDomain(),
            "software_type" => $softwareType,
            "expires_at" => $expiresAt->toDateTimeString(),
            "errors" => $errors,
        ];
    }

    private function resolveExpiresAt(array $response): Carbon
    {
        $accessType = $response[base64_decode('bGljZW5zZV90eXBl')] ?? null;

        if ($accessType === 'lifetime') {
            return Carbon::now()->addDays(60);
        }

        if ($accessType === 'custom') {
            if (!empty($response['expires_at'])) {
                try {
                    return Carbon::parse($response['expires_at']);
                } catch (Exception $exception) {
                    return Carbon::now()->addDays(40);
                }
            }

            return Carbon::now()->addDays(40);
        }

        if (!empty($response['expires_at'])) {
            try {
                return Carbon::parse($response['expires_at']);
            } catch (Exception $exception) {
                return Carbon::now()->addMinutes(20);
            }
        }

        return Carbon::now()->addMinutes(20);
    }

    private function getEnvValue(array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            $value = env($key);
            if ($value !== null && $value !== '') {
                return (string)$value;
            }
        }

        return $default;
    }
}
