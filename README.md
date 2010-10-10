Appointment Module
========================================

Maintainer Contact
------------------
Frank Mullenger (Nickname: _patton, tank)
<frankmullenger (at) gmail (dot) com>
http://deadlytechnology.com

Requirements
------------
* SilverStripe 2.4
* payment module 0.3+
* [panelmodeladmin module](http://ssorg.bigbird.silverstripe.com/all-other-modules/show/292914)
* PHP 5 >= 5.3.0 (for DateInterval object used in Room class)

Install
-------
Firstly install the payments module
Copy appointments-module folder to the root folder for your silverstripe 2.4 install
Rename the appointments-module folder to appointments
Run a /dev/build

Configuration
-------------
Besides what need to be set for configuration of payment module, the module needs to set:

DPSAdapter::set_receipt_from('email address that your like the payment receipt send from');

Set your google account email address, password and URL to calendar in mysite/_config.php:

Booking::setGoogleAccountData('your address@gmail.com', 'your password');

Notes
-----
This module is for making appointment bookings. It is dependant on the payment module and google calendar.

Currently only DPSPayment is supported. This work was heavily based on the payment-test module.

Cannot include the jquery ui css AFTER the jquery.weekcalendar.css because events on the highlighted day will render out of place. 
[Read more.](http://groups.google.com/group/jquery-week-calendar/browse_thread/thread/2ad5c3b987fb5dd5/738e1b396cdcd7bd?lnk=gst&q=event+not+showing+on+correct+time#738e1b396cdcd7bd)

If you include a DateField with the date dropdown enabled the jquery ui css might be automagically included after the jquery 
weekly calendar.

Zend Gdata classes are used to communicate with the google calendar, the Zend library is included in 
code/library/Zend/. Including the entire library is probably too much work for the manifest builder?

Testing
-------
* Request a developer's testing account with DPS, if you want to test using currencies other than NZD make sure your account 
is enabled to accept those additional currencies otherwise all payments with those currencies will fail

* For testing using the DPS payment gateway, test credit card numbers can be [found here](http://www.paymentexpress.com/knowledge_base/faq/developer_faq.html#testing). 

* First create a room and then a conference product in the appointments area of the CMS, you should see an 'Appointments' link 
in the top navigation

* Use a currency that is supported by your DPS developer account when adding the price for the conference product, NZD is usually 
a safe currency to use

* Create a new Appointments Page in the CMS and publish

* Navigate to the new appointments page, you should see a conference product, click on the 'Book Now' link to start the payment process

-----------------------------------------------

