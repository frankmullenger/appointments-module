<h3>Note: Accessing db data from Payment class here, payment was created at: $Created</h3>

<% if ErrorMessages %>
<h3>Errors!</h3>
<ul>
<% control ErrorMessages %>
    <li>$ErrorMessage</li>
<% end_control %>
</ul>
<% end_if %>

<% if Status=Success %>
	<h3>Thanks, we have successfully received your payment.</h3>
	<div id="objectconfirmation">
		<% control PaidObject %>
			$ConfirmationMessage
		<% end_control %>
	</div>
	<p>We also have sent a receipt to <% control PaidBy %>$Email<% end_control %></p>
<% else %>
	<h4>Sorry, the payment is failed.</h4>
	<% if ExceptionError %>
		<p>We have a problem to process the payment due to: <br />
		$ExceptionError
		</p>
	<% else %>
		<p>The payment is in "$Status" status, it failed with the failing message $Message</p>
	<% end_if %>
<% end_if %>




