---
title: Add an image to a SilverStripe DataObject
summary: Add an image to a DataObject using 'Perfect CMS Images' module
---

## Add an Image to a DataObject

```php
class MyPage extends Page
{
    private $has_one = [
        "MyImage" => Image::class
    ];

    public function getCMSFields() {
        $fields = parent::getCMSFields();

        $fields->addFieldToTab(
            "Root.Images",
            PerfectCmsImagesUploadField::create(
                "MyImage",
                "My Image"
            )
        );

        return $fields;
    }
}
```
