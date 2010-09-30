/**
 * Javascript handlers for generic model admin.
 * 
 * Most of the work being done here is intercepting clicks on form submits,
 * and managing the loading and sequencing of data between the different panels of
 * the CMS interface.
 * 
 * @todo add live query to manage application of events to DOM refreshes
 * @todo alias the $ function instead of literal jQuery
 */
(function($) {
$(document).ready(function() {
	/**
	 * Generic ajax error handler
	 */
	$('form').live('ajaxError', function (XMLHttpRequest, textStatus, errorThrown) {
			$('input', this).removeClass('loading');
			statusMessage(ss.i18n._t('ModelAdmin.ERROR', 'Error'), 'bad');
	});
	

	$('input[name=action_search]').live('click', function() {
		//$('#contentPanel').fn('closeRightPanel');
		if($('#Form_AddForm_action_doCreate')){
			$('div[class=ajaxActions]').remove();
		}
	});
	/*
	$('input[name=action_search]').live('click', function() {
		
		//submit this form via ajax
		var base = $('base').attr('href');
		console.log(base);
		console.log('here is a line in the console.');
		
		$.ajax({
		   type: "POST",
		   url: "some.php",
		   data: "name=John&location=Boston",
		   success: function(msg){
		     alert( "Data Saved: " + msg );
		   }
		 });
		
		return false;
	});
	*/
	$('#Form_SearchForm').submit(function() {
		
		var base = $('base').attr('href');
		console.log(base);
		
		$.ajax({
			type: "GET",
			url: base+"admin/payments/SearchForm",
			data: "Title=Testing",
			success: function(msg){
				//alert( "Data Saved: " + msg );
				$('#right').html(msg);
//				$('#right').css('overflow', 'hidden');
			}
		});
		
		return false;
	});
	
	////////////////////////////////////////////////////////////////// 
	// Search form 
	////////////////////////////////////////////////////////////////// 
	
	/**
	 * If a dropdown is used to choose between the classes, it is handled by this code
	 *
    $('#ModelClassSelector select')
        // Set up an onchange function to show the applicable form and hide all others
        .change(function() {
            var $selector = $(this);
            $('option', this).each(function() {
                var $form = $('#'+$(this).val());
                if($selector.val() == $(this).val()) $form.show();
                else $form.hide();
            });
        })
        // Initialise the form by calling this onchange event straight away
        .change();

	/**
	 * Stores a jQuery reference to the last submitted search form.
	 *
	__lastSearch = null;

	/**
	 * Submits a search filter query and attaches event handlers
	 * to the response table, excluding the import form because 
	 * file ($_FILES) submission doesn't work using AJAX 
	 * 
	 * Note: This is used for Form_CreateForm too
	 * 
	 * @todo use jQuery.live to manage ResultTable click handlers
	 *
	$('#SearchForm_holder .tab form:not([id^=Form_ImportForm])').submit(function () {
	    var $form = $(this);
		//$('#contentPanel').fn('closeRightPanel');
		// @todo TinyMCE coupling
		tinymce_removeAll();
		
		$('#ModelAdminPanel').fn('startHistory', $(this).attr('action'), $(this).formToArray());
	    $('#ModelAdminPanel').load($(this).attr('action'), $(this).formToArray(), standardStatusHandler(function(result) {
			if(!this.future || !this.future.length) {
			    $('#Form_EditForm_action_goForward, #Form_ResultsForm_action_goForward').hide();
		    }
			if(!this.history || this.history.length <= 1) {
			    $('#Form_EditForm_action_goBack, #Form_ResultsForm_action_goBack').hide();
		    }

    		$('#form_actions_right').remove();
    		Behaviour.apply();

			if(window.onresize) window.onresize();
    		// Remove the loading indicators from the buttons
    		$('input[type=submit]', $form).removeClass('loading');
	    }, 
	    // Failure handler - we should still remove loading indicator
	
	    function () {
    		$('input[type=submit]', $form).removeClass('loading');
	    }));
	    return false;
	});

	/**
	 * Clear search button
	 */
	$('#SearchForm_holder button[name=action_clearsearch]').click(function(e) {
		$(this.form).resetForm();
		return false;
	});

	/**
	 * Column selection in search form
	  */
	$('a.form_frontend_function.toggle_result_assembly').click(function(){
		var toggleElement = $(this).next();
		toggleElement.toggle();
		return false;
	});
	
	$('a.form_frontend_function.tick_all_result_assembly').click(function(){
		var resultAssembly = $(this).prevAll('div#ResultAssembly').find('ul li input');
		resultAssembly.attr('checked', 'checked');
		return false;
	});
	
	$('a.form_frontend_function.untick_all_result_assembly').click(function(){
		var resultAssembly = $(this).prevAll('div#ResultAssembly').find('ul li input');
		resultAssembly.removeAttr('checked');
		return false;
	});
	
	
	//////////////////////////////////////////////////////////////////
	// Helper functions
	////////////////////////////////////////////////////////////////// 
	/*
	$('#contentPanel').fn({
		/**
		* Close TinyMCE image, link or flash panel.
		* this function is called everytime a new search, back or add new DataObject are clicked
		**
		closeRightPanel: function(){
			if($('#contentPanel').is(':visible')) {
				$('#contentPanel').hide();
				$('#Form_EditorToolbarImageForm').hide();
				$('#Form_EditorToolbarFlashForm').hide();
				$('#Form_EditorToolbarLinkForm').hide();
			}
		}
		
	})
	
    $('#ModelAdminPanel').fn({
        /**
         * Load a detail editing form into the main edit panel
         * @todo Convert everything to jQuery so that the built-in load method can be used with this instead
         *
        loadForm: function(url, successCallback) {
			// @todo TinyMCE coupling
			tinymce_removeAll();
			$('#contentPanel').fn('closeRightPanel');
    	    $('#right #ModelAdminPanel').load(url, standardStatusHandler(function(result) {
				if(typeof(successCallback) == 'function') successCallback.apply();
				if(!this.future || !this.future.length) {
				    $('#Form_EditForm_action_goForward, #Form_ResultsForm_action_goForward').hide();
			    }
				if(!this.history || this.history.length <= 1) {
				    $('#Form_EditForm_action_goBack, #Form_ResultsForm_action_goBack').hide();
				
					// we don't need save and delete button on result form
					$('#Form_EditForm_action_doSave').hide();
					$('#Form_EditForm_action_doDelete').hide();
			    }
				
    			Behaviour.apply(); // refreshes ComplexTableField
				if(window.onresize) window.onresize();
    		}));
    	},
		
    	
		
    	startHistory: function(url, data) {
    	    this.history = [];
    	    $(this).fn('addHistory', url, data);
    	},
    	
    	/**
    	 * Add an item to the history, to be accessed by goBack and goForward
    	 *
    	addHistory: function(url, data) {
    	    // Combine data into URL
    	    if(data) {
    	        if(url.indexOf('?') == -1) url += '?' + $.param(data);
    	        else url += '&' + $.param(data);
	        }
	        
	        // Add to history 
    	    if(this.history == null) this.history = [];
    	    this.history.push(url);
    	    
    	    // Reset future
    	    this.future = [];
    	},
    	
    	goBack: function() {
    	    if(this.history && this.history.length) {
        	    if(this.future == null) this.future = [];
        	    
        	    var currentPage = this.history.pop();
        	    var previousPage = this.history[this.history.length-1];
        	    
        	    this.future.push(currentPage);
        	    $(this).fn('loadForm', previousPage);
    	    }
    	},
    	
    	goForward: function() {
    	    if(this.future && this.future.length) {
        	    if(this.future == null) this.future = [];
        	    
        	    var nextPage = this.future.pop();
        	    
        	    this.history.push(nextPage);
        	    $(this).fn('loadForm', nextPage);
    	    }
    	}

    });
	*/
	
	/**
	 * Standard SilverStripe status handler for ajax responses
	 * It will generate a status message out of the response, and only call the callback for successful responses
	 *
	 * To use:
	 *    Instead of passing your callback function as:
	 *       function(response) { ... }
	 * 
	 *    Pass it as this:
	 *       standardStatusHandler(function(response) { ... })
	 */
	function standardStatusHandler(callback, failureCallback) {
	    return function(response, status, xhr) {
	        // If the response is takne from $.ajax's complete handler, then swap the variables around
	        if(response.status) {
	            xhr = response;
	            response = xhr.responseText;
	        }

	        if(status == 'success') {
	            statusMessage(xhr.statusText, "good");
	            $(this).each(callback, [response, status, xhr]);
			} else {
	            errorMessage(xhr.statusText);
	            if(failureCallback) $(this).each(failureCallback, [response, status, xhr]);
			}
	    }
	}
	
})
})(jQuery);

/**
 * @todo Terrible HACK, but thats the cms UI...
 */
function fixHeight_left() {
	//fitToParent('LeftPane');
	fitToParent('Search_holder',12);
	fitToParent('ResultTable_holder',12);
}

function prepareAjaxActions(actions, formName, tabName) {
	// @todo HACK Overwrites LeftAndMain.js version of this method to avoid double form actions
	// (by new jQuery and legacy prototype) 
	return false;
}