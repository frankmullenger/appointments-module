<?php

class AppointmentAdmin extends PanelModelAdmin{
    
    static $url_segment     = 'appointments';
    static $menu_title      = 'Appointments';
    static $page_length     = 20;
    static $default_model   = 'Booking';    
    
    static $managed_models =array(
        'Conference',
        'Room',
        'Booking'
    );
    public static $allowed_actions = array(
    );

    function init(){
         
        parent::init();
        Requirements::block('sapphire/thirdparty/jquery-ui-themes/base/jquery-ui-1.8rc3.custom.css');
        
        Requirements::css('http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.1/themes/base/jquery-ui.css');
        Requirements::javascript('http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.1/jquery-ui.min.js');
        
        /**
         * Add some multiple panels.
         */
        $this->addPanels(array(
            'BookingSearchPanel'   => new ModelAdminSearchPanel('Search Bookings', 'open', array('Booking')),
            'RoomMenuPanel'        => new ModelAdminMenuPanel('Rooms', 'open', array('Room')),
            'ConferenceMenuPanel'  => new ModelAdminMenuPanel('Conferences', 'open', array('Conference'))
        ));
    }
    
    function CancelBooking($request) {
        
        $id = $request->postVar('ID');
        
//        $id = 60;

        $bookings = DataObject::get('Booking', "ID = $id");
        $booking =$bookings->First();
        
//        $dpsPayment = $booking->getComponent('Payment');
//        
//        $txnRef = $dpsPayment->getField('TxnRef');
//        $merchantRef = $dpsPayment->getField('MerchantReference');
//        $amount = $dpsPayment->getField('AmountAmount');
//        $currency = $dpsPayment->getField('AmountCurrency');
//        $authCode = $dpsPayment->getField('AuthCode');
        
        $updatedData = array(
            'EventStatus' => Booking::EVENT_STATUS_CANCELLED
        );
        
        //TODO check if booking started in the past before trying to cancel?
        
        try {
            $booking->update($updatedData);
            $booking->write();
            
            //Update the google calendar by removing the associated event
            if ($booking->connectToCalendar()) {
                
                $event = $booking->getCalendarEvent();
                
                //If event does not exist then do not need to do anything
                if ($event) {
                    try {
                        $event->delete();
                    }
                    catch (Zend_Gdata_App_Exception $e) {
                        throw new Exception('Could not delete event from google calendar.');
                    }
                }
            }
            else {
                throw new Exception('Could not connect to google calendar.');
            }

            $form = $this->getRecordController($request,'Booking', $id)->EditForm();
            $form->sessionMessage('Booking was cancelled, please do not forget to refund the payment for this booking if necessary.', 'good');
        }
        catch (Exception $e){
            $form = $this->getRecordController($request,'Booking', $id)->EditForm();
            $form->sessionMessage('Could not cancel the booking, please contact your website developer. '.$e->getMessage(), 'bad');
        }

        return $this->getRecordController($request,'Booking', $id)->edit($request);
    }
    
    function ConfirmBooking($request) {
        
        $id = $request->postVar('ID');
        
//        $id = 60;

        $bookings = DataObject::get('Booking', "ID = $id");
        $booking =$bookings->First();
        
        $updatedData = array(
            'EventStatus' => Booking::EVENT_STATUS_CONFIRMED
        );
        
        //TODO check if booking started in the past before trying to confirm?
        
        try {
            $booking->update($updatedData);
            $booking->write();

            $form = $this->getRecordController($request,'Booking', $id)->EditForm();
            
            //Check calendar and re-enter event
            if ($booking->connectToCalendar()) {
            
                if (!$booking->checkCalendarConflict()) {

                    if (!$booking->addCalendarEvent()) {
                        $form->sessionMessage('Could not add the event to your google calendar, please double check that this event does not clash with an existing event on google calendar.', 'warning');
                    }
                }
                else {
                    $form->sessionMessage('Could not connect to your google calendar to add the event again or check that the event does not conflict.', 'warning');
                }
            }
            else {
                $form->sessionMessage('Booking was confirmed (no longer cancelled), please do not forget to undo any refunds you made for this booking if necessary.', 'good');
            }
        }
        catch (Exception $e){
            $form = $this->getRecordController($request,'Booking', $id)->EditForm();
            $form->sessionMessage('Could not confirm (un-cancel) the booking, please contact your website developer. '.$e->getMessage(), 'bad');
        }

        return $this->getRecordController($request,'Booking', $id)->edit($request);
    }

}