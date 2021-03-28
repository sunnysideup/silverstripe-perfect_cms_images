<?php

namespace Sunnysideup\PerfectCmsImages\Tasks;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

class PerfectCmsImagesBuildTaskCheckImages extends BuildTask
{
    protected $title = 'Check Size of Images Uploaded';

    protected $description = 'Checks the size of certain images to make sure they match specifications';

    public function run($request)
    {
        $this->outputToScreen('Expected URL parameters: ?parent=SiteTree&fieldname=MyImage&width=100&height=200', 'created');
        $parent = $request->getVar('parent');
        $fieldName = $request->getVar('fieldname');
        $width = $request->getVar('width');
        $height = $request->getVar('height');
        if ($height || $width) {
            if (class_exists($parent)) {
                $singleton = Injector::inst()->get($parent);
                if ($singleton instanceof DataObject) {
                    if ($singleton->hasMethod($fieldName)) {
                        $objects = $parent::get()->where('"' . $fieldName . 'ID" <> 0 AND "' . $fieldName . 'ID" IS NOT NULL');
                        for ($i = 0; $i < 100000; ++$i) {
                            $array = [];
                            $obj = $objects->limit(1, $i)->first();
                            if (! $obj) {
                                break;
                            }
                            $image = $obj->{$fieldName}();
                            if ($image && $image instanceof $image && $image->exists()) {
                                if ($width) {
                                    $realWidth = $image->getWidth();
                                    if ($realWidth !== $width) {
                                        $array[] = 'width is ' . round($width / $realWidth, 2) . '% of what it should be';
                                    }
                                }
                                if ($height) {
                                    $realHeight = $image->getHeight();
                                    if ($realHeight !== $height) {
                                        $array[] = 'height is ' . round($height / $realHeight, 2) . '% of what it should be';
                                    }
                                }
                                if (count($array) > 0) {
                                    $this->outputToScreen('ERRORS WITH: ' . $obj->getTitle() . ' --- ' . implode('; ', $array), 'deleted');
                                } else {
                                    $this->outputToScreen('PERFECT PASS FOR: ' . $obj->getTitle());
                                }
                            } else {
                                $this->outputToScreen('Skipping ' . $obj->getTitle() . ' as it does not have a valid image attached to it.');
                            }
                        }
                    } else {
                        $this->outputToScreen('Please specify a valid field name like this fieldname=xxx, where xxx is the field name (e.g. Image).', 'deleted');
                    }
                } else {
                    $this->outputToScreen('Please specify a valid class name like this parent=xxx, where xxx is the class name that is a valid data object.', 'deleted');
                }
            } else {
                $this->outputToScreen('Please specify a valid class name like this parent=xxx, where xxx is the class name.', 'deleted');
            }
        } else {
            $this->outputToScreen('Please specify at least one of height or width.', 'deleted');
        }
        echo '<h1>--- COMPLETED ---</h1>';
    }

    /**
     * @param  string $message
     * @param  string $type
     */
    protected function outputToScreen($message, $type = '')
    {
        echo ' ';
        flush();
        ob_end_flush();
        DB::alteration_message($message, $type);
        ob_start();
    }
}
