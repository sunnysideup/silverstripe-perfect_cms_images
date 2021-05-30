<?php

namespace Sunnysideup\PerfectCmsImages\Forms;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Folder;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\SS_List;
use Sunnysideup\PerfectCmsImages\Api\PerfectCMSImages;
use Sunnysideup\PerfectCmsImages\Filesystem\PerfectCmsImageValidator;

/**
 * image-friendly upload field.
 *
 * Usage:
 *     $field = PerfectCmsImagesUploadFielde::create(
 *         "ImageField",
 *         "Add Image",
 * 	   );
 */
class PerfectCmsImagesUploadField extends UploadField
{
    private static $max_size_in_kilobytes = 2048;

    private static $folder_prefix = '';

    /**
     * @config
     *
     * @var array
     */
    private static $allowed_actions = [
        'upload',
    ];

    private $afterUpload;

    /**
     * @param string       $name            the internal field name, passed to forms
     * @param string       $title           the field label
     * @param null|SS_List $items           If no items are defined, the field will try to auto-detect an existing relation
     * @param null|string  $alternativeName
     */
    public function __construct(
        $name,
        $title = null,
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
        if (null === $alternativeName) {
            $alternativeName = $name;
        }
        $this->selectFormattingStandard($alternativeName);

        return $this;
    }

    public function setDescription($string) : self
    {
        parent::setDescription(
            DBField::create_field('HTMLText', $string . '<br />' . $this->RightTitle())
        );
        //important!
        return $this;
    }

    /**
     * @param string $name Formatting Standard
     *
     * @return self
     */
    public function selectFormattingStandard(string $name) : self
    {
        $this->setPerfectFolderName($name);

        $this->setDescription(PerfectCMSImages::get_description_for_cms($name));

        $this->setAllowedFileCategories('image');
        $alreadyAllowed = $this->getAllowedExtensions();
        $this->setAllowedExtensions($alreadyAllowed + ['svg']);
        //keep the size reasonable
        $maxSizeInKilobytes = PerfectCMSImages::max_size_in_kilobytes($name);
        $this->getValidator()->setAllowedMaxFileSize(1 * 1024 * $maxSizeInKilobytes);
        $this->getValidator()->setFieldName($name);

        return $this;
    }

    /**
     * Creates a single file based on a form-urlencoded upload.
     * Allows for hooking AfterUpload.
     *
     * @return HTTPResponse
     */
    public function upload(HTTPRequest $request)
    {
        $response = parent::upload($request);

        // If afterUpload is a function ..
        return is_callable($this->afterUpload) ?
            //  .. then return the results from that ..
            ($this->afterUpload)($response) :
            //  .. else return the original $response
            $response;
    }

    /**
     * Add an anonymous functions to run after upload completes.
     *
     * @param callable $func
     */
    public function setAfterUpload($func): self
    {
        $this->afterUpload = $func;

        return $this;
    }

    protected function setPerfectFolderName(string $name)
    {
        $folderPrefix = $this->Config()->get('folder_prefix');

        $folderName = $this->folderName;
        if ('' === $folderName) {
            //folder related stuff ...
            $folderName = PerfectCMSImages::get_folder($name);
            if ('' === $folderName) {
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
