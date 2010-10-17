Appointment Module
========================================

Maintainer Contact
------------------
Frank Mullenger
<frankmullenger (at) gmail (dot) com>
http://deadlytechnology.com

Requirements
------------
* SilverStripe 2.4
* payment module 0.3+
* [panelmodeladmin module](http://ssorg.bigbird.silverstripe.com/all-other-modules/show/292914)
* PHP 5 >= 5.2 (for DateTime objects)
* A google calendar

Install
-------
* Install the [payments module](http://silverstripe.org/payment-module/)
* Install the [panelmodeladmin module](http://ssorg.bigbird.silverstripe.com/all-other-modules/show/292914?start=0)
* Copy appointments-module folder to the root folder for your silverstripe 2.4 install
* Rename the appointments-module folder to appointments
* Run a /dev/build
* Create an appointments page with a URL slug called 'appointments'

Configuration
-------------
Besides what need to be set for configuration of payment module, the module needs these settings in mysite/_config.php:

* From address for payment receipts:
DPSAdapter::set_receipt_from('email address that your like the payment receipt send from');
* Google account email address, password and URL to calendar:
Booking::setGoogleAccountData('your address@gmail.com', 'your password');
* Its a good idea to set timezone specifically: 
date_default_timezone_set('Pacific/Auckland');

Notes
-----
This module is for making appointment bookings. It is dependant on the payment module, panel model admin and a google calendar.

Currently only DPSPayment is supported. This work was heavily based on the payment-test module.

Cannot include the jquery ui css AFTER the jquery.weekcalendar.css because events on the highlighted day will render out of place. 
[Read more.](http://groups.google.com/group/jquery-week-calendar/browse_thread/thread/2ad5c3b987fb5dd5/738e1b396cdcd7bd?lnk=gst&q=event+not+showing+on+correct+time#738e1b396cdcd7bd)

If you include a DateField with the date dropdown enabled the jquery ui css might be automagically included after the jquery 
weekly calendar.

Zend Gdata classes are used to communicate with the google calendar, the Zend library is included in 
code/library/Zend/. Including the entire library is probably too much work for the manifest builder?

If you are receiving 406 Not Acceptable headers requesting the URL generated by DPS with the ?result= varible in the URI 
it may be due to you apache configuration. Perhaps [LimitRequestLine](http://httpd.apache.org/docs/1.3/mod/core.html#limitrequestline) 
or [Suhosin get max_vars](http://www.hardened-php.net/suhosin/configuration.html#suhosin.get.max_vars).

Ensure the correct timezone is set, if you host the module on a server in the States but are making bookings for NZ the 
timezone of the US will mess up the times booked in. To manually set the timezone in mysite/_config.php: 
//Set timezone
date_default_timezone_set('Pacific/Auckland');

If the site is taking a long time to load try setting ManifestBuilder to ignore the Zend library files in your mysite/_config.php.
//Ignore the Zend framework library files for appointments module
ManifestBuilder::$ignore_folders[] = 'library';

Testing
-------
* Request a developer's testing account with DPS, if you want to test using currencies other than NZD make sure your account 
is enabled to accept those additional currencies otherwise all payments with those currencies will fail

* For testing using the DPS payment gateway, test credit card numbers can be [found here](http://www.paymentexpress.com/knowledge_base/faq/developer_faq.html#testing). 

* First create a room and then a conference product in the appointments area of the CMS, you should see an 'Appointments' link 
in the top navigation

* When creating a room you must enter a google calendar URL for that room. The URL should be in the format:
http://www.google.com/calendar/feeds/ **First part of calendar ID here** %40group.calendar.google.com/private/full. If you are using a public
google calendar swap private for public.

* Use a currency that is supported by your DPS developer account when adding the price for the conference product, NZD is usually 
a safe currency to use

* Create a new Appointments Page in the CMS and publish

* Navigate to the new appointments page, you should see a conference product, click on the 'Book Now' link to start the payment process

-----------------------------------------------

