<picture>
    <% if $IsSVG %>
        <source srcset="$Link" type="image/svg+xml">
    <% else %>
        <% if $MobileNonRetinaLink %>
            <source srcset="$MobileNonRetinaLink 1x, $MobileRetinaLink 2x" media="(max-width: $MobileMediaWidth)" type="$Type">
        <% end_if %>
        <source srcset="$NonRetinaLink 1x, $RetinaLink 2x" type="$Type">
        <img
            src="$NonRetinaLink"
            alt="$Alt"
            <% if $Width %>width="$Width"<% end_if %>
            <% if $Height %>height="$Height"<% end_if %>
            loading="$LoadingStyle"
            $Attributes
        >
    <% end_if %>
</picture>
