<?php

if (!function_exists('checkActivationCache')) {
    /**
     * Check the activation cache for a given app.
     *
     * @param string|null $app The application identifier
     * @return bool
     */
    function checkActivationCache(string|null $app): bool
    {
        $runtimeGate = new \Rixetbd\FluxCore\RuntimeGate();
        return $runtimeGate->checkActivationCache($app);
    }
}
