# Example implementation for a rapid uploader

In some circumstances it may be desirable to allow CMS users to rapidly populate content by uploading files. You can use this module to handle additional actions after a file as been uploaded. For this example we will create a basic form controller that extends from LeftAndMain and has a PerfectCmsImagesUploadField.

```php
<?php

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;

use Sunnysideup\PerfectCmsImages\Forms\PerfectCmsImagesUploadField;

use \CarouselItem;

class RapidUploader extends LeftAndMain
{
    private static $url_segment     = 'uploader-carousel/add';
    private static $ignore_menuitem = true;

    private static $allowed_actions = [
        'EmptyForm',
    ];

    /**
     * @return Form
     */
    public function EmptyForm()
    {
        return Form::create(
            $this,
            "EditForm",
            FieldList::create([
                PerfectCmsImagesUploadField::create('AttachedFile')
                    ->setIsMultiUpload(true)
                    ->setAfterUpload(function(HTTPResponse $response) {
                        // Read data from the original response
                        $data = json_decode($response->getBody())[0];

                        // Create a new CarouselItem
                        $x = CarouselItem::create();

                        // Set the BackgroundImage to the uploaded file
                        $x->BackgroundImageID = $data->id;

                        // preg the Title/SubTitle from the title of the file
                        $title = preg_split('[/.---]', $data->title, PREG_SPLIT_OFFSET_CAPTURE);
                        $x->Title = $title[0];
                        if(isset($title[1])) { $x->SubTitle = $title[1]; }

                        // Write the CarouselItem
                        $x->write();

                        // Return the original response (untouched)
                        return $response;
                    })
            ])
        );
    }
}
```

This is just an example but could easily be extended to read additional metadata from the file for populating as separate fields in the CMS.
