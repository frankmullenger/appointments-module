<div class="object">
	<h5>$Title</h5>
	<h6 class="price">$Amount.Nice ($Amount.Currency)</h6>
	
	<% if Mode=Confirmation %>
	<% else %>
		<a class="button" href="$PayableLink">Book Now</a>
	<% end_if %>
	
</div>