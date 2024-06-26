<?php

namespace Sunnysideup\PerfectCmsImages\Api;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;

/**
 * the assumption we make here is that a particular group of images (e.g. Page.Image) live
 * live in a particular folder.
 */
class SortOutFolders
{
    use Configurable;

    /**
     * the folder where we move images that are not in use.
     *
     * @var Folder
     */
    protected $unusedImagesFolder;

    /**
     * if set to true then dont do it for real!
     *
     * @var bool
     */
    protected $dryRun = false;

    /**
     * @var bool
     */
    protected $verbose = true;

    protected static $my_field_cache = [];

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
     * @param array $data
     *                    Create test jobs for the purposes of testing.
     *                    The array must contains arrays with
     *                    - folder
     *                    - used_by
     *                    used_by is an array that has ClassNames and Relations
     *                    (has_one / has_many / many_many relations)
     *                    e.g. Page.Image, MyDataObject.MyImages
     */
    public function runAdvanced(string $unusedFolderName, array $data)
    {
        if ($unusedFolderName === '' || $unusedFolderName === '0') {
            $unusedFolderName = 'unused-images';
        }
        $this->unusedImagesFolder = Folder::find_or_make($unusedFolderName);

        $folderArray = $this->getFolderArray($data);
        if ($this->verbose) {
            DB::alteration_message('==== List of folders ====');
            echo '<pre>' . print_r($folderArray, 1) . '</pre>';
        }

        DB::alteration_message('==========================================');

        $listOfImageIds = $this->getListOfImages($folderArray);

        // remove
        foreach ($listOfImageIds as $folderName => $listOfIds) {
            if ($this->verbose) {
                DB::alteration_message('<br /><br /><br />==== Checking for images to remove from <u>' . $folderName . '</u>; there are ' . count($listOfIds) . ' images to keep');
            }
            $imagesLeft[$folderName] = $this->removeUnusedFiles($folderName, $listOfIds);
        }

        DB::alteration_message('==========================================');
        // move to right folder
        foreach ($listOfImageIds as $folderName => $listOfIds) {
            if ($this->verbose) {
                DB::alteration_message('<br /><br /><br />==== Checking for images to move to <u>' . $folderName . '</u>');
            }
            $this->moveUsedFilesIntoFolder($folderName, $listOfIds);
        }

        DB::alteration_message('==========================================');

        // check for rogue files
        foreach (array_keys($listOfImageIds) as $folderName) {
            if ($this->verbose) {
                DB::alteration_message('<br /><br /><br />==== Checking for rogue FILES in <u>' . $folderName . '</u>');
            }
            $this->findRoqueFilesInFolder($folderName);
        }
    }

    public function getFolderArray(array $data): array
    {
        // check folders
        $folderArray = [];
        foreach ($data as $dataInner) {
            $folderName = $dataInner['folder'] ?? '';
            if ($folderName) {
                $folderArray[$folderName] = [];
                $folderArray[$folderName]['classesAndMethods'] = [];
                // $folderArray[$folderName]['resize'] = isset($dataInner['force_resize']) && $dataInner['force_resize'] === true  ? true : false;
                $classes = $dataInner['used_by'] ?? [];
                if (! empty($classes)) {
                    if (is_array($classes)) {
                        foreach ($classes as $classAndMethod) {
                            $folderArray[$folderName]['classesAndMethods'][$classAndMethod] = $classAndMethod;
                        }
                    } else {
                        user_error('Bad definition for: ' . print_r($dataInner, 1));
                    }
                }
            }
        }
        $test = [];
        foreach ($folderArray as $folderData) {
            $classAndMethodList = $folderData['classesAndMethods'];
            foreach ($classAndMethodList as $classAndMethod) {
                if (! isset($test[$classAndMethod])) {
                    $test[$classAndMethod] = true;
                } else {
                    user_error('You have doubled up on folder for Class and Method: ' . $classAndMethod);
                }
            }
        }

        return $folderArray;
    }

