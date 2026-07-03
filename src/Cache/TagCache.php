<?php

namespace Sunnysideup\PerfectCmsImages\Cache;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;

class TagCache implements Flushable
{
    use Injectable;

    public function getPerfectCMSImagesTagCacheKey($image, $toAdd)
    {
        if (! $image->isPublished()) {
            return null;
        }

        return 'PCI' . $image->ID . '_' . strtotime((string) $image->LastEdited) . $toAdd;
    }

    public function getPerfectCMSImagesTagCache()
    {
        return Injector::inst()->get(CacheInterface::class . '.perfectcmsimages');
    }

    public static function flush()
    {
        $cache = Injector::inst()->get(static::class);
        $cache->getPerfectCMSImagesTagCache()->clear();
    }
}
