<?php

namespace Sunnysideup\PerfectCmsImages\Forms;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Folder;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\SS_List;
use Sunnysideup\PerfectCmsImages\Api\PerfectCMSImages;
use Sunnysideup\PerfectCmsImages\Filesystem\PerfectCmsImageValidator;

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
    private static $max_size_in_kilobytes = 2048;

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
        $perfectCMSImageValidator = new PerfectCMSImageValidator();
        $this->setValidator($perfectCMSImageValidator);
        if ($alternativeName === null) {
            $alternativeName = $name;
        }
        $this->selectFormattingStandard($alternativeName);
    }

    public function setRightTitle($string)
    {
        parent::setRightTitle(
            DBField::create_field('HTMLText', $string . '<br />' . $this->RightTitle())
        );
        //important!
        return $this;
    }

    /**
     * @param  string $name Formatting Standard
     * @return $this
     */
    public function selectFormattingStandard(string $name)
    {
        $this->setPerfectFolderName($name);

        $this->setRightTitle(PerfectCMSImages::get_description_for_cms($name));

        $this->setAllowedFileCategories('image');
        $alreadyAllowed = $this->getAllowedExtensions();
        $this->setAllowedExtensions($alreadyAllowed + ['svg']);
        //keep the size reasonable
        $maxSizeInKilobytes = PerfectCMSImages::max_size_in_kilobytes($name);
        $this->getValidator()->setAllowedMaxFileSize(1 * 1024 * $maxSizeInKilobytes);
        $this->getValidator()->setFieldName($name);
        return $this;
    }

    protected function setPerfectFolderName(string $name)
    {
        $folderPrefix = $this->Config()->get('folder_prefix');

        $folderName = $this->folderName;
        if (! $folderName) {
            //folder related stuff ...
            $folderName = PerfectCMSImages::get_folder($name);
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
    }
}
