<?php

namespace Sunnysideup\PerfectCmsImages\Tasks;

use Override;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

/**
 * Class DeleteGeneratedImagesTask.
 *
 * Hack to allow removing manipulated images
 * This is needed occasionally when manipulation functions change
 * It isn't directly possible with core so this is a workaround
 *
 * @see https://github.com/silverstripe/silverstripe-assets/issues/109
 * @codeCoverageIgnore
 */
class DeleteAllVariants extends BuildTask
{
    /** @TODO SSU RECTOR UPGRADE TASK - BuildTask::getTitle: Changed return type for method BuildTask::getTitle() from dynamic to string */
    #[Override]
    public function getTitle(): string
    {
        return 'Careful: experimental - DELETE ALL IMAGE VARIANTS';
    }

    /** @TODO SSU RECTOR UPGRADE TASK - SilverStripe\Dev\BuildTask::getDescription: Method BuildTask::getDescription() is now static
     * @TODO SSU RECTOR UPGRADE TASK - BuildTask::getDescription: Changed return type for method BuildTask::getDescription() from dynamic to string
     */
    #[Override]
    public function getDescription(): string
    {
        return 'Delete all the variants';
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
        Director::baseFolder();
        $go = $request->getVar('go');
        $rm = '-exec rm {} \;';
        $find = 'find . -regextype posix-extended -regex \'.*__(Fit|Fill|ResizedImage|Scale|Resampled).*\.(jpg|webp|png|JPG|jpeg)\' ';
        if ($go) {
            exec($find . ' ' . $rm);
            exec($find . ' ' . $rm, $output, $retval);
        } else {
            exec($find);
            exec($find, $output, $retval);
        }

        foreach ($output as $key) {
            DB::alteration_message($key);
        }

        echo 'Returned with status ' . $retval;
    }
}
