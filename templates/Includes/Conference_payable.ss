
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

<div style="clear:both;"></div>
<div id='calendar'></div>

<!--  
<div id="event_edit_container">
    <form>
        <input type="hidden" />
        <ul>
            <li>
                <span>Date: </span><span class="date_holder"></span> 
            </li>
            <li>
                <label for="StartTime">Start Time: </label><select name="StartTime"><option value="">Select Start Time</option></select>
            </li>
            <li>
                <label for="EndTime">End Time: </label><select name="EndTime"><option value="">Select End Time</option></select>
            </li>
            <li>
                <label for="title">Title: </label><input type="text" name="title" />
            </li>
            <li>
                <label for="body">Body: </label><textarea name="body"></textarea>
            </li>
        </ul>
    </form>
</div>
-->