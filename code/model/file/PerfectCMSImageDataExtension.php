<?php

/**
 * defines the image sizes
 * and default upload folder.
 */
class PerfectCMSImageDataExtension extends DataExtension
{


    /**
     *
     * @param       string $name        PerfectCMSImages name
     * @param       bool   $inline      for use within existing image tag - optional
     * @param       string $alt         alt tag for image -optional
     * @param       string $attributes  additional attributes
     *
     * @return string (HTML)
     */
    public function PerfectCMSImageTag(string $name, $inline = false, ?string $alt = '', ?string $attributes = '') : string
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

        if(! $alt) {
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
                'Attributes' => $attributes,
            ]
        );
        $template = 'PerfectCMSImageTag';
        if($inline === true || intval($inline) === 1 || strtolower($inline) === 'true') {
            $template .= 'Inline';
        }
        return $arrayData->renderWith($template)->Raw();
    }

    /**
     * @var string $name name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkNonRetina(string $name) : string
    {
        return $this->PerfectCMSImageLink($name, false, false);
    }

    /**
     * @var string $name name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkRetina(string $name) : string
    {
        return $this->PerfectCMSImageLink($name, true, false);
    }
    /**
     * @var string $name name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkNonRetinaWebP(string $name) : string
    {
        return $this->PerfectCMSImageLink($name, false,true);
    }

    /**
     * @var string $name name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkRetinaWebP(string $name) : string
    {
        return $this->PerfectCMSImageLink($name, true, true);
    }

    /**
     * @var string $name name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkNonRetinaForMobile(string $name) : string
    {
        return $this->PerfectCMSImageLink($name, false, false, true);
    }

    /**
     * @var string $name name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkRetinaForMobile(string $name) : string
    {
        return $this->PerfectCMSImageLink($name, true, false, true);
    }
    /**
     * @var string $name name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkNonRetinaWebPForMobile(string $name) : string
    {
        return $this->PerfectCMSImageLink($name, false, true, true);
    }

    /**
     * @var string $name name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkRetinaWebPForMobile(string $name) : string
    {
        return $this->PerfectCMSImageLink($name, true, true, true);
    }


    /**
     * @var string $name name of Image Field template
     * @return string (link)
     */
    public function getPerfectCMSImageAbsoluteLink(string $link) : string
    {
        return Director::absoluteURL($link);
    }


    /**
     *
     * @param  string  $name
     * @param  boolean $useRetina
     * @param  boolean $isWebP
     * @return string
     */
    public function PerfectCMSImageLink(string $name, ?bool $useRetina = false, ?bool $isWebP = false, ?bool $forMobile = false) : string
    {
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
            
            if($isWebP) {
                $link = ImageManipulations::web_p_link($link);
            }

            $link = $link ? ImageManipulations::add_fake_parts($image, $link) : '';

            return $link;
        }
        // no image -> provide placeholder if in DEV MODE only!!!
        if (Director::isDev()) {
            return ImageManipulations::get_placeholder_image_tag($name);
        }
    }

}
