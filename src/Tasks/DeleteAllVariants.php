<?php

namespace Sunnysideup\PerfectCmsImages\Tasks;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Convert;
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
     */
    public function run($request) // phpcs:ignore
    {
        $go = (bool) $request->requestVar('go');
        if ($go) {
            if (! Director::is_cli()) {
                echo 'This task can only be run on the command line.';

                return;
            }
        }

        $base = defined('ASSETS_PATH') ? ASSETS_PATH : Director::baseFolder() . '/public/assets';
        $base = realpath($base);
        if (! $base || ! is_dir($base)) {
            echo 'Could not find the assets folder.';

            return;
        }

        $pattern = '/__(Fit|Fill|ResizedImage|Scale|Resampled).*\.(jpg|webp|png|jpeg)$/i';
        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile() || $fileInfo->isLink()) {
                continue;
            }

            $path = $fileInfo->getPathname();
            if (! preg_match($pattern, $path)) {
                continue;
            }

            // never leave the assets folder.
            $real = realpath($path);
            if (! $real || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) {
                continue;
            }

            ++$count;
            if ($go) {
                if (@unlink($real)) {
                    DB::alteration_message('Deleted ' . Convert::raw2xml($real), 'deleted');
                } else {
                    DB::alteration_message('Could not delete ' . Convert::raw2xml($real), 'error');
                }
            } else {
                DB::alteration_message(Convert::raw2xml($real));
            }
        }

        echo $go
            ? "Deleted {$count} variants."
            : "Found {$count} variants. Add ?go=1 to delete them.";
    }
}
