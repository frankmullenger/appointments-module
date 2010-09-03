<?php
$path = dirname(__FILE__).'/../library'; 
set_include_path(get_include_path() .PATH_SEPARATOR. $path);

require_once 'Zend/Gdata.php';
require_once 'Zend/Loader.php';

//Let's enable autoload, ZF handles this nicely
//Zend_Loader::registerAutoload();
require_once 'Zend/Loader/Autoloader.php';
$loader = Zend_Loader_Autoloader::getInstance();

define('EMAIL_FOR_GOOGLE_ACCOUNTS','');
define('PASS_FOR_GOOGLE_ACCOUNTS','');
define('CALENDAR_ADDRESS','');

$myEmail = EMAIL_FOR_GOOGLE_ACCOUNTS;
$myPass  = PASS_FOR_GOOGLE_ACCOUNTS;
 
// Parameters for ClientAuth authentication
$service = Zend_Gdata_Calendar::AUTH_SERVICE_NAME;
$client = Zend_Gdata_ClientLogin::getHttpClient($myEmail, $myPass, $service);

$service = new Zend_Gdata_Calendar($client);


/*
 * Get list of calendars available
 */
try {
    $listFeed = $service->getCalendarListFeed();
} catch (Zend_Gdata_App_Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "<h1>Calendar List Feed</h1>";
echo "<ul>";
foreach ($listFeed as $calendar) {
    
//    if ($calendar->title == 'Test conference room') {
//        $cal = $calendar;
//    }
    
    echo "<li>" . $calendar->title .
         " (Event Feed: " . $calendar->id . ")</li>";
}
echo "</ul>";


/*
 * Get events for a particular calendar
 */
$query = $service->newEventQuery(CALENDAR_ADDRESS);
//$query->setUser('default');
//$query->setVisibility('private');
//$query->setProjection('full');
//$query->setOrderby('starttime');
//$query->setFutureevents('true');

//Set this data to null when providing the full URL to the calendar
$query->setUser(null);
$query->setVisibility(null);
$query->setProjection(null);

$queryUrl = $query->getQueryUrl();
 
// Retrieve the event list from the calendar server
try {
    $eventFeed = $service->getCalendarEventFeed(CALENDAR_ADDRESS);
} catch (Zend_Gdata_App_Exception $e) {
    echo "Error: " . $e->getMessage();
}
 
// Iterate through the list of events, outputting them as an HTML list

foreach ($eventFeed as $event) {
    
    echo $event->id . '<br />';
    echo $event->title . '<br />';
    
    echo '<pre>';
    var_dump($event->author[0]->getName()->text);
    var_dump($event->author[0]->getEmail()->text);
    
//    var_dump($event->when);
//    var_dump($event->where);
//    var_dump($event->when);
//    var_dump($event->eventStatus);
//    var_dump($event->visibility);
//    var_dump($event->link);
//    var_dump($event->rights);
//    var_dump($event->contributor);
    
    echo '</pre>';
    echo '<hr />';
    
    //, author, when, event status, visibility, web content, and content
    /*
        protected $_who = array();
    protected $_when = array();
    protected $_where = array();
    protected $_recurrence = null;
    protected $_eventStatus = null;
    protected $_comments = null;
     protected $_transparency = null;
    protected $_visibility = null;
    protected $_recurrenceException = array();
    protected $_extendedProperty = array();
    protected $_originalEvent = null;
    protected $_entryLink = null;
    
    protected $_content = null;

    protected $_published = null;
    protected $_source = null;
    protected $_summary = null;
    protected $_control = null;
    protected $_edited = null;
    
    protected $_service = null;
    protected $_etag = NULL;
    protected $_author = array();
    protected $_category = array();
    protected $_contributor = array();
    protected $_id = null;
    protected $_link = array();
    protected $_rights = null;
    protected $_title = null;
    protected $_updated = null;
    */
    
//    echo '<pre>';
//    var_dump($event);
//    echo '</pre>';
//    exit;
}


/*
 * Add an event to a calendar
 */

// Create a new entry using the calendar service's magic factory method
$event= $service->newEventEntry();
 
// Populate the event with the desired information
// Note that each attribute is crated as an instance of a matching class
$event->title = $service->newTitle("My Event");
$event->where = array($service->newWhere("Christchurch, New Zealand"));
$event->content = $service->newContent(" This is my awesome event. RSVP required.");
 
// Set the date using RFC 3339 format. (http://en.wikipedia.org/wiki/ISO_8601)
$startDate = "2010-09-03";
$startTime = "14:00";
$endDate = "2010-09-03";
$endTime = "16:00";

//Remove offset to assume in local time
//$tzOffset = "-08";
$tzOffset = date('P');
//echo '<pre>';
//var_dump($tzOffset);
//echo '</pre>';
//exit;
 
$when = $service->newWhen();
//$when->startTime = "{$startDate}T{$startTime}:00.000{$tzOffset}:00";
//$when->endTime = "{$endDate}T{$endTime}:00.000{$tzOffset}:00";

$when->startTime = "{$startDate}T{$startTime}:00.000{$tzOffset}";
$when->endTime = "{$endDate}T{$endTime}:00.000{$tzOffset}";
$event->when = array($when);
 
// Upload the event to the calendar server
// A copy of the event as it is recorded on the server is returned
$query = $service->newEventQuery(CALENDAR_ADDRESS);
$query->setUser(null);
$query->setVisibility(null);
$query->setProjection(null);

$conflict = checkConflict($service, $query, $when->startTime, $when->endTime);
if (!$conflict) {
    $newEvent = $service->insertEvent($event, CALENDAR_ADDRESS);
}
else {
    echo '<h2>Conflict was discovered.</h2>';
}


/**
 * @author Siddhant
 * @copyright 2009
 */

//$dateTimeStart = $when->startTime;
//$dateTimeEnd = $when->endTime;
//$query = $service->newEventQuery(CALENDAR_ADDRESS);
//$query->setUser(null);
//$query->setVisibility(null);
//$query->setProjection(null);
//
//checkConflict($service, $query, $dateTimeStart, $dateTimeEnd);

function checkConflict($service, $query, $dateTimeStart, $dateTimeEnd) 
{
    //Order the events found by start time in ascending order
    $query->setOrderby('starttime');
    
    //Set date range
    $query->setStartMin($dateTimeStart);
    $query->setStartMax($dateTimeEnd);
    
    // Retrieve the event list from the calendar server
    // Remember that all-day events will show up while detecting conflicts
    try {
      $feed = $service->getCalendarEventFeed($query);
    } catch (Zend_Gdata_App_Exception $e) {
      echo "Error: " . $e->getResponse();
      return true;
    }
    
    //If even one event is found in the date-time range, then there is a conflict.
    if($feed->totalResults!='0') {
        return true;
    }
    else {
        return false;
    }
    
    //If even one event is found in the date-time range, then there is a conflict.
    /*
    if($feed->totalResults!='0') {
        
        echo($feed->totalResults." Conflicts Found <br/>");
        echo("<ol>");
        
        foreach ($feed as $event) {
          echo "<li>\n";
          echo "<h2>" . stripslashes($event->title) . "</h2>\n";
          echo stripslashes($event->summary) . " <br/>\n";
          $id = substr($event->id, strrpos($event->id, '/')+1);
          echo "</li>\n";
        }
        echo "</ul>";
        echo("</ol>");
    }
    else {
        echo("No Conflicts");
    }
    */
}


echo '<pre>';
var_dump($newEvent);
echo '</pre>';

echo '<hr />';
echo '<pre>';
var_dump($service);
echo '</pre>';

echo 'hello!';