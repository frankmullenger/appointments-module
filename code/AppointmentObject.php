<?php

//TODO remove all this Zend stuff
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

//TODO refactor appointment object to the Appointment decorator class
/**
 * Appointment object declares some useful methods
 * 
 * @author frank
 *
 */
class AppointmentObject extends DataObject {
    
    //TODO move calendar related stuff to Booking
    protected static $googleEmailAddress;
    protected static $googlePassword;
    protected static $googleCalendarUrl;
    
    protected $roomCalendarUrl = null;

    public $errorMessages = array();
    public $formData = array(); 
    public $when;
    
    static $has_many = array(
        'Bookings' => 'Booking'
    );
    
    //TODO move calendar related stuff to Booking
    static function setGoogleAccountData($emailAddress, $password) {

        self::$googleEmailAddress = $emailAddress;
        self::$googlePassword = $password;
    }
    
    //TODO move calendar related stuff to Booking
    //This is deprecated because each room should have its own URL
    static function setCalendarUrl($url) {
        self::$googleCalendarUrl = $url;
    }
    
    //TODO move calendar related stuff to Booking
    function getGoogleAccountData() {
        return array(
            'googleEmailAddress'=>self::$googleEmailAddress,
            'googlePassword'=>self::$googlePassword
        );
    }
    
    //TODO move calendar related stuff to Booking
    function getCalendarUrl() {
        
        if ($this->roomCalendarUrl) {
            return $this->roomCalendarUrl;
        }
        return self::$googleCalendarUrl;
    }
    
    
    function getErrorMessages($formatted = false) {
        
        //Lazy load error messages from the session
        if (empty($this->errorMessages)) {
            $this->setErrorMessages();
        }
        
        //Return error messages to the view
        $errorMessages = new DataObjectSet();
        foreach($this->errorMessages as $errorMessage) {
            $errorMessages->push(new ArrayData(array('ErrorMessage'=>$errorMessage)));
        }
        
        //Clear the errorMessages once we have retrieved them
        $this->clearErrorMessages();
        
        return $errorMessages;
    }
    
    function clearErrorMessages() {
        $this->errorMessages = array();
        //TODO this may not be the best clearing all appointment object errors
        //what if user has multiple tabs open and making multiple bookings in one browser
        Session::clear('AppointmentObjectErrors');
        return true;
    }
    
    function setErrorMessages() {
        //Helper to set error messages
        $errors = Session::get('AppointmentObjectErrors');
        if ($errors) {
            if (isset($errors[$this->owner->ClassName][$this->owner->ID])) {
                $this->errorMessages = $errors[$this->owner->ClassName][$this->owner->ID]['errorMessages'];
            }
        }
    }
    
    protected function getFormData() {
        
        //Lazy load error messages from the session
        if (empty($this->formData)) {
            $this->setFormData();
        }
        $formData = $this->formData;
        
        //Clear the formData once we have it
        $this->clearFormData();
        
        return $formData;
    }
    
    function clearFormData() {
        $this->formData = array();
        //TODO this may not be the best clearing all appointment object errors
        //what if user has multiple tabs open and making multiple bookings in one browser
        Session::clear('AppointmentObjectFormData');
        return true;
    }
    
    function setFormData() {
        //Helper to set error messages
        $data = Session::get('AppointmentObjectFormData');
        if ($data) {
            if (isset($data[$this->owner->ClassName][$this->owner->ID])) {
                $this->formData = $data[$this->owner->ClassName][$this->owner->ID]['formData'];
            }
        }
    }
    
    function setSessionErrors($errorMessages) {
        //Set error messages into the session
        $error = array();
        $className = $this->owner->ClassName;
        $id = $this->owner->ID;
        
        //$errorMessages could be an array or just a string
        if (is_array($errorMessages)) {
            foreach ($errorMessages as $errorMessage) {
                $error[$className][$id]['errorMessages'][] = $errorMessage;
            }
        }
        else {
            $error[$className][$id]['errorMessages'][] = $errorMessages;
        }
        
        Session::set('AppointmentObjectErrors', $error);
        return true; 
    }
    
    function setSessionFormData($formData) {
        
        //Set form data into the session
        $data = array();
        $className = $this->owner->ClassName;
        $id = $this->owner->ID;
        
        $data[$className][$id]['formData'] = $formData;
        
        Session::set('AppointmentObjectFormData', $data);
        
//        echo '<pre>';
//        var_dump(Session::get('AppointmentObjectFormData'));
//        echo '</pre>';
//        exit;
        
        return true;
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

        //Set the room calendar URL if it exists
        $room = $this->getComponent('Room');
        $calendarUrl = $room->CalendarUrl;
        
        if (isset($calendarUrl) && $calendarUrl) {
            $this->roomCalendarUrl = $calendarUrl;
        }
    }
    
