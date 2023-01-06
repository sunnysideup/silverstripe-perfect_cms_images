<?php

namespace Sunnysideup\PerfectCmsImages\Tasks;

use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

use SilverStripe\Assets\Image;
use SilverStripe\Assets\Folder;

use Sunnysideup\PerfectCmsImages\Api\PerfectCMSImages;

class MoveUnexpectedImages extends BuildTask
{

    /**
     * @var Folder
     */
    public $unusedImagesFolder = null;

    private static $unused_images_folder_name = 'unusedimages';

    public function getTitle(): string
    {
        return 'Careful: experimental - DELETE ALL IMAGES THAT ARE IN A FOLDER AND SHOULD NOT BE THERE';
    }

    public function getDescription(): string
    {
        return 'Goes through all the perfect cms images, checks what folder they write to and moves any images that should not be there.';
    }

    /**
     * Create test jobs for the purposes of testing.
     *
     * @param HTTPRequest $request
     */
    public function run($request) // phpcs:ignore
    {
        $this->unusedImagesFolder = Folder::find_or_make($this->config()->get('unused_images_folder_name'));
        $data = PerfectCMSImages::get_all_values_for_images();

        // check folders
        $folderArray = [];
        foreach($data as $name => $dataInner) {
            $folder = $dataInner['folder'] ?? '';
            if($folder) {
                $folderArray[$folder] = [];
                $classes = $dataInner['used_by'] ?? [];
                foreach($classes as $class) {
                    $folderArray[$folder][] = $class;
                }
            }
        }

        $folderArray = [];
        $listOfImageIds = [];
        foreach($folderArray as $folderName => $classAndMethodList) {

            // find all images that should be there...
            $listOfIds = [];
            foreach($classAndMethodList as $classAndMethod) {
                list($class, $method) = explode('.', $classAndMethod);
                $listOfImageIds = array_merge(
                    $listOfImageIds,
                    $class::get()->columnUnique($method.'ID')
                );
            }
            // remove files
            if (count($listOfIds)) {
                $this->removeUnusedFiles($folderName, $listOfImageIds);
            }
        }
    }


    protected function removeUnusedFiles(string $folderName, array $listOfImageIds)
    {
        $folder = Folder::find_or_make($this->config()->get('unused_images_folder_name'));
        $unusedFolderName = $this->unusedImagesFolder->Name;
        $where = " ParentID = " . $folder->ID. ' AND File.ID NOT IN('.implode('.$listOfImageIds.').')';
        $unused = Image::get()->where($where);
        if ($unused->exists()) {
            foreach ($unused as $file) {
                $oldName = $file->getFullPath();
                $file->ParentID = $this->unusedImagesFolder->ID;
                $file->write();
                $file->doPublish();
                $newName = str_replace($folder->Name, $unusedFolderName, $oldName);
                $oldNameFull = Controller::join_links(ASSETS_PATH, $oldName);
                $newNameFull = Controller::join_links(ASSETS_PATH, $newName);
                if (file_exists($oldNameFull) && $newNameFull !== $oldNameFull) {
                    if(file_exists($newNameFull)) {
                        unlink($newNameFull);
                    }
                    rename($oldNameFull, $newNameFull);
                }
            }
        }
    }

}
