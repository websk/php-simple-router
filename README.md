# PHP Simple Router

A small library for routing HTTP requests in PHP applications and generating XML sitemaps.

The router checks the current path against regular expressions in declaration order, extracts parameters from captured groups, and passes them to a controller method. The library does not impose an application structure or require a dependency injection container or HTTP framework.

## Features

- Map URLs to controller methods.
- Pass captured URL segments to method arguments after URL decoding.
- Group routes by a common pattern.
- Register a predefined set of routes for a CRUD controller.
- Resolve a URL handler from the command line.
- Collect URLs from controllers and generate an XML sitemap.
- Split a large sitemap across multiple files.
- Remove expired sitemap files.

## Requirements

- PHP 8.3 or later.
- The `ext-xmlwriter` extension for sitemap generation.

## Installation

```bash
composer require websk/php-simple-router
```

## Basic routing

A controller can be passed as either an object or a class name. When a class name is used, the router creates the object without constructor arguments.

```php
<?php

use WebSK\SimpleRouter\SimpleRouter;

final class ArticleController
{
    public function show(string $slug): void
    {
        echo 'Article: ' . htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

SimpleRouter::route(
    '~^/articles/([^/]+)$~',
    [ArticleController::class, 'show']
);
```

For a request to `/articles/hello%20world?draft=1`, the `show()` method receives `hello world`. The query string is ignored during route matching.

After a matching handler runs, the router terminates the PHP script. To continue checking subsequent routes, the controller method must return `SimpleRouter::CONTINUE_ROUTING`:

```php
public function checkAccess(): string
{
    // Perform an access check or other intermediate processing.

    return SimpleRouter::CONTINUE_ROUTING;
}
```

Declare routes from the most specific to the most general. The application can handle an unmatched request after the route declarations.

## Route groups

`matchGroup()` lets the application skip a block of route declarations when the current URL does not match a common pattern:

```php
if (SimpleRouter::matchGroup('~^/admin(?:/|$)~')) {
    SimpleRouter::route('~^/admin/users$~', [AdminUserController::class, 'list']);
    SimpleRouter::route('~^/admin/users/(\d+)$~', [AdminUserController::class, 'show']);
}
```

## Static routes

`staticRoute()` creates a controller, stores it as the current controller, and calls the specified method. Captured parameters are URL-decoded. An optional layout file is appended as the final method argument.

```php
SimpleRouter::staticRoute(
    '~^/pages/([^/]+)$~',
    PageController::class,
    'show',
    null,
    __DIR__ . '/layout.php'
);
```

The active controller is available through `SimpleRouter::getCurrentControllerObj()`. If the handler returns `SimpleRouter::CONTINUE_ROUTING`, the router clears the current controller and continues execution.

## CRUD routes

The `routeBasedCrud()` method registers a standard set of URLs for a controller:

| URL | Controller method |
| --- | --- |
| `/items` | `listAction()` |
| `/items/add` | `addAction()` |
| `/items/create` | `createAction()` |
| `/items/edit/{id}` | `editAction($id)` |
| `/items/save/{id}` | `saveAction($id)` |
| `/items/delete/{id}` | `deleteAction($id)` |

```php
SimpleRouter::routeBasedCrud('/items', ItemController::class);
```

The controller must provide the public methods shown above and have no required constructor arguments.

## Resolving a URL handler from the CLI

Command-line utilities can provide the URL explicitly:

```php
SimpleRouter::setCurrentUrlByCli('/articles/hello');
SimpleRouter::route('~^/articles/([^/]+)$~', [ArticleController::class, 'show']);
```

When a route matches, its handler is not called. Instead, the router throws an `Exception` whose message contains the controller class, method, and captured arguments:

```text
ArticleController->show(hello)
```

## Sitemap generation

The sitemap generator uses configuration provided by `websk/php-config`:

```php
use WebSK\Config\ConfWrapper;

ConfWrapper::setConfig([
    'static_data_path' => '/var/www/example/public',
    'sitemap' => [
        'root' => '/sitemap',
    ],
    'site_domain' => 'https://example.com',
]);
```

- `static_data_path` is an existing directory for public static files.
- `sitemap.root` is the sitemap directory relative to `static_data_path`.
- `site_domain` is the domain used in the sitemap index.

Controllers that provide sitemap URLs implement `InterfaceSitemapController`:

```php
use WebSK\SimpleRouter\Sitemap\InterfaceSitemapController;

final class ArticleController implements InterfaceSitemapController
{
    public function getUrlsForSitemap(): iterable
    {
        yield [
            'url' => 'https://example.com/articles/first',
            'freq' => 'weekly',
        ];

        yield [
            'url' => 'https://example.com/articles/second',
        ];
    }
}
```

The `url` field is required. When `freq` is omitted, it defaults to `never`.

To build the sitemap, set the builder before loading the application's regular route declarations, then call `finish()`:

```php
use WebSK\SimpleRouter\SimpleRouter;
use WebSK\SimpleRouter\Sitemap\SitemapBuilder;

$builder = new SitemapBuilder();
SimpleRouter::setSitemapBuilder($builder);

require __DIR__ . '/routes.php';

$builder->finish();
```

In sitemap mode, route handlers are not called. Each controller class is processed once, and its URLs are collected through `getUrlsForSitemap()`. Controllers must be instantiable without constructor arguments.

`SitemapBuilder` writes up to 2,500 URLs per XML file and creates a `sitemap.xml` index. Old build directories can be removed with:

```php
SitemapBuilder::removeOldSitemapFiles();
```

This removes sitemap directories older than two days.

## Development

```bash
composer check
```

This command runs PHP syntax checks and the PHPUnit test suite.

## License

MIT. See [LICENSE](LICENSE) for the full license text.
