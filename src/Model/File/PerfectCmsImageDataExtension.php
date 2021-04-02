<?php

namespace Sunnysideup\PerfectCmsImages\Model\File;

use SilverStripe\Assets\Image;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\ArrayData;
use Sunnysideup\PerfectCmsImages\Api\ImageManipulations;
use Sunnysideup\PerfectCmsImages\Api\PerfectCMSImages;

/**
 * defines the image sizes
 * and default upload folder.
 */
class PerfectCmsImageDataExtension extends DataExtension
{
    private static $casting = [
        'PerfectCMSImageTag' => 'HTMLText',
    ];

    /**
     * @param       string $name        PerfectCMSImages name
     * @param       bool   $inline      for use within existing image tag - optional
     * @param       string $alt         alt tag for image -optional
     * @param       string $attributes  additional attributes
     *
     * @return string (HTML)
     */
    public function getPerfectCMSImageTag(string $name, $inline = false, ?string $alt = '', ?string $attributes = '')
    {
        return $this->PerfectCMSImageTag($name, $inline, $alt, $attributes);
    }

    public function PerfectCMSImageTag(string $name, $inline = false, ?string $alt = '', ?string $attributes = '')
    {
        $retinaLink = $this->PerfectCMSImageLinkRetina($name);
        $nonRetinaLink = $this->PerfectCMSImageLinkNonRetina($name);

        $retinaLinkWebP = $this->PerfectCMSImageLinkRetinaWebP($name);
        $nonRetinaLinkWebP = $this->PerfectCMSImageLinkNonRetinaWebP($name);

        $mobileRetinaLink = $this->PerfectCMSImageLinkRetinaForMobile($name);
        $mobileNonRetinaLink = $this->PerfectCMSImageLinkNonRetinaForMobile($name);

        $mobileRetinaLinkWebP = $this->PerfectCMSImageLinkRetinaWebPForMobile($name);
        $mobileNonRetinaLinkWebP = $this->PerfectCMSImageLinkNonRetinaWebPForMobile($name);

        $width = PerfectCMSImages::get_width($name, true);
        $height = PerfectCMSImages::get_height($name, true);
        $mobileMediaWidth = PerfectCMSImages::get_mobile_media_width($name);

        if (! $alt) {
            $alt = $this->owner->Title;
        }

        $arrayData = ArrayData::create(
            [
                'MobileMediaWidth' => $mobileMediaWidth,
                'Width' => $width,
                'Height' => $height,
                'Alt' => Convert::raw2att($alt),
                'MobileRetinaLink' => $mobileRetinaLink,
                'MobileNonRetinaLink' => $mobileNonRetinaLink,
                'MobileRetinaLinkWebP' => $mobileRetinaLinkWebP,
                'MobileNonRetinaLinkWebP' => $mobileNonRetinaLinkWebP,
                'RetinaLink' => $retinaLink,
                'NonRetinaLink' => $nonRetinaLink,
                'RetinaLinkWebP' => $retinaLinkWebP,
                'NonRetinaLinkWebP' => $nonRetinaLinkWebP,
                'Attributes' => DBField::create_field('HTMLText', $attributes),
            ]
        );
        $template = 'Includes/PerfectCMSImageTag';
        if ($inline === true || (int) $inline === 1 || strtolower($inline) === 'true') {
            $template .= 'Inline';
        }
        return DBField::create_field('HTMLText', $arrayData->renderWith($template));
    }

    /**
     * @var string name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkNonRetina(string $name): string
    {
        return $this->PerfectCMSImageLink($name);
    }

    /**
     * @var string name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkRetina(string $name): string
    {
        return $this->PerfectCMSImageLink($name, true);
    }

    /**
     * @var string name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkNonRetinaWebP(string $name): string
    {
        return $this->PerfectCMSImageLink($name, false, true);
    }

    /**
     * @var string name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkRetinaWebP(string $name): string
    {
        return $this->PerfectCMSImageLink($name, true, true);
    }

    /**
     * @var string name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkNonRetinaForMobile(string $name): string
    {
        return $this->PerfectCMSImageLink($name, false, false, true);
    }

    /**
     * @var string name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkRetinaForMobile(string $name): string
    {
        return $this->PerfectCMSImageLink($name, true, false, true);
    }

    /**
     * @var string name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkNonRetinaWebPForMobile(string $name): string
    {
        return $this->PerfectCMSImageLink($name, false, true, true);
    }

    /**
     * @var string name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkRetinaWebPForMobile(string $name): string
    {
        return $this->PerfectCMSImageLink($name, true, true, true);
    }

    /**
     * @var string name of Image Field template
     * @return string (link)
     */
    public function getPerfectCMSImageAbsoluteLink(string $link): string
    {
        return Director::absoluteURL($link);
    }

    /**
     * returns image link
     */
    public function PerfectCMSImageLink(string $name, ?bool $useRetina = false, ?bool $isWebP = false, ?bool $forMobile = false): ?string
    {
        /** @var Image|null $image */
        $image = $this->owner;
        if ($image && $image->exists() && $image instanceof Image) {
            //we are all good ...
        } else {
            $image = ImageManipulations::get_backup_image($name);
        }

        if ($image && $image->exists() && $image instanceof Image) {
            // $backEndString = Image::get_backend();
            // $backend = Injector::inst()->get($backEndString);
            $link = ImageManipulations::get_image_link($image, $name, $useRetina, $forMobile);

            if ($isWebP) {
                $link = ImageManipulations::web_p_link($link);
            }

            return $link !== '' ? ImageManipulations::add_fake_parts($image, $link) : '';
        }
        // no image -> provide placeholder if in DEV MODE only!!!
        if (Director::isDev()) {
            return ImageManipulations::get_placeholder_image_tag($name);
        }
        return null;
    }
}
