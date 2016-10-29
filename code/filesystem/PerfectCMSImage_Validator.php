<?php

class PerfectCMSImage_Validator extends Upload_Validator
{
    protected $fieldName = '';

    public function setFieldName($fieldName)
    {
        $this->fieldName = $fieldName;
    }
    /**
     * Looser check validation that doesn't do is_upload_file()
     * checks as we're faking a POST request that PHP didn't generate
     * itself.
     *
     * @return boolean
     */
    public function validate()
    {
        $widthRecommendation = (PerfectCMSImageDataExtension::get_width($this->fieldName) * 2);
        $heightRecommendation = (PerfectCMSImageDataExtension::get_height($this->fieldName) * 2);
        if (!$this->isImageCorrectWidth($widthRecommendation) && $widthRecommendation) {
            $this->errors[] = "The image you have uploaded is not the correct width. The width should be " . $widthRecommendation . "px";
            return false;
        }

        if (!$this->isImageCorrectHeight($heightRecommendation) && $heightRecommendation) {
            $this->errors[] = "The image you have uploaded is not the correct height. The height should be " . $heightRecommendation . "px";
            return false;
        }

        return parent::validate();
        return false;
    }

    public function isImageCorrectWidth($width)
    {
        $imageSize = getimagesize($this->tmpFile["tmp_name"]);
        $widthRecommendation = $width;
        if ($imageSize !== false) {
            if ($imageSize[0] != $widthRecommendation) {
                return false;
            }
        }
        return true;
    }

    public function isImageCorrectHeight($height)
    {
        $imageSize = getimagesize($this->tmpFile["tmp_name"]);
        $heightRecommendation = $height;
        if ($imageSize !== false) {
            if ($imageSize[1] != $heightRecommendation) {
                return false;
            }
        }
        return true;
    }
}
