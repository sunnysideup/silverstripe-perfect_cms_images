<?php

namespace Sunnysideup\PerfectCmsImages\Api;

use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

use SilverStripe\Assets\Image;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\File;

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
     * the folder where we move images that are not in use
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

    public function runStandard()
    {
        $this->runAdvanced(
            Config::inst()->get(PerfectCMSImages::class, 'unused_images_folder_name'),
            PerfectCMSImages::get_all_values_for_images()
        );
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
    public function runAdvanced(string $unusedFolderName, array $data) // phpcs:ignore
    {
        $this->unusedImagesFolder = Folder::find_or_make($unusedFolderName);

        $folderArray = $this->getFolderArray($data);
        if ($this->verbose) {
            DB::alteration_message('==== List of folders ====');
            echo '<pre>'.print_r($folderArray, 1).'</pre>';
        }

        $listOfImageIds = $this->getListOfImages($folderArray);

        // remove
        foreach($listOfImageIds as $folderName => $listOfIds) {
            if($this->verbose) {
                DB::alteration_message('<br /><br /><br />==== Checking for images to remove from <u>'.$folderName.'</u>; there are '. count($listOfIds).' images to keep');
            }
            $imagesLeft[$folderName] = $this->removeUnusedFiles($folderName, $listOfIds);
        }

        DB::alteration_message('==========================================');
        // move to right folder
        foreach($listOfImageIds as $folderName => $listOfIds) {
            if($this->verbose) {
                DB::alteration_message('<br /><br /><br />==== Checking for images to move to <u>'.$folderName.'</u>');
            }
            $this->moveUsedFilesIntoFolder($folderName, $listOfIds);
        }

        DB::alteration_message('==========================================');

        // check for rogue files
        foreach(array_keys($listOfImageIds) as $folderName) {
            if($this->verbose) {
                DB::alteration_message('<br /><br /><br />==== Checking for rogue FILES in <u>'.$folderName.'</u>');
            }
            $this->findRoqueFilesInFolder($folderName);
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
                $dataClassName = '';
                list($className, $method) = explode('.', $classAndMethod);
                $fieldDetails = $this->getFieldDetails($className, $method);
                if(empty($fieldDetails)) {
                    user_error('Could not find relation: '.$className.'.'.$method);
                }
                if($fieldDetails['dataType'] === 'has_one') {
                    $list = $className::get()->columnUnique($method.'ID');
                } else {
                    $dataClassName = $fieldDetails['dataClassName'];
                    $outerList = $className::get();
                    $list = [];
                    foreach($outerList as $obj) {
                        $list = array_merge($list, $obj->$method()->columnUnique('ID'));
                    }
                    $list = array_unique($list);
                }
                DB::alteration_message($className . '::' .$method . ' resulted in '.count($list));
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
     */
    public function removeUnusedFiles(string $folderName, array $listOfImageIds)
    {
        $unusedFolderName = $this->unusedImagesFolder->Name;
        $folder = Folder::find_or_make($folderName);
        $this->writeFileOrFolder($folder);
        $listAsString = implode(',', $listOfImageIds);
        $where = ' ParentID = ' . $folder->ID. ' AND File.ID NOT IN('.$listAsString.')';
        $unused = Image::get()->where($where);
        if ($unused->exists()) {
            foreach ($unused as $file) {
                $oldName = $file->getFilename();
                if($this->verbose) {
                    DB::alteration_message('moving '.$file->getFilename().' to '.$unusedFolderName);
                }
                if($this->dryRun === false) {
                    $file->ParentID = $this->unusedImagesFolder->ID;
                    $this->writeFileOrFolder($file);
                    $newName = str_replace($folder->Name, $unusedFolderName, $oldName);
                    if($newName !== $file->getFilename()) {
                        DB::alteration_message('ERROR: file names do not match. Compare: '.$newName. ' with ' . $file->getFilename(), 'deleted');
                    }
                    $this->physicallyMovingImage($oldName, $newName);
                }
            }
        }
    }

    public function moveUsedFilesIntoFolder(string $folderName, array $listOfImageIds)
    {
        $folder = Folder::find_or_make($folderName);
        $this->writeFileOrFolder($folder);
        $listAsString = implode(',', $listOfImageIds);
        $where = ' ParentID <> ' . $folder->ID. ' AND File.ID IN('.$listAsString.')';
        $used = Image::get()->where($where);
        if ($used->exists()) {
            foreach ($used as $file) {
                $oldName = $file->getFilename();

                $oldFolderName = $file->Parent()->getFilename();
                $newFolderName = $folder->getFilename();

                if($this->verbose) {
                    DB::alteration_message('moving '.$file->getFilename().' to '.$newFolderName, 'created');
                }
                if($this->dryRun === false) {
                    $newName =  Controller::join_links($newFolderName, $file->Name);
                    $file->setFilename($newName);
                    $file->ParentID = $folder->ID;
                    $this->writeFileOrFolder($file);
                    if($this->verbose && $newName !== $file->getFilename()) {
                        DB::alteration_message('ERROR: file names do not match. Compare: '.$newName. ' with ' . $file->getFilename(), 'deleted');
                    } else {
                        $this->physicallyMovingImage($oldName, $newName);
                    }
                }
            }
        }
    }


    public function findRoqueFilesInFolder(string $folderName)
    {
        $unusedFolderName = $this->unusedImagesFolder->Name;
        $folder = Folder::find_or_make($folderName);
        $this->writeFileOrFolder($folder);
        $path = Controller::join_links(ASSETS_PATH, $folder->getFilename());
        $excludeArray = Image::get()->filter(['ParentID' => $folder->ID])->columnUnique('Name');
        if (is_dir($path)) {
            $files = array_diff(scandir($path), array('.', '..'));
            foreach ($files as $fileName) {
                if(! in_array($fileName, $excludeArray)) {
                    $associatedClassName = File::get_class_for_file_extension(pathinfo($fileName, PATHINFO_EXTENSION));
                    if($associatedClassName === Image::class) {
                        $filePath = Controller::join_links($path, $fileName);
                        if (is_file($filePath)) {
                            $oldName = $folderName . '/' . $fileName;
                            $newName = $unusedFolderName . '/' . $fileName;
                            if($this->verbose) {
                                DB::alteration_message('moving '.$oldName.' to '.$unusedFolderName);
                            }
                            if ($this->dryRun === false) {
                                $this->physicallyMovingImage($oldName, $newName);
                            }
                        } elseif($this->verbose) {
                            DB::alteration_message('skippping '.$fileName. ', because it is not a valid file.');
                        }
                    } elseif($this->verbose) {
                        DB::alteration_message('skippping '.$fileName. ', because it is not an image.');
                    }
                }
            }
        } elseif($this->verbose) {
            DB::alteration_message('skippping '.$path. ', because it is not a valid directory.');
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
                        DB::alteration_message('... ... Deleting '.$newName.' to make place for a new file.', 'deleted');
                    }
                    if($this->dryRun === false) {
                        unlink($newNameFull);
                    }
                }
                if ($this->verbose) {
                    DB::alteration_message('... Moving '.$oldNameFull.' to '.$newNameFull, 'created');
                }
                if($this->dryRun === false) {
                    rename($oldNameFull, $newNameFull);
                }
            } elseif($this->verbose) {
                DB::alteration_message('... Error could not find:  '.$oldNameFull, 'created');
            }
        } elseif($this->verbose) {
            DB::alteration_message('... ERROR: old and new file names are the same '.$oldName, 'deleted');
        }
    }

    protected function writeFileOrFolder($fileOrFolder)
    {
        $fileOrFolder->write();
        $fileOrFolder->doPublish();
        $fileOrFolder->publishFile();
        $fileOrFolder->flushCache();

        return $fileOrFolder;
    }


}
