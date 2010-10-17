<?php
Object::add_extension('Member', 'AppointmentBuyer');
Object::add_extension('Conference', 'Appointment');

//Set timezone
date_default_timezone_set('Pacific/Auckland');

//Ignore the Zend framework library files for appointments module
//ManifestBuilder::$ignore_folders[] = 'library';
?>