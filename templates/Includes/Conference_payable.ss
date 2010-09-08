<h2>Testing here:</h2>
<p>Foo is here: </p>

<ul>
<% control ErrorMessages %>
    <li>$ErrorMessage</li>
<% end_control %>
</ul>

<div id="object_payable">
	<h4>You have selected to book</h4>
	<h5>$Title</h5>
	<h6 class="price">$Amount.Nice ($Amount.Currency)</h6>

	<% control Theatre %>
		<% include Theatre %>
	<% end_control %>
</div>