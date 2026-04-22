<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Service\WebAuthn;

use Psr\Cache\CacheItemPoolInterface;

class ChallengeStore
{
    private const int TTL = 300; // 5 minutes

    /**
     * @param  CacheItemPoolInterface  $cache
     */
    public function __construct(
        private readonly CacheItemPoolInterface $cache
    ) {
    }

    /**
     * Store a challenge with a key and optional metadata.
     *
     * @param  string  $key
     * @param  string  $challenge
     * @param  array  $metadata  Additional data to store alongside the challenge
     *
     * @return void
     */
    public function store(string $key, string $challenge, array $metadata = []): void
    {
        $cacheKey = $this->buildKey($key);
        $item = $this->cache->getItem($cacheKey);
        $item->set([
            'challenge' => $challenge,
            'metadata' => $metadata,
        ]);
        $item->expiresAfter(self::TTL);
        $this->cache->save($item);
    }

    /**
     * Retrieve and delete a challenge by key. Single-use.
     *
     * @param  string  $key
     *
     * @return array{challenge: string, metadata: array}|null
     */
    public function retrieve(string $key): ?array
    {
        $cacheKey = $this->buildKey($key);
        $item = $this->cache->getItem($cacheKey);

        if (!$item->isHit()) {
            return null;
        }

        $data = $item->get();
        $this->cache->deleteItem($cacheKey);

        // Backwards compatibility: old entries stored plain string
        if (is_string($data)) {
            return ['challenge' => $data, 'metadata' => []];
        }

        return $data;
    }

    /**
     * @param  string  $key
     *
     * @return string
     */
    private function buildKey(string $key): string
    {
        return 'webauthn_challenge_' . hash('sha256', $key);
    }
}
