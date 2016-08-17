<?php

/**
 * image-friendly upload field.

 * Usage:
 *     $field = PerfectCMSImagesUploadFielde::create(
 *         "ImageField",
 *         "Add Image",
 *         null,
 *         300,
 * 	       "anything you like",
 *         "folder-name",
 *         "png or jpg"
 * 	);
 */
class PerfectCMSImagesUploadField extends UploadField
{
    private static $max_size_in_kilobytes = 1024;

    /**
     * @param string  $name
     * @param string  $title
     * @param SS_List $items If no items are defined, the field will try to auto-detect an existing relation
     *
     * @return UploadField
     */
    public function __construct(
        $name,
        $title,
        SS_List $items = null
    ) {
        parent::__construct(
            $name,
            $title,
            $items
        );
        $this->selectFormattingStandard($name);
        return $this;
    }

    public function setRightTitle($string)
    {
        parent::setRightTitle(
            $string.
            '<br />'.
            $this->RightTitle()
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
    public function selectFormattingStandard($name) {
        $this->setRightTitle('-');
        $widthRecommendation = PerfectCMSImageDataExtension::get_width($name);
        $heightRecommendation = PerfectCMSImageDataExtension::get_height($name);
        $folderName = PerfectCMSImageDataExtension::get_folder($name);
        if (!$folderName) {
            $folderName = 'other-images';
        }
        $recommendedFileType = PerfectCMSImageDataExtension::get_file_type($name);
        if (!$recommendedFileType) {
            $recommendedFileType = 'jpg';
        }
        $folderName = 'Uploads/'.$folderName.'/';
        if ($widthRecommendation) {
            if (intval($widthRecommendation)) {
                //cater for retina
                $widthRecommendation = $widthRecommendation * 2;
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
                $heightRecommendation = $heightRecommendation * 2;
                $actualHeightDescription = $heightRecommendation.'px';
            } else {
                $actualHeightDescription = $heightRecommendation;
            }
        } else {
            $actualHeightDescription = 'flexible';
        }


        $rightTitle = "";

        if ($actualWidthDescription == 'flexible'){
            $rightTitle .= 'Image width is flexible, and ';
        }
        else {
            $rightTitle .= "Image should be <strong>$actualWidthDescription</strong> wide and ";
        }


        if ($actualHeightDescription == 'flexible'){
            $rightTitle .= 'image height is flexible, and ';
        }
        else {
            $rightTitle .= "image should be <strong>$actualHeightDescription</strong> high and ";
        }

        $rightTitle .= "the image should be less than 1MB in size. <br/>
            The recommend file type (file extension) is <strong>".$recommendedFileType.'</strong>.';


        $this->setRightTitle($rightTitle);

        //create folder
        Folder::find_or_make($folderName);
        //set folder
        $this->setFolderName($folderName);
        $this->setAllowedFileCategories('image');
        $alreadyAllowed = $this->getAllowedExtensions();
        $this->setAllowedExtensions($alreadyAllowed + array('svg'));
        //keep the size reasonable
        $this->getValidator()->setAllowedMaxFileSize(1 * 1024 * Config::inst()->get('PerfectCMSImagesUploadFieldeProvider', 'max_size_in_kilobytes'));

        return $this;
    }

}
