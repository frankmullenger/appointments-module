<h2>Payments</h2>
 


<div id="treepanes">

<p>
This is going to be where we have a search form to update the main
</p>

<ul id="TreeActions">
    <li class="action" id="addlink"><button><% _t('CREATENL','Create New Link xXx') %></button></li>
    <li class="action" id="deletelink"><button><% _t('DEL','Delete Link xXx') %></button></li>
</ul>
<div style="clear:both;"></div>
<form class="actionparams" id="addlink_options" style="display: none" action="admin/randomlinks/addlink">
    <input type="hidden" name="ID" value="new" />
    <input type="submit" value="<% _t('ADDLINK','Add a link xXx') %>" />
</form>
<form class="actionparams" id="deletelink_options" style="display: none" action="admin/randomlinks/deleteitems">
    <p><% _t('SELECTLINKS','Select the links that you want to delete and then click the button below xXx') %></p>
    <input type="hidden" name="csvIDs" />
    <input type="submit" value="<% _t('DELLINKS','Delete the selected links xXx') %>" />
</form>

$EditForm

<div id="sitetree_holder" style="overflow:auto">
    <% if Items %>
        <ul id="sitetree" class="tree unformatted">
        <li id="$ID" class="root Root"><a>Items</a>
            <ul>
            <% control Items %>
                <li id="record-$class">
                <a href="admin/my/show/$ID">$Title</a>
                </li>
            <% end_control %>
            </ul>
        </li>
        </ul>
    <% end_if %>
</div>
</div>

<!-- 
<div id="treepanes" style="overflow-y: auto;">
    <ul id="TreeActions">
        <li class="action" id="addlink"><button><% _t('CREATENL','Create New Link xXx') %></button></li>
        <li class="action" id="deletelink"><button><% _t('DEL','Delete Link xXx') %></button></li>
    </ul>
    <div style="clear:both;"></div>
    <form class="actionparams" id="addlink_options" style="display: none" action="admin/randomlinks/addlink">
        <input type="hidden" name="ID" value="new" />
        <input type="submit" value="<% _t('ADDLINK','Add a link xXx') %>" />
    </form>
    <form class="actionparams" id="deletelink_options" style="display: none" action="admin/randomlinks/deleteitems">
        <p><% _t('SELECTLINKS','Select the links that you want to delete and then click the button below xXx') %></p>
        <input type="hidden" name="csvIDs" />
        <input type="submit" value="<% _t('DELLINKS','Delete the selected links xXx') %>" />
    </form>
    $SiteTreeAsUL
</div>
-->