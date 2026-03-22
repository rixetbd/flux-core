<?php

namespace Rixetbd\FluxCore;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class CachedRouteReader
{
    protected $cacheKey = 'routes_list';
    protected $cacheTTL = 259200; // 3 days

    public function getRoutes($refresh = false)
    {
        if ($refresh) {
            Cache::forget($this->cacheKey);
        }

        return Cache::remember($this->cacheKey, $this->cacheTTL, function () {
            Artisan::call('route:list', ['--json' => true]);
            return json_decode(Artisan::output(), true);
        });
    }

    public function sendRoutes()
    {
        $refresh = request()->query('refresh', false);
        return $this->getRoutes($refresh);
    }
}
