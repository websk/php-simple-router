<?php

namespace WebSK\SimpleRouter\Sitemap;

/**
 * Interface InterfaceSitemapBuilder
 * @package WebSK\SimpleRouter\Sitemap
 */
interface InterfaceSitemapBuilder
{
    public function add($url, $freq);

    public function log($controller_name);
}
