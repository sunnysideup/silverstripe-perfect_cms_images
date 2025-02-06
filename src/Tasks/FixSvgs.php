<?php

namespace Sunnysideup\PerfectCmsImages\Tasks;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

/**
 * Class DeleteGeneratedImagesTask.
 *
 * @see https://github.com/silverstripe/silverstripe-assets/issues/109
 * @codeCoverageIgnore
 */
class FixSvgs extends BuildTask
{
    public function getTitle(): string
    {
        return 'Fix Svg Images that are saved as Files rather than Images';
    }

    public function getDescription(): string
    {
        return 'Go through all the Files, check if they are SVGs and then change the classname to Image.';
    }

    /**
     * Create test jobs for the purposes of testing.
     *
     * @param HTTPRequest $request
     */
    public function run($request) // phpcs:ignore
    {
        $files = $this->getBatchOfFiles();
        while ($files && $files->exists()) {
            foreach ($files as $file) {
                if ('svg' === $file->getExtension()) {
                    DB::alteration_message('Fixing ' . $file->Link());
                    $isPublished = $file->isPublished() && ! $file->isModifiedOnDraft();
                    $file->ClassName = Image::class;
                    $file->write();
                    if ($isPublished) {
                        $file->publishSingle();
                    }
                }
            }
            $files = $this->getBatchOfFiles();
        }
    }

    protected function getBatchOfFiles()
    {
        return File::get()->filter(['Name:PartialMatch' => '.svg'])->exclude(['ClassName' => Image::class])->limit(100);
    }
}
