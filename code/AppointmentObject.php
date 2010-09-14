<?php

/**
 * Interface for appointments
 * 
 * @author frank
 *
 */
interface AppointmentObjectInterface{
	/**
	 * get all the fields that make the payment form when buying this object
	 *
	 * @return FieldSet
	 */
	function getPaymentFields();
	
	/**
	 * get all the required fields that will make them mandatory in the payment form when buying this object
	 *
	 * @return array;
	 */
	function getPaymentFieldRequired();
	
	/**
	 * get text that merchant like to use as reference in their bill/invoice.
	 *
	 * @return string, less than 64 bits for DPS payment
 	 */
	function getMerchantReference();
	
	/**
	 * get Confirmation message that could be used in the successful page after payment being paid
	 *
	 * @return string, in HTML style.
	 */
	function ConfirmationMessage();	
}

/**
 * Conference type of appointments
 * 
 * @author frank
 *
 */
class Conference extends DataObject implements AppointmentObjectInterface {
    
    static $db = array(
        'Title' => 'Varchar',
        //'ContactEmail' => 'Varchar',
        //TODO some other fields specific to conference products
    );
    static $has_one = array(
        'Room' => 'Room'
    );
    static $summary_fields = array(
        'Title' => 'Conference Product',
        'Room.Title' => 'Room'
    );
    
    function getPaymentFields() {
        
        //TODO set these testing defaults to nulls after testing over
        $testDate = date('Y-m-d', strtotime("+1 day"));
        $defaults = array(
            'StartDate' => $testDate,
            'StartTime' => '1pm',
            'EndTime' => '2pm',
            'FirstName' => 'Joe',
            'LastName' => 'Bloggs',
            'Email' => 'joe@example.com'
        );
        
        //Try and get form data from the session to prepopulate the form fields
        $booking = singleton('Booking');
        
        $defaults = array_merge($defaults, $booking->getFormData($this->owner->ClassName, $this->owner->ID));
        $fields = $booking->getPaymentFields($defaults);
        
        //Remove the endDate field because we don't need it
        $fields->removeByName('EndDate');
        
        return $fields;
    }
    
    function getPaymentFieldRequired() {
        return array(
            'FirstName',
            'LastName',
            'Email',
            'StartDate',
            'StartTime',
            'EndTime'
        );
    }
    
    function getMerchantReference(){
        return substr("Booking for ".$this->Title." in ".$this->Room()->Title, 0, 63);
    }
    
    function ConfirmationMessage(){
        //TODO need to get Date, StartTime, EndTime
        $message = "<h5>This is a confirmation of your booking for: </h5><br /><h6>".$this->Title."</h6><h6>".$this->Room()->Title."</h6>";
        $message .= $this->Room()->renderWith('Room');
        return $message;
    }
    
