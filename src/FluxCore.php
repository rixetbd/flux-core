<?php

namespace Rixetbd\FluxCore;

use Illuminate\Session\SessionManager;
use Illuminate\Config\Repository as Config;

/**
 * Class FluxCore
 *
 * Main FluxCore class for managing runtime access and core functionality.
 *
 * @package Rixetbd\FluxCore
 */
class FluxCore
{
    /**
     * The session manager instance.
     *
     * @var SessionManager
     */
    protected $session;

    /**
     * The config repository instance.
     *
     * @var Config
     */
    protected $config;

    /**
     * Create a new FluxCore instance.
     *
     * @param SessionManager $session
     * @param Config $config
     */
    public function __construct(SessionManager $session, Config $config)
    {
        $this->session = $session;
        $this->config = $config;
    }

    /**
     * Get the session manager instance.
     *
     * @return SessionManager
     */
    public function getSession(): SessionManager
    {
        return $this->session;
    }

    /**
     * Get the config repository instance.
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }
}
