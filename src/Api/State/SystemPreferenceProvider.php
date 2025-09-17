<?php

namespace App\Api\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\SystemPreference;
use App\Config\SystemPrefRegistry;
use App\Service\net\exelearning\Service\SystemPreferencesService;

class SystemPreferenceProvider implements ProviderInterface
{
    public function __construct(
        private readonly SystemPrefRegistry $registry,
        private readonly SystemPreferencesService $prefs,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $items = [];
            foreach ($this->registry->all() as $key => $def) {
                $val = $this->prefs->get($key, $def['default'] ?? null);
                $items[] = new SystemPreference($key, $val, $def['type'] ?? 'string');
            }

            return $items;
        }

        $key = $uriVariables['key'] ?? null;
        if (!$key) {
            return null;
        }
        $def = $this->registry->get($key);
        if (!$def) {
            return null; // 404 for unknown keys
        }
        $val = $this->prefs->get($key, $def['default'] ?? null);

        return new SystemPreference($key, $val, $def['type'] ?? 'string');
    }
}
