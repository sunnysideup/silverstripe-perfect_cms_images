<?php

namespace Sunnysideup\PerfectCmsImages\Api;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;

/**
 * the assumption we make here is that a particular group of images (e.g. Page.Image) live
 * live in a particular folder.
 */
class SortOutFolders
{
    use Injectable;

    /**
     * the folder where we move images that are not in use.
     */
    protected Folder $unusedImagesFolder;

    /**
     * if set to true then dont do it for real!
     */
    protected bool $dryRun = false;

    protected bool $verbose = true;

    protected array $fieldCache = [];

    protected $folderArray = [];

    protected $listOfImageIds = [];

    public function setVerbose(?bool $b = true): static
    {
        $this->verbose = $b;

        return $this;
    }

    public function setDryRun(?bool $b = true): static
    {
        $this->dryRun = $b;

        return $this;
    }

    public function runStandard(): void
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
    public function runAdvanced(string $unusedFolderName, array $data): void
    {
        if ($unusedFolderName === '' || $unusedFolderName === '0') {
            $unusedFolderName = 'unused-images';
        }
        $this->unusedImagesFolder = Folder::find_or_make($unusedFolderName);

        $this->setFolderArray($data);
        if ($this->verbose) {
            DB::alteration_message('==== List of folders ====');
            echo '<pre>' . print_r($this->folderArray, 1) . '</pre>';
        }

        DB::alteration_message('==========================================');

        $this->setListOfImages();

        // remove
        foreach ($this->listOfImageIds as $folderName => $listOfIds) {
            if ($this->verbose) {
                DB::alteration_message('<br /><br /><br />==== Checking for images to remove from <u>' . $folderName . '</u>; there are ' . count($listOfIds) . ' images to keep');
            }
            $this->removeUnusedFiles($folderName);
        }

        DB::alteration_message('==========================================');
        // move to right folder
        foreach ($this->listOfImageIds as $folderName => $listOfIds) {
            if ($this->verbose) {
                DB::alteration_message('<br /><br /><br />==== Checking for images to move to <u>' . $folderName . '</u>');
            }
            $this->moveUsedFilesIntoFolder($folderName);
        }

        DB::alteration_message('==========================================');

        // check for rogue files
        foreach (array_keys($this->listOfImageIds) as $folderName) {
            if ($this->verbose) {
                DB::alteration_message('<br /><br /><br />==== Checking for rogue FILES in <u>' . $folderName . '</u>');
            }
            $this->findRoqueFilesInFolder($folderName);
        }
    }

    /**
     * uses the standard perfect_cms_images configuration to create a list of folders
     * and the associated Model.Method pairs that should be in the folder.
     */
    protected function setFolderArray(array $data): void
    {
        // check folders
        $this->folderArray = [];
        foreach ($data as $dataInner) {
            $folderName = $dataInner['folder'] ?? '';
            if ($folderName) {
                $this->folderArray[$folderName] = [];
                $this->folderArray[$folderName]['classesAndMethods'] = [];
                // $this->folderArray[$folderName]['resize'] = isset($dataInner['force_resize']) && $dataInner['force_resize'] === true  ? true : false;
                $classes = $dataInner['used_by'] ?? [];
                if (! empty($classes)) {
                    if (is_array($classes)) {
                        foreach ($classes as $classAndMethod) {
                            $this->folderArray[$folderName]['classesAndMethods'][$classAndMethod] = $classAndMethod;
                        }
                    } else {
                        user_error('Bad definition for: ' . print_r($dataInner, 1));
                    }
                }
            }
        }
        $test = [];
        foreach ($this->folderArray as $folderData) {
            $classAndMethodList = $folderData['classesAndMethods'];
            foreach ($classAndMethodList as $classAndMethod) {
                if (! isset($test[$classAndMethod])) {
                    $test[$classAndMethod] = true;
                } else {
                    user_error('You have doubled up on folder for Class and Method: ' . $classAndMethod);
                }
            }
        }

    }

