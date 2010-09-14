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
    
    const EVENT_STATUS_TENTATIVE = 0;
    const EVENT_STATUS_CONFIRMED = 1;
    const EVENT_STATUS_CANCELLED = 2;

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
	    'Appointment' => 'DataObject' //TODO remove this in favour of AppointmentID as Int in db fields
	);
	public static $create_table_options = array(
		'MySQLDatabase' => 'ENGINE=InnoDB' //Make payment table transactional
	);
	//TODO rename this to interval
	public static $minPeriod;
	
	protected static $googleEmailAddress;
    protected static $googlePassword;
    protected static $googleCalendarUrl;
    
	public $service;
	public $roomCalendarUrl;
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
    
    static function setMinPeriod($periodLength) {
        //Set the length of time minimum can be booked, the period between time in dropdowns
        //periodLength should be an integer to represent minutes
        
        //TODO should only be able to set 15, 30, 60 minutes here
        //TODO maybe just set a minimum of 15 minutes? but also full days need to be taken into account?
        $allowed = array('PT15M', 'PT30M', 'PT60M');
        if (in_array($periodLength, $allowed)) {
            self::$minPeriod = $periodLength;
        }
        else {
            throw new Exception('Minimum period set in _config.php is not PT15M, PT30M or PT60M.');
        }
    }
    
    static function setCalendarUrl($url) {
        //This is deprecated because each room should have its own URL
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
	
    function connectToCalendar() {

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
    
    function setWhen($data = null) {
        
        if (!$data) {
            $data = $this->getAllFields();
        }
        
        if (empty($data)) {
            throw new Exception('Cannot set when, no booking data set.');
        }
        
        $startDate = $data['StartDate'];
        $startTime = $data['StartTime'];
        $endDate = $startDate;
        $endTime = $data['EndTime'];
        
        //TODO ability to override the time zone and daylight savings time in admin area
        //Assume in local time zone of server
        $tzOffset = date('P');
        
        //Check calendar for conflicts
        $when = $this->service->newWhen();
        
        //Depending on whether the data was passed from the form in format: HH:mm
        //or passed from database in format: HH:mm:ss
        if (preg_match('/\d{2}:\d{2}:00/i', $startTime) && preg_match('/\d{2}:\d{2}:00/i', $endTime)) {
            $when->startTime = "{$startDate}T{$startTime}.000{$tzOffset}";
            $when->endTime = "{$endDate}T{$endTime}.000{$tzOffset}";
        }
        else {
            $when->startTime = "{$startDate}T{$startTime}:00.000{$tzOffset}";
            $when->endTime = "{$endDate}T{$endTime}:00.000{$tzOffset}";
        }
        //$event->when = array($when);
        
        $this->when = $when;
    }
    
    function checkCalendarConflict($when = null, $room = null) {
        
        //Set the room when using booking as singleton
        if (isset($room)) {

            $calendarUrl = $room->getField('CalendarUrl');
        
            if (isset($calendarUrl) && $calendarUrl) {
                $this->roomCalendarUrl = $calendarUrl;
            }
        }

        if (!$when) {
            if (!$this->when) {
                $this->setWhen();
            }
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
    
    function addCalendarEvent($when = null, $data = null, $room = null) {

        //Set the room when using booking as singleton
        if (isset($room)) {
            $calendarUrl = $room->CalendarUrl;
        
            if (isset($calendarUrl) && $calendarUrl) {
                $this->roomCalendarUrl = $calendarUrl;
            }
        }
        
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
             
            //Populate the event with the desired information
            $room = $this->getComponent('Room');
            $appt = $this->getBookedObject();
            
            $apptTitle = $appt->Title;
            $street = $room->Street;
            $city = $room->City;
            $country = $room->Country;
            $userEmail = $data['Email'];
            $userName = $data['FirstName'].' '.$data['LastName'];
            
            /*
             * Note that each attribute is crated as an instance of a matching class using magic methods
             * in Gdata/Extension or Gdata/App/Extension or Gdata/Calendar/Extension
             */
            $event->title = $this->service->newTitle("$apptTitle booking made by $userName $userEmail");
            $event->where = array($this->service->newWhere("$street, $city, $country"));
            $event->content = $this->service->newContent("This $apptTitle was booked in by $userName $userEmail.");
            $event->when = array($when);
            $event->who = array($this->service->newWho($userEmail, null, $userName));
            
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
    
    function setSessionErrors($errorMessages, $apptClass = null, $apptClassID = null) {
        //Set error messages into the session
        
        $error = array();
        
        if (!$apptClass) {
            $apptClass = $this->AppointmentClass;
        }
        if (!$apptClassID) {
            $apptClassID = $this->AppointmentID;
        }
        
//        $className = $this->AppointmentClass;
//        $id = $this->AppointmentID;
        
        //$errorMessages could be an array or just a string
        if (is_array($errorMessages)) {
            foreach ($errorMessages as $errorMessage) {
                $error[$apptClass][$apptClassID]['errorMessages'][] = $errorMessage;
            }
        }
        else {
            $error[$apptClass][$apptClassID]['errorMessages'][] = $errorMessages;
        }
        
        //TODO refactor so that AppointmentObjectErrors is not necessary to be set in session
        Session::set('AppointmentObjectErrors', $error);
        return true; 
    }
    
    function getErrorMessages($apptClass = null, $apptClassID = null) {
        
        //Lazy load error messages from the session
        if (empty($this->errorMessages)) {
            $this->setErrorMessages($apptClass, $apptClassID);
        }

        $errorMessages = $this->errorMessages;
        
        //Clear the errorMessages once we have retrieved them
        $this->clearErrorMessages($apptClass, $apptClassID);
        
        return $errorMessages;
    }
    
    function clearErrorMessages($apptClass = null, $apptClassID = null) {
        
        if (!$apptClass) {
            $apptClass = $this->AppointmentClass;
        }
        if (!$apptClassID) {
            $apptClassID = $this->AppointmentID;
        }
        
        $this->errorMessages = array();
        Session::clear("AppointmentObjectErrors.$apptClass.$apptClassID.errorMessages");
        return true;
    }
    
    function setErrorMessages($apptClass = null, $apptClassID = null) {
        
        if (!$apptClass) {
            $apptClass = $this->AppointmentClass;
        }
        if (!$apptClassID) {
            $apptClassID = $this->AppointmentID;
        }
        
        $this->errorMessages = Session::get("AppointmentObjectErrors.$apptClass.$apptClassID.errorMessages");
        if (!$this->errorMessages) {
            $this->errorMessages = array();
        }

    }
    
    function setSessionFormData($formData, $apptClass = null, $apptClassID = null) {
        
        if (!$apptClass) {
            $apptClass = $this->AppointmentClass;
        }
        if (!$apptClassID) {
            $apptClassID = $this->AppointmentID;
        }
        
        //Set form data into the session
        $data = array();
        
        $data[$apptClass][$apptClassID]['formData'] = $formData;
        
        //TODO refactor so that AppointmentObjectFormData is not necessary to be set in session
        Session::set('AppointmentObjectFormData', $data);
        return true;
    }
    
    function getFormData($apptClass = null, $apptClassID = null) {
        
        //Lazy load error messages from the session
        if (empty($this->formData)) {
            $this->setFormData($apptClass, $apptClassID);
        }
        $formData = $this->formData;
        
        //Clear the formData once we have it
        $this->clearFormData($apptClass, $apptClassID);
        
        return $formData;
    }
    
    function clearFormData($apptClass = null, $apptClassID = null) {
        
        if (!$apptClass) {
            $apptClass = $this->AppointmentClass;
        }
        if (!$apptClassID) {
            $apptClassID = $this->AppointmentID;
        }
        
        $this->formData = array();
        Session::clear("AppointmentObjectFormData.$apptClass.$apptClassID.formData");
        return true;
    }
    
    function setFormData($apptClass = null, $apptClassID = null) {
        
        if (!$apptClass) {
            $apptClass = $this->AppointmentClass;
        }
        if (!$apptClassID) {
            $apptClassID = $this->AppointmentID;
        }
        
        $this->formData = Session::get("AppointmentObjectFormData.$apptClass.$apptClassID.formData");
        if (!$this->formData) {
            $this->formData = array();
        }
    }
    
    function getBookedObject(){
        return DataObject::get_by_id($this->AppointmentClass, $this->AppointmentID);
    }
    
    function getPaymentFields($defaults = array()) {
        
        $firstNameField = new TextField("FirstName", "First Name");
        $lastNameField = new TextField("LastName", "Last Name");
        $emailField = new EmailField("Email", "Email");
        
        $startDateField = new DateField("StartDate", "Start Date");
        $startDateField->setConfig('showcalendar', true);
        $startDateField->setConfig('dateformat', 'yyyy-MM-dd');
        
        $endDateField = new DateField("EndDate", "End Date");
        $endDateField->setConfig('showcalendar', true);
        $endDateField->setConfig('dateformat', 'yyyy-MM-dd');
        
        //TODO Want to make these dropdown fields limited by appointment or room, whichever is least
        $startTimeField = new TimeField("StartTime", "Start Time");
        $startTimeField->setConfig('timeformat', 'HH:mm');
        
        $endTimeField = new TimeField("EndTime", "End Time");
        $endTimeField->setConfig('timeformat', 'HH:mm');
        
        $fields = new FieldSet(
            $firstNameField,
            $lastNameField,
            $emailField,
            $startDateField,
            $endDateField,
            $startTimeField,
            $endTimeField
        );
        
        //Set defaults
        if (!empty($defaults)) {
            $fields->setValues($defaults);
        }
        
        return $fields;
    }
		
}
?>