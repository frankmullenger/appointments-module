<?php

class AppointmentAdmin extends ModelAdmin{
	static $menu_title = "Appointments";
	static $url_segment = "appointments";
	
	static $managed_models = array(
	    "Conference",
	    "Room",
	    "Booking",
	    "Payment"
	);
	
	static $allowed_actions = array(
        "Conference",
        "Room",
        "Booking",
	    "Payment"
	);
	
	public function init() {
        parent::init();
        
        $this->showImportForm = false;
	}
}
?>