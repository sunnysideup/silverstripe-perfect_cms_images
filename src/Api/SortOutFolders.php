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
     * if set to true then dont do it for real!
     * @var bool
     */
    protected $dryRun = false;

    /**
     *
     * @var bool
     */
    protected $verbose = true;

    private static $unused_images_folder_name = 'unusedimages';

    public function setVerbose(?bool $b = true)
    {
        $this->verbose = $b;
        return $this;
    }

    public function setDryRun(?bool $b = true)
    {
        $this->dryRun = $b;
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
        $this->unusedImagesFolder = Folder::find_or_make($unusedFolderName);

        $folderArray = $this->getFolderArray($data);
        if ($this->verbose) {
            DB::alteration_message('==== List of folders ====');
            echo '<pre>'.print_r($folderArray, 1).'</pre>';
        }

        $listOfImageIds = $this->getListOfImages($folderArray);

        // remove
        $imagesLeft = [];
        foreach($listOfImageIds as $folderName => $listOfIds) {
            DB::alteration_message('==== DOING '.$folderName.' of Image IDs ===='. count($listOfIds).' images to keep');
            $imagesLeft[$folderName] = $this->removeUnusedFiles($folderName, $listOfIds);
        }

        // reintroduce
        foreach($imagesLeft as $folderName => $listOfIds) {
            DB::alteration_message('==== DOING '.$folderName.' of Image IDs ===='. count($listOfIds).' images to re-introduce');
            $this->moveUsedFilesIntoFolder($folderName, $listOfIds);
        }
    }


    public function getFolderArray(array $data) :array
    {
        // check folders
        $folderArray = [];
        foreach($data as $dataInner) {
            $folder = $dataInner['folder'] ?? '';
            if($folder) {
                $folderArray[$folder] = [];
                $classes = $dataInner['used_by'] ?? [];
                if(! empty($classes)) {
                    if(is_array($classes)) {
                        foreach($classes as $classAndMethodList) {
                            $folderArray[$folder][$classAndMethodList] = $classAndMethodList;
                        }
                    } else {
                        user_error('Bad definition for: '.print_r($dataInner, 1));
                    }
                }
            }
        }
        return $folderArray;
    }

    public function getListOfImages(array $folderArray) : array
    {
        $listOfImageIds = [];
        foreach($folderArray as $folderName => $classAndMethodList) {

            // find all images that should be there...
            $listOfIds = [];
            foreach($classAndMethodList as $classAndMethod) {
                list($className, $method) = explode('.', $classAndMethod);
                $fieldDetails = $this->getFieldDetails($className, $method);
                if(empty($fieldDetails)) {
                    user_error('Could not find relation: '.$className.'.'.$method);
                }
                if($fieldDetails['dataType'] === 'has_one') {
                    $list = $className::get()->columnUnique($method.'ID');
                } else {
                    $dataClassName = $fieldDetails['dataClassName'];
                    $list = $dataClassName::get()->relation($method)->columnUnique('ID');
                }
                $listOfIds = array_unique(
                    array_merge(
                        $listOfIds,
                        $list
                    )
                );
            }
            if(count($listOfIds)) {
                $listOfImageIds[$folderName] = $listOfIds;
            }
        }
        return $listOfImageIds;
    }

    /**
     * returns the images in the ID list that were not found in the folder.
     * @param  string $folderName                   Folder moving to
     * @param  array  $listOfImageIds               Images that should be in the folder
     * @return array                                Unused images
     */
    public function removeUnusedFiles(string $folderName, array $listOfImageIds) : array
    {
        $unusedFolderName = $this->unusedImagesFolder->Name;
        $folder = Folder::find_or_make($folderName);
        $listAsString = implode(',', $listOfImageIds);
        $where = ' ParentID = ' . $folder->ID. ' AND File.ID NOT IN('.$listAsString.')';
        $unused = Image::get()->where($where);
        if ($unused->exists()) {
            foreach ($unused as $file) {
                if (in_array($file->ID, $listOfImageIds)) {
                    unset($array[array_search($file->ID, $listOfImageIds)]);
                }
                $oldName = $file->getFileName();
                if($this->verbose) {
                    DB::alteration_message('moving '.$file->getFileName().' to '.$unusedFolderName);
                }
                if($this->dryRun === false) {
                    $file->ParentID = $this->unusedImagesFolder->ID;
                    $file->write();
                    $file->doPublish();
                    $newName = str_replace($folder->Name, $unusedFolderName, $oldName);
                    $file->flushCache();
                    if($newName !== $file->getFileName()) {
                        DB::alteration_message('ERROR: file names do not match. Compare: '.$newName. ' with ' . $file->getFileName(), 'deleted');
                    }
                    $this->physicallyMovingImage($oldName, $newName);
                }
            }
        }
        return $listOfImageIds;
    }

    public function moveUsedFilesIntoFolder(string $folderName, array $listOfImageIds)
    {
        $folder = Folder::find_or_make($folderName);
        $listAsString = implode(',', $listOfImageIds);
        $where = ' ParentID <> ' . $folder->ID. ' AND File.ID IN('.$listAsString.')';
        $used = Image::get()->where($where);
        if ($used->exists()) {
            foreach ($used as $file) {
                $oldFolderName = $file->Parent()->Name;
                $oldName = $file->getFileName();
                if($this->verbose) {
                    DB::alteration_message('moving '.$file->getFileName().' to '.$folderName);
                }
                if($this->dryRun === false) {
                    $file->ParentID = $folder->ID;
                    $file->write();
                    $file->doPublish();
                    if($oldFolderName === '') {
                        $newName = $folder->Name . '/' . $oldName;
                    } else {
                        $newName = str_replace($oldFolderName, $folder->Name, $oldName);
                    }
                    $file->flushCache();
                    if($this->verbose && $newName !== $file->getFileName()) {
                        DB::alteration_message('ERROR: file names do not match. Compare: '.$newName. ' with ' . $file->getFileName(), 'deleted');
                    } else {
                        $this->physicallyMovingImage($oldName, $newName);
                    }
                }
            }
        }
    }

    protected static $my_field_cache = [];

    protected function getFieldDetails(string $originClassName, string $originMethod) : array
    {
        $key = $originClassName.'_'.$originMethod;
        if(! isset(self::$my_field_cache[$key])) {
            $types = ['has_one', 'has_many', 'many_many'];
            $classNames = ClassInfo::ancestry($originClassName, true);
            foreach ($classNames as $className) {
                $obj = Injector::inst()->get($className);
                foreach ($types as $type) {
                    $rels = Config::inst()->get($className, $type, Config::UNINHERITED);
                    if (is_array($rels) && ! empty($rels)) {
                        foreach ($rels as $relName => $relType) {
                            if (Image::class === $relType && $relName === $originMethod) {
                                self::$my_field_cache[$key] = [
                                    'dataClassName' => $className,
                                    'dataType' => $type,
                                ];
                            }
                        }
                    }
                }
            }
        }
        return self::$my_field_cache[$key];
    }

    protected function physicallyMovingImage(string $oldName, string $newName)
    {
        if ($oldName !== $newName) {
            $oldNameFull = Controller::join_links(ASSETS_PATH, $oldName);
            $newNameFull = Controller::join_links(ASSETS_PATH, $newName);
            if (file_exists($oldNameFull)) {
                if(file_exists($newNameFull)) {
                    if ($this->verbose) {
                        DB::alteration_message('Deleting '.$newName.' to make place for a new file.', 'deleted');
                    }
                    unlink($newNameFull);
                }
                if ($this->verbose) {
                    DB::alteration_message('Moving '.$oldNameFull.' to '.$newNameFull, 'created');
                }
                rename($oldNameFull, $newNameFull);
            }
        } elseif($this->verbose) {
            DB::alteration_message('ERROR: old and new file names are the same '.$oldName, 'deleted');
        }
    }

    protected function checkForRogueFiles()
    {

    }

}
