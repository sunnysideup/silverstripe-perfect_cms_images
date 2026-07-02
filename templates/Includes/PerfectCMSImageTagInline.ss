<% if $IsSVG %>
    src="$Link"
    <% if $Width %>width="$Width"<% end_if %>
    <% if $Height %>height="$Height"<% end_if %>
    loading="$LoadingStyle"
<% else %>
src="$NonRetinaLink"
srcset="$NonRetinaLink 1x, $RetinaLink 2x"
<% if $Width %>width="$Width"<% end_if %>
<% if $Height %>height="$Height"<% end_if %>
loading="$LoadingStyle"
<% end_if %>
