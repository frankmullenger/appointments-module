<?php

class AppointmentsPage extends Page {

}

class AppointmentsPage_Controller extends Page_Controller {
    
    private $isAjax = false;
	
	function init(){
	    
        if(Director::is_ajax()) {
            $this->isAjax = true;
        }
	    
		parent::init();
		Requirements::css("appointments/css/Appointments.css");
	}

    function getConferences() {
        return DataObject::get('Conference');
    }
    
    function getBookings() {
        //TODO extract start and end dates and use Booking class to retrieve array of booked times, then convert to json
        //JSONDataFormatter
        
        //TODO Get booked times for a room based on URL string for that room

        $room = $this->Object();
        
        //Javascript passes milliseconds from 1 Jan 1970, convert to seconds for timestamp
        $startTS = $this->requestParams['start'] / 1000;
        $endTS = $this->requestParams['end'] / 1000;    
        $bookedTimes = $room->getTimes(date('Y-m-d', $startTS), date('Y-m-d', $endTS));

        if ($this->isAjax) {
            return json_encode($bookedTimes);
        }
        
//        echo '<pre>';
//        var_dump(date('Y-m-d', $startTS));
//        var_dump(date('Y-m-d', $endTS));
//        var_dump($this->requestParams);
//        echo '</pre>';
        
        return json_encode($bookedTimes);
    }
	
	function payfor() {
	    
	    Requirements::css("appointments/css/smoothness/jquery-ui-1.8.css");
	    Requirements::css("appointments/css/jquery.weekcalendar.css");
	    //Requirements::css("appointments/css/reset.css");
	    Requirements::css("appointments/css/sandbox.css");
	    
	    Requirements::javascript("appointments/js/jquery-1.4.2.min.js");
	    Requirements::javascript("appointments/js/jquery-ui-1.8.min.js");
	    Requirements::javascript("appointments/js/date.js");
	    Requirements::javascript("appointments/js/jquery.weekcalendar.js");
	    Requirements::javascript("appointments/js/sandbox.js");

	    //Get the object based on URL params like: http://localhost/silverstripe2/payments/payfor/MovieTicket/2
		$object = $this->Object();
		
		$booking = singleton('Booking');
		$room = $object->getComponent('Room');
		
		$content = $object->renderWith($object->ClassName."_payable");
		
//		$form = $this->ObjectForm();
		$javascriptForm = $this->JavascriptObjectForm();
		
		$cancel = "<div class=\"clear\"></div><a href=\"".$this->Link()."\" class=\"button\">I've changed mind, cancel.</a>";
		
		//Pass a form for the javascript popup onto the page
		$hiddenForm = '<div id="event_edit_container">'.$javascriptForm->forTemplate().'</div>';
		
		//Set which room on the page
		//TODO change so that many rooms can be supported
		$roomData = <<<EOS
<form id="roomData"><input type="hidden" name="roomID" value="$room->ID" /></form>
EOS;
		
		/*
		 * This is concatenating the content:
		 * $content
		 * $form->forTemplate
		 * $cancel
		 * Then merging that data with the customised controller object, in essence passing it all to the view as content
		 */
		$customisedController = $this->customise(array(
//			"Content" => $content.$form->forTemplate().$cancel.$hiddenForm,
		    "Content" => $content.$cancel.$hiddenForm.$roomData,
			"Form" => '',
		));
		
		return $customisedController->renderWith("Page");
	}
	
	function confirm() {
	    
	    //TODO update the booking row here because this should always be for success?
	    //Will need to update the google calendar from here

	    $payment = $this->Object();
	    
	    //Get the appointment object
	    $appointment = $payment->PaidObject();

	    //Get the booking object and update calendar with the details from booking row
	    $booking = $this->getBooking($payment->getField('ID'));
	    
	    if ($booking->connectToCalendar()) {
	        
	        if (!$booking->checkCalendarConflict()) {
	            
                //Book in a new event
                if (!$booking->addCalendarEvent()) {
                    //TODO abort payment with error and email
                    //TODO refund the payment here also perhaps?

                    //TODO set session errors through booking, retrieve and set for view, 
                    //have link on confirmation page to go back to form and prepopulate with form data from session
                    $booking->setSessionErrors('Could not add event to calendar, an error occurred. You will be refunded we hae been notified and will be in touch soon.');
                    $booking->setSessionFormData($booking->getAllFields());
                }
            }
            else {
                //TODO abort payment with error and email
                $booking->setSessionErrors('Could not add event to calendar, spot has been taken. You will be refunded we hae been notified and will be in touch soon.');
                $booking->setSessionFormData($booking->getAllFields());
            }
	    }
	    
	    //TODO update the booking, set event status to confirmed
	    
//	    $data = Session::get('AppointmentObjectFormData');
//	    echo '<pre>';
//	    var_dump($data);
//	    echo '</pre>';
//	    exit;

	    $goback = "<div class=\"clear\"></div><a href=\"".$this->Link()."\" class=\"button\">Go Back</a>";

        //Setting data from Payment class into the template with renderWith() 
        //Can access db fields of Payment object in the view such as $Status
        //@see DataObject/ViewableData->renderWith()
        //@see class Payment in payment/code/Payment.php
        $errorMessagesArray = $booking->getErrorMessages();
        
//        echo '<pre>';
//        var_dump($errorMessages);
//        echo '</pre>';
        
        
        //Check if errors exist
        if ($errorMessagesArray) {
        
            //Format error messages for view
            $errorMessages = new DataObjectSet();
            foreach ($errorMessagesArray as $errorMessage) {
                $errorMessages->push(new ArrayData(array('ErrorMessage'=>$errorMessage)));
            }
            
            //TODO need a link back to the original form
            $linkBack = $appointment->PayableLink();
            
            $payment = $payment->customise(array(
                "ErrorMessages" => $errorMessages,
                'PayableLink' => $appointment->PayableLink()
            ));
            
            //Clear the go back button
            $goback = null;
        }

		$content = $payment->renderWith($payment->ClassName."_confirmation");

		$customisedController = $this->customise(array(
			"Content" => $content.$goback,
			"Form" => ''
		));

		return $customisedController->renderWith("Page");
	}
	
