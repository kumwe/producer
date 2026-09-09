<?php

/**
 * Prove the same-origin transport profile admits exactly the browser's
 * same-origin fetch tuple and refuses every other shape with a stable code.
 * @since 0.3.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Deployment\DeploymentException;
use Kumwe\Producer\Deployment\SameOriginFetchMetadataPolicy;
use Kumwe\Producer\Tests\TestCase;

final class FetchMetadataPolicyTest extends TestCase
{
    public function testTheExactSameOriginFetchTupleIsAdmitted(): void
    {
        $policy = new SameOriginFetchMetadataPolicy('https://admin.example.test');
        $this->assertSame(null, $policy->admit(self::request()), 'The canonical Studio fetch is admitted.');
        $this->assertSame(
            null,
            $policy->admit([
                'ORIGIN' => ['HTTPS://ADMIN.EXAMPLE.TEST'],
                'sec-fetch-site' => ['Same-Origin'],
                'sec-fetch-mode' => ['CORS'],
                'sec-fetch-dest' => ['Empty'],
            ]),
            'Header names and token values compare case-insensitively.'
        );
    }

    public function testEveryOtherRequestShapeIsRefusedWithAStableCode(): void
    {
        $policy = new SameOriginFetchMetadataPolicy('https://admin.example.test');
        $cases = [
            'origin-missing' => ['Origin' => []],
            'origin-duplicate' => ['Origin' => ['https://admin.example.test', 'https://admin.example.test']],
            'origin-mismatch' => ['Origin' => ['https://evil.example']],
            'fetch-site' => ['Sec-Fetch-Site' => ['cross-site']],
            'fetch-mode' => ['Sec-Fetch-Mode' => ['navigate']],
            'fetch-dest' => ['Sec-Fetch-Dest' => ['document']],
        ];
        foreach ($cases as $expected => $override) {
            $this->assertSame($expected, $policy->admit(array_merge(self::request(), $override)), $expected . ' is refused.');
        }
        foreach (['Sec-Fetch-Site', 'Sec-Fetch-Mode', 'Sec-Fetch-Dest'] as $header) {
            $absent = self::request();
            unset($absent[$header]);
            $this->assertSame(
                strtolower(substr($header, strlen('Sec-'))),
                $policy->admit($absent),
                'A missing ' . $header . ' fails closed.'
            );
            $duplicated = self::request();
            $duplicated[$header][] = $duplicated[$header][0];
            $this->assertSame(
                strtolower(substr($header, strlen('Sec-'))),
                $policy->admit($duplicated),
                'A duplicated ' . $header . ' fails closed.'
            );
        }
        $refusal = $this->assertThrows(
            static fn () => new SameOriginFetchMetadataPolicy('https://*.example.test'),
            DeploymentException::class,
            'The allowed origin must be exact.'
        );
        $this->assertTrue($refusal instanceof DeploymentException, 'Typed refusal.');
        $this->assertSame('origin', $refusal->rejection(), 'The refusal names the origin rule.');
    }

    /**
     * @return array<string, list<string>>
     */
    private static function request(): array
    {
        return [
            'Origin' => ['https://admin.example.test'],
            'Sec-Fetch-Site' => ['same-origin'],
            'Sec-Fetch-Mode' => ['cors'],
            'Sec-Fetch-Dest' => ['empty'],
        ];
    }
}
