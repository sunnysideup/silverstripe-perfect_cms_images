<?php

declare(strict_types=1);

namespace Sunnysideup\PerfectCmsImages\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Sunnysideup\PerfectCmsImages\Api\SortOutFolders;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class SortOutFoldersTask extends BuildTask
{
    protected static string $commandName = 'sort-out-image-folders';

    protected string $title = 'Careful: experimental - MOVE ALL IMAGES THAT ARE IN A FOLDER AND SHOULD NOT BE THERE';

    protected static string $description = 'Goes through all the perfect cms images, checks what folder they write to and moves any images that should not be there.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        SortOutFolders::create()->runStandard();

        return Command::SUCCESS;
    }
}
