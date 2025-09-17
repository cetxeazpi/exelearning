<?php 

// src/Service/net/exelearning/SystemPreferences.php
namespace App\Service\net\exelearning\Service;

use App\Entity\SystemPreference;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;

class SystemPreferences
{
    public function __construct(
        private EntityManagerInterface $em,
        private CacheItemPoolInterface $cache // Symfony Cache contracts
    ) {}

    private function cacheKey(string $key): string { return 'sys_pref:'.$key; }

    public function get(string $key, mixed $default=null): mixed
    {
        $item = $this->cache->getItem($this->cacheKey($key));
        if ($item->isHit()) return $item->get();

        $pref = $this->em->getRepository(SystemPreference::class)->findOneBy(['key'=>$key]);
        if (!$pref) { $item->set($default); $this->cache->save($item); return $default; }

        $val = $this->castOut($pref->getValue(), $pref->getType());
        $item->set($val); $this->cache->save($item);
        return $val;
    }

    public function set(string $key, mixed $value, ?string $type=null, ?string $updatedBy=null): void
    {
        $repo = $this->em->getRepository(SystemPreference::class);
        $pref = $repo->findOneBy(['key'=>$key]) ?? (new SystemPreference())->setKey($key);
        $pref->setType($type);
        $pref->setValue($this->castIn($value, $type));
        $pref->setUpdatedBy($updatedBy);
        $this->em->persist($pref);
        $this->em->flush();
        $this->cache->deleteItem($this->cacheKey($key));
    }

    public function delete(string $key): void
    {
        $pref = $this->em->getRepository(SystemPreference::class)->findOneBy(['key'=>$key]);
        if ($pref) { $this->em->remove($pref); $this->em->flush(); }
        $this->cache->deleteItem($this->cacheKey($key));
    }

    private function castIn(mixed $value, ?string $type): ?string
    {
        return match ($type) {
            'bool' => $value ? '1' : '0',
            'int' => (string) (int)$value,
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'datetime' => $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : (string)$value,
            default => (null === $value ? null : (string)$value),
        };
    }
    private function castOut(?string $raw, ?string $type): mixed
    {
        return match ($type) {
            'bool' => $raw === '1',
            'int' => null === $raw ? null : (int)$raw,
            'json' => $raw ? json_decode($raw, true) : null,
            'datetime' => $raw ? new \DateTimeImmutable($raw) : null,
            default => $raw,
        };
    }
}
