<?php

namespace Sunnysideup\PerfectCmsImages\Api;

use SilverStripe\Assets\Image;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SiteConfig\SiteConfig;

class ImageManipulations
{
    use Configurable;
    use Injectable;


    private static $imageLinkCache = [];

    /**
     * work out the best image link.
     *
     * There are basically three options:
     * a. if the height and/or width matches or is smaller than it should be
     *    then just return natural image
     * b. if crop is set to true then Fill
     * c. otherwise resize Height/Width/Both
     *
     * @param Image     $image
     * @param null|bool $useRetina     optional
     * @param null|bool $forMobile     optional
     * @param null|int  $resizeToWidth optional
     */
    public static function get_image_link($image, string $name, ?bool $useRetina = null, ?bool $forMobile = null, ?int $resizeToWidth = 0): string
    {
        // if specified to use retina, then check if it is needed at all.
        if ($useRetina) {
            $useRetina = PerfectCMSImages::use_retina($name);
        }
        $cacheKey =
            implode(
                '_',
                array_filter(
                    [
                        $image->ID,
                        $name,
                        ($useRetina ? 'Y' : 'N'),
                        ($forMobile ? 'MY' : 'MN'),
                        $resizeToWidth,
                    ]
                )
            );
        if (! isset(self::$imageLinkCache[$cacheKey])) {
            $link = '';
            if ($forMobile) {
                $perfectWidth = (int) PerfectCMSImages::get_mobile_width($name, true);
                $perfectHeight = (int) PerfectCMSImages::get_mobile_height($name, true);
                if (! $perfectHeight && ! $perfectWidth) {
                    self::$imageLinkCache[$cacheKey] = '';
                    return self::$imageLinkCache[$cacheKey];
                }
            } else {
                $perfectWidth = (int) PerfectCMSImages::get_width($name, true);
                $perfectHeight = (int) PerfectCMSImages::get_height($name, true);
            }

            $useCrop = PerfectCMSImages::is_crop($name);
            $usePad = PerfectCMSImages::is_pad($name);

            $multiplier = PerfectCMSImages::get_multiplier($useRetina);
            $perfectWidth *= $multiplier;
            $perfectHeight *= $multiplier;

            //get current width and height
            $myWidth = $image->getWidth();
            $myHeight = $image->getHeight();
            //if we are trying to resize to a width that is small than the perfect width
            //and the resize width is small than the current width, then lets resize...
            if (0 !== (int) $resizeToWidth && ($resizeToWidth < $perfectWidth && $resizeToWidth < $myWidth)) {
                $perfectWidth = $resizeToWidth;
            }

            $link = null;
            if ($perfectWidth && $perfectHeight) {
                //if the height or the width are already perfect then we can not do anything about it.
                if ($myWidth === $perfectWidth && $myHeight === $perfectHeight) {
                    $link = $image->getURL();
                } elseif ($usePad && ($myWidth < $perfectWidth || $myHeight < $perfectHeight)) {
                    $link = $image->getImageLinkCachedIfExists(
                        'Pad',
                        $perfectWidth,
                        $perfectHeight,
                        PerfectCMSImages::get_padding_bg_colour($name)
                    );
                } elseif ($useCrop) {
                    $link = $image->getImageLinkCachedIfExists(
                        'Fill',
                        $perfectWidth,
                        $perfectHeight
                    );
                } else {
                    $link = $image->getImageLinkCachedIfExists(
                        'FitMax',
                        $perfectWidth,
                        $perfectHeight
                    );
                }
            } elseif ($perfectWidth) {
                if ($myWidth === $perfectWidth) {
                    $link = $image->getURL();
                } elseif ($useCrop) {
                    $link = $image->getImageLinkCachedIfExists(
                        'Fill',
                        $perfectWidth,
                        $myHeight
                    );
                } else {
                    $link = $image->getImageLinkCachedIfExists(
                        'ScaleWidth',
                        $perfectWidth
                    );
                }
            } elseif ($perfectHeight) {
                if ($myHeight === $perfectHeight) {
                    $link = $image->getUrl();
                } elseif ($useCrop) {
                    $link = $image->getImageLinkCachedIfExists(
                        'Fill',
                        $myWidth,
                        $perfectHeight
                    );
                } else {
                    $link = $image->getImageLinkCachedIfExists(
                        'ScaleHeight',
                        $perfectHeight
                    );
                }
            } else {
                // not for mobile, we definitely want to have some sort of link!
                $link = $image->getUrl();
            }
            self::$imageLinkCache[$cacheKey] = (string) $link;
        }

        return self::$imageLinkCache[$cacheKey];
    }

    /**
     * back-up image.
     *
     * @return null|Image
     */
    public static function get_backup_image(string $name)
    {
        $image = null;
        $backupObject = SiteConfig::current_site_config();
        if ($backupObject->hasMethod($name)) {
            $image = $backupObject->{$name}();
        }

        return $image;
    }

    /**
     * placeholder image.
     */
    public static function get_placeholder_image_tag(string $name): string
    {
        $multiplier = PerfectCMSImages::get_multiplier(true);
        $perfectWidth = (int) PerfectCMSImages::get_width($name, true);
        $perfectHeight = (int) PerfectCMSImages::get_height($name, true);
        $perfectWidth *= $multiplier;
        $perfectHeight *= $multiplier;
        if ($perfectWidth || $perfectHeight) {
            if (0 === $perfectWidth && $perfectHeight === 0) {
                $perfectWidth = 200;
            } elseif (0 === $perfectWidth) {
                $perfectWidth = $perfectHeight;
            } elseif ($perfectHeight === 0) {
                $perfectHeight = $perfectWidth;
            }

            $text = "{$perfectWidth} x {$perfectHeight} /2 = " . round($perfectWidth / 2) . ' x ' . round($perfectHeight / 2) . '';

            return 'https://placehold.it/' . $perfectWidth . 'x' . $perfectHeight . '?text=' . urlencode($text);
        }

        return 'https://placehold.it/1500x1500?text=' . urlencode('no size set');
    }


    public static function add_fake_parts($image, string $link): string
    {
        // first get the timestamp
        $time1 = strtotime((string) $image->LastEdited);
        $time2 = 0;
        $path = Controller::join_links(Director::baseFolder(), PUBLIC_DIR, $link);
        if (file_exists($path)) {
            $time2 = filemtime($path);
        }

        // first convert to hash extension
        if (class_exists('HashPathExtension')) {
            /** @var null|Controller $curr */
            $curr = Controller::curr();
            if ($curr && $curr->hasMethod('HashPath')) {
                $link = $curr->HashPath($link, false);
            }
        }

        // now you can add the time
        $link .= '?time=' . max($time1, $time2);

        // finally add the title
        if ($image->Title) {
            $imageClasses = Config::inst()->get(PerfectCMSImages::class, 'perfect_cms_images_append_title_to_image_links_classes');
            if (in_array($image->ClassName, $imageClasses, true)) {
                $link .= '&title=' . urlencode(Convert::raw2att($image->Title));
            }
        }

        return $link;
    }
}
