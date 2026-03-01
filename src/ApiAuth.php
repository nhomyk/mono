<?php
declare(strict_types=1);
namespace App;

/**
 * API key authentication.
 *
 * Validates Bearer tokens against the API_KEY environment variable.
 * Uses hash_equals() for timing-safe comparison.
 */
class ApiAuth
{
    /**
     * Validate the Authorization header.
     *
     * @return array{valid: bool, error: string}
     */
    public static function validate(string $authHeader): array
    {
        $apiKey = getenv('API_KEY') ?: '';

        // If no API_KEY configured, allow all requests (development mode)
        if ($apiKey === '') {
            return ['valid' => true, 'error' => ''];
        }

        if ($authHeader === '') {
            return ['valid' => false, 'error' => 'Missing Authorization header'];
        }

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return ['valid' => false, 'error' => 'Authorization header must use Bearer scheme'];
        }

        $token = substr($authHeader, 7);

        if (!hash_equals($apiKey, $token)) {
            return ['valid' => false, 'error' => 'Invalid API key'];
        }

        return ['valid' => true, 'error' => ''];
    }
}
