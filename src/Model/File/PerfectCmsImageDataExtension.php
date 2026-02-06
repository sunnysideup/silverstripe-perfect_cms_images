<?php

namespace Sunnysideup\PerfectCmsImages\Model\File;

use Exception;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\ArrayData;
use Sunnysideup\PerfectCmsImages\Api\ImageManipulations;
use Sunnysideup\PerfectCmsImages\Api\PerfectCMSImages;

/**
 * defines the image sizes
 * and default upload folder.
 *
 * @property Image|PerfectCmsImageDataExtension $owner
 */
class PerfectCmsImageDataExtension extends Extension
{
    private static $casting = [
        'PerfectCMSImageTag' => 'HTMLText',
    ];

    /**
     * you can provide as many arguments as needed here
     *
     * @param string $method
     * @param [mixed] $args- zero to many arguments
     */
    public function getImageLinkCachedIfExists($method, $args = null): string
    {
        $image = $this->getOwner();
        if (! $image->canView()) {
            return '';
        }
        $args = func_get_args();
        //remove the method argument
        array_shift($args);

        $variant = $image->variantName($method, ...$args);
        $store = Injector::inst()->get(AssetStore::class);
        if ($store->exists($image->getFilename(), $image->getHash(), $variant)) {
            return $store->getAsURL($image->getFilename(), $image->getHash(), $variant, false);
        } else {
            try {
                $resizeImage = $image->$method(
                    ...$args
                );
                if ($resizeImage) {
                    return $resizeImage->Link();
                }
            } catch (Exception $e) {
                return $image->Link();
            }
        }
        return '';
    }

    /**
     * @param string $name       PerfectCMSImages name
     * @param bool   $inline     for use within existing image tag - optional
     * @param string $alt        alt tag for image -optional
     * @param string $attributes additional attributes
     *
     * @return string (HTML)
     */
    public function getPerfectCMSImageTag(string $name, $inline = false, ?string $alt = '', ?string $attributes = '')
    {
        return $this->PerfectCMSImageTag($name, $inline, $alt, $attributes);
    }

    /**
     * @param string $name       PerfectCMSImages name
     * @param bool   $inline     for use within existing image tag - optional. can be TRUE, "TRUE" or 1 also...
     * @param string $alt        alt tag for image -optional
     * @param string $attributes additional attributes
     *
     * @return string (HTML)
     */
    public function PerfectCMSImageTag(string $name, $inline = false, ?string $alt = '', ?string $attributes = '')
    {
        $cacheKey = $this->getPerfectCMSImagesTagCacheKey($name . $inline . $alt . $attributes);
        $cache = $this->getPerfectCMSImagesTagCache();
        if ($cacheKey && $cache->has($cacheKey)) {
            return $cache->get($cacheKey);
        }
        $arrayData = $this->getPerfectCMSImageTagArrayData($name, $alt, $attributes);
        $template = 'Includes/PerfectCMSImageTag';
        if (true === $inline || 1 === (int) $inline || 'true' === strtolower($inline)) {
            $template .= 'Inline';
        }
        $string = DBField::create_field('HTMLText', $arrayData->renderWith($template));
        if ($cacheKey && $cache) {
            $cache->set($cacheKey, $string);
        }
        return $string;
    }

    protected function getPerfectCMSImagesTagCacheKey($toAdd)
    {
        if (! $this->owner->isPublished()) {
            return null;
        }
        return 'PCI' . $this->owner->ID . '_' . strtotime($this->owner->LastEdited) . $toAdd;
    }

    protected function getPerfectCMSImagesTagCache()
    {
        return Injector::inst()->get(CacheInterface::class . '.perfectcmsimages');
    }

    /**
     * @param string $name       PerfectCMSImages name
     * @param string $alt        alt tag for image -optional
     * @param string $attributes additional attributes
     *
     * @return ArrayData
     */
    private function getPerfectCMSImageTagArrayData(string $name, ?string $alt = '', ?string $attributes = '')
    {
        $retinaLink = $this->PerfectCMSImageLinkRetina($name);
        $nonRetinaLink = $this->PerfectCMSImageLinkNonRetina($name);

        $width = PerfectCMSImages::get_width($name, true);
        $height = PerfectCMSImages::get_height($name, true);
        $hasMobile = PerfectCMSImages::has_mobile($name);

        if ($hasMobile) {
            $mobileRetinaLink = $this->PerfectCMSImageLinkRetinaForMobile($name);
            $mobileNonRetinaLink = $this->PerfectCMSImageLinkNonRetinaForMobile($name);
            $mobileMediaWidth = PerfectCMSImages::get_mobile_media_width($name);
        }

        if (! $alt) {
            $alt = $this->getOwner()->Title;
        }
        $myArray = [
            'Width' => $width,
            'Height' => $height,
            'Alt' => Convert::raw2att($alt),
            'RetinaLink' => $retinaLink,
            'NonRetinaLink' => $nonRetinaLink,
            'Type' => $this->owner->getMimeType(),
            'Attributes' => DBField::create_field('HTMLText', $attributes),
        ];
        if ($hasMobile) {
            $myArray += [
                'MobileMediaWidth' => $mobileMediaWidth,
                'MobileRetinaLink' => $mobileRetinaLink,
                'MobileNonRetinaLink' => $mobileNonRetinaLink,

            ];
        }

        return ArrayData::create(
            $myArray
        );
    }

