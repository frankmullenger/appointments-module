<?php

class AppointmentsPage extends Page {

}

class AppointmentsPage_Controller extends Page_Controller {
	
	function init(){
		parent::init();
		Requirements::css("appointment/css/Appointments.css");
	}

    function getConferences() {
        return DataObject::get('Conference');
    }
	
	function payfor() {
	    
	    //TODO Get the calendar for this particular room and show just the hours and days that can be used
	    //TODO Update the hours depending on the date using AJAX
	    
	    //Get the object based on URL params like: http://localhost/silverstripe2/payments/payfor/MovieTicket/2
		$object = $this->Object();
		
		$booking = singleton('Booking');
		$room = $object->getComponent('Room');
        
	    if ($booking->connectToCalendar()) {
            
            $availability = $room->getCalendarTimes($booking->service, date('Y-m-d', strtotime('2010-09-14')), date('Y-m-d', strtotime('2010-09-14')), true);
            
//            echo '<pre>';
//            var_dump($availability);
//            echo '</pre>';
            exit('payfor');
        }
		
		$content = $object->renderWith($object->ClassName."_payable");
		$form = $this->ObjectForm();
		$cancel = "<div class=\"clear\"></div><a href=\"".$this->Link()."\" class=\"button\">I've changed mind, cancel.</a>";
		
		/*
		 * This is concatenating the content:
		 * $content
		 * $form->forTemplate
		 * $cancel
		 * Then merging that data with the customised controller object, in essence passing it all to the view as content
		 */
		$customisedController = $this->customise(array(
			"Content" => $content.$form->forTemplate().$cancel,
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

	function ObjectForm(){
		$object = $this->Object();

		//TODO pass through the date and time dropdown defaults?
		$fields = $object->getPaymentFields();
		
		$fields->push(new HiddenField('ObjectClass', 'ObjectClass', $object->ClassName));
		$fields->push(new HiddenField('ObjectID', 'ObjectID', $object->ID));
		$required = $object->getPaymentFieldRequired();
		
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