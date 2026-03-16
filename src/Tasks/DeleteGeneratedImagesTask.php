<?php

namespace Sunnysideup\PerfectCmsImages\Tasks;

use Override;
use League\Flysystem\Filesystem;
use ReflectionMethod;
use SilverStripe\Assets\Flysystem\FlysystemAssetStore;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;

/**
 * SOURCE: https://gist.github.com/blueo/6598bc349b406cf678f9a8f009587a95
 * Class DeleteGeneratedImagesTask.
 *
 * Hack to allow removing manipulated images
 * This is needed occasionally when manipulation functions change
 * It isn't directly possible with core so this is a workaround
 *
 * @see https://github.com/silverstripe/silverstripe-assets/issues/109
 * @codeCoverageIgnore
 */
class DeleteGeneratedImagesTask extends BuildTask
{
    protected $title = 'Delete Generated Images';

    protected $description = 'Delete all generated images for a specific asset';

    /** @TODO SSU RECTOR UPGRADE TASK - SilverStripe\Dev\BuildTask::getDescription: Method BuildTask::getDescription() is now static
     * @TODO SSU RECTOR UPGRADE TASK - BuildTask::getDescription: Changed return type for method BuildTask::getDescription() from dynamic to string
     */
    #[Override]
    public function getDescription(): string
    {
        return 'Regenerate Images for an asset';
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
        $Id = $request->getVar('ID');
        if (! $Id) {
            echo 'No ID provided, make sure to supply an ID to the URL eg ?ID=2';

            return;
        }

        $image = Image::get()->byID($Id);

        if (! $image) {
            echo 'No Image found with that ID';

            return;
        }

        $asetValues = $image->File->getValue();
        $store = Injector::inst()->get(AssetStore::class);

        // warning - super hacky as accessing private methods
        $getID = new ReflectionMethod(FlysystemAssetStore::class, 'getFileID');

        $flyID = $getID->invoke($store, $asetValues['Filename'], $asetValues['Hash']);
        $getFileSystem = new ReflectionMethod(FlysystemAssetStore::class, 'getFilesystemFor');
        /** @var Filesystem $system */
        $system = $getFileSystem->invoke($store, $flyID);

        $findVariants = new ReflectionMethod(FlysystemAssetStore::class, 'findVariants');
        foreach ($findVariants->invoke($store, $flyID, $system) as $variant) {
            $isGenerated = strpos((string) $variant, '__');
            if (! $isGenerated) {
                continue;
            }

            $system->delete($variant);
        }

        echo 'Deleted generated images for ' . $image->Name;
    }
}