	function Object() {
	    
//	    echo '<pre>';
//        var_dump($this->URLParams);
//        echo '</pre>';

		if(isset($this->URLParams['ID'])){
			if(isset($this->URLParams['OtherID'])) {
				$object = DataObject::get_by_id($this->URLParams['ID'], $this->URLParams['OtherID']);
			}else{
				$object = singleton($this->URLParams['ID']);
			}
		} else if($_REQUEST['ObjectClass']){
			if($_REQUEST['ObjectID']){
				$object = DataObject::get_by_id($_REQUEST['ObjectClass'], $_REQUEST['ObjectID']);
			}else{
				$object = singleton($_REQUEST['ObjectClass']);
			}
		}
		return $object;
	}

	function ObjectForm($times = array()){
	    
	    //TODO refactor to pass room to this function then get only times available for that room to display on the form?
	    //TODO look at placing endDate on the form to get rid of all these issues related to booking over midnight
	    
		$object = $this->Object();

		//TODO pass through the date and time dropdown defaults?
		$fields = $object->getPaymentFields();
		
		$fields->push(new HiddenField('ObjectClass', 'ObjectClass', $object->ClassName));
		$fields->push(new HiddenField('ObjectID', 'ObjectID', $object->ID));
		$required = $object->getPaymentFieldRequired();
		
		if (!empty($times)) {
		    //TODO replace the startTime and endTime fields
//		    $startTimeField = new DropdownField("StartTime", "Start Time", $times);
//            $endTimeField = new DropdownField("EndTime", "End Time", $times);

    		$timesArray = array();
            foreach ($times as $dt) {
                $timesArray[$dt->format('H:i')] = $dt->format('H:i');
            }

		    $booking = singleton('Booking');
		    $timeFields = $booking->createTimeFields($timesArray);
		    
		    $fields->replaceField('StartTime', $timeFields['startTimeField']);
		    $fields->replaceField('EndTime', $timeFields['endTimeField']);
		}
		
		$form = new Form($this,
			'ObjectForm',
			$fields,
			new FieldSet(
				new FormAction('processDPSPayment', "Yes, go and proceed to pay")
			),
			new RequiredFields($required)
		);
		return $form;
	}
	
	function JavascriptObjectForm() {

	    $object = $this->Object();
        $fields = $object->getPaymentFields();
        
        $fields->push(new HiddenField('ObjectClass', 'ObjectClass', $object->ClassName));
        $fields->push(new HiddenField('ObjectID', 'ObjectID', $object->ID));
        $required = $object->getPaymentFieldRequired();
        
        //replace the StartTime and EndTime fields to remove default select options
        $fields->replaceField('StartTime', new DropdownField("StartTime", "Start Time", array()));
        $fields->replaceField('EndTime', new DropdownField("EndTime", "End Time", array()));
        
        //Cannot include jquery ui css after jquery.weekcalendar.css, 
        //jquery ui css is included automagically if jquery ui elements included on the page
        $startDateField = new DateField("StartDate", "Start Date");
        $startDateField->setConfig('showcalendar', false);
        $startDateField->setConfig('dateformat', 'yyyy-MM-dd');
//        $startDateField->addExtraClass('date_holder');
        $fields->replaceField('StartDate', $startDateField);
        
        $form = new Form($this,
            'ObjectForm',
            $fields,
            new FieldSet(
                new FormAction('processDPSPayment')
            ),
            new RequiredFields($required)
        );
        //$form->setFormAction('/sandbox-v2.4.1/appointments/processDPSPayment');
        
        return $form;
	}
	
	function processDPSPayment($data, $form, $request) {
	    
	    //Processing the payment form
		$object = $this->Object();
		$object->processDPSPayment($data, $form);
	}
	
    function getBooking($objectID) {
        $booking = DataObject::get_one('Booking', "PaymentID = $objectID");
        return $booking;
    }
    
    function getAppointmentObject($objectID) {
        
    }
}

?>
