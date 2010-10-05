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
        $dpsPayment = $booking->getComponent('Payment');
        
        $txnRef = $dpsPayment->getField('TxnRef');
        $merchantRef = $dpsPayment->getField('MerchantReference');
        $amount = $dpsPayment->getField('AmountAmount');
        $currency = $dpsPayment->getField('AmountCurrency');
        $authCode = $dpsPayment->getField('AuthCode');
        
        $updatedData = array(
            'EventStatus' => Booking::EVENT_STATUS_CANCELLED
        );
        
        try {
            $booking->update($updatedData);
            $booking->write();
        }
        catch (Exception $e){
            //Set an error here
        }
        
//        echo '<pre>';
//        var_dump($booking);
//        var_dump($txnRef);
//        var_dump($merchantRef);
//        var_dump($amount);
//        var_dump($currency);
//        var_dump($dpsPayment);
//        echo '</pre>';
//        exit;
        
        //TODO update the booking and payment objects

        //TODO change booking eventStatus to cancelled, update the associated payments?
        //TODO need to update the google calendar by removing the associated event
        
        //$parent->ModelAdminResultsForm();
        
//        return $id . ' that is the booking ID';
        return $this->getRecordController($request,'Booking', $id)->edit($request);
    }

}