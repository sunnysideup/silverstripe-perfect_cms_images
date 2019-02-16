<?php

namespace Sunnysideup\PerfectCMSImages\Forms;

use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\FieldType\DBField;
use Sunnysideup\PerfectCMSImages\Filesystem\PerfectCMSImage_Validator;
use Sunnysideup\PerfectCMSImages\Model\File\PerfectCMSImageDataExtension;
use SilverStripe\Core\Config\Config;
use Sunnysideup\PerfectCMSImages\Forms\PerfectCMSImagesUploadField;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Filesystem;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Core\Flushable;

/**
 * image-friendly upload field.

 * Usage:
 *     $field = PerfectCMSImagesUploadFielde::create(
 *         "ImageField",
 *         "Add Image",
 *         null,
 * 	);
 */
class PerfectCMSImagesUploadField extends UploadField implements Flushable
{
    private static $max_size_in_kilobytes = 1024;

    private static $folder_prefix = '';

    /**
     * @param string  $name
     * @param string  $title
     * @param SS_List|null $items If no items are defined, the field will try to auto-detect an existing relation
     * @param string|null $alternativeName
     *
     * @return UploadField
     */
    public function __construct(
        $name,
        $title,
        SS_List $items = null,
        $alternativeName = null
    ) {
        parent::__construct(
            $name,
            $title,
            $items
        );
        $perfectCMSImageValidator = new PerfectCMSImage_Validator();
        $this->setValidator($perfectCMSImageValidator);
        if ($alternativeName === null) {
            $alternativeName = $name;
        }
        $this->selectFormattingStandard($alternativeName);

        return $this;
    }

    public function setRightTitle($string)
    {
        parent::setRightTitle(
            DBField::create_field('HTMLText',
                $string.
                '<br />'.
                $this->RightTitle()
            )
        );
        //important!
        return $this;
    }

    /**
     *
     *
     *
     * @param  string $name Formatting Standard
     * @return this
     */
    public function selectFormattingStandard($name)
    {
        parent::setRightTitle('');
        $widthRecommendation = PerfectCMSImageDataExtension::get_width($name, false);
        $heightRecommendation = PerfectCMSImageDataExtension::get_height($name, false);
        $useRetina = PerfectCMSImageDataExtension::use_retina($name);
        $multiplier = 1;
        if ($useRetina) {
            $multiplier = 2;
        }
        $maxSizeInKilobytes = PerfectCMSImageDataExtension::max_size_in_kilobytes($name);
        if (! $maxSizeInKilobytes) {
            $maxSizeInKilobytes = Config::inst()->get(PerfectCMSImagesUploadField::class, 'max_size_in_kilobytes');
        }

        if ($this->folderName) {
            $folderName = $this->folderName;
        } else {
            //folder related stuff ...
            $folderName = PerfectCMSImageDataExtension::get_folder($name);
            $folderPrefix = $this->Config()->get('folder_prefix');
            if (!$folderName) {
                $folderName = 'other-images';
            }
            $folderName = implode(
                '/',
                array_filter([$folderPrefix, $folderName])
            );
        }
        //create folder
        Folder::find_or_make($folderName);
        //set folder
        $this->setFolderName($folderName);

        $recommendedFileType = PerfectCMSImageDataExtension::get_file_type($name);
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


        $rightTitle = "";

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


        parent::setRightTitle(
            DBField::create_field('HTMLText', $rightTitle)
        );


        $this->setAllowedFileCategories('image');
        $alreadyAllowed = $this->getAllowedExtensions();
        $this->setAllowedExtensions($alreadyAllowed + array('svg'));
        //keep the size reasonable
        $this->getValidator()->setAllowedMaxFileSize(1 * 1024 * $maxSizeInKilobytes);
        $this->getValidator()->setFieldName($name);
        return $this;
    }

    public static function flush()
    {
        if (class_exists('HashPathExtension')) {
            if (ASSETS_PATH) {
                if (! file_exists(ASSETS_PATH)) {
                    Filesystem::makeFolder(ASSETS_PATH);
                }
                $fileName = ASSETS_PATH.'/.htaccess';
                if (! file_exists($fileName)) {
                    $string = '
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.+)\.(v[A-Za-z0-9]+)\.(js|css|png|jpg|gif|svg)$ $1.$3 [L]
</IfModule>
                    ';
                    if (!file_exists(ASSETS_PATH)) {
                        Filesystem::makeFolder(ASSETS_PATH);
                    }
                    file_put_contents($fileName, $string);
                }
            }
        }
    }
}