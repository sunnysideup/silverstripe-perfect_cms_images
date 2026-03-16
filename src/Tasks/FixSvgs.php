<?php

namespace Sunnysideup\PerfectCmsImages\Tasks;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class DeleteGeneratedImagesTask.
 *
 * @see https://github.com/silverstripe/silverstripe-assets/issues/109
 * @codeCoverageIgnore
 */
class FixSvgs extends BuildTask
{
    protected static string $commandName = 'fix-svgs';

    protected string $title = 'Fix Svg Images that are saved as Files rather than Images';

    protected static string $description = 'Go through all the Files, check if they are SVGs and then change the classname to Image.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $files = $this->getBatchOfFiles();
        while ($files && $files->exists()) {
            foreach ($files as $file) {
                if ('svg' === $file->getExtension()) {
                    $output->writeln('Fixing ' . $file->Link());
                    $isPublished = $file->isPublished() && !$file->isModifiedOnDraft();
                    $file->ClassName = Image::class;
                    $file->write();
                    if ($isPublished) {
                        $file->publishSingle();
                    }
                }
            }

            $files = $this->getBatchOfFiles();
        }

        return Command::SUCCESS;
    }

    protected function getBatchOfFiles()
    {
        return File::get()->filter(['Name:PartialMatch' => '.svg'])->exclude(['ClassName' => Image::class])->limit(100);
    }
}
