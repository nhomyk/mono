<?php
declare(strict_types=1);
namespace App;

/**
 * Simple in-memory rate limiter using a sliding window.
 *
 * For production, replace with Redis-backed implementation.
 * This prevents abuse of the API without external dependencies.
 */
class RateLimiter
{
    /** @var array<string, array<float>> */
    private array $windows = [];
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct(int $maxRequests = 60, int $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    /**
     * Check if a request from the given key is allowed.
     *
     * @return array{allowed: bool, remaining: int, reset_at: int}
     */
    public function check(string $key): array
    {
        $now = microtime(true);
        $cutoff = $now - $this->windowSeconds;

        // Initialize or prune old entries
        if (!isset($this->windows[$key])) {
            $this->windows[$key] = [];
        }

        $this->windows[$key] = array_values(
            array_filter($this->windows[$key], fn(float $t) => $t > $cutoff)
        );

        $count = count($this->windows[$key]);

        if ($count >= $this->maxRequests) {
            $resetAt = (int)ceil($this->windows[$key][0] + $this->windowSeconds);
            return ['allowed' => false, 'remaining' => 0, 'reset_at' => $resetAt];
        }

        $this->windows[$key][] = $now;
        return [
            'allowed' => true,
            'remaining' => $this->maxRequests - $count - 1,
            'reset_at' => (int)ceil($now + $this->windowSeconds),
        ];
    }
}