    public function getListOfImages(array $folderArray): array
    {
        $listOfImageIds = [];
        foreach ($folderArray as $folderName => $folderData) {
            $classAndMethodList = $folderData['classesAndMethods'];

            // find all images that should be there...
            $listOfIds = [];
            foreach ($classAndMethodList as $classAndMethod) {
                $dataClassName = '';
                list($className, $method) = explode('.', $classAndMethod);
                $fieldDetails = $this->getFieldDetails($className, $method);
                if ($fieldDetails === []) {
                    user_error('Could not find relation: ' . $className . '.' . $method);
                }
                if ('has_one' === $fieldDetails['dataType']) {
                    $list = $className::get()->columnUnique($method . 'ID');
                } else {
                    $dataClassName = $fieldDetails['dataClassName'];
                    $outerList = $className::get();
                    $list = [];
                    foreach ($outerList as $obj) {
                        $list = array_merge($list, $obj->{$method}()->columnUnique('ID'));
                    }
                    $list = array_unique($list);
                }
                DB::alteration_message($className . '::' . $method . ' resulted in ' . count($list));
                $listOfIds = array_unique(
                    array_merge(
                        $listOfIds,
                        $list
                    )
                );
            }
            if ($listOfIds !== []) {
                $listOfImageIds[$folderName] = $listOfIds;
            }
        }

        return $listOfImageIds;
    }

    /**
     * returns the images in the ID list that were not found in the folder.
     *
     * @param string $folderName     Folder moving to
     * @param array  $listOfImageIds Images that should be in the folder
     */
    public function removeUnusedFiles(string $folderName, array $listOfImageIds)
    {
        $unusedFolderName = $this->unusedImagesFolder->Name;
        $folder = Folder::find_or_make($folderName);
        $listAsString = implode(',', $listOfImageIds);
        $where = ' ParentID = ' . $folder->ID . ' AND File.ID NOT IN(' . $listAsString . ')';
        $unused = Image::get()->where($where);
        if ($unused->exists()) {
            foreach ($unused as $file) {
                echo '.';
                $oldName = $file->getFilename();
                if ($this->verbose) {
                    DB::alteration_message('moving ' . $file->getFilename() . ' to ' . $unusedFolderName);
                }
                if (false === $this->dryRun) {
                    $newName = Controller::join_links($this->unusedImagesFolder->getFileName(), $file->Name);
                    $file = $this->moveToNewFolder($file, $this->unusedImagesFolder, $newName);
                    if ($newName !== $file->getFilename()) {
                        DB::alteration_message('ERROR: file names do not match. Compare: ' . $newName . ' with ' . $file->getFilename(), 'deleted');
                    } else {
                        $this->physicallyMovingImage($oldName, $newName);
                    }
                }
            }
        }
    }

