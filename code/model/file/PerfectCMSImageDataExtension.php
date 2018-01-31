<?php

/**
 * defines the image sizes
 * and default upload folder.
 */
class PerfectCMSImageDataExtension extends DataExtension
{
    /**
     * background image for padded images...
     *
     * @var string
     */
    private static $perfect_cms_images_background_padding_color = '#cccccc';

    /***
     * sizes of the images
     *     width: 3200
     *     height: 3200
     *     folder: "myfolder"
     *     filetype: "try jpg"
     *
     * @var array
     *
     */
    private static $perfect_cms_images_image_definitions = array();

    /***
     *  Images Titles will be appended to the links only
     *  if the ClassName of the Image is in this array
     * @var array
     *
     */
    private static $perfect_cms_images_append_title_to_image_links_classes = array();

    /**
     * @var string $name name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkNonRetina($name)
    {
        return $this->PerfectCMSImageLink($name, null, '', false);
    }

    /**
     * @var string $name name of Image Field template
     * @return string (link)
     */
    public function PerfectCMSImageLinkRetina($name)
    {
        return $this->PerfectCMSImageLink($name, null, '', true);
    }

    /**
     *
     * @param       string $name
     * @return string (HTML)
     */
    public function PerfectCMSImageTag($name)
    {
        $nonRetina = $this->PerfectCMSImageLinkNonRetina($name);
        $retina = $this->PerfectCMSImageLinkRetina($name);
        $width = self::get_width($name, true);
        $widthString = '';
        if ($width) {
            $widthString = ' width="'.$width.'"';
        }
        $heightString = '';
        $height = self::get_height($name, true);
        if ($height) {
            $heightString = ' height="'.$height.'"';
        }
        return
            '<img src="'.$nonRetina.'"'.
            ' srcset="'.$nonRetina.' 1x, '.$retina.' 2x" '.
            ' alt="'.Convert::raw2att($this->owner->Title).'"'.
            $widthString.
            $heightString.

            ' />';
    }

    /**
     * @param string            $name
     * @param object (optional) $backupObject
     * @param string (optional) $backupField
     *
     * @return string
     */
    public function PerfectCMSImageLink(
        $name,
        $backupObject = null,
        $backupField = '',
        $useRetina = null
    ) {
        if (isset($_GET['flush'])) {
            if (! Config::inst()->get('Image', 'force_resample')) {
                Config::inst()->update('Image', 'force_resample', true);
            }
        }
        $image = $this->owner;
        if ($image && $image->exists()) {
            //we are all good ...
        } else {
            if (!$backupObject) {
                $backupObject = SiteConfig::current_site_config();
            }
            if (!$backupField) {
                $backupField = $name;
            }
            if ($backupObject->hasMethod($backupField)) {
                $image = $backupObject->$backupField();
            }
        }

        $perfectWidth = self::get_width($name, true);
        $perfectHeight = self::get_height($name, true);

        if ($image) {
            if ($image instanceof Image) {
                if ($image->exists()) {
                    //work out perfect with and height
                    if (!$useRetina) {
                        $useRetina = PerfectCMSImageDataExtension::use_retina($name);
                    } else {
                        $useRetina = $useRetina;
                    }
                    $crop = PerfectCMSImageDataExtension::crop($name);
                    $multiplier = 1;
                    if ($useRetina) {
                        $multiplier = 2;
                    }
                    $perfectWidth = $perfectWidth * $multiplier;
                    $perfectHeight = $perfectHeight  * $multiplier;

                    //get current width and height
                    $myWidth = $image->getWidth();
                    $myHeight = $image->getHeight();
                    // $backEndString = Image::get_backend();
                    // $backend = Injector::inst()->get($backEndString);
                    if ($perfectWidth && $perfectHeight) {

                        //if the height or the width are already perfect then we can not do anything about it.
                        if ($myWidth == $perfectWidth && $myHeight ==  $perfectHeight) {
                            $link = $image->Link();
                        } elseif ($crop) {
                            $link = $image->Fill($perfectWidth, $perfectHeight)->Link();
                        } elseif ($myWidth < $perfectWidth || $myHeight < $perfectHeight) {
                            $link = $image->Pad(
                                $perfectWidth,
                                $perfectHeight,
                                PerfectCMSImageDataExtension::get_padding_bg_colour($name)
                            )->Link();
                        } else {
                            $link = $image->FitMax($perfectWidth, $perfectHeight)->Link();
                        }
                    } elseif ($perfectWidth) {
                        if ($myWidth == $perfectWidth) {
                            $link = $image->Link();
                        } elseif ($crop) {
                            $link = $image->Fill($perfectHeight, $myHeight)->Link();
                        } else {
                            $link = $image->ScaleWidth($perfectWidth)->Link();
                        }
                    } elseif ($perfectHeight) {
                        if ($myHeight == $perfectHeight) {
                            $link = $image->Link();
                        } elseif ($crop) {
                            $link = $image->Fill($myWidth, $perfectHeight)->Link();
                        } else {
                            $link = $image->ScaleHeight($perfectHeight)->Link();
                        }
                    } else {
                        $link = $image->ScaleWidth($myWidth)->Link();
                    }
                    $path_parts = pathinfo($link);

                    if (class_exists('HashPathExtension')) {
                        if ($curr = Controller::curr()) {
                            if ($curr->hasMethod('HashPath')) {
                                $link = $curr->HashPath($link, false);
                            }
                        }
                    }
                    $imageClasses = Config::inst()->get('PerfectCMSImageDataExtension', 'perfect_cms_images_append_title_to_image_links_classes');
                    if (in_array($image->ClassName, $imageClasses) && $image->Title) {
                        $link = $this->replaceLastInstance(
                            '.'.$path_parts['extension'],
                            '.pci/'.$image->Title.'.'.$path_parts['extension'],
                            $link
                        );
                    }

                    return $link;
                }
            }
        }
        // no image -> provide placeholder if in DEV MODE only!!!
        if (Director::isDev()) {
            if ($perfectWidth || $perfectHeight) {
                if (!$perfectWidth) {
                    $perfectWidth = $perfectHeight;
                }
                if (!$perfectHeight) {
                    $perfectHeight = $perfectWidth;
                }
                $text = "$perfectWidth x $perfectHeight /2 = ".round($perfectWidth/2)." x ".round($perfectHeight/2)."";

                return 'https://placehold.it/'.($perfectWidth).'x'.($perfectHeight).'?text='.urlencode($text);
            } else {
                return 'https://placehold.it/500x500?text='.urlencode('no size set');
            }
        }
    }

