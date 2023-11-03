<?php

namespace Sunnysideup\PerfectCmsImages\Tasks;

use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Axllent\ScaledUploads\ScaledUploads;
use Sunnysideup\ResizeAssets\ResizeAssetsRunner;

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
class ResizeAllImages extends BuildTask
{
    public function getTitle(): string
    {
        return 'Careful: experimental - DELETE ALL IMAGE VARIANTS';
    }

    public function getDescription(): string
    {
        return 'Resize all images to a maximum as set for Axllent\ScaledUploads\ScaledUploads';
    }

    private static $segment = 'resize-all-images';

    /**
     * Create test jobs for the purposes of testing.
     *
     * @param HTTPRequest $request
     */
    public function run($request) // phpcs:ignore
    {
        echo "---" . PHP_EOL;
        echo "---" . PHP_EOL;

        $directory = ASSETS_PATH;
        $maxWidth = Config::inst()->get(ScaledUploads::class, 'max_width') ?: 2800;
        $maxHeight = Config::inst()->get(ScaledUploads::class, 'max_height') ?: 1867;
        $maxSize = Config::inst()->get(ScaledUploads::class, 'max_size_in_mb') ?: 2;
        $dryRun = isset($argv[1]) && $argv[1] === "--dry-run"; // Pass --dry-run as an argument to perform a dry run
        echo "--- DO-IT: " . ($dryRun ? 'YES' : 'NO') . ' ... To do anything at all, please add --do-it=1 to command string ' . PHP_EOL;
        echo "--- DIRECTORY: " . $directory . PHP_EOL;
        echo "--- MAX-WIDTH: " . $maxWidth . PHP_EOL;
        echo "--- MAX-HEIGHT: " . $maxHeight . PHP_EOL;
        echo "--- MAX-SIZE: " . $maxSize . PHP_EOL;
        echo "--- DRY-RUN: " . ($dryRun ? 'YES' : 'NO') . PHP_EOL;
        // RUN!
        ResizeAssetsRunner::run_dir($directory, $maxWidth, $maxHeight, $dryRun);

        echo "Operation completed." . PHP_EOL;
    }
}
