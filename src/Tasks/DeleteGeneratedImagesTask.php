<?php

namespace Sunnysideup\PerfectCmsImages\Tasks;

use League\Flysystem\Filesystem;
use ReflectionMethod;
use SilverStripe\Assets\Flysystem\FlysystemAssetStore;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

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
    protected static string $commandName = 'delete-generated-images';

    protected string $title = 'Delete Generated Images';

    protected static string $description = 'Delete all generated images for a specific asset';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $id = $input->getOption('id');
        if (!$id) {
            $output->writeln('No ID provided, make sure to supply an ID with --id=<ID>');
            return Command::FAILURE;
        }

        $image = Image::get()->byID($id);

        if (!$image) {
            $output->writeln('No Image found with that ID');
            return Command::FAILURE;
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

        $output->writeln('Deleted generated images for ' . $image->Name);

        return Command::SUCCESS;
    }

    protected function getOptions(): array
    {
        return array_merge(
            parent::getOptions(),
            [
                new InputOption(
                    'id',
                    'i',
                    InputOption::VALUE_REQUIRED,
                    'ID of the image to clean up'
                ),
            ]
        );
    }
}
