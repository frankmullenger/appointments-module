
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
</div>

<p>Please select a time below by clicking on a time and dragging with your mouse. Make sure your time does not clash with an existing booking</p>

<div id="calendar-wrap" style="text-align: center;">
    <div id='calendar' style="margin: auto;"></div>
</div>

<div id="calendar-loading">Please wait while the calendar loads existing events.</div>