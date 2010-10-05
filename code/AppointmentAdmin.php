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

        $booking = DataObject::get('Booking', "ID = $id");
        echo '<pre>';
        var_dump($booking);
        echo '</pre>';
        exit;
        
//        return 'still working?';
//        return $request;

        //TODO change booking eventStatus to cancelled, update the associated payments?
        //TODO need to update the google calendar by removing the associated event
        
        //$parent->ModelAdminResultsForm();
        
//        return $id . ' that is the booking ID';
        return $this->getRecordController($request,'Booking', $id)->edit($request);
    }

}