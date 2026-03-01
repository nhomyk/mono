<?php
declare(strict_types=1);

use App\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    public function testFirstRequestAllowed(): void
    {
        $limiter = new RateLimiter(10, 60);
        $result = $limiter->check('client-1');
        $this->assertTrue($result['allowed']);
        $this->assertSame(9, $result['remaining']);
    }

    public function testExceedingLimitBlocks(): void
    {
        $limiter = new RateLimiter(3, 60);

        $limiter->check('client-1');
        $limiter->check('client-1');
        $limiter->check('client-1');

        $result = $limiter->check('client-1');
        $this->assertFalse($result['allowed']);
        $this->assertSame(0, $result['remaining']);
    }

    public function testDifferentKeysIndependent(): void
    {
        $limiter = new RateLimiter(2, 60);

        $limiter->check('client-1');
        $limiter->check('client-1');
        // client-1 is now at limit

        $result = $limiter->check('client-2');
        $this->assertTrue($result['allowed']);
        $this->assertSame(1, $result['remaining']);
    }

    public function testResetAtIsReasonable(): void
    {
        $limiter = new RateLimiter(10, 60);
        $result = $limiter->check('client-1');
        $this->assertGreaterThan(time(), $result['reset_at']);
        $this->assertLessThanOrEqual(time() + 61, $result['reset_at']);
    }
}
