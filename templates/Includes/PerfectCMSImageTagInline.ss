<% if $IsSVG %>
src="$Link"
<% else %>
src="$NonRetinaLink"
srcset="$NonRetinaLink 1x, $RetinaLink 2x"
<% end_if %>
<% if $Width %>width="$Width"<% end_if %>
<% if $Height %>height="$Height"<% end_if %>
loading="$LoadingStyle"
