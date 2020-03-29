<?php

class PerfectCMSImages extends Object implements Flushable
{


    /**
     *.htaccess content for assets ...
     * @var string
     */
    private static $htaccess_content = <<<EOT
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.+)\.(v[A-Za-z0-9]+)\.(js|css|png|jpg|gif|svg|webp)$ $1.$3 [L]
</IfModule>

EOT;


    /**
     * force resample, put htaccess content if it does not exists.
     */
    public static function flush()
    {
        if (! Config::inst()->get('Image', 'force_resample')) {
            Config::inst()->update('Image', 'force_resample', true);
        }
        if (class_exists('HashPathExtension')) {
            if (ASSETS_PATH) {
                if (! file_exists(ASSETS_PATH)) {
                    Filesystem::makeFolder(ASSETS_PATH);
                }
                $fileName = ASSETS_PATH.'/.htaccess';
                if (! file_exists($fileName)) {
                    $string = Config::inst()->get('PerfectCMSImages', 'htaccess_content');
                    file_put_contents($fileName, $string);
                }
            }
        }
    }


    /**
     * background image for padded images...
     *
     * @var string
     */
    private static $perfect_cms_images_background_padding_color = '#cccccc';

    /***
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
     *
     * @var array
     */
    private static $perfect_cms_images_image_definitions = [];

    /***
     * Images Titles will be appended to the links only
     * if the ClassName of the Image is in this array
     * @var array
     *
     */
    private static $perfect_cms_images_append_title_to_image_links_classes = [
        Image::class,
    ];

    private static $retina_multiplier = 2;

    public static function get_description_for_cms(string $name) : string
    {
        $widthRecommendation = PerfectCMSImages::get_width($name, false);
        $heightRecommendation = PerfectCMSImages::get_height($name, false);
        $useRetina = PerfectCMSImages::use_retina($name);
        $recommendedFileType = PerfectCMSImages::get_file_type($name);
        $multiplier = PerfectCMSImages::get_multiplier($useRetina);
        if (!$recommendedFileType) {
            $recommendedFileType = 'jpg';
        }
        if ($widthRecommendation) {
            if (intval($widthRecommendation)) {
                //cater for retina
                $widthRecommendation = $widthRecommendation * $multiplier;
                $actualWidthDescription = $widthRecommendation.'px';
            } else {
                $actualWidthDescription = $widthRecommendation;
            }
        } else {
            $actualWidthDescription = 'flexible';
        }
        if ($heightRecommendation) {
            if (intval($heightRecommendation)) {
                //cater for retina
                $heightRecommendation = $heightRecommendation * $multiplier;
                $actualHeightDescription = $heightRecommendation.'px';
            } else {
                $actualHeightDescription = $heightRecommendation;
            }
        } else {
            $actualHeightDescription = 'flexible';
        }


        $rightTitle = '';

        if ($actualWidthDescription == 'flexible') {
            $rightTitle .= 'Image width is flexible';
        } else {
            $rightTitle .= "Image should to be <strong>$actualWidthDescription</strong> wide";
        }

        $rightTitle .= ' and ';

        if ($actualHeightDescription == 'flexible') {
            $rightTitle .= 'height is flexible';
        } else {
            $rightTitle .= " <strong>$actualHeightDescription</strong> tall";
        }

        $rightTitle .= '<br />';
        $maxSizeInKilobytes = PerfectCMSImages::max_size_in_kilobytes($name);
        if ($maxSizeInKilobytes) {
            $rightTitle .= 'Maximum file size: '.round($maxSizeInKilobytes / 1024, 2).' megabyte.';
            $rightTitle .= '<br />';
        }
        if ($recommendedFileType) {
            if (strlen($recommendedFileType) < 5) {
                $rightTitle .= 'The recommend file type (file extension) is <strong>'.$recommendedFileType.'</strong>.';
            } else {
                $rightTitle .= '<strong>'.$recommendedFileType.'</strong>';
            }
        }

        return $rightTitle;

    }

    /**
     * @param string           $name
     *
     * @return boolean
     */
    protected static function image_info_available(string $name) : bool
    {
        $sizes = self::get_all_values_for_images();
        //print_r($sizes);die();
        return isset($sizes[$name]) ? true : false;
    }


    /**
     * @param string           $name
     *
     * @return bool
     */
    public static function use_retina(string $name) : bool
    {
        return self::get_one_value_for_image($name, "use_retina", true);
    }

    /**
     * @param bool           $useRetina
     *
     * @return int
     */
    public static function get_multiplier(bool $useRetina) : int
    {
        $multiplier = 1;
        if ($useRetina) {
            $multiplier = Config::inst()->get('PerfectCMSImages', 'retina_multiplier');
        }
        if(! $multiplier) {
            $multiplier = 1;
        }
        return $multiplier;

    }


    /**
     * @param string           $name
     *
     * @return boolean
     */
    public static function is_crop(string $name) : bool
    {
        return self::get_one_value_for_image($name, "crop", false);
    }

    /**
     * @param string           $name
     * @param bool             $forceInteger
     *
     * @return int?string
     */
    public static function get_width(string $name, bool $forceInteger = false)
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
     * @return int|string
     */
    public static function get_height(string $name, bool $forceInteger = false)
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
    public static function get_folder(string $name) : string
    {
        return self::get_one_value_for_image($name, "folder", 'other-images');
    }

    /**
     * @param string           $name
     *
     * @return int
     */
    public static function max_size_in_kilobytes(string $name) : int
    {
        $maxSizeInKilobytes = self::get_one_value_for_image($name, "max_size_in_kilobytes", 0);;
        if (! $maxSizeInKilobytes) {
            $maxSizeInKilobytes = Config::inst()->get('PerfectCMSImagesUploadField', 'max_size_in_kilobytes');
        }
        return intval($maxSizeInKilobytes) - 0;
    }

    /**
     * @param string           $name
     *
     * @return string
     */
    public static function get_file_type(string $name) : string
    {
        return self::get_one_value_for_image($name, "filetype", 'jpg');
    }

    /**
     * @param string           $name
     *
     * @return boolean
     */
    public static function get_enforce_size(string $name) :bool
    {
        return self::get_one_value_for_image($name, "enforce_size", false);
    }

    /**
     * @param string           $name
     *
     * @return string
     */
    protected static function get_padding_bg_colour(string $name) : string
    {
        return self::get_one_value_for_image(
            $name,
            "padding_bg_colour",
            Config::inst()->get('PerfectCMSImages', 'perfect_cms_images_background_padding_color')
        );
    }

    /**
     * @param string $name
     * @param int    $key
     * @param mixed  $default
     *
     * @return mixed
     */
    protected static function get_one_value_for_image(string $name, string $key, ?string $default = '')
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
    protected static function get_all_values_for_images() : array
    {
        return Config::inst()->get('PerfectCMSImages', 'perfect_cms_images_image_definitions');
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
    protected function replaceLastInstance(string $search, string $replace, string $subject) : string
    {
        $pos = strrpos($subject, $search);

        if ($pos !== false) {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }

}
