<?php

/**
 */
class PerfectCMSImagesDataExtension extends DataExtension
{
    /**
     * @param string $name
     *
     * @return string
     */
    public function BestImageLink($name)
    {
        return ImageSizeConfig::get_template_link($this->owner, $name);
    }
}
