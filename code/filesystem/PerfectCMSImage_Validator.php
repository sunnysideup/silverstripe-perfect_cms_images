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
        $name = $this->fieldName;
        if(PerfectCMSImageDataExtension::get_enforce_size($name)) {
            $useRetina = PerfectCMSImageDataExtension::use_retina($name);
            $multiplier = 1;
            if($useRetina) {
                $multiplier = 2;
            }
            $widthRecommendation = (PerfectCMSImageDataExtension::get_width($name, true) * $multiplier);
            $heightRecommendation = (PerfectCMSImageDataExtension::get_height($name, true) * $multiplier);
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
