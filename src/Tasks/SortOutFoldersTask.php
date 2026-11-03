<?php

namespace Sunnysideup\PerfectCmsImages\Tasks;

use SilverStripe\Control\Director;
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
        return 'Goes through all the perfect cms images, checks what folder they write to and moves any images that should not be there. Dry run by default: add ?forreal=1 to really move the files.';
    }

    /**
     * Create test jobs for the purposes of testing.
     *
     * @param HTTPRequest $request
     */
    public function run($request) // phpcs:ignore
    {
        $forReal = (bool) $request->requestVar('forreal');
        if ($forReal) {
            if (! Director::is_cli()) {
                echo 'This task can only be run on the command line.';

                return;
            }
        } else {
            echo '<p>DRY RUN: nothing is moved. Add ?forreal=1 to really move the files.</p>';
        }

        (new SortOutFolders())
            ->setDryRun(! $forReal)
            ->runStandard()
        ;
    }
}
