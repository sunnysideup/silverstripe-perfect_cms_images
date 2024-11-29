<?php

namespace Sunnysideup\PerfectCmsImages\Api;

use Psr\Log\LoggerInterface;
use SilverStripe\Assets\Filesystem;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\PerfectCmsImages\Forms\PerfectCmsImagesUploadField;
use Sunnysideup\PerfectCmsImages\Model\File\PerfectCmsImageDataExtension;

class PerfectCMSImages implements Flushable
{
    use Configurable;
    use Injectable;

    /**
     * background image for padded images...
     *
     * @var string
     */
    private static string $perfect_cms_images_background_padding_color = '#cccccc';

    /**
     * @var array
     */
    private static array $perfect_cms_images_image_definitions = [];

    public const MULTI_USE_CODE = 'multiuse';
    public const UNUSED_CODE = 'unused';

    /**
     *.htaccess content for assets ...
     *
     * @var string
     */
    private static $htaccess_content = <<<'EOT'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.+)\.(v[A-Za-z0-9]+)\.(png|jpg|gif|svg|webp)$ $1.$3 [L]
</IfModule>

EOT;

    private static $unused_images_folder_name = 'unusedimages';

    private static $multiuse_images_folder_name = 'multiuseimages';

    /**
     * used to set the max width of the media value for mobile images,
     * eg <source srcset="small.jpg, small2x.jpg 2x" media="(max-width: 600px)">.
     *
     * @var string
     */
    private static $mobile_media_max_width = '600px';

    /*
     * Images Titles will be appended to the links only
     * if the ClassName of the Image is in this array
     * @var array
     *
     */
    private static $perfect_cms_images_append_title_to_image_links_classes = [
        Image::class,
    ];

    private static $retina_multiplier = 2;

    /**
     * force resample, put htaccess content if it does not exists.
     */
    public static function flush()
    {
        if (isset($_GET['forceresample'])) {
            Config::modify()->set(Image::class, 'force_resample', true);
        }

        if (class_exists('HashPathExtension')) {
            if (! file_exists(ASSETS_PATH)) {
                Filesystem::makeFolder(ASSETS_PATH);
            }

            $fileName = ASSETS_PATH . '/.htaccess';
            if (! file_exists($fileName)) {
                $string = Config::inst()->get(PerfectCMSImages::class, 'htaccess_content');
                file_put_contents($fileName, $string);
            }
        }
    }

    public static function get_description_for_cms(string $name): string
    {
        $widthRecommendation = (int) PerfectCMSImages::get_width($name);
        $heightRecommendation = (int) PerfectCMSImages::get_height($name);
        $useRetina = PerfectCMSImages::use_retina($name);
        $recommendedFileType = PerfectCMSImages::get_file_type($name);
        $multiplier = PerfectCMSImages::get_multiplier($useRetina);
        if ('' === $recommendedFileType) {
            $recommendedFileType = 'jpg';
        }

        if (0 !== $widthRecommendation) {
            //cater for retina
            $widthRecommendation *= $multiplier;
            $actualWidthDescription = $widthRecommendation . 'px';
        } else {
            $actualWidthDescription = $widthRecommendation;
            $actualWidthDescription = 'flexible';
        }

        if (0 !== $heightRecommendation) {
            //cater for retina
            $heightRecommendation *= $multiplier;
            $actualHeightDescription = $heightRecommendation . 'px';
        } else {
            $actualHeightDescription = $heightRecommendation;
            $actualHeightDescription = 'flexible';
        }

        $rightTitle = '<span>';

        if ('flexible' === $actualWidthDescription) {
            $rightTitle .= 'Image width is flexible';
        } else {
            $rightTitle .= "Image should to be <strong>{$actualWidthDescription}</strong> wide";
        }

        $rightTitle .= ' and ';

        if ('flexible' === $actualHeightDescription) {
            $rightTitle .= 'height is flexible';
        } else {
            $rightTitle .= " <strong>{$actualHeightDescription}</strong> tall";
        }

        $rightTitle .= '<br />';
        $maxSizeInKilobytes = PerfectCMSImages::max_size_in_kilobytes($name);
        if (0 !== $maxSizeInKilobytes) {
            $rightTitle .= 'Maximum file size: ' . round($maxSizeInKilobytes / 1024, 2) . ' megabyte.';
            $rightTitle .= '<br />';
        }

        if (strlen($recommendedFileType) < 5) {
            $rightTitle .= 'The recommend file type (file extension) is <strong>' . $recommendedFileType . '</strong>.';
        } else {
            $rightTitle .= '<strong>' . $recommendedFileType . '</strong>';
        }
        $rightTitle .= '<br />You can also use a service like <a href="https://tinypng.com/" target="_blank" rel="noreferrer">TinyPNG</a> to reduce the file size and convert it.';

        return $rightTitle . '</span>';
    }

    public static function use_retina(string $name): bool
    {
        return self::get_one_value_for_image($name, 'use_retina', true);
    }

    public static function get_multiplier(bool $useRetina): int
    {
        $multiplier = 1;
        if ($useRetina) {
            $multiplier = Config::inst()->get(PerfectCMSImages::class, 'retina_multiplier');
        }

        if (! $multiplier) {
            $multiplier = 1;
        }

        return $multiplier;
    }

    public static function is_crop(string $name): bool
    {
        return self::get_one_value_for_image($name, 'crop', false);
    }

    /**
     * @return int|string
     */
    public static function get_width(string $name, bool $forceInteger = false)
    {
        $v = self::get_one_value_for_image($name, 'width', 0);
        if ($forceInteger) {
            $v = (int) $v;
        }

        return $v;
    }

    /**
     * @return int|string
     */
    public static function get_height(string $name, bool $forceInteger = false)
    {
        $v = self::get_one_value_for_image($name, 'height', 0);
        if ($forceInteger) {
            $v = (int) $v;
        }

        return $v;
    }

    public static function has_mobile($name): bool
    {
        return self::get_mobile_width($name, true) || self::get_mobile_height($name, true);
    }

    /**
     * @return int?string
     */
    public static function get_mobile_width(string $name, bool $forceInteger = false)
    {
        $v = self::get_one_value_for_image($name, 'mobile_width', 0);
        if ($forceInteger) {
            $v = (int) $v;
        }

        return $v;
    }

    /**
     * @return int|string
     */
    public static function get_mobile_height(string $name, bool $forceInteger = false)
    {
        $v = self::get_one_value_for_image($name, 'mobile_height', 0);
        if ($forceInteger) {
            $v = (int) $v;
        }

        return $v;
    }

    public static function get_folder(string $name): string
    {
        return self::get_one_value_for_image($name, 'folder', 'other-images');
    }

    public static function move_to_right_folder(string $name): bool
    {
        return self::get_one_value_for_image($name, 'move_to_right_folder', true);
    }

    public static function loading_style(string $name): string
    {
        return self::get_one_value_for_image($name, 'loading_style', 'lazy');
    }

    public static function max_size_in_kilobytes(string $name): int
    {
        $maxSizeInKilobytes = self::get_one_value_for_image($name, 'max_size_in_kilobytes', 0);
        if (! $maxSizeInKilobytes) {
            $maxSizeInKilobytes = Config::inst()->get(PerfectCmsImagesUploadField::class, 'max_size_in_kilobytes');
        }

        return (int) $maxSizeInKilobytes;
    }

    public static function get_file_type(string $name): string
    {
        return self::get_one_value_for_image($name, 'filetype', 'webp');
    }

    public static function get_enforce_size(string $name): bool
    {
        return self::get_one_value_for_image($name, 'enforce_size', false);
    }

    /**
     * @return null|string
     */
    public static function get_mobile_media_width(string $name)
    {
        return self::get_one_value_for_image(
            $name,
            'mobile_media_max_width',
            Config::inst()->get(PerfectCMSImages::class, 'mobile_media_max_width')
        );
    }

    public static function get_padding_bg_colour(string $name): string
    {
        return self::get_one_value_for_image(
            $name,
            'padding_bg_colour',
            Config::inst()->get(PerfectCMSImages::class, 'perfect_cms_images_background_padding_color')
        );
    }

    public static function image_info_available(string $name): bool
    {
        $sizes = self::get_all_values_for_images();
        //print_r($sizes);die();
        return isset($sizes[$name]);
    }

    public static function get_all_values_for_images(): array
    {
        return Config::inst()->get(
            PerfectCMSImages::class,
            'perfect_cms_images_image_definitions'
        ) ?: [];
    }

    public static function get_resizer_conversion(string $name): array
    {
        // if(class_exists('\\Sunnysideup\\ScaledUploads\\Api\\Resizer')) {
        //     if(self::get_auto_resize($name))
        //     $array = [
        //         'maxWidth' => self::get_width()
        //         'maxHeight' =>
        //         'maxSizeInMb' =>
        //         'maxSizeInMb' =>
        //     ]
        // }
        // $sizes = self::get_all_values_for_images();
        // *     - width: 3200
        // *     - height: 3200
        // *     - max_mb: 0.4
        // *     - folder: "myfolder"
        // *     - filetype: "try jpg"
        // *     - enforce_size: false
        // *     - skip_auto_resize: false
        // *     - skip_auto_convert: false
        // *     - folder: my-image-folder-a
        // *     - filetype: "jpg or a png with a transparant background"
        // *     - use_retina: true
        // *     - padding_bg_colour: '#dddddd'
        // *     - crop: true
        // *     - move_to_right_folder: true
        // *     - loading_style: 'eager'
        // *     - used_by:
        // *       - MyClass.MyHasOne
        // *       - MyOtherClass.MyHasManyMethod
        // *       - MyOtherClass.MyManyManyRel
        return [];
    }

    /**
     * @param mixed $default - optional
     *
     * @return mixed
     */
    protected static function get_one_value_for_image(string $name, string $key, $default = '')
    {
        $sizes = self::get_all_values_for_images();

        return $sizes[$name][$key] ?? $default;
        // Injector::inst()->get(LoggerInterface::class)->info('no information for image with the name: ' . $name . '.' . $key);
    }

}
