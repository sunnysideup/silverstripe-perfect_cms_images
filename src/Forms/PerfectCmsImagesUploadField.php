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
 *        );
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
     * @param null|string  $title           the field label
     * @param null|SS_List $items           If no items are defined, the field will try to auto-detect an existing relation
     * @param null|string  $alternativeName - name used for formatting
     */
    public function __construct(
        string $name,
        ?string $title = null,
        ?SS_List $items = null,
        ?string $alternativeName = null
    ) {
        parent::__construct(
            $name,
            $title,
            $items
        );
        $perfectCMSImageValidator = new PerfectCmsImageValidator();
        $this->setValidator($perfectCMSImageValidator);
        $finalName = $name;
        if (null !== $alternativeName) {
            $finalName = $alternativeName;
        }
        $this->selectFormattingStandard($finalName);
    }

    public function setDescription($string): self
    {
        parent::setDescription(
            DBField::create_field('HTMLText', $string . '<br />' . $this->RightTitle())
        );
        //important!
        return $this;
    }

    /**
     * @param string $name Formatting Standard
     */
    public function selectFormattingStandard(string $name): self
    {
        //folder
        $this->setPerfectFolderName($name);

        // description
        $this->setDescription(PerfectCMSImages::get_description_for_cms($name));

        // standard stuff
        $this->setAllowedFileCategories('image');
        $alreadyAllowed = $this->getAllowedExtensions();
        $this->setAllowedExtensions($alreadyAllowed + ['svg']);
        //keep the size reasonable
        $maxSizeInKilobytes = PerfectCMSImages::max_size_in_kilobytes($name);
        $this->getValidator()->setAllowedMaxFileSize(1 * 1024 * $maxSizeInKilobytes);

        //make sure the validator knows about the name.
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

        $folderName = (string) trim($this->folderName);
        //folder related stuff ...
        $folderName = (string) PerfectCMSImages::get_folder($name);
        $folderName = implode(
            '/',
            array_filter([$folderPrefix, $folderName])
        );
        Folder::find_or_make($folderName);
        //set folder
        $this->setFolderName($folderName);
    }
}