    /**
     * @param string $name of Image Field template
     *
     * @return string (link)
     */
    public function PerfectCMSImageLinkNonRetina(string $name): string
    {
        return $this->PerfectCMSImageLink($name);
    }

    /**
     * @param string $name of Image Field template
     *
     * @return string (link)
     */
    public function PerfectCMSImageLinkRetina(string $name): string
    {
        return $this->PerfectCMSImageLink($name, true);
    }

    /**
     * @param string $name of Image Field template
     *
     * @return string (link)
     */
    public function PerfectCMSImageLinkNonRetinaForMobile(string $name): string
    {
        return $this->PerfectCMSImageLink($name, false, true);
    }

    /**
     * @param string $name of Image Field template
     *
     * @return string (link)
     */
    public function PerfectCMSImageLinkRetinaForMobile(string $name): string
    {
        return $this->PerfectCMSImageLink($name, true, true);
    }

    /**
     * @return string (link)
     */
    public function getPerfectCMSImageAbsoluteLink(string $link): string
    {
        return Director::absoluteURL($link);
    }

    /**
     * returns image link (if any).
     */
    public function PerfectCMSImageLink(
        string $name,
        ?bool $useRetina = true,
        ?bool $forMobile = false
    ): string {
        /** @var null|Image $image */
        $image = $this->owner;
        $allOk = false;
        if ($image && $image->exists() && $image instanceof Image) {
            $allOk = true;
            //we are all good ...
        } else {
            $image = ImageManipulations::get_backup_image($name);
            if ($image && $image->exists() && $image instanceof Image) {
                $allOk = true;
            }
        }

        if ($allOk) {
            // $backEndString = Image::get_backend();
            // $backend = Injector::inst()->get($backEndString);
            $link = ImageManipulations::get_image_link($image, $name, $useRetina, $forMobile);
            if (! $link || $link === '0' || $link === '') {
                $link = $image->Link();
            }
            return ImageManipulations::add_fake_parts($image, $link);
        } elseif (Director::isDev()) {
            // no image -> provide placeholder if in DEV MODE only!!!
            return ImageManipulations::get_placeholder_image_tag($name);
        }

        // no image -> provide placeholder if in DEV MODE only!!!
        if (Director::isDev()) {
            return ImageManipulations::get_placeholder_image_tag($name);
        }

        return '';
    }

    public function PerfectCMSImageFixFolder($name, ?string $folderName = ''): ?Folder
    {
        if (! $name) {
            $name = 'Uploads';
        }
        $folder = null;
        if (PerfectCMSImages::move_to_right_folder($name) || $folderName) {
            $image = $this->getOwner();
            if ($image) {
                if (! $folderName) {
                    $folderName = PerfectCMSImages::get_folder($name);
                }
                $folder = Folder::find_or_make($folderName);
                if (! $folder->ID) {
                    $folder->write();
                }
                if ($image->ParentID !== $folder->ID) {
                    $wasPublished = $image->isPublished() && ! $image->isModifiedOnDraft();
                    $image->ParentID = $folder->ID;
                    $image->write();
                    if ($wasPublished) {
                        $image->publishRecursive();
                    }
                }
            }
            // user_error('could not find image');
        }

        return $folder;
    }

    public function getCMSThumbnail()
    {
        $owner = $this->getOwner();
        if ($owner->ID) {
            return $this->getImageAsSVG() ?? $owner->CMSThumbnail();
        }

        return $owner->CMSThumbnail();
    }

    public function IsSVG(): bool
    {
        return 'svg' === $this->owner->getExtension();
    }

    public function getSVGFormat()
    {
        $owner = $this->getOwner();
        if ($owner->ID) {
            return $this->getImageAsSVG() ?? $owner->forTemplate();
        }

        return $owner->forTemplate();
    }

    public function getImageAsSVG(): DBHTMLText|null
    {
        $owner = $this->getOwner();
        if ($this->IsSVG()) {
            $data = file_get_contents(PUBLIC_PATH . $owner->Link());
            return DBHTMLText::create_field('HTMLText', $data);
        }
        return null;
    }

    public function updatePreviewLink(&$link, $action)
    {
        $owner = $this->getOwner();
        if ('svg' === $this->owner->getExtension()) {
            return $owner->Link();
        }

        return $link;
    }
}
