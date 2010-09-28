var year = new Date().getFullYear();
var month = new Date().getMonth();
var day = new Date().getDate();

$.noConflict();
jQuery(document).ready(function($) {
	
	var $calendar = $('#calendar');
    var id = 10;

    $('#calendar').weekCalendar({
        timeslotsPerHour: 4,
		allowCalEventOverlap : false,
		overlapEventsSeparate: false,
		firstDayOfWeek : 1,
		businessHours :{start: 7, end: 1, limitDisplay: false },
		daysToShow : 7,
		//switchDisplay: {'1 day': 1, '3 next days': 3, 'work week': 5, 'full week': 7},
		dateFormat : "Y-m-d",
		
        height: function($calendar){
            return 518;
        },
		
        eventRender : function(calEvent, $event) {
            if(calEvent.end.getTime() < new Date().getTime()) {
                $event.css("backgroundColor", "#aaa");
                $event.find(".time").css({"backgroundColor": "#999", "border":"1px solid #888"});
				$event.find(".wc-time").css({
	               "backgroundColor" : "#999",
	               "border" : "1px solid #888"
	            });
            }
        },

		eventNew : function(calEvent, $event) {
			
			var $dialogContent = $("#event_edit_container");
			
			//Reset date and time inputs so that they can be prepopulated with user selection
			resetForm($dialogContent);

			var startField = $dialogContent.find("select[name='StartTime']").val(calEvent.start);
			var endField = $dialogContent.find("select[name='EndTime']").val(calEvent.end);
			
			
			$dialogContent.dialog({
				modal: true,
				title: "New Calendar Event",
				width: 500,
				close: function() {
				   $dialogContent.dialog("destroy");
				   $dialogContent.hide();
				   $('#calendar').weekCalendar("removeUnsavedEvents");
				},
				buttons: {
				    "yes, go and proceed to pay" : function() {
					
						//TODO submit the form here
						
						calEvent.id = id;
						id++;
						calEvent.start = new Date(startField.val());
						calEvent.end = new Date(endField.val());
						  
						calEvent.title = 'Some Title here';
						calEvent.body = 'Some Body here';
						
						$calendar.weekCalendar("removeUnsavedEvents");
						$calendar.weekCalendar("updateEvent", calEvent);
						$dialogContent.dialog("close");
						
						$dialogContent.dialog("close");
						
						//TODO display an ajax loading icon
						
						$('#Form_ObjectForm').submit();
					},
				   /*
				   save : function() {
				      calEvent.id = id;
				      id++;
				      calEvent.start = new Date(startField.val());
				      calEvent.end = new Date(endField.val());
				      
				      calEvent.title = 'Some Title here';
				      calEvent.body = 'Some Body here';
				
				      $calendar.weekCalendar("removeUnsavedEvents");
				      $calendar.weekCalendar("updateEvent", calEvent);
				      $dialogContent.dialog("close");
				   },
				   */
				   cancel : function() {
				      $dialogContent.dialog("close");
				   }
				}
			}).show();
			
			//Update the date in the popup
//			$dialogContent.find(".date_holder").text($calendar.weekCalendar("formatDate", calEvent.start));
//			$dialogContent.find("input[name='StartDate']").val($calendar.weekCalendar("formatDate", calEvent.start)).attr('disabled', 'disabled');
			
			$dialogContent.find("input[name='StartDate']").val($calendar.weekCalendar("formatDate", calEvent.start));
			$dialogContent.find("#Form_ObjectForm div.Actions").css('display', 'none');
			
			console.log($dialogContent.find("#Form_ObjectForm div.Actions"));
			
			setupStartAndEndTimeFields(startField, endField, calEvent, $calendar.weekCalendar("getTimeslotTimes", calEvent.start));

        },
        eventDrop : function(calEvent, $event) {
			//Disable moving items
        	return false;
        },
        eventResize : function(calEvent, $event) {
        	//Disable resizing items
        	return false;
        },
		eventClick : function(calEvent, $event) {
        	//Disable showing events
        	return false;
		},
		draggable : function(calEvent, $event) {
			//Disable draggable
			return false;
        },
        resizable : function(calEvent, $event) {
        	//Disable resizeable
        	return false;
        },
        eventMouseover : function(calEvent, $event) {
        	return;
        },
        eventMouseout : function(calEvent, $event) {
        	return;
        },
        noEvents : function() {
        	return;
        },
        data: function(start, end, callback) {
        	//Retrieve JSON encoded data via AJAX
        	var roomID = $('input[name=roomID]').val();
        	
        	//TODO get base URL correctly
			$.getJSON("http://localhost/silverstripe-v2.4.1/appointments/getBookings/Room/"+roomID+".json", {
//			$.getJSON("http://localhost/sandbox-v2.4.1/appointments/getBookings/Room/"+roomID+".json", {
				start: start.getTime(),
				end: end.getTime()
			},  
			function(result) {
				callback(result);
			});
    	}
    });
	
    /*
     * Reset popup form data, only clear the date and time fields, other fields need to 
     * stay populated if errors are returned etc.
     */
	function resetForm($dialogContent) {
//		$dialogContent.find("input[type!='hidden']").val("");
//		$dialogContent.find("textarea").val("");
		
		$dialogContent.find("#Form_ObjectForm_StartDate").val("");
		$dialogContent.find("#Form_ObjectForm_EndDate").val("");
		$dialogContent.find('#Form_ObjectForm_StartTime').val("");
		$dialogContent.find('#Form_ObjectForm_EndTime').val("");
	}
	
	/*
    * Sets up the start and end time fields in the calendar event
    * form for editing based on the calendar event being edited
    */
    function setupStartAndEndTimeFields($startTimeField, $endTimeField, calEvent, timeslotTimes) {

      $startTimeField.empty();
      $endTimeField.empty();

      for (var i = 0; i < timeslotTimes.length; i++) {
         var startTime = timeslotTimes[i].start;
         var endTime = timeslotTimes[i].end;
         var startSelected = "";
         if (startTime.getTime() === calEvent.start.getTime()) {
            startSelected = "selected=\"selected\"";
         }
         var endSelected = "";
         if (endTime.getTime() === calEvent.end.getTime()) {
            endSelected = "selected=\"selected\"";
         }
         $startTimeField.append("<option value=\"" + startTime + "\" " + startSelected + ">" + timeslotTimes[i].startFormatted + "</option>");
         $endTimeField.append("<option value=\"" + endTime + "\" " + endSelected + ">" + timeslotTimes[i].endFormatted + "</option>");

         $timestampsOfOptions.start[timeslotTimes[i].startFormatted] = startTime.getTime();
         $timestampsOfOptions.end[timeslotTimes[i].endFormatted] = endTime.getTime();

      }
      $endTimeOptions = $endTimeField.find("option");
      $startTimeField.trigger("change");
   }

   var $endTimeField = $("select[name='EndTime']");
   var $endTimeOptions = $endTimeField.find("option");
   var $timestampsOfOptions = {start:[],end:[]};

   //reduces the end time options to be only after the start time options.
   $("select[name='StartTime']").change(function() {
      var startTime = $timestampsOfOptions.start[$(this).find(":selected").text()];
      var currentEndTime = $endTimeField.find("option:selected").val();
      $endTimeField.html(
            $endTimeOptions.filter(function() {
               return startTime < $timestampsOfOptions.end[$(this).text()];
            })
            );

      var endTimeSelected = false;
      $endTimeField.find("option").each(function() {
         if ($(this).val() === currentEndTime) {
            $(this).attr("selected", "selected");
            endTimeSelected = true;
            return false;
         }
      });

      if (!endTimeSelected) {
         //automatically select an end date 2 slots away.
         $endTimeField.find("option:eq(1)").attr("selected", "selected");
      }

   });
    
});