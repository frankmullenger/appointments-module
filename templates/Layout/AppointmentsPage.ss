<div class="typography">
	<% if Menu(2) %>
		<% include SideBar %>
		<div id="Content">
	<% end_if %>

	<% if Level(2) %>
	  	<% include BreadCrumbs %>
	<% end_if %>
	
		<h2>$Title</h2>
		$Content

		<div class="clear"></div>
        
        <div class="section">
            <h4>Conference Bookings</h4>
            <% include Conferences %>
        </div>
		
	<% if Menu(2) %>
		</div>
	<% end_if %>
</div>