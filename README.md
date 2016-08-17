Perfect CMS Image
================

Prerequisite:
-------------
This is specifically meant for images that are always the same size (e.g. Page Banner, Team Member, etc...).

What it does:
-------------
 * provides clear instructions in upload field
 * takes care of double size for retina
 * always saves the image in a specific folder
 * makes sure image is valid and not too big in file size
 * provides a back-up image
 * in case there is no back-up, then it adds a placeholder image


# Instructions:

  1. add an image in Page or MyDataObject
  2. add a back-up image to siteconfig
  3. define the settings for the image in the `PerfectCMSImageDataExtension` class using yml configuration.
  4. in the CMS Fields, use `PerfectCMSImagesUploadField`
  5. in the template write: `$MyImage.PerfectCMSImageLink(NameOfFormat)`

# Nota Bene
 * use a unique image name for each image field you add to the site
   e.g. call an image AccountsBanner instead of Banner
 * dont double the image sizes
 * you can choose to only set the standard height or the width

# add an image in Page or MyDataObject

```
    class MyPage extends Page
    {
        private $has_one = array("MyImage" => "Image");
    }
```

# in a class that decorates / extends SiteConfig, add the same image

```
    class MySiteConfigExtension extends DataExtension
    {
        private $has_one = array("MyImage" => "Image");
    }
```

# define image

copy `perfect_cms_images/_config/perfect_cms_images.yml.example`
to `mysite/_config/perfect_cms_images.yml`
and rewrite like this:

```
    ---
    Name: perfect_cms_images_custom
    ---
    PerfectCMSImageDataExtension:
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


```
    class MyPage extends Page
    {
        private $has_one = array("MyImage" => "Image");

        public function getCMSFields() {
            //...
            $fields->addFieldToTab(
                "Root.Images",
                PerfectCMSImagesUploadField::create(
                    $name = "MyImage",
                    $title = "My Cool Image"
                )
            );
            //...
        }
    }
```

you can also use a different formatting standard:


```
    class MyPage extends Page
    {
        private $has_one = array("MyImage" => "Image");

        public function getCMSFields() {
            //...
            $fields->addFieldToTab(
                "Root.Images",
                PerfectCMSImagesUploadField::create(
                    $name = "MyImage",
                    $title = "My Cool Image"
                )-selectFormattingStandard('MyOtherImage')
            );
            //...
        }
    }
```

# set up CMS Field in SiteConfig


```
    class MySiteConfigExtension extends DataExtension
    {
        private $has_one = array("MyImage" => "Image");

        public function getCMSFields() {
            //...
            $fields->addFieldToTab(
                "Root.Images",
                PerfectCMSImagesUploadField::create(
                    $name = "MyImage",
                    $title = "My Default Cool Image"
                )
            );
            //...
        }
    }
```


# templage Usage

```
    <img src="$MyImage.PerfectCMSImageLink(MyImage)" alt="$Title.ATT" />
```
