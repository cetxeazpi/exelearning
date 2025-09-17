<?php

namespace App\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\SystemPreference;
use App\Config\SystemPrefRegistry;
use App\Service\net\exelearning\Service\SystemPreferencesService;

class SystemPreferenceProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly SystemPrefRegistry $registry,
        private readonly SystemPreferencesService $prefs,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof SystemPreference) {
            return $data;
        }
        // If key not in payload, use URI
        if (!$data->key && isset($uriVariables['key'])) {
            $data->key = (string) $uriVariables['key'];
        }
        $def = $this->registry->get($data->key);
        if (!$def) {
            // ignore unknown
            return $data;
        }

        // Use provided type or default to def type
        $type = $data->type ?: ($def['type'] ?? 'string');
        $this->prefs->set($data->key, $data->value, $type, 'api');

        $val = $this->prefs->get($data->key, $def['default'] ?? null);

        return new SystemPreference($data->key, $val, $type);
    }
}