    /**
     * get a list of image IDs that should be in the folder.
     */
    protected function setListOfImages(): void
    {
        $this->listOfImageIds = [];
        foreach ($this->folderArray as $folderName => $folderData) {
            $classAndMethodList = $folderData['classesAndMethods'];

            // find all images that should be there...
            $listOfIds = [];
            foreach ($classAndMethodList as $classAndMethod) {
                list($className, $method) = explode('.', $classAndMethod);
                $fieldDetails = $this->getFieldDetails($className, $method);
                if ($fieldDetails === []) {
                    user_error('Could not find relation: ' . $className . '.' . $method);
                }
                if ('has_one' === $fieldDetails['dataType']) {
                    $list = $className::get()->columnUnique($method . 'ID');
                } else {
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
                $this->listOfImageIds[$folderName] = $listOfIds;
            }
        }
    }

    /**
     * returns the images in the ID list that were not found in the folder.
     *
     * @param string $folderName     Folder moving to
     */
    public function removeUnusedFiles(string $folderName)
    {
        $unusedFolderName = $this->unusedImagesFolder->Name;
        $folder = Folder::find_or_make($folderName);
        $listAsString = implode(',', $this->listOfImageIds);
        $where = ' ParentID = ' . $folder->ID . ' AND File.ID NOT IN(' . $listAsString . ')';
        $unused = Image::get()->where($where);
        if ($unused->exists()) {
            foreach ($unused as $file) {
                echo '.';
                $oldNameFromAssetRoot = $file->getFilename();
                if ($this->verbose) {
                    DB::alteration_message('moving ' . $file->getFilename() . ' to ' . $unusedFolderName);
                }
                if (false === $this->dryRun) {
                    $newNameFromAssetRoot = Controller::join_links($this->unusedImagesFolder->getFileName(), $file->Name);
                    $file = $this->moveToNewFolder($file, $this->unusedImagesFolder, $newNameFromAssetRoot);
                    if ($newNameFromAssetRoot !== $file->getFilename()) {
                        DB::alteration_message('ERROR: file names do not match. Compare: ' . $newNameFromAssetRoot . ' with ' . $file->getFilename(), 'deleted');
                    } else {
                        $this->physicallyMovingImage($oldNameFromAssetRoot, $newNameFromAssetRoot);
                    }
                }
            }
        }
    }

    /**
     * find any images and move them into the specified folder.
     */
    public function moveUsedFilesIntoFolder(string $folderName)
    {
        $folder = Folder::find_or_make($folderName);
        $listAsString = implode(',', $this->listOfImageIds);
        $where = ' ParentID <> ' . $folder->ID . ' AND File.ID IN(' . $listAsString . ')';
        $used = Image::get()->where($where);
        if ($used->exists()) {
            foreach ($used as $file) {
                $oldNameFromAssetRoot = $file->getFilename();

                $newFolderName = $folder->getFilename();

                if ($this->verbose) {
                    DB::alteration_message('moving ' . $file->getFilename() . ' to ' . $newFolderName, 'created');
                }
                if (false === $this->dryRun) {
                    $newNameFromAssetRoot = Controller::join_links($newFolderName, $file->Name);
                    $file = $this->moveToNewFolder($file, $folder, $newNameFromAssetRoot);
                    if ($newNameFromAssetRoot !== $file->getFilename()) {
                        DB::alteration_message('ERROR: file names do not match. Compare: ' . $newNameFromAssetRoot . ' with ' . $file->getFilename(), 'deleted');
                    } else {
                        $this->physicallyMovingImage($oldNameFromAssetRoot, $newNameFromAssetRoot);
                    }
                }
            }
        }
    }

    /**
     * find any superfluous images in the folder, not listed in the database at all,
     * and move them to the unused folder.
     */
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
                            $oldNameFromAssetRoot = $folderName . '/' . $fileName;
                            $newNameFromAssetRoot = $unusedFolderName . '/' . $fileName;
                            if ($this->verbose) {
                                DB::alteration_message('moving ' . $oldNameFromAssetRoot . ' to ' . $unusedFolderName);
                            }
                            if (false === $this->dryRun) {
                                $this->physicallyMovingImage($oldNameFromAssetRoot, $newNameFromAssetRoot);
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
        if (! isset($this->fieldCache[$key])) {
            $types = ['has_one', 'has_many', 'many_many'];
            $classNames = ClassInfo::ancestry($originClassName, true);
            foreach ($classNames as $className) {
                $obj = Injector::inst()->get($className);
                foreach ($types as $type) {
                    $rels = Config::inst()->get($className, $type, Config::UNINHERITED);
                    if (is_array($rels) && $rels !== []) {
                        foreach ($rels as $relName => $relType) {
                            if (Image::class === $relType && $relName === $originMethod) {
                                $this->fieldCache[$key] = [
                                    'dataClassName' => $className,
                                    'dataType' => $type,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $this->fieldCache[$key];
    }

    protected function physicallyMovingImage(string $oldNameFromAssetRoot, string $newNameFromAssetRoot)
    {
        $oldNameFullPath = Controller::join_links(ASSETS_PATH, $oldNameFromAssetRoot);
        if (file_exists($oldNameFullPath)) {
            $newNameFromAssetRoot = $this->changeNameBasedOnExistingFiles($newNameFromAssetRoot);
            $newNameFullPath = Controller::join_links(ASSETS_PATH, $newNameFromAssetRoot);
            if ($oldNameFromAssetRoot !== $newNameFromAssetRoot) {

                if ($this->verbose) {
                    DB::alteration_message('... Moving ' . $oldNameFullPath . ' to ' . $newNameFullPath . ' (file only)', 'created');
                }
                if (false === $this->dryRun) {
                    rename($oldNameFullPath, $newNameFullPath);
                }
            } elseif ($this->verbose && ! file_exists($newNameFullPath)) {
                DB::alteration_message('... Error: could not find:  ' . $oldNameFullPath . ' and it is also not here: ' . $newNameFullPath, 'created');
            }
        } elseif ($this->verbose) {
            DB::alteration_message('... ERROR: old and new file names are the same ' . $oldNameFromAssetRoot, 'deleted');
        }
    }

    protected function writeFileOrFolder($fileOrFolder)
    {
        $isPublished = $fileOrFolder->isPublished() && ! $fileOrFolder->isModifiedOnDraft();
        $fileOrFolder->writeToStage(Versioned::DRAFT);
        if ($isPublished) {
            $fileOrFolder->publishSingle();
        }
        $fileOrFolder->flushCache();
        return $fileOrFolder;
    }

    protected function moveToNewFolder($image, Folder $newFolder, string $newNameFromAssetRoot)
    {
        $beforePath = (Controller::join_links(ASSETS_PATH, $image->getFilename()));
        $newNameFromAssetRoot = $this->changeNameBasedOnExistingFiles($newNameFromAssetRoot);
        $afterPath = (Controller::join_links(ASSETS_PATH, $newNameFromAssetRoot));
        $image->ParentID = $newFolder->ID;
        $image->setFilename($newNameFromAssetRoot);
        $image = $this->writeFileOrFolder($image);
        if (file_exists($beforePath) && ! file_exists($afterPath)) {
            rename($beforePath, $afterPath);
        }

        return $image;
    }

    protected function changeNameBasedOnExistingFiles(string $newNameFromAssetRoot): string
    {
        $x = 1;
        $pathInfo = pathinfo($newNameFromAssetRoot);
        while (file_exists(Controller::join_links(ASSETS_PATH, $newNameFromAssetRoot)) && $x < 100) {
            $x++;
            $newNameFromAssetRoot = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '-v' . $x . '.' . $pathInfo['extension'];
        }
        return $newNameFromAssetRoot;
    }
}
