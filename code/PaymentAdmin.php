<?php
/*
class PaymentAdmin extends ModelAdmin{
	static $menu_title = "Payments";
	static $url_segment = "payments";
	
	static $managed_models = array(
	    "Booking"
	);
	
	static $allowed_actions = array(
        "Booking"
	);
	
	public function init() {
        parent::init();
        
        $this->showImportForm = false;
	}
}
*/
class PaymentAdmin extends LeftAndMain {
 
    static $menu_title = 'Payments';
    static $url_segment = 'payments';
    static $url_rule = '$Action/$ID';
    static $menu_priority = -1;
 
    /**
     * Initialisation method called before accessing any functionality that BulkLoaderAdmin has to offer
     */
    public function init() {
//        Requirements::javascript('appointments/js/something.js');
 
        parent::init();
    }
 
    /**
     * Form that will be shown when we open one of the items
     */  
    public function getEditForm($id = null) {
        
//        return new Form($this, "EditForm",
//            new FieldSet(
//                new ReadonlyField('id #',$id)
//            ),
//            new FieldSet(
//                new FormAction('go')
//            )
//        );
        
        // Create a validator
        $validator = new RequiredFields();
 
        // Create form fields
        $fields = new FieldSet(
            // TODO The ID field needs to be hidden but while testing make it readonly
            new ReadonlyField('ID','id #',$id),
            new TextField('Stuff', 'stuff')
        );
        
        $actions = new FieldSet(
            new FormAction('doUpdateLink', _t('RandomLinksAdmin.UPDATELINK','Update Link xXx'))
        );
 
        /*
        if ($id != 'new') {
            $actions = new FieldSet(
                new FormAction('doUpdateLink', _t('RandomLinksAdmin.UPDATELINK','Update Link xXx'))
            );
        } else {
            $actions = new FieldSet(
                new FormAction('doSaveLink', _t('RandomLinksAdmin.SAVELINK','Save Link xXx'))
            );
        }
        */
 
        $form = new Form($this, "EditForm", $fields, $actions, $validator);
// 
//        if ($id != 'new') {
//            $currentLink = DataObject::get_by_id( 'RandomLinks', $id );
//            $form->loadDataFrom(array(
//                'ID' => $currentLink->ID,
//                'LinkTitle' => $currentLink->LinkTitle,
//                'LinkURL' => $currentLink->LinkURL,
//                'LinkImage' => $currentLink->LinkImage
//            ));
//        }
        return $form;
        
        
    }
    
    public function getSearchForm() {
        
    }
}
?>