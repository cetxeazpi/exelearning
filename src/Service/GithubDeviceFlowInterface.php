<?php

namespace App\Service;

interface GithubDeviceFlowInterface
{
    /**
     * Start the device flow and return device_code, user_code, verification_uri, interval, etc.
     */
    public function start(string $scope = 'read:user public_repo'): array;

    /**
     * Poll for token; may return authorization_pending, slow_down, expired_token; success returns access_token.
     */
    public function poll(string $deviceCode): array;
}
