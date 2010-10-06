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
 * Using this to make attaching appointments to bookings easier
 * 
 * @author frank
 *
 */
class AppointmentObject extends DataObject {
    
}

/**
 * Conference type of appointments
 * 
 * @author frank
 *
 */
class Conference extends AppointmentObject implements AppointmentObjectInterface {
    
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
            'StartTime' => '13:00',
            'EndTime' => '14:00',
            'FirstName' => 'Joe',
            'LastName' => 'Bloggs',
            'Email' => 'joe@example.com'
        );
        
        //Try and get form data from the session to prepopulate the form fields
        $booking = singleton('Booking');
        $defaults = array_merge($defaults, $booking->getFormData($this->owner->ClassName, $this->owner->ID));
        
//        echo '<pre>';
//        var_dump($defaults);
//        echo '</pre>';
        
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
    
    function getMerchantReference($data = null){
//        return substr("Booking for ".$this->Title." in ".$this->Room()->Title, 0, 63);

        //Create a random number including date and time of booking approximately
        if ($data) {
            $startDate = str_replace('-', '', $data['StartDate']);
            $datetime = $startDate.$data['StartTime'];
        }
        else {
            $datetime = date('YmdH:i');
        }
        
        list($usec, $sec) = explode(' ', microtime());
        $seed = (float) $sec + ((float) $usec * 100000);
        mt_srand($seed);
        $randval = mt_rand(1000000, 9999999);
        
        return $randval.'-'.$datetime;
    }
    
    function ConfirmationMessage(){
        //TODO need to get Date, StartTime, EndTime, merchant reference
//        $message = "<h5>This is a confirmation of your booking for: </h5><br /><h6>".$this->Title."</h6><h6>".$this->Room()->Title."</h6>";
//        $message .= $this->Room()->renderWith('Room');
        
        $title = $this->Title;
        $roomTitle = $this->Room()->Title;
        $merchantReference = $this->MerchantReference;
        $message = <<<EOS
<h5>This is a confirmation of your booking for: </h5>
<h6>A $title in $roomTitle</h6>
<p>Do not lose this reference: $merchantReference</p>
EOS;
        
        return $message;
    }
    
    function processDPSPayment($data, $form) {

        $data['StartTime'] = date('H:i', strtotime($data['StartTime']));
        $data['EndTime'] = date('H:i', strtotime($data['EndTime']));
        
        $booking = singleton('Booking');
        $room = $this->getComponent('Room');
        
        //TODO remove all this checking of the calendar in favour of checking the database of bookings
        $startDate = $data['StartDate'];
        $startTime = $data['StartTime'];
        $endDate = $startDate;
        $endTime = $data['EndTime'];
        
        $startDateTime = new DateTime($startDate.' '.$startTime);
        $endDateTime = new DateTime($endDate.' '.$endTime);
        
        //Check booking conflict from database first
        if ($booking->checkBookingConflict($startDateTime, $endDateTime, $room)) {
            $booking->setSessionErrors('Could not make this booking, it clashes with an existing one. Please select another time.', $this->owner->ClassName, $this->owner->ID);
            $booking->setSessionFormData($data, $this->owner->ClassName, $this->owner->ID);
            
            Director::redirectBack();
            return;
        }
        
        //Check booking conflict from Google calendar as well
        if ($booking->connectToCalendar()) {
            
            $booking->setWhen($data);
            
            //if ($booking->checkCalendarConflict($when->startTime, $when->endTime, $room)) {
            if ($booking->checkCalendarConflict(null, $room)) {
                //Set error and form data in session and redirect to previous form
                
                $booking->setSessionErrors('Could not make this booking, it clashes with an existing one. Please select another time.', $this->owner->ClassName, $this->owner->ID);
                $booking->setSessionFormData($data, $this->owner->ClassName, $this->owner->ID);
                
                Director::redirectBack();
                return;
            }
        }
        else {
            $booking->setSessionErrors('Could not connect to calendar. Please inform us of this error.', $this->owner->ClassName, $this->owner->ID);
            $booking->setSessionFormData($data, $this->owner->ClassName, $this->owner->ID);
            
            Director::redirectBack();
            return;
        }

        //TODO wrap this in a transaction
        
        //Because this is a decorator $this->owner will reference this itself
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
        $payment->MerchantReference = $this->owner->getMerchantReference($data);
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
        
        //TODO save ISO 8601 strings for convenience later on
        $booking->StartDate = $data['StartDate'];
        $booking->EndDate = $data['StartDate'];
        $booking->StartTime = $data['StartTime'];
        $booking->EndTime = $data['EndTime'];

        //Save which appointment class and appointment ID is for this booking
        $booking->AppointmentID = $this->owner->ID;
        $booking->AppointmentClass = $this->owner->ClassName;
        
        $booking->PaymentID = $paymentID;
        $booking->RoomID = $room->getField('ID');
        $booking->write();
        
        //TODO send an email to the user with merchant reference, date, time, type, room, duration, options, booking
        
        //TODO figure out how to add a component and save the data object?
        //instead of saving the ids explicitly
//        $booking->setComponent('Payment', $payment);
//        $booking->setComponent('Room', $this->owner->getComponent('Room'));
        
        $payment->dpshostedPurchase(array());
    }
}
?>