<?php

declare(strict_types=1);

namespace WebSK\SimpleRouter\Tests;

use PHPUnit\Framework\TestCase;

final class CacheHeadersWebTest extends TestCase
{
    public function testCacheHeadersCurrentlyDoesNotEmitCacheHeadersUnderWebSapi(): void
    {
        $phpCgi = PHP_BINDIR . '/php-cgi';
        if (!is_executable($phpCgi)) {
            self::markTestSkipped('php-cgi is required for the web SAPI header test');
        }

        $pipes = [];
        $process = proc_open(
            [
                $phpCgi,
                '-d',
                'error_reporting=E_ALL',
                '-d',
                'display_errors=1',
                __DIR__ . '/fixtures/cache_headers.php',
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            dirname(__DIR__)
        );

        self::assertIsResource($process);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, $errorOutput);
        self::assertStringEndsWith("\r\n\r\nok", $output);
        self::assertStringNotContainsString('Cache-Control:', $output);
        self::assertStringNotContainsString('Expires:', $output);
    }
}
