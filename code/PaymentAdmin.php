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
    //static $url_rule = '$Action/$ID';
    static $url_rule = '$ID/$Action';
    static $menu_priority = -1;
    
    public $modelClass;
    
    private $isAjax = false;
 
    /**
     * Initialisation method called before accessing any functionality that BulkLoaderAdmin has to offer
     */
    public function init() {
        Requirements::javascript("appointments/js/jquery-1.4.2.min.js");
        Requirements::javascript('appointments/js/PaymentAdmin_left.js');
        
        if(Director::is_ajax()) {
            $this->isAjax = true;
        }
        
        $this->modelClass = 'DPSPayment';
 
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
    
    public function SearchForm() {
        
        // Create fields          
        $fields = new FieldSet(
            new TextField('Title', 'Enter Title')
        );
            
        // Create action
        $actions = new FieldSet(
            new FormAction('search', 'Search')
        );
        
        // Create Validators
        $validator = new RequiredFields('Title');
        $validator->setJavascriptValidationHandler('none'); 
        
        $form = new Form($this, 'SearchForm', $fields, $actions, $validator);
        
        $form->setFormMethod('get');
        $form->disableSecurityToken();
        
        return $form;
    }
    
    function search($request, $form) {
        
        //$request, $form
        
        if ($this->isAjax) {
            
//            var_dump($form->getData());
            
            //TODO use the data to do a search of Payments/DPSPayments
            $dpsPayments = DataObject::get(
                'DPSPayment'
            );
            
            //TableListField
            
//            var_dump($dpsPayments);
//            //TableListField Conference array(2) { ["Title"]=>  string(18) "Conference Product" ["Room.Title"]=>  string(4) "Room" } 
//            
//            $resultsForm = $this->ResultsForm(array_merge($form->getData(), $request));
//            echo '<pre>';
//            var_dump($resultsForm);
//            echo '</pre>';
//            
//            return 'po';
            
            // Get the results form to be rendered
            $resultsForm = $this->ResultsForm(array_merge($form->getData(), $request));
            
            // Before rendering, let's get the total number of results returned
//            $tableField = $resultsForm->Fields()->fieldByName($this->modelClass);
//            $numResults = $tableField->TotalCount();
            
            $numResults = 100;
            
            if($numResults) {
                return new SS_HTTPResponse(
                    $resultsForm->forTemplate(), 
                    200, 
                    sprintf(
                        _t('ModelAdmin.FOUNDRESULTS',"Your search found %s matching items"), 
                        $numResults
                    )
                );
            } else {
                return new SS_HTTPResponse(
                    $resultsForm->forTemplate(), 
                    200, 
                    _t('ModelAdmin.NORESULTS',"Your search didn't return any matching items")
                );
            }
            
            
        }
        
        $dpsPayments = DataObject::get(
            'DPSPayment'
        );
        
        $resultsForm = $this->ResultsForm(array_merge($form->getData(), $request));
            echo '<pre>';
            var_dump($resultsForm);
            echo '</pre>';
        
        echo '<pre>';
        
        var_dump($request);
        echo '</ hr>';
        var_dump($form->getData());
        echo '</ hr>';
        
        var_dump($form);
        echo '</ hr>';
        var_dump($dpsPayments);
        
        echo '</pre>';
        return 'lala';
        
        //Extract data
        extract($data);
      
        //Set data
        $From = $data['Email'];
        $To = $this->Mailto;
        $Subject = "$Name has sent an enquiry via your website";
            
        $email = new Email($From, $To, $Subject);
        //set template
        $email->setTemplate('ContactEmail');
        //populate template
        $email->populateTemplate($data);
        //send mail
        $success = $email->send();
        
        //return to submitted message
        Director::redirect(Director::baseURL(). $this->URLSegment . "/?success=1");
    }
    
    function ResultsForm($searchCriteria) {
        
        $tf = $this->getResultsTable($searchCriteria);
        
        // implemented as a form to enable further actions on the resultset
        // (serverside sorting, export as CSV, etc)
        $form = new Form(
            $this,
            'ResultsForm',
            new FieldSet(
                new HeaderField('SearchResultsHeader',_t('ModelAdmin.SEARCHRESULTS','Search Results'), 2),
                $tf
            ),
            new FieldSet(
                new FormAction("goBack", _t('ModelAdmin.GOBACK', "Back")),
                new FormAction("goForward", _t('ModelAdmin.GOFORWARD', "Forward"))
            )
        );
        
        // Include the search criteria on the results form URL, but not dodgy variables like those below
        $filteredCriteria = $searchCriteria;
        unset($filteredCriteria['ctf']);
        unset($filteredCriteria['url']);
        unset($filteredCriteria['action_search']);

        $form->setFormAction($this->Link() . '/ResultsForm?' . http_build_query($filteredCriteria));
        return $form;
        
    }
    
    function getResultsTable($searchCriteria) {
        
        $summaryFields = array('Title' => 'Payments Title');
        $className = 'TableListField';
        
        $tf = new $className(
            $this->modelClass,
            $this->modelClass,
            $summaryFields
        );

        $tf->setCustomQuery($this->getSearchQuery($searchCriteria));

        $tf->setPageSize(30);
        
        $tf->setShowPagination(true);
        // @todo Remove records that can't be viewed by the current user
        $tf->setPermissions(array_merge(array('view','export'), TableListField::permissions_for_object($this->modelClass)));

        // csv export settings (select all columns regardless of user checkbox settings in 'ResultsAssembly')
//        $exportFields = $this->getResultColumns($searchCriteria, false);
//        $tf->setFieldListCsv($exportFields);

        $url = '<a href=\"' . $this->Link() . '/$ID/edit\">$value</a>';
        $tf->setFieldFormatting(array_combine(array_keys($summaryFields), array_fill(0,count($summaryFields), $url)));
    
        return $tf;
    }
    
    function getSearchQuery($searchCriteria) {
        $context = singleton($this->modelClass)->getDefaultSearchContext();
        return $context->getQuery($searchCriteria);
    }
    
}
?>