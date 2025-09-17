<?php

// src/Service/net/exelearning/SystemPreferencesService.php

namespace App\Service\net\exelearning\Service;

use App\Config\SystemPrefRegistry;
use App\Entity\net\exelearning\Entity\SystemPreferences;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;

class SystemPreferencesService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CacheItemPoolInterface $cache, // Symfony Cache contracts
        private SystemPrefRegistry $registry,
    ) {
    }

    private function cacheKey(string $key): string
    {
        return 'sys_pref:'.$key;
    }

    // public function get(string $key, mixed $default = null): mixed
    // {
    //     $item = $this->cache->getItem($this->cacheKey($key));
    //     if ($item->isHit()) {
    //         return $item->get();
    //     }

    //     $pref = $this->em->getRepository(SystemPreferences::class)->findOneBy(['key' => $key]);
    //     if (!$pref) {
    //         $item->set($default);
    //         $this->cache->save($item);

    //         return $default;
    //     }

    //     $val = $this->castOut($pref->getValue(), $pref->getType());
    //     $item->set($val);
    //     $this->cache->save($item);

    //     return $val;
    // }

    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->cache->getItem($this->cacheKey($key));
        if ($item->isHit()) {
            return $item->get();
        }

        $repo = $this->em->getRepository(SystemPreferences::class);
        $pref = $repo->findOneBy(['key' => $key]);

        if ($pref) {
            $def = $this->registry->get($key) ?? [];
            $type = $pref->getType() ?? ($def['type'] ?? 'string');
            $val = $this->castOut($pref->getValue(), $type);
            $item->set($val);
            $this->cache->save($item);

            return $val;
        }

        // Fallback to provided default, else registry default, else null
        $def = $this->registry->get($key) ?? [];
        $fallback = $default ?? ($def['default'] ?? null);
        $item->set($fallback);
        $this->cache->save($item);

        return $fallback;
    }

    public function set(string $key, mixed $value, ?string $type = null, ?string $updatedBy = null): void
    {
        $repo = $this->em->getRepository(SystemPreferences::class);
        $pref = $repo->findOneBy(['key' => $key]) ?? (new SystemPreferences())->setKey($key);

        $type ??= $this->detectType($value);
        $pref->setType($type);
        $pref->setValue($this->castIn($value, $type));
        $pref->setUpdatedBy($updatedBy);
        $this->em->persist($pref);
        $this->em->flush();
        $this->cache->deleteItem($this->cacheKey($key));
    }

    private function detectType(mixed $v): string
    {
        return match (true) {
            $v instanceof \DateTimeInterface => 'datetime',
            is_bool($v) => 'bool',
            $this->looksLikeHtml($v) => 'html',
            default => 'string',
        };
    }

    private function looksLikeHtml(mixed $v): bool
    {
        if (!is_string($v)) {
            return false;
        }

        return str_contains($v, '<') && str_contains($v, '>');
    }

    public function delete(string $key): void
    {
        $pref = $this->em->getRepository(SystemPreferences::class)->findOneBy(['key' => $key]);
        if ($pref) {
            $this->em->remove($pref);
            $this->em->flush();
        }
        $this->cache->deleteItem($this->cacheKey($key));
    }

    private function castIn(mixed $value, ?string $type): ?string
    {
        return match ($type) {
            'bool' => $value ? '1' : '0',
            'int' => (string) (int) $value,
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'datetime' => $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : (string) $value,
            default => (null === $value ? null : (string) $value),
        };
    }

    private function castOut(?string $raw, ?string $type): mixed
    {
        return match ($type) {
            'bool' => '1' === $raw,
            'int' => null === $raw ? null : (int) $raw,
            'json' => $raw ? json_decode($raw, true) : null,
            'datetime' => $raw ? new \DateTimeImmutable($raw) : null,
            default => $raw,
        };
    }
}
