You can use something like this to show images as SVGs:

```html
<% if $MyImage.IsSVG %>
$MyImage.SVGFormat
<% else %>
$MyImage.ScaleHeight(100)
<% end_if %>

```
