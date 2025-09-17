<?php

namespace App\Config;

use App\Config\Attribute\Setting;

final class SystemPrefRegistry
{
    /** @return array<string, array{key:string,type:string,group:string,default:mixed,label:?string,help:?string}> */
    public function all(): array
    {
        $out = [];
        $re = new \ReflectionEnum(SystemPref::class);
        foreach ($re->getCases() as $case) {
            /** @var SystemPref $enum */
            $enum = $case->getValue();
            $meta = $case->getAttributes(Setting::class)[0] ?? null;
            /** @var Setting|null $cfg */
            $cfg = $meta?->newInstance();
            $out[$enum->value] = [
                'key' => $enum->value,
                'type' => $cfg?->type ?? 'string',
                'group' => $cfg?->group ?? 'general',
                'default' => $cfg?->default ?? null,
                'label' => $cfg?->label,
                'help' => $cfg?->help,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        $map = [];
        foreach ($this->all() as $k => $def) {
            $map[$k] = $def['default'];
        }

        return $map;
    }

    /** @return list<array{...}> */
    public function byGroup(string $group): array
    {
        return array_values(array_filter($this->all(), fn ($d) => $d['group'] === $group));
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }
}
