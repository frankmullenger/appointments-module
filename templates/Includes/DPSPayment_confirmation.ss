<% if Status=Success %>

	<% if ErrorMessages %>
		<h3>Errors!</h3>
		<ul>
		<% control ErrorMessages %>
		    <li>$ErrorMessage</li>
		<% end_control %>
		</ul>
		<a class="button" href="$PayableLink">Go back and try again</a><br />
	<% else %>
	    <h3>Thanks, we have successfully received your payment.</h3>
	    <div id="objectconfirmation">
	        <% control PaidObject %>
	            $ConfirmationMessage
	        <% end_control %>
	        Booked in for $BookingStartDate<br /> 
	        Starting at $BookingStartTime<br />
	        Ending at $BookingEndTime
	    </div>
	    <p>We have sent a receipt for both the payment and booking to <% control PaidBy %>$Email<% end_control %> which includes the above information.</p>
	<% end_if %>  

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




