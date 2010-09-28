<?php 
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
    static $summary_fields = array(
        'Title' => 'Title',
        'Description' => 'Description',
        'Street' => 'Street',
        'City' => 'City',
        'PostCode' => 'Post Code'
    );
    
    function getCalendarTimes($service, $startDate, $endDate, $available=false) {
        
        //startDate and endDate need to be in format: Y-m-d
        
        $calendarUrl = $this->getField('CalendarUrl');
        
        $query = $service->newEventQuery($calendarUrl);
        $query->setUser(null);
        $query->setVisibility(null);
        $query->setProjection(null);
        
        //Order the events found by start time in ascending order
        $query->setOrderby('starttime');
        $query->setSortOrder('ascending');
        
//        $startTime = $startDate.' 00:00:00';
//        $endTime = $endDate.' 23:59:59';
        
        //TODO pass in the timezone offset here
        $startTime = $startDate.'T00:00:00.000+12:00';
        $endTime   = $endDate.'T23:59:59.000+12:00';
        
        $query->setStartMin($startTime);
        $query->setStartMax($endTime);
        
//        echo $startTime . '<br />';
//        echo $endTime . '<br />';
        
//        $testStartMin = $query->getStartMin();
//        $testStartMax = $query->getStartMax();
//        
//        echo $testStartMin . '<br />';
//        echo $testStartMax . '<br />';
         
        //Retrieve the event list from the calendar server
        try {
//            $service->useObjectMapping(false);
            $eventFeed = $service->getCalendarEventFeed($query);
            
//            echo '<pre>';
//            var_dump($eventFeed);
//            echo '</pre>';
//            exit;
            
        } catch (Zend_Gdata_App_Exception $e) {
            echo "Error: " . $e->getMessage();
        }

        $events = array();
        foreach ($eventFeed as $event) {
            //echo "<li>" . $event->title . " (Event ID: " . $event->id . ")</li>";
            
            $when = $event->getWhen();
            
//            echo '<pre>';
//            var_dump($when);
//            echo '</pre>';
//            echo '<hr />';
            
            $when = $when[0];

            $eventData = array(
                'id' => $event->id->__toString(),
                'title' => $event->title->__toString(),
                'startTime' => $when->getStartTime(),
                'endTime' => $when->getEndTime(),
                'startDateTime' => new DateTime($when->getStartTime()),
                'endDateTime' => new DateTime($when->getEndTime())
            );
            
            $events[] = $eventData;
        }
        
//        echo '<pre>';
//        var_dump($events);
//        echo '</pre>';
        
        
            
        //TODO how to manage if the minPeriod changes from 30 mins to say 60 mins
        //going to miss elements in the array, will look like a time is available but it won't be?
        
        //TODO return the available times for booking seperated by minimum period
        $minPeriod = Booking::$minPeriod;
//        echo "$minPeriod <br />";
        
        $begin = new DateTime($startTime);
        $end = new DateTime($endTime);
            
//            echo $begin->format('Y-m-d H:i:s');
//            echo '<br />';
//            echo $end->format('Y-m-d H:i:s');
//            echo '<hr />';
            
        $interval = new DateInterval($minPeriod);
        $period = new DatePeriod($begin, $interval, $end);
        
        $freeTimes = array();
        $busyTimes = array();
        foreach ( $period as $dt ) {
            
            $currentTimestamp = $dt->format('U');
            
            $timeIsFree = true;
            $echoed = false;
            foreach ($events as $id => $eventData) {
                $startDateTime = $eventData['startDateTime'];
                $endDateTime = $eventData['endDateTime'];

                //Check if the current timestamp is in between existing ones
                if ($currentTimestamp >= $startDateTime->format('U') && $currentTimestamp < $endDateTime->format('U')) {
                    $timeIsFree = false;
                }
                    
//                    //Debugging output
//                    if (!$timeIsFree && !$echoed) {
//                        echo 'Outer start time: ' . $startDateTime->format('l Y-m-d H:i:s').'<br />';
//                        echo 'Time was in between these two.<br />';
//                        $echoed = true;
//                        echo 'Outer end time: ' . $endDateTime->format('l Y-m-d H:i:s').'<br />';
//                    }
                
            }
            
            if ($timeIsFree) {
                $freeTimes[] = $dt;
            }
            else {
                $busyTimes[] = $dt;
            }
        }
        
//        echo '<pre>';
//        var_dump($busyTimes);
//        var_dump($freeTimes);
//        echo '</pre>';
        
        //TODO check if the period is within an existing time, ignore the end time
        //this should work for booking hotels for days, appts for different amounts of time like 15, 30, 45 mins
        //be totally flexible
        //TODO get the array of times that are taken - can't do this, need to check in between dates

        
        if ($available) {
            return $freeTimes;
        }
        return $busyTimes;
    }
    
    function getTimes($startDate, $endDate, $available=false) {
        
        //TODO start and end dates should be for full week
        $times = array();

        //Get busy times from the database
        $roomID = $this->ID;
        $bookings = DataObject::get(
            'Booking', 
            "`StartDate` >= '$startDate' AND `EndDate` <= '$endDate' AND `RoomID` = $roomID",
            '',
//            'INNER JOIN Payment ON Payment.ID = Booking.PaymentID'
//            'INNER JOIN Room ON Room.ID = Booking.RoomID INNER JOIN DPSPayment ON DPSPayment.ID = Booking.PaymentID'
            ''
        );
        
        //TODO if this room is set to use complex closed times, grab calendr times and merge with these times

        //TODO could not pull components in in a single query, this is a mickey mouse way of getting everything
        //could reduce 
        foreach ($bookings as $booking) {
            
            $room = $booking->getComponent('Room');
            $dpsPayment = $booking->getComponent('Payment');

            /*
             * Check whether: 
             * TxnType == Purchase
             * Status = Success
             * Message = APPROVED
             * Hopefully these values will never arbitrarily change?
             */
            $paymentStatus = $dpsPayment->Status;
            $paymentMessage = $dpsPayment->Message;
            $TxnType = $dpsPayment->TxnType;
            
            $eventData = array();
            if ($paymentStatus == Booking::PAYMENT_STATUS_SUCCESS 
                && $TxnType == Booking::PAYMENT_TXNTYPE_PURCHASE 
                && $paymentMessage == Booking::PAYMENT_MESSAGE_APPROVED) {
                    
                $eventData['id'] = $booking->ID;
                
                $startDateTime = $booking->StartDate.' '.$booking->StartTime;
                $endDateTime = $booking->EndDate.' '.$booking->EndTime;
                
                $startDate = new DateTime($startDateTime);
                $endDate = new DateTime($endDateTime);
                
                $eventData['start'] = $startDate->format('c');
                $eventData['end'] = $endDate->format('c');
                
                $eventData['title'] = null;
                $eventData['readOnly'] = true;
                
                $times[] = $eventData;
            }
        }
        return $times;
    }
    
    function getCMSFields() {
        $fields = parent::getCMSFields();
        
        //TODO get a calendar in here to allow admins to block out times?
        
        return $fields;
    }
}
?>