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

//TODO refactor appointment object to the Appointment decorator class
/**
 * Appointment object declares some useful methods
 * 
 * @author frank
 *
 */
class AppointmentObject extends DataObject {
    
    static $has_many = array(
        'Bookings' => 'Booking'
    );

    /*
     * TODO look at moving these error functions to booking class
     * because they are related to an individual booking and not an appointment
     * 
     * convenience function for view, get error messages from Booking class and set in DataObjectSet for view
     */
    function getErrorMessages($formatted = false) {
        
        //Get error messages from Booking class
        $booking = singleton('Booking');

        $errorMessagesArray = $booking->getErrorMessages($this->owner->ClassName, $this->owner->ID);
        
        $errorMessages = new DataObjectSet();
        foreach ($errorMessagesArray as $errorMessage) {
            $errorMessages->push(new ArrayData(array('ErrorMessage'=>$errorMessage)));
        }
        return $errorMessages;
    }

}

//TODO this should not extend AppointmentObject but instead just be 'decorated' by Appointment
/**
 * Conference type of appointments
 * 
 * @author frank
 *
 */
class Conference extends AppointmentObject implements AppointmentObjectInterface {
    
    private $service;
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
    
    //TODO move calendar related stuff to Booking
    function __construct($record = null, $isSingleton = false) {
        parent::__construct($record, $isSingleton);
    }
    
    function getPaymentFields() {

        //TODO these fields should be the same that are in the booking object so we can 
        //prepopulate from session from AppointmentsPage
        //get a singular booking object and then get booking payment fields to compliment booking fields
        //of this particular appointment object, remove any fields from the list if necessary
        
        
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
        
        //TODO figure out how to add a component and save the data object
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
}
?>