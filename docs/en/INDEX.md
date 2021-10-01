Perfect CMS Images
================

Features
--------
- Provide content editor with information on image size and type
- Automatically generate & provide _retina ready_ images



Prerequisites
-------------
Designed for use with images that are always the same size.



What it does
-------------
 * Provides clear instructions accompanying upload field in the CMS
 * Generates higher resolution (double size) images for retina displays
 * Saves the image in a specific folder
 * Ensures the image is valid and not too large in file size
 * Provides a backup image
 * Adds a placeholder image when there is no backup



Instructions
------------
[CHILDREN Folder=01_Instructions]


# Nota Bene

 * use a unique image name for each image field you add to the site
   e.g. call an image AccountsBanner instead of Banner
 * dont double the image sizes
 * you can choose to only set the standard height or the width


# using web p / webp images

You must turn set `SS_ENABLE_WEBP=true` in your `.env` file.
To turn it off, you have to remove `SS_ENABLE_WEBP` altogether.


# Important Note for those using Hash Path module


Note that you can use `perfect_cms_images_append_title_to_image_links_classes`
to add titles to images so that you get better SEO results.
For this you will need to strip out the title part from the link using `.htaccess` (see bottom of this info)

If you are using the Hash Path module then a hash path will be added to all links created by the Perfect CMS Images module.  To ensure that your images will be displayed you may need to add the following to your .htaccess file in the Assets folder.

```apacheconfig
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
