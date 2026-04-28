<?php

declare(strict_types=1);

namespace Sunnysideup\PerfectCmsImages\Tasks;

use Override;
use SilverStripe\Control\Director;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

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
    protected static string $commandName = 'delete-all-variants';

    protected string $title = 'Careful: experimental - DELETE ALL IMAGE VARIANTS';

    protected static string $description = 'Delete all the variants';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        Director::baseFolder();
        $shouldRemove = (bool) $input->getOption('go');
        $rm = '-exec rm {} \;';
        $find = "find . -regextype posix-extended -regex '.*__(Fit|Fill|ResizedImage|Scale|Resampled).*\\.(jpg|webp|png|JPG|jpeg)' ";
        $commandOutput = [];
        $retval = 0;

        if ($shouldRemove) {
            exec($find . ' ' . $rm, $commandOutput, $retval);
        } else {
            exec($find, $commandOutput, $retval);
        }

        foreach ($commandOutput as $message) {
            $output->writeln($message);
        }

        $output->writeln('Returned with status ' . $retval);

        return Command::SUCCESS;
    }

    #[Override]
    public function getOptions(): array
    {
        return array_merge(
            parent::getOptions(),
            [
                new InputOption(
                    'go',
                    'g',
                    InputOption::VALUE_NONE,
                    'Execute removal commands instead of dry run'
                ),
            ]
        );
    }
}
