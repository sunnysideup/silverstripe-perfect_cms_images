<?php

namespace Sunnysideup\PerfectCmsImages\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use Sunnysideup\PerfectCmsImages\Api\SortOutFolders;

class SortOutFoldersTask extends BuildTask
{
    public function getTitle(): string
    {
        return 'Careful: experimental - MOVE ALL IMAGES THAT ARE IN A FOLDER AND SHOULD NOT BE THERE';
    }

    public function getDescription(): string
    {
        return 'Goes through all the perfect cms images, checks what folder they write to and moves any images that should not be there.';
    }

    /**
     * Create test jobs for the purposes of testing.
     *
     * @param HTTPRequest $request
     */
    public function run($request) // phpcs:ignore
    {
        (new SortOutFolders())
            ->runStandard()
        ;
    }
}
