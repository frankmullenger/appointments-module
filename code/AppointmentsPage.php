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
        
        //Get booked times for a room based on URL string for that room
        $room = $this->Object();
        
        //Javascript passes milliseconds from 1 Jan 1970, convert to seconds for timestamp
        $startTS = $this->requestParams['start'] / 1000;
        $endTS = $this->requestParams['end'] / 1000;    
        $bookedTimes = $room->getTimes(date('Y-m-d', $startTS), date('Y-m-d', $endTS));
        
        //Also get the booked times from google calendar
        $booking = singleton('Booking');
        $booking->connectToCalendar();
        $service = $booking->service;
        $calendarBookedTimes = $room->getCalendarTimes($service, date('Y-m-d', $startTS), date('Y-m-d', $endTS), false, true);

        //TODO merge the two arrays without dupes
        $bookedTimes = array_merge($bookedTimes, $calendarBookedTimes);

        if ($this->isAjax) {
            return json_encode($bookedTimes);
        }
        else {
//        echo '<pre>';
//        var_dump($calendarBookedTimes);
//        var_dump($bookedTimes);
//        echo '</pre>';            
            
//        echo '<pre>';
//        var_dump(date('Y-m-d', $startTS));
//        var_dump(date('Y-m-d', $endTS));
//        var_dump($this->requestParams);
//        echo '</pre>';
        }
        
        return json_encode($bookedTimes);
    }
	
	function payfor() {
	    
	    Requirements::css("appointments/css/smoothness/jquery-ui-1.8.css");
	    Requirements::css("appointments/css/jquery.weekcalendar.css");
	    Requirements::css("appointments/css/calendar.css");
	    
	    Requirements::javascript("appointments/js/jquery-1.4.2.min.js");
	    Requirements::javascript("appointments/js/jquery-ui-1.8.min.js");
	    Requirements::javascript("appointments/js/date.js");
	    Requirements::javascript("appointments/js/jquery.weekcalendar.js");
	    Requirements::javascript("appointments/js/calendar.js");

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
		
		return $customisedController->renderWith(array("AppointmentsPage_payfor", "AppointmentsPage", "Page"));
	}
	
	function confirm() {

	    //Get the payment and appointment objects
	    $payment = $this->Object();
	    $appointment = $payment->PaidObject();

	    //Get the booking object and update calendar with the details from booking row
	    $booking = $this->getBooking($payment->getField('ID'));
	    if ($booking->connectToCalendar()) {
	        
	        if (!$booking->checkCalendarConflict()) {
                //Book in a new event
                if (!$booking->addCalendarEvent()) {
                    $booking->setSessionErrors('Could not add event to calendar, an error occurred. You will be refunded we hae been notified and will be in touch soon.');
                    $booking->setSessionFormData($booking->getAllFields());
                }
            }
            else {
                $booking->setSessionErrors('Could not add event to calendar, spot has been taken. You will be refunded we hae been notified and will be in touch soon.');
                $booking->setSessionFormData($booking->getAllFields());
            }
	    }
	    
	    //update the booking, set event status to confirmed
	    try {
    	    $updatedData = array(
                'EventStatus' => Booking::EVENT_STATUS_CONFIRMED
            );
            $booking->update($updatedData);
            $booking->write();
	    }
	    catch (Exception $e) {
	        $booking->setSessionErrors('Could not update the event as confirmed.');
            $booking->setSessionFormData($booking->getAllFields());
	    }

	    //Email the user with details of the booking, they will have received an email with the payment confirmation
	    $this->sendBookingConfirmation();

	    $goback = "<div class=\"clear\"></div><a href=\"".$this->Link()."\" class=\"button\">Go Back</a><br />";

        //Check if errors exist
        $errorMessagesArray = $booking->getErrorMessages();
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
        
        //Add booking data to payment class so that it can be retrieved in the template
        $payment->setField('BookingStartDate', $booking->getField('StartDate'));
        $payment->setField('BookingStartTime', $booking->getField('StartTime'));
        $payment->setField('BookingEndTime', $booking->getField('EndTime'));

		$content = $payment->renderWith($payment->ClassName."_confirmation");

		$customisedController = $this->customise(array(
			"Content" => $content.$goback,
			"Form" => ''
		));

		return $customisedController->renderWith("Page");
	}
	
	function sendBookingConfirmation() {

        $payment = $this->Object();
        
        $booking = $this->getBooking($payment->getField('ID'));
        $booking->setField('PaymentMerchantReference', $payment->getField('MerchantReference'));
	    
	    $member = $payment->PaidBy();
        $from = DPSAdapter::get_receipt_from();

        if($member->exists() && $member->Email){
            $from = DPSAdapter::get_receipt_from();
            if($from){
                $body =  $booking->renderWith("Booking_receipt");
                $email = new Email($from, $member->Email, "Booking receipt (Ref no. #".$booking->ID.")", $body);
                $email->send();
            }
        }
	}
	
	function Object() {

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
}
?>