<?php

namespace App\Api;

use ApiPlatform\Metadata as Api;
use App\Api\State\SystemPreferenceProcessor;
use App\Api\State\SystemPreferenceProvider;

#[Api\ApiResource(
    operations: [
        new Api\GetCollection(
            uriTemplate: '/system-preferences',
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Api\Get(
            uriTemplate: '/system-preferences/{key}',
            uriVariables: ['key'],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Api\Put(
            uriTemplate: '/system-preferences/{key}',
            uriVariables: ['key'],
            security: "is_granted('ROLE_ADMIN')"
        ),
    ],
    provider: SystemPreferenceProvider::class,
    processor: SystemPreferenceProcessor::class,
)]
class SystemPreference
{
    public function __construct(
        public string $key,
        public mixed $value = null,
        public ?string $type = null,
    ) {
    }
}
