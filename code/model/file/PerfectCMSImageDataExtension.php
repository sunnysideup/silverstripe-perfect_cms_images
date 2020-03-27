<?php

/**
 * defines the image sizes
 * and default upload folder.
 */
class PerfectCMSImageDataExtension extends DataExtension
{

    /**
     *
     * @param       string $name
     * @param       bool $inline Add only the attributes src, srcset, width, height (for use inside an existing img tag)
     * @param       string $alt alt tag for image
     *
     * @return string (HTML)
     */
    public function PerfectCMSImageTag($name, $inline = false, $alt = null) : string
    {
        $nonRetina = $this->PerfectCMSImageLinkNonRetina($name);
        $retina = $this->PerfectCMSImageLinkRetina($name);
        $width = PerfectCMSImages::get_width($name, true);
        $widthAtt = '';
        if ($width) {
            $widthAtt = ' width="'.$width.'"';
        }
        $heightAtt = '';
        $height = PerfectCMSImages::get_height($name, true);
        if ($height) {
            $heightAtt = ' height="'.$height.'"';
        }
        if(! $alt) {
            $alt = $this->owner->Title;
        }
        $imgStart = '';
        $imgEnd = '';
        $altAtt = '';
        $srcAtt = 'src="'.$nonRetina.'"';
        $srcSetAtt = ' srcset="'.$nonRetina.' 1x, '.$retina.' 2x" ';
        if($inline === false) {
            $imgStart = '<img ';
            $imgEnd = ' />';
            $altAtt = ' alt="'.Convert::raw2att($alt).'"';

        }
        return
            $imgStart.
            $altAtt.
            $srcAtt.
            $srcSetAtt.
            $widthAtt.
            $heightAtt.
            $imgEnd;
    }

    /**
     * @var string $name name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkNonRetina(string $name) : string
    {
        return $this->PerfectCMSImageLink($name, null, '', false);
    }

    /**
     * @var string $name name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkRetina(string $name) : string
    {
        return $this->PerfectCMSImageLink($name, null, '', true);
    }

    /**
     * @var string $name name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageAbsoluteLink(string $name) : string
    {
        $abs = Director::absoluteURL($this->PerfectCMSImageLink($name, null, '', true));

        return $abs;
    }


    /**
     * @param string            $name
     * @param object (optional) $backupObject
     * @param string (optional) $backupField
     *
     * @return string
     */
    public function PerfectCMSImageLink(string $name, $backupObject = null, ?string $backupField = '', ?bool $useRetina = false) : string
    {
        $image = $this->owner;
        if ($image && $image->exists()) {
            //we are all good ...
        } else {
            $image = PerfectCMSImages::get_backup_image($name, $backupObject, $backupField);
        }

        if ($image) {
            if ($image instanceof Image) {
                if ($image->exists()) {

                    // $backEndString = Image::get_backend();
                    // $backend = Injector::inst()->get($backEndString);
                    $link = PerfectCMSImages::get_image_link($image, $name, $useRetina );

                    if (class_exists('HashPathExtension')) {
                        if ($curr = Controller::curr()) {
                            if ($curr->hasMethod('HashPath')) {
                                $link = $curr->HashPath($link, false);
                            }
                        }
                    }
                    $imageClasses = Config::inst()->get('PerfectCMSImages', 'perfect_cms_images_append_title_to_image_links_classes');
                    if (in_array($image->ClassName, $imageClasses) && $image->Title) {
                        $link .= '?title=' . urlencode(Convert::raw2att($image->Title));
                    }

                    return $link;
                }
            }
        }
        // no image -> provide placeholder if in DEV MODE only!!!
        if (Director::isDev()) {
            return PerfectCMSImages::get_placeholder_image_tag();
        }
    }

}
