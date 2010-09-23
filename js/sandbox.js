var year = new Date().getFullYear();
var month = new Date().getMonth();
var day = new Date().getDate();

var eventData = {
    events : [
       {
	   	   "id":1, 
		   "start": new Date(year, month, day, 12), 
		   "end": new Date(year, month, day, 13, 35),
		   "title":"Lunch with Mike",
           readOnly : true
	   },
       {
	   	   "id":2, 
		   "start": new Date(year, month, day, 14), 
		   "end": new Date(year, month, day, 14, 45),
		   "title":"Dev Meeting",
           readOnly : true
	   },
       {
	   	   "id":3, 
		   "start": new Date(year, month, day + 1, 18), 
		   "end": new Date(year, month, day + 1, 18, 45),
		   "title":"Hair cut",
		   readOnly : true
	   },
       {
	   	   "id":4, 
		   "start": new Date(year, month, day - 1, 8), 
		   "end": new Date(year, month, day - 1, 9, 30),
		   "title":"Team breakfast",
           readOnly : true
	   },
       {
	   	   "id":5, 
		   "start": new Date(year, month, day + 1, 14), 
		   "end": new Date(year, month, day + 1, 15),
		   "title":"Product showcase",
           readOnly : true
	   },
	   {
	   	   "id":3, 
		   "start": new Date(year, month, day + 1, 23), 
		   "end": new Date(year, month, day + 2, 01, 45),
		   "title":"Overnight",
		   readOnly : false
	   },
    ]
};

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
			
			//TODO do we need to reset the form? I don't think so
			resetForm($dialogContent);
			
			//TODO get form fields to update correctly
			var startField = $dialogContent.find("select[name='StartTime']").val(calEvent.start);
			var endField = $dialogContent.find("select[name='EndTime']").val(calEvent.end);
//			var titleField = $dialogContent.find("input[name='title']");
//			var bodyField = $dialogContent.find("textarea[name='body']");
			
			
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
			   save : function() {
			      calEvent.id = id;
			      id++;
			      calEvent.start = new Date(startField.val());
			      calEvent.end = new Date(endField.val());
//			      calEvent.title = titleField.val();
//			      calEvent.body = bodyField.val();
			      
			      calEvent.title = 'Some Title here';
			      calEvent.body = 'Some Body here';
			
			      $calendar.weekCalendar("removeUnsavedEvents");
			      $calendar.weekCalendar("updateEvent", calEvent);
			      $dialogContent.dialog("close");
			   },
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
			
			//TODO if it has moved to the past do not allow move
			
            displayMessage("<strong>Moved Event</strong><br/>Start: " + calEvent.start + "<br/>End: " + calEvent.end);
        },
        eventResize : function(calEvent, $event) {
        	
        	//TODO do not allow a resize into the past, in fact, need at least a days grace I think
        	
            displayMessage("<strong>Resized Event</strong><br/>Start: " + calEvent.start + "<br/>End: " + calEvent.end);
        },
        
		/*
		 * If readOnly do not show event
		 */
		eventClick : function(calEvent, $event) {

			if (calEvent.readOnly) {
			    return;
			}
			
			var $dialogContent = $("#event_edit_container");
			
			resetForm($dialogContent);
			
			
			var startField = $dialogContent.find("select[name='StartTime']").val(calEvent.start);
			var endField = $dialogContent.find("select[name='EndTime']").val(calEvent.end);
			
			var titleField = $dialogContent.find("input[name='title']").val(calEvent.title);
			var bodyField = $dialogContent.find("textarea[name='body']");
			bodyField.val(calEvent.body);
			
			$dialogContent.dialog({
			modal: true,
			title: "Edit - " + calEvent.title,
			close: function() {
			   $dialogContent.dialog("destroy");
			   $dialogContent.hide();
			   $('#calendar').weekCalendar("removeUnsavedEvents");
			},
			buttons: {
			   save : function() {
			
			      calEvent.start = new Date(startField.val());
			      calEvent.end = new Date(endField.val());
			      calEvent.title = titleField.val();
			      calEvent.body = bodyField.val();
			
			      $calendar.weekCalendar("updateEvent", calEvent);
			      $dialogContent.dialog("close");
			   },
			   "delete" : function() {
			      $calendar.weekCalendar("removeEvent", calEvent.id);
			      $dialogContent.dialog("close");
			   },
			   cancel : function() {
			      $dialogContent.dialog("close");
			   }
			}
			}).show();
			
			var startField = $dialogContent.find("select[name='start']").val(calEvent.start);
			var endField = $dialogContent.find("select[name='end']").val(calEvent.end);
			$dialogContent.find(".date_holder").text($calendar.weekCalendar("formatDate", calEvent.start));
			setupStartAndEndTimeFields(startField, endField, calEvent, $calendar.weekCalendar("getTimeslotTimes", calEvent.start));
			$(window).resize().resize(); //fixes a bug in modal overlay size ??
			
		},
		
		draggable : function(calEvent, $event) {
           return calEvent.readOnly != true;
        },
        resizable : function(calEvent, $event) {
           return calEvent.readOnly != true;
        },
		
        eventMouseover : function(calEvent, $event) {
            displayMessage("<strong>Mouseover Event</strong><br/>Start: " + calEvent.start + "<br/>End: " + calEvent.end);
        },
        eventMouseout : function(calEvent, $event) {
            displayMessage("<strong>Mouseout Event</strong><br/>Start: " + calEvent.start + "<br/>End: " + calEvent.end);
        },
        noEvents : function() {
            displayMessage("There are no events for this week");
        },
        data:eventData
    });

    function displayMessage(message) {
        $("#message").html(message).fadeIn();
    }

    $("<div id=\"message\" class=\"ui-corner-all\"></div>").prependTo($("body"));
	
	function resetForm($dialogContent) {
		$dialogContent.find("input[type!='hidden']").val("");
//		$dialogContent.find("input").val("");
		$dialogContent.find("textarea").val("");
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