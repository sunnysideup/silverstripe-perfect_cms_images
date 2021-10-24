<?php

namespace Sunnysideup\PerfectCmsImages\Tasks;

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
    public function getTitle(): string
    {
        return 'Careful: experimental - DELETE ALL IMAGE VARIANTS';
    }

    public function getDescription(): string
    {
        return 'Delete all the variants';
    }

    /**
     * Create test jobs for the purposes of testing.
     *
     * @param HTTPRequest $request
     *
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function run($request) // phpcs:ignore
    {
        $base = Director::baseFolder();
        $go = $request->getVar('go');
        $rm = '-exec rm {} \;';
        $find = 'find . -regextype posix-extended -regex \'.*__(Fit|Fill|ResizedImage|Scale|Resampled).*\.(jpg|png|JPG|jpeg)\' ';
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
        echo "Returned with status {$retval}";
    }
}
