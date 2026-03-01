<?php
declare(strict_types=1);

use App\ApiAuth;
use PHPUnit\Framework\TestCase;

class ApiAuthTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear any existing API_KEY
        putenv('API_KEY');
    }

    public function testNoApiKeyConfiguredAllowsAll(): void
    {
        putenv('API_KEY=');
        $result = ApiAuth::validate('');
        $this->assertTrue($result['valid']);
    }

    public function testValidBearerTokenAccepted(): void
    {
        putenv('API_KEY=test-secret-key-123');
        $result = ApiAuth::validate('Bearer test-secret-key-123');
        $this->assertTrue($result['valid']);
    }

    public function testMissingHeaderRejected(): void
    {
        putenv('API_KEY=test-secret-key-123');
        $result = ApiAuth::validate('');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Missing', $result['error']);
    }

    public function testWrongSchemeRejected(): void
    {
        putenv('API_KEY=test-secret-key-123');
        $result = ApiAuth::validate('Basic dXNlcjpwYXNz');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Bearer', $result['error']);
    }

    public function testInvalidTokenRejected(): void
    {
        putenv('API_KEY=test-secret-key-123');
        $result = ApiAuth::validate('Bearer wrong-key');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Invalid', $result['error']);
    }

    protected function tearDown(): void
    {
        putenv('API_KEY');
    }
}
