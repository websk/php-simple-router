<?php

declare(strict_types=1);

namespace WebSK\SimpleRouter\Tests;

use DOMDocument;
use Exception;
use PHPUnit\Framework\TestCase;
use WebSK\Config\ConfWrapper;
use WebSK\SimpleRouter\Sitemap\SitemapBuilder;

final class SitemapBuilderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/php-simple-router-' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0777, true);

        ConfWrapper::setConfig([
            'static_data_path' => $this->tempDir,
            'sitemap' => ['root' => '/sitemap'],
            'site_domain' => 'https://example.test',
        ]);
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->tempDir);
        ConfWrapper::setConfig([]);
    }

    public function testConstructorRejectsMissingDataDirectory(): void
    {
        ConfWrapper::setConfig([
            'static_data_path' => $this->tempDir . '/missing',
            'sitemap' => ['root' => '/sitemap'],
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No data directory');

        new SitemapBuilder();
    }

    public function testFinishCreatesValidSitemapAndIndex(): void
    {
        $builder = new SitemapBuilder();
        $builder->add('https://example.test/one', 'daily');
        $builder->add('https://example.test/two');
        $builder->finish();

        $indexPath = $this->tempDir . '/sitemap/sitemap.xml';
        self::assertFileExists($indexPath);
        self::assertXmlFileValid($indexPath);

        $urlFiles = glob($this->tempDir . '/sitemap/*/*.xml');
        self::assertCount(1, $urlFiles);
        self::assertXmlFileValid($urlFiles[0]);

        $contents = file_get_contents($urlFiles[0]);
        self::assertSame(2, substr_count($contents, '<url>'));
        self::assertStringContainsString('<changefreq>daily</changefreq>', $contents);
        self::assertStringContainsString('<changefreq>never</changefreq>', $contents);
    }

    public function testSitemapIsSplitAtConfiguredLimit(): void
    {
        $builder = new SitemapBuilder();

        for ($i = 1; $i <= SitemapBuilder::XML_URL_LIMIT; $i++) {
            $builder->add('https://example.test/' . $i);
        }

        $builder->finish();

        $urlFiles = glob($this->tempDir . '/sitemap/*/*.xml');
        self::assertCount(2, $urlFiles);

        $index = file_get_contents($this->tempDir . '/sitemap/sitemap.xml');
        self::assertSame(2, substr_count($index, '<sitemap>'));
    }

    public function testRemoveOldSitemapFilesKeepsRecentDirectory(): void
    {
        new SitemapBuilder();

        $oldDirectory = $this->tempDir . '/sitemap/' . (time() - 3 * 24 * 60 * 60);
        $recentDirectory = $this->tempDir . '/sitemap/' . (time() - 60);
        mkdir($oldDirectory, 0777, true);
        mkdir($recentDirectory, 0777, true);
        file_put_contents($oldDirectory . '/1.xml', '<old/>');
        file_put_contents($recentDirectory . '/1.xml', '<recent/>');

        SitemapBuilder::removeOldSitemapFiles();

        self::assertDirectoryDoesNotExist($oldDirectory);
        self::assertDirectoryExists($recentDirectory);
    }

    private static function assertXmlFileValid(string $file): void
    {
        $document = new DOMDocument();

        self::assertTrue($document->load($file));
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                self::removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
