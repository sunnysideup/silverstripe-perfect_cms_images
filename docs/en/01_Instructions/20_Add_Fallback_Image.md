---
title: Add fallback Image
---

## Add fallback Image

A fallback (or backup) image can be assigned to the SiteConfig and used when there is no image available.

## Add DataExtension to SiteConfig

An example DataExtension to decorate SiteConfig;

```php
class MySiteConfigExtension extends Extension
{
    private $has_one = [
        "MyImage" => Image::class
    ];

    public function getCMSFields() {
        //...
        $fields->addFieldToTab(
            "Root.Images",
            PerfectCmsImagesUploadField::create(
                "MyImage",
                "My Image"
            )
        );
        //...
    }
}
```
