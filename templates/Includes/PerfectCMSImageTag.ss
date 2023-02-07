<picture>
    <% if $MobileNonRetinaLink %>
        <source srcset="$MobileNonRetinaLinkWebP 1x, $MobileRetinaLinkWebP 2x" media="(max-width: $MobileMediaWidth)" type="image/webp">
        <% if $HasWebP %><source srcset="$MobileNonRetinaLinkWebP 1x, $MobileRetinaLink 2x" media="(max-width: $MobileMediaWidth)" type="$Type"><% end_if %>
    <% end_if %>
    <source srcset="$NonRetinaLink 1x, $RetinaLink 2x" type="$Type">
    <% if $HasWebP %><source srcset="$NonRetinaLinkWebP 1x, $RetinaLinkWebP 2x" type="image/webp"><% end_if %>
    <img
        src="$NonRetinaLink"
        alt="$Alt"
        <% if $Width %>width="$Width"<% end_if %>
        <% if $Height %>height="$Height"<% end_if %>
        loading="$LoadingStyle"
        $Attributes
    >
</picture>
