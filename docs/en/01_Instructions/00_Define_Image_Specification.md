---
title: Define image specification
---

## Define image specification

Copy `perfect_cms_images/_config/perfect_cms_images.yml.example` to `app/_config/perfect_cms_images.yml` and specify images ie:


```yml
---
Name: perfect_cms_images_custom
---
Sunnysideup\PerfectCmsImages\Model\File\PerfectCmsImageDataExtension:
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
    "MyThirdImage":
      width: 600
      height: 600
      folder: "uploaded-third-images"
      enforce_size: true
```


## Template Usage


```html
    $MyImage.PerfectCmsImageTag(MyImage)
```

OR

```html
    <img src="$MyImage.PerfectCmsImageLink(MyImage)" alt="$Title.ATT" />
    <img src="$MyImage.PerfectCmsImageLink(MyOtherImage)" alt="$Title.ATT" />
    <img src="$MyImage.PerfectCmsImageLinkNonRetina(MyOtherImage)" alt="$Title.ATT" />
    <img src="$MyImage.PerfectCmsImageLinkRetina(MyOtherImage)" alt="$Title.ATT" />
```


