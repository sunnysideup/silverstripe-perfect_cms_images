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


/**
 * the assumption we make here is that a particular group of images (e.g. Page.Image) live
 * live in a particular folder.
 */
class SortOutFolders
{

    use Configurable;

    /**
     * @var Folder
     */
    protected $unusedImagesFolder = null;

    /**
     *
     * @var bool
     */
    protected $debug = false;

    /**
     *
     * @var bool
     */
    protected $verbose = false;

    private static $unused_images_folder_name = 'unusedimages';

    public function setVerbose(?bool $b = true)
    {
        $this->verbose = $b;
        return $this;
    }

    public function setDebug(?bool $b = true)
    {
        $this->debug = $b;
        return $this;
    }


    /**
     * @param string $unusedFolderName
     * @param array $data
     * Create test jobs for the purposes of testing.
     * The array must contains arrays with
     * - folder
     * - used_by
     * used_by is an array that has ClassNames and Relations
     * (has_one / has_many / many_many relations)
     * e.g. Page.Image, MyDataObject.MyImages
     *
     * @param HTTPRequest $request
     */
    public function run(string $unusedFolderName, array $data) // phpcs:ignore
    {
        $unusedImagesFolder = Folder::find_or_make($unusedFolderName);

        $folderArray = $this->getFolderArray($data);
        if ($this->verbose) {
            DB::alteration_message('==== List of folders ====');
            print_r($folderArray);
        }

        $listOfImageIds = $this->getListOfImages($folderArray);
        if ($this->verbose) {
            DB::alteration_message('==== List of Image IDs ====');
            print_r($listOfImageIds);
        }

        // remove
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
                if(is_array($classes) && ! empty($classes)) {
                    foreach($classes as $classAndMethodList) {
                        $folderArray[$folder][$classAndMethodList] = $classAndMethodList;
                    }
                } else {
                    user_error('Bad definition for: '.print_r($dataInner, 1));
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
                $fieldDetails = $this->getFieldDetails($className, $method);
                if(empty($field)) {
                    user_error('Could not find relation: '.$className.'.'.$method);
                }
                if($fieldDetails['dataType'] === 'has_one') {
                    $list = $className::get()->columnUnique($method.'ID');
                } else {
                    $dataClassName = $fieldDetails['dataClassName'];
                    $list = $dataClassName::get()->relation($method)->columnUnique('ID');
                }
                $listOfImageIds = array_merge(
                    $listOfImageIds,
                    $list
                );
            }
            $listOfImageIds[$folderName] = $listOfIds;
        }
        return $listOfImageIds;
    }

    protected function removeUnusedFiles(string $folderName, array $listOfImageIds)
    {
        $unusedFolderName = $this->unusedImagesFolder->Name;
        $folder = Folder::find_or_make($folderName);
        $where = " ParentID = " . $folder->ID. ' AND File.ID NOT IN('.implode('.$listOfImageIds.').')';
        $unused = Image::get()->where($where);
        if ($unused->exists()) {
            foreach ($unused as $file) {
                $oldName = $file->getFullPath();
                if($this->verbose) {
                    DB::alteration_message('DEBUG ONLY '.$file->getFileName().' to '.$unusedFolderName);
                }
                if($this->debug) {
                    echo 'skipping as we are in debug mode';
                } else {
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

    protected static $my_cache = [];

    protected function getFieldDetails(string $originClassName, string $originMethod) : array
    {
        $key = $originClassName.'_'.$originMethod;
        if(! isset(self::$my_cache[$key])) {
            $types = ['has_one', 'has_many', 'many_many'];
            $classNames = ClassInfo::ancestry($originClassName, true);
            foreach ($classNames as $className) {
                $obj = Injector::inst()->get($className);
                foreach ($types as $type) {
                    $rels = Config::inst()->get($className, $type, Config::UNINHERITED);
                    if (is_array($rels) && ! empty($rels)) {
                        foreach ($rels as $relName => $relType) {
                            if (Image::class === $relType && $relName === $originatingFieldName) {
                                self::$my_cache[$key] = [
                                    'dataClassName' => $className,
                                    'dataType' => $type,
                                ];
                            }
                        }
                    }
                }
            }
        }
        return self::$my_cache[$key];
    }


}
