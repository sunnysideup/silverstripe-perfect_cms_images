# Example of usage in template

```ss

$MyImage.PerfectCMSImageTag

```

That is all.

Below are a list of all public methods on Images.

## Image Tag & Link Generation

### `getPerfectCMSImageTag(string $name, $inline = false, ?string $alt = '', ?string $attributes = '')`

A convenience wrapper method that passes all arguments directly to `PerfectCMSImageTag`. Returns the HTML string for the image.

### `PerfectCMSImageTag(string $name, $inline = false, ?string $alt = '', ?string $attributes = '')`

Generates and returns the complete HTML `<img>` tag (or an inline template) for a specific image configuration name. It handles caching the generated HTML to improve performance and dynamically applies alt text and extra HTML attributes.

### `PerfectCMSImageLink(string $name, ?bool $useRetina = true, ?bool $forMobile = false)`

The core method for generating the actual URL of the image. It checks if the image exists (or falls back to a backup/placeholder image), processes retina and mobile flags, and constructs the appropriate file path.

### `PerfectCMSImageLinkNonRetina(string $name)`

Returns a standard resolution (non-retina) URL string for a specified image configuration name.

### `PerfectCMSImageLinkRetina(string $name)`

Returns a high-resolution (retina) URL string for a specified image configuration name.

### `PerfectCMSImageLinkNonRetinaForMobile(string $name)`

Returns a standard resolution URL string specifically sized for mobile viewports.

### `PerfectCMSImageLinkRetinaForMobile(string $name)`

Returns a high-resolution (retina) URL string specifically sized for mobile viewports.

### `getPerfectCMSImageAbsoluteLink(string $link)`

Takes a relative image URL and converts it into a full, absolute URL (including the domain) using SilverStripe's `Director`.

---

## SVG Handling & Rendering

### `IsSVG()`

Returns a boolean (`true` or `false`) indicating whether the current image file is an SVG by checking its file extension.

### `getSVGFormat()`

Returns the SVG for template rendering. If the file is an SVG, it returns the raw SVG wrapped safely as a `DBHTMLText` object. Otherwise, it falls back to the default SilverStripe `forTemplate()` rendering.

### `getSVGFormatInner()`

Fetches the raw SVG file contents and converts it into a URL-encoded Data URI string (prefixed with `data:image/svg+xml;utf8,`). Returns the string `"error"` if no SVG data is found.

### `getImageAsSVG()`

Reads the physical SVG file from the server's public path and returns it safely wrapped in a `DBHTMLText` object so it can be injected directly into HTML templates. Returns `null` if the file is not an SVG.

---

## CMS Administration & File Management

### `PerfectCMSImageFixFolder($name, ?string $folderName = '')`

Ensures the image file is organized into the correct folder in the CMS file system. It looks up or creates the target directory, moves the image if necessary, and handles republishing the image if it was already published. Returns the `Folder` object.

### `getCMSThumbnail()`

Overrides the default CMS thumbnail generation. If the image is an SVG, it renders the SVG directly so it displays properly in the backend; otherwise, it falls back to the standard thumbnail generator.

### `updatePreviewLink(&$link, $action)`

An extension hook that modifies the preview link in the CMS. If the asset is an SVG, it forces the preview link to point directly to the SVG file rather than a modified version.
