Perfect CMS Image
================

Why we build this module ...
------------
To make it easier to manage image sizes in the various places (CMS, templates) we have set up a system to manage image sizes in just one place (the config layer).  Each unique image collection (e.g. HomePageBanner) has its own standard settings (all optional):
 - `width`
 - `height`
 - `folder for upload`
 - `file type`
 - `enforce_size`

You can also provide a backup image in the SiteConfig in case the user has not (yet) uploaded an image.

Prerequisites
-------------
This is specifically meant for images that are always the same size (e.g. Page Banner, Team Member, etc...).

What it does
-------------
 * provides clear instructions with upload field
 * takes care of double size for retina
 * always saves the image in a specific folder
 * makes sure image is valid and not too big in file size
 * provides a backup image
 * in case there is no backup, it adds a placeholder image


Instructions
------------

  1. add an image in Page or MyDataObject
  2. add a back-up image to siteconfig
  3. define the settings for the image in the `PerfectCmsImageDataExtension` class using yml configuration.
  4. in the CMS Fields, use `PerfectCmsImagesUploadField`
  5. in the template write: `$MyImage.PerfectCmsImageLink(NameOfFormat)`

# Nota Bene

 * use a unique image name for each image field you add to the site
   e.g. call an image AccountsBanner instead of Banner
 * dont double the image sizes
 * you can choose to only set the standard height or the width

# add an image in Page or MyDataObject

```php
    class MyPage extends Page
    {
        private $has_one = array("MyImage" => "Image");
    }
```

# in a class that decorates / extends SiteConfig, add the same image

```php
    class MySiteConfigExtension extends DataExtension
    {
        private $has_one = array("MyImage" => "Image");
    }
```

# define image

copy `perfect_cms_images/_config/perfect_cms_images.yml.example`
to `app/_config/perfect_cms_images.yml`
and rewrite like this:

```yml
    ---
    Name: perfect_cms_images_custom
    ---
    PerfectCmsImageDataExtension:
      perfect_cms_images_background_padding_color: "#cccccc"
      perfect_cms_images_image_definitions:
        "MyImage":
          width: 900 (could be zero)
          height: 300 (could be zero)
          folder: uploaded-my-images
          filetype: "png"
        "MyOtherImage":
          width: 400
          height: 0
          folder: "uploaded-my-images2"
          filetype: "jpg or gif"
```

# set up CMS Field in Page


```php
    class MyPage extends Page
    {
        private $has_one = array("MyImage" => "Image");

        public function getCMSFields() {
            //...
            $fields->addFieldToTab(
                "Root.Images",
                PerfectCmsImagesUploadField::create(
                    $name = "MyImage",
                    $title = "My Cool Image"
                )
            );
            //...
        }
    }
```

you can also use a different formatting standard


```php
    class MyPage extends Page
    {
        private $has_one = array("MyImage" => "Image");

        public function getCMSFields() {
            //...
            $fields->addFieldToTab(
                "Root.Images",
                PerfectCmsImagesUploadField::create(
                    $name = "MyImage",
                    $title = "My Cool Image"
                )->selectFormattingStandard('MyOtherImage')
            );
            //...
        }
    }
```

# set up CMS Field in SiteConfig


```php
    class MySiteConfigExtension extends DataExtension
    {
        private $has_one = array("MyImage" => "Image");

        public function getCMSFields() {
            //...
            $fields->addFieldToTab(
                "Root.Images",
                PerfectCmsImagesUploadField::create(
                    $name = "MyImage",
                    $title = "My Default Cool Image"
                )
            );
            //...
        }
    }
```


# templage Usage

```html
    <img src="$MyImage.PerfectCmsImageLink(MyImage)" alt="$Title.ATT" />
```

OR

```html
    <img src="$MyImage.PerfectCmsImageLink(MyOtherImage)" alt="$Title.ATT" />
```

# Important Note for those using Hash Path module

If you are using the Hash Path module then a hash path will be added to all links created by the Perfect CMS Images module.  To ensure that your images will be displayed add the following to an htaccess file in the Assets folder.

```php
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.+)\.(v[A-Za-z0-9]+)\.(js|css|png|PNG|jpg|JPG|gif|GIF)$ $1.$3 [L]
    </IfModule>
```

Credits
------------

Special thank you to Klemen Novak for help with this module.