    public function moveUsedFilesIntoFolder(string $folderName, array $listOfImageIds)
    {
        $folder = Folder::find_or_make($folderName);
        $listAsString = implode(',', $listOfImageIds);
        $where = ' ParentID <> ' . $folder->ID . ' AND File.ID IN(' . $listAsString . ')';
        $used = Image::get()->where($where);
        if ($used->exists()) {
            foreach ($used as $file) {
                $oldName = $file->getFilename();

                $oldFolderName = $file->Parent()->getFilename();
                $newFolderName = $folder->getFilename();

                if ($this->verbose) {
                    DB::alteration_message('moving ' . $file->getFilename() . ' to ' . $newFolderName, 'created');
                }
                if (false === $this->dryRun) {
                    $newName = Controller::join_links($newFolderName, $file->Name);
                    $file = $this->moveToNewFolder($file, $folder, $newName);
                    if ($newName !== $file->getFilename()) {
                        DB::alteration_message('ERROR: file names do not match. Compare: ' . $newName . ' with ' . $file->getFilename(), 'deleted');
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
        $fullFolderPath = Controller::join_links(ASSETS_PATH, $folder->getFilename());
        $excludeArray = Image::get()->filter(['ParentID' => $folder->ID])->columnUnique('Name');
        if (is_dir($fullFolderPath)) {
            $files = array_diff(scandir($fullFolderPath), ['.', '..']);
            foreach ($files as $fileName) {
                if (! in_array($fileName, $excludeArray, true)) {
                    $associatedClassName = File::get_class_for_file_extension(pathinfo($fileName, PATHINFO_EXTENSION));
                    if (Image::class === $associatedClassName) {
                        $filePath = Controller::join_links($fullFolderPath, $fileName);
                        if (is_file($filePath)) {
                            $oldName = $folderName . '/' . $fileName;
                            $newName = $unusedFolderName . '/' . $fileName;
                            if ($this->verbose) {
                                DB::alteration_message('moving ' . $oldName . ' to ' . $unusedFolderName);
                            }
                            if (false === $this->dryRun) {
                                $this->physicallyMovingImage($oldName, $newName);
                            }
                        } elseif ($this->verbose) {
                            DB::alteration_message('skippping ' . $fileName . ', because it is not a valid file.');
                        }
                    } elseif ($this->verbose) {
                        DB::alteration_message('skippping ' . $fileName . ', because it is not an image.');
                    }
                }
            }
        } elseif ($this->verbose) {
            DB::alteration_message('skippping ' . $fullFolderPath . ', because it is not a valid directory.');
        }
    }

    protected function getFieldDetails(string $originClassName, string $originMethod): array
    {
        $key = $originClassName . '_' . $originMethod;
        if (! isset(self::$my_field_cache[$key])) {
            $types = ['has_one', 'has_many', 'many_many'];
            $classNames = ClassInfo::ancestry($originClassName, true);
            foreach ($classNames as $className) {
                $obj = Injector::inst()->get($className);
                foreach ($types as $type) {
                    $rels = Config::inst()->get($className, $type, Config::UNINHERITED);
                    if (is_array($rels) && $rels !== []) {
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
                if (file_exists($newNameFull)) {
                    if ($this->verbose) {
                        DB::alteration_message('... ... Deleting ' . $newName . ' to make place for a new file.', 'deleted');
                    }
                    if (false === $this->dryRun) {
                        unlink($newNameFull);
                    }
                }
                if ($this->verbose) {
                    DB::alteration_message('... Moving ' . $oldNameFull . ' to ' . $newNameFull . ' (file only)', 'created');
                }
                if (false === $this->dryRun) {
                    rename($oldNameFull, $newNameFull);
                }
            } elseif ($this->verbose && ! file_exists($newNameFull)) {
                DB::alteration_message('... Error: could not find:  ' . $oldNameFull . ' and it is also not here: ' . $newNameFull, 'created');
            }
        } elseif ($this->verbose) {
            DB::alteration_message('... ERROR: old and new file names are the same ' . $oldName, 'deleted');
        }
    }

    protected function writeFileOrFolder($fileOrFolder)
    {
        $fileOrFolder->writeToStage(Versioned::DRAFT);
        $fileOrFolder->publishSingle();

        return $fileOrFolder;
    }

    protected function moveToNewFolder($image, Folder $newFolder, string $newName)
    {
        $beforePath = (Controller::join_links(ASSETS_PATH, $image->getFilename()));
        $afterPath = (Controller::join_links(ASSETS_PATH, $newFolder->getFileName(), $image->Name));
        if (file_exists($afterPath)) {
            unlink($afterPath);
        }
        $image->ParentID = $newFolder->ID;
        $image->setFilename($newName);
        $image = $this->writeFileOrFolder($image);
        $image->flushCache();
        if (file_exists($beforePath) && ! file_exists($afterPath)) {
            rename($beforePath, $afterPath);
        }

        return $image;
    }
}
