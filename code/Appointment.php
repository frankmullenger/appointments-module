<?php

class Appointment extends DataObjectDecorator {
	function extraStatics(){
		return array(
			'db' => array(
				'Amount' => 'Money',
			),
		);
	}
	
	function updateCMSFields(&$fields){
		$money= $fields->datafieldByName('Amount');
		$money->allowedCurrencies = DPSAdapter::$allowed_currencies;
	}
	
	function PayableLink() {
		$appointmentsPage = DataObject::get_one('AppointmentsPage');
		$id = $this->owner->ID?$this->owner->ID:"";
		return $appointmentsPage->Link()."payfor/".$this->owner->ClassName."/".$id;
	}
	
	function ConfirmLink($payment) {
		$appointmentsPage = DataObject::get_one('AppointmentsPage');
		return $appointmentsPage->Link()."confirm/".$payment->ClassName."/".$payment->ID;
	}
	
	function processDPSPayment($data, $form) {
	    
	    //Because this is a decorator $this->owner will reference 
		$form->saveInto($this->owner);
		$this->owner->write();
		
		if(!$member = DataObject::get_one('Member', "\"Email\" = '".$data['Email']."'")){
			$member = new Member();
			$form->saveInto($member);
			$member->write();
		}else{
			$member->update($data);
			$member->write();
		}

		$payment = new DPSPayment();
		$payment->Amount->Amount = $this->owner->Amount->Amount;
		$payment->Amount->Currency = $this->owner->Amount->Currency;
		
		$payment->PaidByID = $member->ID;
		$payment->PaidForClass = $this->owner->ClassName;
		$payment->PaidForID = $this->owner->ID;
		$payment->MerchantReference = $this->owner->getMerchantReference();
		$payment->write();
		
		$payment->DPSHostedRedirectURL = $this->ConfirmLink($payment);
		$payment->write();
		$payment->dpshostedPurchase(array());
	}
	
	
}

?>