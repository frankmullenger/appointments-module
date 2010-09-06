<?php

//Include necessary Zend Framework libs for google calendar integration
$path = dirname(__FILE__).'/../library'; 
set_include_path(get_include_path() .PATH_SEPARATOR. $path);

require_once 'Zend/Gdata.php';
require_once 'Zend/Loader.php';

//Enable autoloader
require_once 'Zend/Loader/Autoloader.php';
$loader = Zend_Loader_Autoloader::getInstance();

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
 * Appointment object declares some useful methods
 * 
 * @author frank
 *
 */
class AppointmentObject extends DataObject {
    
    protected static $googleEmailAddress;
    protected static $googlePassword;
    protected static $googleCalendarUrl;
    
    static function setGoogleAccountData($emailAddress, $password) {

        self::$googleEmailAddress = $emailAddress;
        self::$googlePassword = $password;
    }
    
    //This is deprecated because each room should have its own URL
    static function setCalendarUrl($url) {
        self::$googleCalendarUrl = $url;
    }
    
    function getGoogleAccountData() {
        return array(
            'googleEmailAddress'=>self::$googleEmailAddress,
            'googlePassword'=>self::$googlePassword
        );
    }
    
    function getCalendarUrl() {
        return self::$googleCalendarUrl;
    }
}


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
    
    function getPaymentFields() {
        
        $dateField = new DateField("Date", "Date");
        $dateField->setConfig('showcalendar', true);
        
        $startTimeField = new TimeField("StartTime", "Start Time");
        $startTimeField->setConfig('showdropdown', true);
        $startTimeField->setValue('11am');
        
        $endTimeField = new TimeField("EndTime", "End Time");
        $endTimeField->setConfig('showdropdown', true);
        $endTimeField->setValue('1pm');
        
        $fields = new FieldSet(
            new HeaderField("Enter your details", 4),
            new TextField("FirstName", "First Name"),
            new TextField("Surname", "Last Name"),
            new EmailField("Email", "Email"),
            
            $dateField,
            $startTimeField,
            $endTimeField
        );
        return $fields;
    }
    
    function getPaymentFieldRequired() {
        
        //TODO: Save this data in the payment table
        return array(
            'FirstName',
            'Surname',
            'Email',
            'Date',
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
    
    private function connectToCalendar()
    {
        try {
            // Parameters for ClientAuth authentication
            $service = Zend_Gdata_Calendar::AUTH_SERVICE_NAME;
            $client = Zend_Gdata_ClientLogin::getHttpClient($this->googleEmailAddress, $this->googlePassword, $service);
            
            $this->service = new Zend_Gdata_Calendar($client);
            return true;
        }
        catch (Exception $e) {
            return false;
        }
    }
    
    private function checkCalendarConflict($dateTimeStart, $dateTimeEnd)
    {
//        $query = $this->service->newEventQuery(self::CALENDAR_ADDRESS);
        $query = $this->service->newEventQuery($this->googleCalendarUrl);
        $query->setUser(null);
        $query->setVisibility(null);
        $query->setProjection(null);
        
        //Order the events found by start time in ascending order
        $query->setOrderby('starttime');
        
        //Set date range
        $query->setStartMin($dateTimeStart);
        $query->setStartMax($dateTimeEnd);
        
        // Retrieve the event list from the calendar server
        // Remember that all-day events will show up while detecting conflicts
        try {
            $feed = $this->service->getCalendarEventFeed($query);
        } catch (Zend_Gdata_App_Exception $e) {
            return true;
        }
        
        //If even one event is found in the date-time range, then there is a conflict.
        if($feed->totalResults!='0') {
            return true;
        }
        else {
            return false;
        }
    }
    
    private function addCalendarEvent($when, $data)
    {
        try {
            // Create a new entry using the calendar service's magic factory method
            $event= $this->service->newEventEntry();
             
            // Populate the event with the desired information
            // Note that each attribute is crated as an instance of a matching class
            $event->title = $this->service->newTitle("A Conference Package");
            $event->where = array($this->service->newWhere("Christchurch, New Zealand"));
            $event->content = $this->service->newContent("This conference was booked in by ".$data['Email'].".");
            $event->when = array($when);
            
//            $newEvent = $this->service->insertEvent($event, self::CALENDAR_ADDRESS);
            $newEvent = $this->service->insertEvent($event, $this->googleCalendarUrl);
            
            if ($newEvent) {
                return true;
            }
            return false;
        }
        catch(Exception $e) {
            return false;
        }
    }
    
    function processDPSPayment($data, $form) {
        
        //TODO create a booking object and save it before saving the rest, then update as successing after?
        //Maybe link it with a DPSPayment? And that way could rely on the Status field of Payment table for if the booking is valid
        //Wrap in a transaction and save the 2 objects at once and rely on status field of Payment table
        
        //This is where will need to check that the calendar does not have the time filled already, if so will need to bail at this stage
        //Also will have to write the time to the database and if Status is empty or success of Payment then cannot allow anyone else to make booking
        //for same time
        
        //TODO adding the calendar too early, need to do it once the user has made payment
        
        //Get the calendar and check the dates against it here
        if ($this->connectToCalendar()) {
            
            //Get the event data
//            echo '<pre>';
//            var_dump($data);
//            echo '</pre>';

            //TODO validate the format of the post data
            
            // Set the date using RFC 3339 format. (http://en.wikipedia.org/wiki/ISO_8601)
            $startDate = $data['Date'];
            $startTime = $data['StartTime'];
            $endDate = $startDate;
            $endTime = $data['EndTime'];
            
            //Assume in local time zone of server
            //$tzOffset = "-08:00";
            $tzOffset = date('P');
            
            //Check calendar for conflicts
            $when = $this->service->newWhen();
            $when->startTime = "{$startDate}T{$startTime}:00.000{$tzOffset}";
            $when->endTime = "{$endDate}T{$endTime}:00.000{$tzOffset}";
            $event->when = array($when);
            
            if (!$this->checkCalendarConflict($when->startTime, $when->endTime)) {
                //Book in a new event
//                if (!$this->addCalendarEvent($when, $data)) {
//                    //TODO abort payment with error
//                }
            }
            else {
                //TODO abort payment with error
            }

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
        
        //TODO add endDate and other data pertaining to google calendar event
        //Write the booking
        $booking = new Booking();
        $booking->StartTime = $data['StartTime'];
        $booking->EndTime = $data['EndTime'];
        $booking->Date = $data['Date'];
        $booking->Name = $data['FirstName'].' '.$data['Surname'];
        $booking->Email = $data['Email'];
        
        $booking->PaymentID = $paymentID;
        $room = $this->owner->getComponent('Room');
        $booking->RoomID = $room->getField('ID');
        
//        $booking->setComponent('Payment', $payment);
//        $booking->setComponent('Room', $this->owner->getComponent('Room'));
        
        $booking->write();
        
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