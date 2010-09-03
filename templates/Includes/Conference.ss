<div class="object">
	<h5>$Title</h5>
	<h6 class="price">$Amount.Nice ($Amount.Currency)</h6>

	<% control Room %>
		<% include Room %>
	<% end_control %>
	
	<% if Mode=Confirmation %>
	<% else %>
		<% control Room %>
			<div class="description">$Description.Summary(35)</div>
		<% end_control %>
		<br />
		<a class="button" href="$PayableLink">Buy Now</a>
	<% end_if %>
	
</div>