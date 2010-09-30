if(typeof SiteTreeHandlers == 'undefined') SiteTreeHandlers = {};
SiteTreeHandlers.loadPage_url = 'admin/payments/getitem';
SiteTreeHandlers.controller_url = 'admin/payments';
 
_HANDLER_FORMS['addlink'] = 'addlink_options';
_HANDLER_FORMS['deletelink'] = 'deletelink_options';

//A form we want to submit via AJAX
_HANDLER_FORMS['searchpayments'] = 'Form_SearchForm';

/**
 * Search form action
 */
searchpayments = {
	button_onclick : function() {
		addlink.showNewForm();
		return false;
	},
 
	showNewForm : function() {
		Ajax.SubmitForm('addlink_options', null, {
			onSuccess : function(response) {
				Ajax.Evaluator(response);
			},
			onFailure : function(response) {
				errorMessage('Error adding link', response);
			}
		});
	}
}

/**
 * New link action
 */
addlink = {
	button_onclick : function() {
		addlink.showNewForm();
		return false;
	},
 
	showNewForm : function() {
		Ajax.SubmitForm('addlink_options', null, {
			onSuccess : function(response) {
				Ajax.Evaluator(response);
			},
			onFailure : function(response) {
				errorMessage('Error adding link', response);
			}
		});
	}
}

/**
 * Delete link action
 */
deletelink = {
	button_onclick : function() {
		if(treeactions.toggleSelection(this)) {
			$('deletelink_options').style.display = 'block';
 
			deletelink.o1 = $('sitetree').observeMethod('SelectionChanged', deletelink.treeSelectionChanged);
			deletelink.o2 = $('deletelink_options').observeMethod('Close', deletelink.popupClosed);
			addClass($('sitetree'),'multiselect');
 
			deletelink.selectedNodes = { };
 
			var sel = $('sitetree').firstSelected();
			if(sel && sel.className.indexOf('nodelete') == -1) {
				var selIdx = $('sitetree').getIdxOf(sel);
				deletelink.selectedNodes[selIdx] = true;
				sel.removeNodeClass('current');
				sel.addNodeClass('selected');
			}
		} else {
			$('deletelink_options').style.display = 'none';
		}
		return false;
	},
 
	treeSelectionChanged : function(selectedNode) {
		var idx = $('sitetree').getIdxOf(selectedNode);
 
		if(selectedNode.className.indexOf('nodelete') == -1) {
			if(selectedNode.selected) {
				selectedNode.removeNodeClass('selected');
				selectedNode.selected = false;
				deletelink.selectedNodes[idx] = false;
			} else {
				selectedNode.addNodeClass('selected');
				selectedNode.selected = true;
				deletelink.selectedNodes[idx] = true;
			}
		}
		return false;
	},
 
	popupClosed : function() {
		removeClass($('sitetree'),'multiselect');
		$('sitetree').stopObserving(deletelink.o1);
		$('deletelink_options').stopObserving(deletelink.o2);
 
		for(var idx in deletelink.selectedNodes) {
			if(deletelink.selectedNodes[idx]) {
				node = $('sitetree').getTreeNodeByIdx(idx);
				if(node) {
					node.removeNodeClass('selected');
					node.selected = false;
				}
			}
		}
	},
 
	form_submit : function() {
		var csvIDs = "";
		for(var idx in deletelink.selectedNodes) {
			if(deletelink.selectedNodes[idx]) csvIDs += (csvIDs ? "," : "") + idx;
		}
		if(csvIDs) {
			if(confirm("Do you really want to delete these links?")) {
				$('deletelink_options').elements.csvIDs.value = csvIDs;
 
				Ajax.SubmitForm('deletelink_options', null, {
					onSuccess : function(response) {
						Ajax.Evaluator(response);
						var sel;
						if((sel = $('sitetree').firstSelected()) && sel.parentNode) sel.addNodeClass('current');
						else $('Form_EditForm').innerHTML = "";
						treeactions.closeSelection($('deletelink'));
					},
					onFailure : function(response) {
						errorMessage('Error deleting links', response);
					}
				});
 
				$('deletelink').getElementsByTagName('button')[0].onclick();
			}
		} else {
			alert("Please select at least one link.");
		}
		return false;
	}
}

/** 
 * Initialisation function to set everything up
 */
Behaviour.addLoader(function () {
	// Set up add link
	Observable.applyTo($('addlink_options'));
	$('addlink').onclick = addlink.button_onclick;
	$('addlink').getElementsByTagName('button')[0].onclick = function() {return false;};
 
	// Set up delete link
	Observable.applyTo($('deletelink_options'));
	$('deletelink').onclick = deletelink.button_onclick;
	$('deletelink').getElementsByTagName('button')[0].onclick = function() {return false;};
	$('deletelink_options').onsubmit = deletelink.form_submit;
});

