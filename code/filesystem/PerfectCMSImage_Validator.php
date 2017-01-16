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
        $hasError = false;
        if(PerfectCMSImageDataExtension::get_enforce_size($this->fieldName)) {
            $widthRecommendation = (PerfectCMSImageDataExtension::get_width($this->fieldName) * 2);
            $heightRecommendation = (PerfectCMSImageDataExtension::get_height($this->fieldName) * 2);
            if ($widthRecommendation) {
                if (! $this->isImageCorrectWidth(true, $widthRecommendation)) {
                    $this->errors[] = "Expected width: " . $widthRecommendation . "px;";
                    $hasError = true;
                }
            }

            if ($heightRecommendation) {
                if (! $this->isImageCorrectWidth(false, $heightRecommendation)) {
                    $this->errors[] = "Expected height: " . $heightRecommendation . "px;";
                    $hasError = true;
                }
            }
        }
        $parentResult = parent::validate();
        if ($hasError) {
            return false;
        }
        return $parentResult;
    }

    protected function isImageCorrectWidth($isWidth, $recommendedWidthOrHeight)
    {
        $actualWidthOrHeight = $this->getWidthOrHeight($isWidth);
        if ($actualWidthOrHeight) {
            if ($actualWidthOrHeight != $recommendedWidthOrHeight) {
                return false;
            }
        }
        return true;
    }


    protected function getWidthOrHeight($isWidth)
    {
        $imageSize = false;
        if (isset($this->tmpFile["tmp_name"])) {
            $imageSize = getimagesize($this->tmpFile["tmp_name"]);
        } else {
            // $imagefile = $this->getFullPath();
            // if($this->exists() && file_exists($imageFile)) {
            //     $imageSize = getimagesize($imagefile);
            // }
        }
        if ($imageSize === false) {
            return false;
        } else {
            if ($isWidth) {
                return $imageSize[0];
            } else {
                return $imageSize[1];
            }
        }
    }
}
