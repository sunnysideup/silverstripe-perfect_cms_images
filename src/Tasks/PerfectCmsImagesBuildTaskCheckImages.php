<?php

namespace Sunnysideup\PerfectCmsImages\Tasks;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class PerfectCmsImagesBuildTaskCheckImages extends BuildTask
{
    protected static string $commandName = 'check-image-sizes';

    protected string $title = 'Check Size of Images Uploaded';

    protected static string $description = 'Checks the size of certain images to make sure they match specifications';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->outputToScreen(
            $output,
            'Expected parameters: --parent=SiteTree --fieldname=MyImage --width=100 --height=200',
            'created'
        );
        $parent = $input->getOption('parent');
        $fieldName = $input->getOption('fieldname');
        $width = $input->getOption('width');
        $height = $input->getOption('height');
        if ($height || $width) {
            if (class_exists($parent)) {
                $singleton = Injector::inst()->get($parent);
                if ($singleton instanceof DataObject) {
                    if ($singleton->hasMethod($fieldName)) {
                        $objects = $parent::get()->where('"' . $fieldName . 'ID" <> 0 AND "' . $fieldName . 'ID" IS NOT NULL');
                        for ($i = 0; $i < 100000; ++$i) {
                            $array = [];
                            $obj = $objects->limit(1, $i)->first();
                            if (! $obj) {
                                break;
                            }

                            $image = $obj->{$fieldName}();
                            if ($image && $image instanceof $image && $image->exists()) {
                                if ($width) {
                                    $realWidth = $image->getWidth();
                                    if ($realWidth !== (int) $width) {
                                        $array[] = 'width is ' . round($width / $realWidth, 2) . '% of what it should be';
                                    }
                                }

                                if ($height) {
                                    $realHeight = $image->getHeight();
                                    if ($realHeight !== (int) $height) {
                                        $array[] = 'height is ' . round($height / $realHeight, 2) . '% of what it should be';
                                    }
                                }

                                if ($array !== []) {
                                    $this->outputToScreen(
                                        $output,
                                        'ERRORS WITH: ' . $obj->getTitle() . ' --- ' . implode('; ', $array),
                                        'deleted'
                                    );
                                } else {
                                    $this->outputToScreen($output, 'PERFECT PASS FOR: ' . $obj->getTitle());
                                }
                            } else {
                                $this->outputToScreen(
                                    $output,
                                    'Skipping ' . $obj->getTitle() . ' as it does not have a valid image attached to it.'
                                );
                            }
                        }
                    } else {
                        $this->outputToScreen(
                            $output,
                            'Please specify a valid field name with --fieldname=xxx where xxx is the field name (e.g. Image).',
                            'deleted'
                        );
                    }
                } else {
                    $this->outputToScreen(
                        $output,
                        'Please specify a valid class name with --parent=xxx where xxx is the class name that is a valid data object.',
                        'deleted'
                    );
                }
            } else {
                $this->outputToScreen(
                    $output,
                    'Please specify a valid class name with --parent=xxx where xxx is the class name.',
                    'deleted'
                );
            }
        } else {
            $this->outputToScreen($output, 'Please specify at least one of height or width.', 'deleted');
        }

        $output->writeln('--- COMPLETED ---');

        return Command::SUCCESS;
    }

    protected function getOptions(): array
    {
        return array_merge(
            parent::getOptions(),
            [
                new InputOption(
                    'parent',
                    'p',
                    InputOption::VALUE_REQUIRED,
                    'Class name of the parent DataObject'
                ),
                new InputOption(
                    'fieldname',
                    'f',
                    InputOption::VALUE_REQUIRED,
                    'Image relationship field name'
                ),
                new InputOption(
                    'width',
                    'w',
                    InputOption::VALUE_REQUIRED,
                    'Expected width in pixels'
                ),
                new InputOption(
                    'height',
                    'h',
                    InputOption::VALUE_REQUIRED,
                    'Expected height in pixels'
                ),
            ]
        );
    }

    /**
     * @param string $message
     * @param string $type
     */
    protected function outputToScreen(PolyOutput $output, $message, $type = '')
    {
        unset($type);
        $output->writeln($message);
    }
}
