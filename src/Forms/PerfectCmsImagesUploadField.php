<?php

namespace Sunnysideup\PerfectCmsImages\Forms;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Folder;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\SS_List;
use Sunnysideup\PerfectCmsImages\Filesystem\PerfectCmsImageValidator;
use Sunnysideup\PerfectCmsImages\Model\File\PerfectCmsImageDataExtension;

/**
 * image-friendly upload field.

 * Usage:
 *     $field = PerfectCmsImagesUploadFielde::create(
 *         "ImageField",
 *         "Add Image",
 *         null,
 * 	);
 */
class PerfectCmsImagesUploadField extends UploadField
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
        $perfectCMSImageValidator = new PerfectCmsImageValidator();
        $this->setValidator($perfectCMSImageValidator);
        if ($alternativeName === null) {
            $alternativeName = $name;
        }
        $this->selectFormattingStandard($alternativeName);
    }

    public function setRightTitle($string)
    {
        parent::setRightTitle(
            DBField::create_field(
                'HTMLText',
                $string .
                '<br />' .
                $this->RightTitle()
            )
        );
        //important!
        return $this;
    }

    /**
     * @param  string $name Formatting Standard
     * @return $this
     */
    public function selectFormattingStandard($name)
    {
        parent::setRightTitle('');
        $widthRecommendation = PerfectCmsImageDataExtension::get_width($name, false);
        $heightRecommendation = PerfectCmsImageDataExtension::get_height($name, false);
        $useRetina = PerfectCmsImageDataExtension::use_retina($name);
        $multiplier = 1;
        if ($useRetina) {
            $multiplier = 2;
        }
        $maxSizeInKilobytes = PerfectCmsImageDataExtension::max_size_in_kilobytes($name);
        if (! $maxSizeInKilobytes) {
            $maxSizeInKilobytes = Config::inst()->get(PerfectCmsImagesUploadField::class, 'max_size_in_kilobytes');
        }

        if ($this->folderName) {
            $folderName = $this->folderName;
        } else {
            //folder related stuff ...
            $folderName = PerfectCmsImageDataExtension::get_folder($name);
            $folderPrefix = $this->Config()->get('folder_prefix');
            if (! $folderName) {
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

        $recommendedFileType = PerfectCmsImageDataExtension::get_file_type($name);
        if (! $recommendedFileType) {
            $recommendedFileType = 'jpg';
        }
        if ($widthRecommendation) {
            if (intval($widthRecommendation)) {
                //cater for retina
                $widthRecommendation *= $multiplier;
                $actualWidthDescription = $widthRecommendation . 'px';
            } else {
                $actualWidthDescription = $widthRecommendation;
            }
        } else {
            $actualWidthDescription = 'flexible';
        }
        if ($heightRecommendation) {
            if (intval($heightRecommendation)) {
                //cater for retina
                $heightRecommendation *= $multiplier;
                $actualHeightDescription = $heightRecommendation . 'px';
            } else {
                $actualHeightDescription = $heightRecommendation;
            }
        } else {
            $actualHeightDescription = 'flexible';
        }

        $rightTitle = '';

        if ($actualWidthDescription === 'flexible') {
            $rightTitle .= 'Image width is flexible';
        } else {
            $rightTitle .= "Image should to be <strong>${actualWidthDescription}</strong> wide";
        }

        $rightTitle .= ' and ';

        if ($actualHeightDescription === 'flexible') {
            $rightTitle .= 'height is flexible';
        } else {
            $rightTitle .= " <strong>${actualHeightDescription}</strong> tall";
        }

        $rightTitle .= '<br />';

        if ($maxSizeInKilobytes) {
            $rightTitle .= 'Maximum file size: ' . round($maxSizeInKilobytes / 1024, 2) . ' megabyte.';
            $rightTitle .= '<br />';
        }
        if ($recommendedFileType) {
            if (strlen($recommendedFileType) < 5) {
                $rightTitle .= 'The recommend file type (file extension) is <strong>' . $recommendedFileType . '</strong>.';
            } else {
                $rightTitle .= '<strong>' . $recommendedFileType . '</strong>';
            }
        }

        parent::setRightTitle(
            DBField::create_field('HTMLText', $rightTitle)
        );

        $this->setAllowedFileCategories('image');
        $alreadyAllowed = $this->getAllowedExtensions();
        $this->setAllowedExtensions($alreadyAllowed + ['svg']);
        //keep the size reasonable
        $this->getValidator()->setAllowedMaxFileSize(1 * 1024 * $maxSizeInKilobytes);
        $this->getValidator()->setFieldName($name);
        return $this;
    }
}
