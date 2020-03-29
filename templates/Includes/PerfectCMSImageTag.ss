<picture>
    <source srcset="$NonRetinaLinkWebP 1x, $RetinaLinkWebP 2x" type="image/webp">
    <source srcset="$NonRetinaLink 1x, $RetinaLink 2x" type="image/jpeg">
    <img
        src="$NonRetinaLink"
        alt="$Alt"
        <% if $Width %>width="$Width"<% end_if %>
        <% if $Height %>height="$Height"<% end_if %>
        $Attributes
    >
</picture>
