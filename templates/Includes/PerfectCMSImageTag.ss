<picture>
    <% if $MobileNonRetinaLink %>
        <source srcset="$MobileNonRetinaLink 1x, $MobileRetinaLink 2x" media="(max-width: $MobileMediaWidth)" type="image/">
        <% if $Has %><source srcset="$MobileNonRetinaLink 1x, $MobileRetinaLink 2x" media="(max-width: $MobileMediaWidth)" type="$Type"><% end_if %>
    <% end_if %>
    <source srcset="$NonRetinaLink 1x, $RetinaLink 2x" type="$Type">
    <% if $Has %><source srcset="$NonRetinaLink 1x, $RetinaLink 2x" type="image/"><% end_if %>
    <img
        loading="lazy"
        src="$NonRetinaLink"
        alt="$Alt"
        <% if $Width %>width="$Width"<% end_if %>
        <% if $Height %>height="$Height"<% end_if %>
        loading="$LoadingStyle"
        $Attributes
    >
</picture>