    /**
     * @param string           $name
     *
     * @return boolean
     */
    public static function image_info_available($name)
    {
        $sizes = self::get_all_values_for_images();
        //print_r($sizes);die();
        return isset($sizes[$name]) ? true : false;
    }


    /**
     * @param string           $name
     *
     * @return boolean
     */
    public static function use_retina($name)
    {
        return self::get_one_value_for_image($name, "use_retina", true);
    }


    /**
     * @param string           $name
     *
     * @return boolean
     */
    public static function crop($name)
    {
        return self::get_one_value_for_image($name, "crop", false);
    }

    /**
     * @param string           $name
     * @param bool             $forceInteger
     *
     * @return int
     */
    public static function get_width($name, $forceInteger = false)
    {
        $v = self::get_one_value_for_image($name, "width", 0);
        if ($forceInteger) {
            $v = intval($v) - 0;
        }

        return $v;
    }

    /**
     * @param string           $name
     * @param bool             $forceInteger
     *
     * @return int
     */
    public static function get_height($name, $forceInteger)
    {
        $v = self::get_one_value_for_image($name, "height", 0);
        if ($forceInteger) {
            $v = intval($v) - 0;
        }

        return $v;
    }

    /**
     * @param string           $name
     *
     * @return string
     */
    public static function get_folder($name)
    {
        return self::get_one_value_for_image($name, "folder", 'other-images');
    }

    /**
     * @param string           $name
     *
     * @return int
     */
    public static function max_size_in_kilobytes($name)
    {
        return self::get_one_value_for_image($name, "max_size_in_kilobytes", 0);
    }

    /**
     * @param string           $name
     *
     * @return string
     */
    public static function get_file_type($name)
    {
        return self::get_one_value_for_image($name, "filetype", 'jpg');
    }

    /**
     * @param string           $name
     *
     * @return boolean
     */
    public static function get_enforce_size($name)
    {
        return self::get_one_value_for_image($name, "enforce_size", false);
    }

    /**
     * @param string           $name
     *
     * @return boolean
     */
    public static function get_padding_bg_colour($name)
    {
        return self::get_one_value_for_image(
            $name,
            "padding_bg_colour",
            Config::inst()->get('PerfectCMSImageDataExtension', 'perfect_cms_images_background_padding_color')
        );
    }

    /**
     * @param string $name
     * @param int    $key
     * @param mixed  $default
     *
     * @return mixed
     */
    private static function get_one_value_for_image($name, $key, $default = '')
    {
        $sizes = self::get_all_values_for_images();
        //print_r($sizes);die();
        if (isset($sizes[$name])) {
            if (isset($sizes[$name][$key])) {
                return $sizes[$name][$key];
            }
        } else {
            user_error('no information for image with name: '.$name);
        }

        return $default;
    }

    /**
     * @return array
     */
    private static function get_all_values_for_images()
    {
        return Config::inst()->get('PerfectCMSImageDataExtension', 'perfect_cms_images_image_definitions');
    }

    /**
     * replace the last instance of a string occurence.
     *
     * @param  string $search  needle
     * @param  string $replace new needle
     * @param  string $subject haystack
     *
     * @return string
     */
    private function replaceLastInstance($search, $replace, $subject)
    {
        $pos = strrpos($subject, $search);

        if ($pos !== false) {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }
}
