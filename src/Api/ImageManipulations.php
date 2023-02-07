<?php

namespace Sunnysideup\PerfectCmsImages\Api;

use SilverStripe\Assets\Image;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use Sunnysideup\PerfectCmsImages\Model\PerfectCMSImageCache;

class ImageManipulations
{
    private static $webp_enabled = true;

    private static $webp_quality = 77;

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
        $cacheKey =
            implode(
                '_',
                array_filter(
                    [
                        $image->ID,
                        $name,
                        ($useRetina ? 'Y' : 'N'),
                        ($forMobile ? 'MY' : 'MN'),
                        $resizeToWidth
                    ]
                )
            );
        if (empty(self::$imageLinkCache[$cacheKey])) {
            $item = DataObject::get_one(PerfectCMSImageCache::class, ['Code' => $cacheKey]);
            if ($item) {
                return $item->Link;
            }
            //work out perfect width and height
            if (null === $useRetina) {
                $useRetina = PerfectCMSImages::use_retina($name);
            }
            $crop = PerfectCMSImages::is_crop($name);

            $multiplier = PerfectCMSImages::get_multiplier($useRetina);
            $perfectWidth = (int) PerfectCMSImages::get_width($name, true);
            $perfectHeight = (int) PerfectCMSImages::get_height($name, true);

            if ($forMobile) {
                $perfectWidth = (int) PerfectCMSImages::get_mobile_width($name, true);
                $perfectHeight = (int) PerfectCMSImages::get_mobile_height($name, true);
            }

            $perfectWidth *= $multiplier;
            $perfectHeight *= $multiplier;

            //get current width and height
            $myWidth = $image->getWidth();
            $myHeight = $image->getHeight();

            //if we are trying to resize to a width that is smaller than the perfect width
            //and the resize width is small than the current width, then lets resize...
            if (0 !== (int) $resizeToWidth) {
                if ($resizeToWidth < $perfectWidth && $resizeToWidth < $myWidth) {
                    $perfectWidth = $resizeToWidth;
                }
            }
            if ($perfectWidth && $perfectHeight) {
                if ($myWidth === $perfectWidth && $myHeight === $perfectHeight) {
                    // perfect already?
                    $link = $image->Link();
                } elseif ($myWidth < $perfectWidth || $myHeight < $perfectHeight) {
                    // too small? adding padding.
                    $link = $image->Pad(
                        $perfectWidth,
                        $perfectHeight,
                        PerfectCMSImages::get_padding_bg_colour($name)
                    )->Link();
                } elseif ($crop) {
                    // first of two situations where we make it smaller.
                    $link = $image->Fill($perfectWidth, $perfectHeight)->Link();
                } else {
                    // second of two situations where we make it smaller.
                    $link = $image->FitMax($perfectWidth, $perfectHeight)->Link();
                }
            } elseif ($perfectWidth) {
                if ($myWidth === $perfectWidth) {
                    // perfect already?
                    $link = $image->Link();
                } elseif ($crop) {
                    $link = $image->Fill($perfectWidth, $myHeight)->Link();
                } else {
                    $link = $image->ScaleWidth($perfectWidth)->Link();
                }
            } elseif ($perfectHeight) {
                if ($myHeight === $perfectHeight) {
                    $link = $image->Link();
                } elseif ($crop) {
                    $link = $image->Fill($myWidth, $perfectHeight)->Link();
                } else {
                    $link = $image->ScaleHeight($perfectHeight)->Link();
                }
            } elseif ($forMobile) {
                $link = '';
            } else {
                $link = $image->Link();
            }
            self::$imageLinkCache[$cacheKey] = $link;
            PerfectCMSImageCache::add_one($cacheKey, $link);
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
        $multiplier = (int) PerfectCMSImages::get_multiplier(true);
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

    public static function web_p_link(string $link): string
    {
        if (self::web_p_enabled() && $link) {
            $fileNameWithBaseFolder = Director::baseFolder() . '/public' . $link;
            $arrayOfLink = explode('.', $link);
            $extension = array_pop($arrayOfLink);
            $pathWithoutExtension = rtrim($link, '.' . $extension);
            $webPFileName = $pathWithoutExtension . '_' . $extension . '.webp';
            $webPFileNameWithBaseFolder = Director::baseFolder() . '/public' . $webPFileName;
            if (file_exists($fileNameWithBaseFolder)) {
                if (isset($_GET['flush']) && file_exists($webPFileNameWithBaseFolder)) {
                    unlink($webPFileNameWithBaseFolder);
                }
                if (file_exists($webPFileNameWithBaseFolder)) {
                    //todo: check that image is the same ...
                } else {
                    list($width, $height, $type) = getimagesize($fileNameWithBaseFolder);
                    $img = null;
                    if ($width && $height) {
                        if (2 === $type) {
                            $img = imagecreatefromjpeg($fileNameWithBaseFolder);
                        } elseif (3 === $type) {
                            $img = imagecreatefrompng($fileNameWithBaseFolder);
                            imagesavealpha($img, true);
                        }
                        if (null !== $img) {
                            $quality = Config::inst()->get(ImageManipulations::class, 'webp_quality');
                            imagewebp($img, $webPFileNameWithBaseFolder, $quality);
                        }
                    }
                }

                return $webPFileName;
            }
        }

        return $link;
    }

    public static function add_fake_parts($image, string $link): string
    {
        if (class_exists('HashPathExtension')) {
            /** @var null|Controller $curr */
            $curr = Controller::curr();
            if ($curr) {
                if ($curr->hasMethod('HashPath')) {
                    $link = $curr->HashPath($link, false);
                }
            }
        }
        if ($image->Title) {
            $imageClasses = Config::inst()->get(PerfectCMSImages::class, 'perfect_cms_images_append_title_to_image_links_classes');
            if (in_array($image->ClassName, $imageClasses, true)) {
                $link .= '?title=' . urlencode(Convert::raw2att($image->Title));
            }
        }

        return $link;
    }

    public static function web_p_enabled(): bool
    {
        if (Config::inst()->get(ImageManipulations::class, 'webp_enabled')) {
            if (function_exists('imagewebp')) {
                if (function_exists('imagecreatefromjpeg')) {
                    if (function_exists('imagecreatefrompng')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
