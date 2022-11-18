<?php

namespace Sunnysideup\PerfectCmsImages\Model\File;

use SilverStripe\Assets\Image;
use SilverStripe\Assets\Folder;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\ArrayData;
use Sunnysideup\PerfectCmsImages\Api\ImageManipulations;
use Sunnysideup\PerfectCmsImages\Api\PerfectCMSImages;

/**
 * defines the image sizes
 * and default upload folder.
 */
class PerfectCmsImageDataExtension extends DataExtension
{

    /**
     * background image for padded images...
     *
     * @var string
     */
    private static $perfect_cms_images_background_padding_color = '#cccccc';

    /*
     * details of the images
     *     - width: 3200
     *     - height: 3200
     *     - folder: "myfolder"
     *     - filetype: "try jpg"
     *     - enforce_size: false
     *     - folder: my-image-folder-a
     *     - filetype: "jpg or a png with a transparant background"
     *     - use_retina: true
     *     - padding_bg_colour: '#dddddd'
     *     - crop: true
     *     - move_to_right_folder: true
     *     - loading_style: 'eager'
     * @var array
     */
    private static $perfect_cms_images_image_definitions = [];

    private static $casting = [
        'PerfectCMSImageTag' => 'HTMLText',
    ];

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
        $arrayData = $this->getPerfectCMSImageTagArrayData($name, $inline, $alt, $attributes);
        $template = 'Includes/PerfectCMSImageTag';
        if (true === $inline || 1 === (int) $inline || 'true' === strtolower($inline)) {
            $template .= 'Inline';
        }

        return DBField::create_field('HTMLText', $arrayData->renderWith($template));
    }

    /**
     * @param string $name       PerfectCMSImages name
     * @param bool   $inline     for use within existing image tag - optional. can be TRUE, "TRUE" or 1 also...
     * @param string $alt        alt tag for image -optional
     * @param string $attributes additional attributes
     *
     * @return ArrayData
     */
    public function PerfectCMSImageTagArrayData(string $name, $inline = false, ?string $alt = '', ?string $attributes = '')
    {
        return $this->getPerfectCMSImageTagArrayData($name, $inline, $alt, $attributes);
    }

    /**
     * @param string $name       PerfectCMSImages name
     * @param bool   $inline     for use within existing image tag - optional. can be TRUE, "TRUE" or 1 also...
     * @param string $alt        alt tag for image -optional
     * @param string $attributes additional attributes
     *
     * @return ArrayData
     */
    public function getPerfectCMSImageTagArrayData(string $name, $inline = false, ?string $alt = '', ?string $attributes = '')
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
        $loadingStyle = PerfectCMSImages::loading_style($name);
        $mobileMediaWidth = PerfectCMSImages::get_mobile_media_width($name);

        if (! $alt) {
            $alt = $this->getOwner()->Title;
        }

        return ArrayData::create(
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
                'LoadingStyle' => $loadingStyle,
                'Attributes' => DBField::create_field('HTMLText', $attributes),
            ]
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
    public function PerfectCMSImageLinkNonRetinaWebP(string $name): string
    {
        return $this->PerfectCMSImageLink($name, false, true);
    }

    /**
     * @param string $name of Image Field template
     *
     * @return string (link)
     */
    public function PerfectCMSImageLinkRetinaWebP(string $name): string
    {
        return $this->PerfectCMSImageLink($name, true, true);
    }

    /**
     * @param string $name of Image Field template
     *
     * @return string (link)
     */
    public function PerfectCMSImageLinkNonRetinaForMobile(string $name): string
    {
        return $this->PerfectCMSImageLink($name, false, false, true);
    }

    /**
     * @param string $name of Image Field template
     *
     * @return string (link)
     */
    public function PerfectCMSImageLinkRetinaForMobile(string $name): string
    {
        return $this->PerfectCMSImageLink($name, true, false, true);
    }

    /**
     * @param string $name of Image Field template
     *
     * @return string (link)
     */
    public function PerfectCMSImageLinkNonRetinaWebPForMobile(string $name): string
    {
        return $this->PerfectCMSImageLink($name, false, true, true);
    }

    /**
     * @param string $name of Image Field template
     *
     * @return string (link)
     */
    public function PerfectCMSImageLinkRetinaWebPForMobile(string $name): string
    {
        return $this->PerfectCMSImageLink($name, true, true, true);
    }

    /**
     * @param string $link
     *
     * @return string (link)
     */
    public function getPerfectCMSImageAbsoluteLink(string $link): string
    {
        return Director::absoluteURL($link);
    }

    /**
     * returns image link (if any).
     */
    public function PerfectCMSImageLink(string $name, ?bool $useRetina = false, ?bool $isWebP = false, ?bool $forMobile = false): string
    {
        /** @var null|Image $image */
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

            return '' !== $link ? ImageManipulations::add_fake_parts($image, $link) : '';
        }

        // no image -> provide placeholder if in DEV MODE only!!!
        if (Director::isDev()) {
            return ImageManipulations::get_placeholder_image_tag($name);
        }

        return '';
    }

    public function PerfectCMSImageFixFolder($name, ?string $folderName = ''): ?Folder
    {
        $folder = null;
        if(PerfectCMSImages::move_to_right_folder($name) || $folderName) {
            $image = $this->getOwner();
            if($image && $image->exists()) {
                if(! $folderName) {
                    $folderName = PerfectCMSImages::get_folder($name);
                }
                $folder = Folder::find_or_make($folderName);
                if(!$folder->exists()) {
                    $folder->write();
                }
                if ($image->ParentID !== $folder->ID) {
                    $wasPublished = $image->isPublished() && ! $image->isModifiedOnDraft();;
                    $image->ParentID = $folder->ID;
                    $image->write();
                    if($wasPublished) {
                        $image->publishSingle();
                    }
                }
            } else {
                // user_error('could not find image');
            }
        }
        return $folder;
    }

    public function getThumbnail() {
        if($this->owner->ID){
            if($this->owner->getExtension() == 'svg'){
                $obj= DBHTMLText::create();
                $obj->setValue(file_get_contents(BASE_PATH.$this->owner->Link()));
                return $obj;
            }else {
                return $this->owner->CMSThumbnail();
            }
        } else {
            return $this->owner->CMSThumbnail();
        }
    }

    public function updatePreviewLink(&$link, $action)
    {
        $owner = $this->getOwner();
        if($this->owner->getExtension() == 'svg'){
            return $owner->Link();
        }
        return $link;
    }

}
