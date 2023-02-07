<?php

namespace Sunnysideup\PerfectCmsImages\Model;

use SilverStripe\ORM\DataObject;

/**
 * defines the image sizes
 * and default upload folder.
 */
class PerfectCMSImageCache extends DataObject
{


    private static $table_name = 'PerfectCMSImageCache';
    private static $db = [
        'Code' => 'Varchar(128)',
        'Link' => 'Text',
    ];

    private static $has_one = [
        'Image' => Image::class,
    ];

    private static $indexes = [
        'Code' => true
    ];

    public static function add_one(string $code, string $link, $image)
    {
        $item = DataObject::get_one(self::class, ['Code' => $code, 'ImageID' => $image->ID], false);
        if (!$item) {
            PerfectCMSImageCache::create(['Code' => $code, 'Link' => $link])->write();
        }
    }
}