    function getPaymentFields() {

        //TODO these fields should be the same that are in the booking object so we can 
        //prepopulate from session from AppointmentsPage
        //get a singular booking object and then get booking payment fields to compliment booking fields
        //of this particular appointment object, remove any fields from the list if necessary
        
//        echo '<pre>';
//        var_dump($this);
//        echo '</pre>';
//        exit;
        
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
        //TODO get form data saved in session via Booking class, cannot do with singleton because some data needs to be set in the object
//        $defaults = array_merge($defaults, $booking->getFormData());
        $defaults = array_merge($defaults, $this->getFormData());
        
//        $booking = DataObject::get_by_id('Booking', $bookingID);

        $booking = singleton('Booking');
        $fields = $booking->getPaymentFields($defaults);
        
        //Remove the endDate field because we don't need it
        $fields->removeByName('EndDate');
        
        return $fields;
        

        /*
        $dateField = new DateField("Date", "Date");
        $dateField->setConfig('showcalendar', true);
        $dateField->setConfig('dateformat', 'yyyy-MM-dd');
        $dateField->setValue($defaults['Date']);
        
        $startTimeField = new TimeField("StartTime", "Start Time");
        //$startTimeField->setConfig('showdropdown', true);
        $startTimeField->setConfig('timeformat', 'HH:mm');
        $startTimeField->setValue($defaults['StartTime']);
        
        $endTimeField = new TimeField("EndTime", "End Time");
        //$endTimeField->setConfig('showdropdown', true);
        $endTimeField->setConfig('timeformat', 'HH:mm');
        $endTimeField->setValue($defaults['EndTime']);
        
        $fields = new FieldSet(
            new HeaderField("Enter your details", 4),
            new TextField("FirstName", "First Name", $defaults['FirstName']),
            new TextField("LastName", "Last Name", $defaults['Surname']),
            new EmailField("Email", "Email", $defaults['Email']),
            
            $dateField,
            $startTimeField,
            $endTimeField
        );
        return $fields;
        */
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
    
    //TODO move calendar related stuff to Booking
    public function connectToCalendar()
    {
        try {
            // Parameters for ClientAuth authentication
            $service = Zend_Gdata_Calendar::AUTH_SERVICE_NAME;
            $client = Zend_Gdata_ClientLogin::getHttpClient(self::$googleEmailAddress, self::$googlePassword, $service);
            
            $this->service = new Zend_Gdata_Calendar($client);
            return true;
        }
        catch (Exception $e) {
            return false;
        }
    }
    
    //TODO move calendar related stuff to Booking
    public function checkCalendarConflict($dateTimeStart, $dateTimeEnd)
    {

        $query = $this->service->newEventQuery($this->getCalendarUrl());
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
    
    //TODO move calendar related stuff to Booking
    public function addCalendarEvent($when, $data)
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
            $newEvent = $this->service->insertEvent($event, $this->getCalendarUrl());
            
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
        
        //TODO This is where will need to check that the calendar does not have the time filled already, if so will need to bail at this stage
        //Also will have to write the time to the database and if Status is empty or success of Payment then cannot allow anyone else to make booking
        //for same time
        
        //TODO adding the calendar too early, need to do it once the user has made payment, just need to check for conflicts here
        
        //TODO connect to calendar through the Booking class instead
        
        //Get the calendar and check the dates against it here
        if ($this->connectToCalendar()) {
            
            //Get the event data
//            echo '<pre>';
//            var_dump($data);
//            echo '</pre>';
//            exit;

            //TODO validate the format of the post data
            //TODO validate that the date is in the future
            
            // Set the date using RFC 3339 format. (http://en.wikipedia.org/wiki/ISO_8601)
            $startDate = $data['StartDate'];
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
            
            if ($this->checkCalendarConflict($when->startTime, $when->endTime)) {
                //Set error adn form data in session and redirect to previous form
                $this->setSessionErrors('Could not make this booking, it clashes with an existing one.');
                $this->setSessionFormData($data);
                Director::redirectBack();
                return;
            }

        }
        else {
            //Set error adn form data in session and redirect to previous form
            $this->setSessionErrors('Could not connect to calendar.');
            $this->setSessionFormData($data);
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