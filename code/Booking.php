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
 * Class to represent bookings.
 */
class Booking extends DataObject {

    //TODO need to add a bunch of fields to this
	public static $db = array(
        //When
        'StartTime' => 'Time',
        'EndTime' => 'Time',
        'StartDate' => 'Date',
        'EndDate' => 'Date',
        //Author
        'FirstName' => 'Varchar',
        'LastName' => 'Varchar',
        'Email' => 'Varchar',
        //Event
        'Title' => 'Varchar(128)',
        'Content' => 'Varchar(255)',
        'EventStatus' => 'Int', //Confirmed, Tentative, Cancelled
        'Hidden' => 'Boolean',
	    'Transparency' => 'Boolean',
	    'Visibility' => 'Boolean',
	    'ExceptionError' => 'Text', //Used to store any Exception during the payment Process.
        'AppointmentClass' => 'Varchar' //Type of appointment made
	);
	public static $has_one = array(
		'Payment' => 'Payment',
	    'Room' => 'Room',
	    'Appointment' => 'AppointmentObject' //TODO this will need to change if AppointmentObject turns into the Appointment decorator
	);
	public static $create_table_options = array(
		'MySQLDatabase' => 'ENGINE=InnoDB' //Make payment table transactional
	);
	
	protected static $googleEmailAddress;
    protected static $googlePassword;
    protected static $googleCalendarUrl;
    
	protected $service;
	protected $roomCalendarUrl;
	
	public $errorMessages = array();
    public $formData = array();
	public $when;
	
    function __construct($record = null, $isSingleton = false) {
        
        parent::__construct($record, $isSingleton);

        //Set the room calendar URL if it exists
        $room = $this->getComponent('Room');
        $calendarUrl = $room->CalendarUrl;
        
        if (isset($calendarUrl) && $calendarUrl) {
            $this->roomCalendarUrl = $calendarUrl;
        }
    }
    
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
        
        if ($this->roomCalendarUrl) {
            return $this->roomCalendarUrl;
        }
        return self::$googleCalendarUrl;
    }
	
    public function connectToCalendar() {
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
    
    public function setWhen() {
        $data = $this->getAllFields();
        
        if (empty($data)) {
            throw new Exception('Cannot set when, no booking data set.');
        }
        
        $startDate = $data['Date'];
        $startTime = $data['StartTime'];
        $endDate = $startDate;
        $endTime = $data['EndTime'];
        
        //TODO ability to override the time zone and daylight savings time in admin area
        //Assume in local time zone of server
        $tzOffset = date('P');
        
        //Check calendar for conflicts
        $when = $this->service->newWhen();
        $when->startTime = "{$startDate}T{$startTime}.000{$tzOffset}";
        $when->endTime = "{$endDate}T{$endTime}.000{$tzOffset}";
        //$event->when = array($when);
        
        $this->when = $when;
    }
    
    public function checkCalendarConflict($when = null) {

        if (!$when) {
            $this->setWhen();
            $when = $this->when;
        }
        
        $dateTimeStart = $when->startTime;
        $dateTimeEnd = $when->endTime;
        
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
    
    public function addCalendarEvent($when = null, $data = null) {
        
        return false;
        
        if (!$when) {
            $this->setWhen();
            $when = $this->when;
        }
        
        if (!$data) {
            $data = $this->getAllFields();
        }
        
        try {
            // Create a new entry using the calendar service's magic factory method
            $event= $this->service->newEventEntry();
             
            // Populate the event with the desired information
            // Note that each attribute is crated as an instance of a matching class
            $event->title = $this->service->newTitle("Conference Package Booking");
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
    
    function getErrorMessages() {
        
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
            if (isset($errors[$this->AppointmentClass][$this->AppointmentID])) {
                $this->errorMessages = $errors[$this->AppointmentClass][$this->AppointmentID]['errorMessages'];
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
            if (isset($data[$this->AppointmentClass][$this->AppointmentID])) {
                $this->formData = $data[$this->AppointmentClass][$this->AppointmentID]['formData'];
            }
        }
    }
    
    function setSessionErrors($errorMessages) {
        //Set error messages into the session
        $error = array();
        
        //TODO use this appointment class and ID for these error messages
        
        $className = $this->AppointmentClass;
        $id = $this->AppointmentID;
        
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
        $className = $this->AppointmentClass;
        $id = $this->AppointmentID;
        
        $data[$className][$id]['formData'] = $formData;
        
        Session::set('AppointmentObjectFormData', $data);
        return true;
    }
    
    //Retrieve the booked in object which is a type of appointment object
    function BookedObject(){
        return DataObject::get_by_id($this->AppointmentClass, $this->AppointmentID);
    }
		
}
?>