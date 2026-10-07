<?php

declare(strict_types=1);

namespace WebSK\SimpleRouter\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WebSK\SimpleRouter\SimpleRouter;
use WebSK\SimpleRouter\Sitemap\InterfaceSitemapBuilder;
use WebSK\SimpleRouter\Sitemap\InterfaceSitemapController;

final class SimpleRouterTest extends TestCase
{
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        self::resetRouterState();
        RecordingController::reset();
        CrudController::reset();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        self::resetRouterState();
    }

    public function testRouteInvokesMatchingActionWithDecodedArguments(): void
    {
        $_SERVER['REQUEST_URI'] = '/articles/hello%20world?draft=1';
        $controller = new RecordingController();

        SimpleRouter::route('~^/articles/(.+)$~', [$controller, 'show']);

        self::assertSame([['hello world']], RecordingController::$calls);
    }

    public function testRouteDoesNotInvokeActionWhenUrlDoesNotMatch(): void
    {
        $_SERVER['REQUEST_URI'] = '/other';
        $controller = new RecordingController();

        SimpleRouter::route('~^/articles/(.+)$~', [$controller, 'show']);

        self::assertSame([], RecordingController::$calls);
    }

    public function testCliRouteReportsControllerActionAndDecodedArguments(): void
    {
        SimpleRouter::setCurrentUrlByCli('/articles/%D0%98%D0%B2%D0%B0%D0%BD');
        $controller = new RecordingController();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('->show(Иван)');

        SimpleRouter::route('~^/articles/(.+)$~u', [$controller, 'show']);
    }

    public function testMatchGroupUsesCliUrl(): void
    {
        SimpleRouter::setCurrentUrlByCli('/admin/users');

        self::assertTrue(SimpleRouter::matchGroup('~^/admin/~'));
        self::assertFalse(SimpleRouter::matchGroup('~^/public/~'));
    }

    public function testStaticRouteSupportsNullableArgumentsAndContinuesRouting(): void
    {
        $_SERVER['REQUEST_URI'] = '/pages/about';

        SimpleRouter::staticRoute(
            '~^/pages/(.+)$~',
            RecordingController::class,
            'show',
            null,
            'layout.php'
        );

        self::assertSame([['about', 'layout.php']], RecordingController::$calls);
        self::assertNull(SimpleRouter::getCurrentControllerObj());
    }

    public function testStaticRouteCacheTimeCurrentlyDoesNotChangeDispatchArguments(): void
    {
        $_SERVER['REQUEST_URI'] = '/pages/about';

        foreach ([null, 0, 3600] as $cacheTime) {
            RecordingController::reset();

            SimpleRouter::staticRoute(
                '~^/pages/(.+)$~',
                RecordingController::class,
                'show',
                $cacheTime
            );

            self::assertSame([['about']], RecordingController::$calls);
            self::assertNull(SimpleRouter::getCurrentControllerObj());
        }
    }

    public function testCrudRouterDispatchesMatchingAction(): void
    {
        $_SERVER['REQUEST_URI'] = '/items/edit/42';

        SimpleRouter::routeBasedCrud('/items', CrudController::class);

        self::assertSame([['edit', '42']], CrudController::$calls);
    }

    public function testSitemapModeCollectsControllerUrlsOnce(): void
    {
        $builder = new RecordingSitemapBuilder();
        SimpleRouter::setSitemapBuilder($builder);
        $controller = new SitemapController();

        SimpleRouter::route('~ignored~', [$controller, 'index']);
        SimpleRouter::route('~ignored~', [$controller, 'index']);

        self::assertSame([
            ['https://example.test/one', 'daily'],
            ['https://example.test/two', 'never'],
        ], $builder->urls);
        self::assertSame([SitemapController::class], $builder->messages);
    }

    private static function resetRouterState(): void
    {
        $reflection = new ReflectionClass(SimpleRouter::class);
        $defaults = $reflection->getDefaultProperties();

        foreach ($defaults as $name => $value) {
            if ($reflection->getProperty($name)->isStatic()) {
                $reflection->setStaticPropertyValue($name, $value);
            }
        }
    }
}

final class RecordingController
{
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    public function show(string ...$arguments): string
    {
        self::$calls[] = $arguments;

        return SimpleRouter::CONTINUE_ROUTING;
    }
}

final class CrudController
{
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    public function addAction(): string
    {
        self::$calls[] = ['add'];

        return SimpleRouter::CONTINUE_ROUTING;
    }

    public function createAction(): string
    {
        self::$calls[] = ['create'];

        return SimpleRouter::CONTINUE_ROUTING;
    }

    public function editAction(string $id): string
    {
        self::$calls[] = ['edit', $id];

        return SimpleRouter::CONTINUE_ROUTING;
    }

    public function saveAction(string $id): string
    {
        self::$calls[] = ['save', $id];

        return SimpleRouter::CONTINUE_ROUTING;
    }

    public function deleteAction(string $id): string
    {
        self::$calls[] = ['delete', $id];

        return SimpleRouter::CONTINUE_ROUTING;
    }

    public function listAction(): string
    {
        self::$calls[] = ['list'];

        return SimpleRouter::CONTINUE_ROUTING;
    }
}

final class RecordingSitemapBuilder implements InterfaceSitemapBuilder
{
    public array $urls = [];
    public array $messages = [];

    public function add($url, $freq): void
    {
        $this->urls[] = [$url, $freq];
    }

    public function log($controller_name): void
    {
        $this->messages[] = $controller_name;
    }
}

final class SitemapController implements InterfaceSitemapController
{
    public function index(): string
    {
        return SimpleRouter::CONTINUE_ROUTING;
    }

    public function getUrlsForSitemap(): array
    {
        return [
            ['url' => 'https://example.test/one', 'freq' => 'daily'],
            ['url' => 'https://example.test/two'],
            ['freq' => 'weekly'],
        ];
    }
}
