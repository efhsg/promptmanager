<?php

namespace app\components;

use yii\web\Request;

/**
 * Request component that correctly detects HTTPS when behind Tailscale Serve.
 *
 * Tailscale Serve terminates HTTPS and forwards to the application over HTTP,
 * but may not send X-Forwarded-Proto headers. This class detects the secure
 * connection by checking if the original request port suggests HTTPS (e.g., 8443).
 */
class TailscaleAwareRequest extends Request
{
    /**
     * @var int[] Ports that indicate the original connection was HTTPS.
     * Default includes 443 (standard HTTPS) and 8443 (common alternative).
     */
    public array $httpsIndicatorPorts = [443, 8443];

    /**
     * @inheritdoc
     *
     * Extends the default HTTPS detection to also check if the forwarded port
     * or Host header port is in the list of known HTTPS ports.
     */
    public function getIsSecureConnection(): bool
    {
        if (parent::getIsSecureConnection()) {
            return true;
        }

        $port = $this->detectOriginalPort();
        if ($port !== null && in_array($port, $this->httpsIndicatorPorts, true)) {
            return true;
        }

        return false;
    }

    /**
     * Detect the original request port from various sources.
     */
    private function detectOriginalPort(): ?int
    {
        $headers = $this->getHeaders();

        // Check X-Forwarded-Port header first
        $forwardedPort = $headers->get('X-Forwarded-Port');
        if ($forwardedPort !== null) {
            return (int) $forwardedPort;
        }

        // Check Host header for port (e.g., "100.107.169.66:8443")
        $host = $headers->get('Host');
        if ($host !== null && preg_match('/:(\d+)$/', $host, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