    function processDPSPayment($data, $form) {
        
        //TODO create a booking object and save it before saving the rest, then update as successing after?
        //Maybe link it with a DPSPayment? And that way could rely on the Status field of Payment table for if the booking is valid
        //Wrap in a transaction and save the 2 objects at once and rely on status field of Payment table
        
        //TODO This is where will need to check that the calendar does not have the time filled already, if so will need to bail at this stage
        //Also will have to write the time to the database and if Status is empty or success of Payment then cannot allow anyone else to make booking
        //for same time
        
        //TODO adding the calendar too early, need to do it once the user has made payment, just need to check for conflicts here
        
        //TODO connect to calendar through the Booking class instead
        //TODO set form data and errors in session through Booking class instead
        
        $booking = singleton('Booking');
        $room = $this->getComponent('Room');
        
        //Get the calendar and check the dates against it here
        if ($booking->connectToCalendar()) {
            
            $booking->setWhen($data);
            
            //if ($booking->checkCalendarConflict($when->startTime, $when->endTime, $room)) {
            if ($booking->checkCalendarConflict(null, $room)) {
                //Set error and form data in session and redirect to previous form
                
                $booking->setSessionErrors('Could not make this booking, it clashes with an existing one.', $this->owner->ClassName, $this->owner->ID);
                $booking->setSessionFormData($data, $this->owner->ClassName, $this->owner->ID);
                
                Director::redirectBack();
                return;
            }
        }
        else {
            //Set error and form data in session and redirect to previous form
            
            $booking->setSessionErrors('Could not connect to calendar.', $this->owner->ClassName, $this->owner->ID);
            $booking->setSessionFormData($data, $this->owner->ClassName, $this->owner->ID);
            
            Director::redirectBack();
            return;
        }
        
        //TODO wrap this in a transaction
        
        //Because this is a decorator $this->owner will reference 
        $form->saveInto($this->owner);
        $this->owner->write();
        
        if(!$member = DataObject::get_one('Member', "\"Email\" = '".$data['Email']."'")){
            $member = new Member();
            $form->saveInto($member);
            $member->write();
        }else{
            $member->update($data);
            $member->write();
        }

        //Write payment
        $payment = new DPSPayment();
        $payment->Amount->Amount = $this->owner->Amount->Amount;
        $payment->Amount->Currency = $this->owner->Amount->Currency;
        
        $payment->PaidByID = $member->ID;
        $payment->PaidForClass = $this->owner->ClassName;
        $payment->PaidForID = $this->owner->ID;
        $payment->MerchantReference = $this->owner->getMerchantReference();
        $payment->write();
        
        $payment->DPSHostedRedirectURL = $this->ConfirmLink($payment);
        $paymentID = $payment->write();
        
        //Get room
        $room = $this->owner->getComponent('Room');
        
        //Write the booking
        $booking = new Booking();
        $booking->FirstName = $data['FirstName'];
        $booking->LastName = $data['LastName'];
        $booking->Email = $data['Email'];
        
        $booking->StartDate = $data['StartDate'];
        $booking->StartTime = $data['StartTime'];
        $booking->EndTime = $data['EndTime'];

        //Save which appointment class and appointment ID is for this booking
        $booking->AppointmentID = $this->owner->ID;
        $booking->AppointmentClass = $this->owner->ClassName;
        
        $booking->PaymentID = $paymentID;
        $booking->RoomID = $room->getField('ID');
        $booking->write();
        
        //TODO figure out how to add a component and save the data object?
        //instead of saving the ids explicitly
//        $booking->setComponent('Payment', $payment);
//        $booking->setComponent('Room', $this->owner->getComponent('Room'));
        
        $payment->dpshostedPurchase(array());
    }
}

/**
 * Room class to represent rooms available for booking appointments in.
 * 
 * @author frank
 *
 */
class Room extends DataObject {
    
    static $db = array(
        'Title' => 'Varchar(128)',
        'Description' => 'Varchar(128)',
        'Street' =>     'Varchar(128)',
        'Suburb' =>     'Varchar',
        'PostCode' =>     'Varchar',
        'City' =>   'Varchar',
        'Country' =>    'Varchar',
        'CalendarUrl' =>    'Varchar(255)'
    );
    
    function getCalendarTimes($service, $startTime, $endTime) {
        
        $calendarUrl = $this->getField('CalendarUrl');
        
        $query = $service->newEventQuery($calendarUrl);
        $query->setUser(null);
        $query->setVisibility(null);
        $query->setProjection(null);
        
        //Order the events found by start time in ascending order
        $query->setOrderby('starttime');
        
        $startTime = '2010-09-14 00:00:00';
        $endTime = '2010-09-14 23:59:59';
        $query->setStartMin($startTime);
        $query->setStartMax($endTime);
         
        //Retrieve the event list from the calendar server
        try {
            $eventFeed = $service->getCalendarEventFeed($query);
        } catch (Zend_Gdata_App_Exception $e) {
            echo "Error: " . $e->getMessage();
        }

        $events = array();
        foreach ($eventFeed as $event) {
            //echo "<li>" . $event->title . " (Event ID: " . $event->id . ")</li>";
            
            $when = $event->getWhen();
            $when = $when[0];
            $eventData = array(
                'id' => $event->id->__toString(),
                'title' => $event->title->__toString(),
                'startTime' => $when->getStartTime(),
                'endTime' => $when->getEndTime()
            );
            
            $events[] = $eventData;
        }
        return $events;
    }
}
?>