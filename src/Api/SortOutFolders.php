<?php

namespace Sunnysideup\PerfectCmsImages\Api;

use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

use SilverStripe\Assets\Image;
use SilverStripe\Assets\Folder;

use Sunnysideup\PerfectCmsImages\Api\PerfectCMSImages;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;

use SilverStripe\Core\ClassInfo;

use SilverStripe\Core\Injector\Injector;
class SortOutFolders
{

    use Configurable;

    /**
     * @var Folder
     */
    public $unusedImagesFolder = null;

    private static $unused_images_folder_name = 'unusedimages';

    /**
     * Create test jobs for the purposes of testing.
     * The array must contains arrays with
     * - folder
     * - used_by (has_one / has_many / many_many relation)
     *
     * @param HTTPRequest $request
     */
    public function run(string $unusedFolderName, array $data) // phpcs:ignore
    {

        $folderArray = $this->getFolderArray($data);

        $listOfImageIds = $this->getListOfImages($folderArray);
        foreach($listOfImageIds as $folderName => $listOfIds) {
            $this->removeUnusedFiles($folderName, $listOfIds);
        }
    }


    protected function getFolderArray(array $data) :array
    {

        // check folders
        $folderArray = [];
        foreach($data as $dataInner) {
            $folder = $dataInner['folder'] ?? '';
            if($folder) {
                $folderArray[$folder] = [];
                $classes = $dataInner['used_by'] ?? [];
                foreach($classes as $classAndMethodList) {
                    $folderArray[$folder][$classAndMethodList] = $classAndMethodList;
                }
            }
        }
        return $folderArray;
    }

    protected function getListOfImages(array $folderArray) : array
    {
        $listOfImageIds = [];
        foreach($folderArray as $folderName => $classAndMethodList) {

            // find all images that should be there...
            $listOfIds = [];
            foreach($classAndMethodList as $classAndMethod) {
                list($className, $method) = explode('.', $classAndMethod);
                $listOfImageIds = array_merge(
                    $listOfImageIds,
                    $className::get()->columnUnique($method.'ID')
                );
            }
            // remove files
            $listOfImageIds[$folderName] = $listOfIds;
        }
        return $listOfImageIds;
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

    private static $my_cache = [];

    protected function getField(string $originClassName, string $originFieldName)
    {
        $types = ['has_one', 'has_many', 'many_many', 'belongs_many_many', 'belongs_to'];
        $classNames = ClassInfo::ancestry($className, true);
        foreach ($classNames as $className) {
            $obj = Injector::inst()->get($className);
            foreach ($types as $type) {
                $rels = Config::inst()->get($className, $type, Config::UNINHERITED);
                if (is_array($rels) && ! empty($rels)) {
                    foreach ($rels as $relName => $relType) {
                        if (Image::class === $relType && $relName === $originatingFieldName) {
                            self::$my_cache[$originatingClassName.'_'.$originFieldName] => [
                                'originClass' => $originClassName,
                                'originFieldName' => $originFieldName,
                                'dataClassName' => $className,
                                'dataType' => $type,
                            ];
                        }
                    }
                }
            }
        }
    }


}
