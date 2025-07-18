<?php

namespace Sunnysideup\PerfectCmsImages\Forms;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Folder;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
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
        PerfectCMSImages::legacy_check();
        $perfectCMSImageValidator = new PerfectCmsImageValidator();
        $this->setValidator($perfectCMSImageValidator);
        $finalName = $alternativeName ?: $name;
        if (PerfectCMSImages::is_valid_image_name($finalName)) {
            //if the name is a valid formatting standard, then use it.
            $this->setFormattingStandard($finalName);
        }
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
    public function setFormattingStandard(string $name): self
    {
        return $this->selectFormattingStandard($name);
    }

    public function selectFormattingStandard(string $name): self
    {
        //folder
        $this->setPerfectFolderName($name);

        // description
        $this->setDescription(PerfectCMSImages::get_description_for_cms($name));

        // standard stuff
        $this->setAllowedFileCategories('image');
        $alreadyAllowed = $this->getAllowedExtensions();
        $allowedExtensions = array_unique(array_merge($alreadyAllowed, ['svg', 'webp', 'avif', 'png', 'jpeg', 'jpg', 'gif']));
        $this->setAllowedExtensions($allowedExtensions);
        //keep the size reasonable
        $maxSizeInKilobytes = PerfectCMSImages::max_size_in_kilobytes($name);

        /** @var PerfectCmsImageValidator $validator */
        $validator = $this->getValidator();
        $validator->setAllowedMaxFileSize(1024 * $maxSizeInKilobytes);

        //make sure the validator knows about the name.
        $validator->setFieldName($name);

        return $this;
    }

    /**
     * Creates a single file based on a form-urlencoded upload.
     * Allows for hooking afterUpload.
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
        //folder related stuff ...
        $folderName = PerfectCMSImages::get_folder($name);
        $folderName = implode(
            '/',
            array_filter([$folderPrefix, $folderName])
        );
        if ($folderName === '' || $folderName === '0') {
            $folderName = 'Uploads';
        }
        Folder::find_or_make($folderName);
        //set folder
        $this->setFolderName($folderName);
    }

    public function saveInto(DataObject|DataObjectInterface $record): static
    {
        parent::saveInto($record);

        return $this;
    }
}
