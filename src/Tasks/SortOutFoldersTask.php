<?php

namespace Sunnysideup\PerfectCmsImages\Tasks;

use Override;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use Sunnysideup\PerfectCmsImages\Api\SortOutFolders;

class SortOutFoldersTask extends BuildTask
{
    /** @TODO SSU RECTOR UPGRADE TASK - BuildTask::getTitle: Changed return type for method BuildTask::getTitle() from dynamic to string */
    #[Override]
    public function getTitle(): string
    {
        return 'Careful: experimental - MOVE ALL IMAGES THAT ARE IN A FOLDER AND SHOULD NOT BE THERE';
    }

    /** @TODO SSU RECTOR UPGRADE TASK - SilverStripe\Dev\BuildTask::getDescription: Method BuildTask::getDescription() is now static
     * @TODO SSU RECTOR UPGRADE TASK - BuildTask::getDescription: Changed return type for method BuildTask::getDescription() from dynamic to string
     */
    #[Override]
    public function getDescription(): string
    {
        return 'Goes through all the perfect cms images, checks what folder they write to and moves any images that should not be there.';
    }

    /**
     * Create test jobs for the purposes of testing.
     *
     * @param HTTPRequest $request
     * @TODO SSU RECTOR UPGRADE TASK - BuildTask::run: Added new parameter $output in BuildTask::run()
     * @TODO SSU RECTOR UPGRADE TASK - BuildTask::run: Changed type of parameter $request in BuildTask::run() from dynamic to Symfony\Component\Console\Input\InputInterface
     * @TODO SSU RECTOR UPGRADE TASK - BuildTask::run: Renamed parameter $request in BuildTask::run() to $input
     * @TODO SSU RECTOR UPGRADE TASK - BuildTask::run: Changed return type for method BuildTask::run() from dynamic to int
     */
    public function run($request) // phpcs:ignore
    {
        (SortOutFolders::create())
            ->runStandard()
        ;
    }
}
