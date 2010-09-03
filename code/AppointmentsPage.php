<?php

$path = dirname(__FILE__).'/../library'; 
set_include_path(get_include_path() .PATH_SEPARATOR. $path);

require_once 'Zend/Gdata.php';
require_once 'Zend/Loader.php';

//Let's enable autoload, ZF handles this nicely
//Zend_Loader::registerAutoload();
require_once 'Zend/Loader/Autoloader.php';
$loader = Zend_Loader_Autoloader::getInstance();

class AppointmentsPage extends Page {

}

class AppointmentsPage_Controller extends Page_Controller {
    
    //TODO frank: move these to config
    const EMAIL_FOR_GOOGLE_ACCOUNTS = '';
    const PASS_FOR_GOOGLE_ACCOUNTS = '';
    const CALENDAR_ADDRESS = '';
    
    private $service;
	
	function init(){
		parent::init();
		Requirements::css("appointment/css/Appointments.css");
	}

    function Conferences() {
        return DataObject::get('Conference');
    }
	
	function payfor() {
	    
	    //Get the object based on URL params like: http://localhost/silverstripe2/payments/payfor/MovieTicket/2
		$object = $this->Object();
		
//		echo '<pre>';
//		var_dump($object);
//		echo '</pre>';
//		exit;
		
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
		
//		echo '<pre>';
//        var_dump($customisedController);
//        echo '</pre>';
//        exit;
		
		return $customisedController->renderWith("Page");
	}
	
    private function connectToCalendar()
    {
        try {
            // Parameters for ClientAuth authentication
            $service = Zend_Gdata_Calendar::AUTH_SERVICE_NAME;
            $client = Zend_Gdata_ClientLogin::getHttpClient(self::EMAIL_FOR_GOOGLE_ACCOUNTS, self::PASS_FOR_GOOGLE_ACCOUNTS, $service);
            
            $this->service = new Zend_Gdata_Calendar($client);
            return true;
        }
        catch (Exception $e) {
            return false;
        }
    }
    
    private function checkCalendarConflict($dateTimeStart, $dateTimeEnd)
    {
        $query = $this->service->newEventQuery(self::CALENDAR_ADDRESS);
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
            
            $newEvent = $this->service->insertEvent($event, self::CALENDAR_ADDRESS);
            
            if ($newEvent) {
                return true;
            }
            return false;
        }
        catch(Exception $e) {
            return false;
        }
    }
	
	function confirm() {
	    
	    //TODO update the booking row here because this should always be for success?
	    //Will need to update the google calendar from here
//	    echo '<pre>';
//	    var_dump($this->Object()->getField('ID'));
//	    echo '</pre>';
	    
	    
	    $payment = $this->Object();
	    
	    //Get the booking object and update calendar with the details from booking row
	    $booking = $this->getBooking($payment->getField('ID'));
	    
	    //Get the calendar and check the dates against it here
        if ($this->connectToCalendar()) {
    	    
    	    // Set the date using RFC 3339 format. (http://en.wikipedia.org/wiki/ISO_8601)
            $data = $booking->getAllFields();
            
            $startDate = $data['Date'];
            $startTime = $data['StartTime'];
            $endDate = $startDate;
            $endTime = $data['EndTime'];
            
            //Assume in local time zone of server
            $tzOffset = date('P');
            
            //Check calendar for conflicts
            $when = $this->service->newWhen();
            $when->startTime = "{$startDate}T{$startTime}.000{$tzOffset}";
            $when->endTime = "{$endDate}T{$endTime}.000{$tzOffset}";
            $event->when = array($when);
            
//            exit('making it to here');
            
    	    if (!$this->checkCalendarConflict($when->startTime, $when->endTime)) {
                //Book in a new event
                if (!$this->addCalendarEvent($when, $data)) {
                    //TODO abort payment with error and email
                }
            }
            else {
                //TODO abort payment with error and email
            }
        }
	    
//	    echo '<pre>';
//        var_dump($booking);
//        echo '</pre>';
//        exit('getting to here');
		
		$content = $payment->renderWith($payment->ClassName."_confirmation");
		$goback = "<div class=\"clear\"></div><a href=\"".$this->Link()."\" class=\"button\">Go Back</a>";
		$customisedController = $this->customise(array(
			"Content" => $content.$goback,
			"Form" => '',
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
	
	function getBooking($objectID)
	{
	    $booking = DataObject::get_one('Booking', "PaymentID = $objectID");
	    return $booking;
	}
	
	function ObjectForm(){
		$object = $this->Object();
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
//		echo '<pre>';
//		var_dump($object);
//		echo '</pre>';
//		exit;
		$object->processDPSPayment($data, $form);
	}
}

?>