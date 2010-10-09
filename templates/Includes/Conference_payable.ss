
<% if ErrorMessages %>
<h3>Errors!</h3>
<ul>
<% control ErrorMessages %>
    <li>$ErrorMessage</li>
<% end_control %>
</ul>
<% end_if %>

<div id="object_payable">
	<h4>You have selected to book</h4>
	<h5>$Title</h5>
	<h6 class="price">$Amount.Nice ($Amount.Currency)</h6>

	<% control Theatre %>
		<% include Theatre %>
	<% end_control %>
</div>

<div id="calendar-wrap" style="text-align: center;">
    <div id='calendar' style="margin: auto;"></div>
</div>