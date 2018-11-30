<?php

namespace WebSK\SimpleRouter\Sitemap;

/**
 * Interface InterfaceSitemapController
 * @package WebSK\SimpleRouter\Sitemap
 */
interface InterfaceSitemapController
{
    /**
     * @return array|\Generator
     */
    public function getUrlsForSitemap();
}
