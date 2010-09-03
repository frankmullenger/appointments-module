<?php 
/**
 * Class to represent bookings.
 */
class Booking extends DataObject {

    //TODO need to add a bunch of fields to this
	public static $db = array(
	    'StartTime' => 'Time',
        'EndTime' => 'Time',
		'Date' => 'Date',
    	'Name' => 'Varchar',
    	'Email' => 'Varchar',
		
		//Used to store any Exception during the payment Process.
		'ExceptionError' => 'Text'
	);
	
	public static $has_one = array(
		'Payment' => 'Payment',
	    'Room' => 'Room'
	);
	
	/**
	 * Make payment table transactional.
	 */
	static $create_table_options = array(
		'MySQLDatabase' => 'ENGINE=InnoDB'
	);
		
}
?>