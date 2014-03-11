<?php

/** 
 * PDF Receipt Extension for CiviCRM - Circle Interactive 2012-14
 * 
 * @package com.uk.andyw.pdfreceipt
 * @version 1.0
 * @author  andyw@circle
 *
 * Distributed under GPL v2
 * https://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * Implementation of hook_civicrm_alterMailParams
 */
function pdfreceipt_civicrm_alterMailParams(&$params) {

    switch(true) {
        
        # event receipt ..

        case $params['groupName'] == 'msg_tpl_workflow_event' and $params['valueName'] == 'event_offline_receipt':

            # for offline receipt, retrieve any missing info that we need

            if (!isset($params['tplParams']['participantID']))
                $params['tplParams']['participantID'] = pdfreceipt_get_last_participantID_for_contactID($params['contactId']);

            if (!isset($params['tplParams']['event']['id']) and isset($params['tplParams']['participantID']))
                $params['tplParams']['event']['id'] = pdfreceipt_get_eventID_for_participantID($params['tplParams']['participantID']);

            if (!isset($params['tplParams']['event']['id']) or !isset($params['tplParams']['participantID']))
                return;

            # intentionally falls through to ..

        case $params['groupName'] == 'msg_tpl_workflow_event' and $params['valueName'] == 'event_online_receipt':
            
            # if no receipt format selected, exit the hook
            if (!$template_class = pdfreceipt_get_format('Event', $params['tplParams']['event']['id'])) 
                return;
          
            # set this sodding invoice number correctly on pay laters
            if (isset($params['tplParams']['participant']['is_pay_later']) and !empty($params['tplParams']['participant']['is_pay_later']))
                if ($contribution_id = pdfreceipt_get_last_contributionID_for_contactID($params['contactId']))
                    CRM_Core_DAO::executeQuery("
                        UPDATE civicrm_contribution SET invoice_id = REPLACE(invoice_id, 'HPMA', 'HPEVENTS') 
                         WHERE id = %1
                    ", array(
                          1 => array($contribution_id, 'Positive')
                       )
                    );

            # if a class name was returned, instantiate it
            $receipt = new $template_class;
            
            # call 'create' method, supplying the necessary params       
            $pdf = $receipt->create(array(
                'ids' => array(
                    'participant' => $params['tplParams']['participantID']
                ),
                'filename' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . md5(time()) . '.pdf'
            ));
            
            # attach the new attachment ..
            $params['attachments'][] = array(
                'fullPath'  => $pdf['filename'],
                'mime_type' => 'application/pdf',
                'cleanName' => 'receipt.pdf'
            );
            
            break;

        case $params['groupName'] == 'msg_tpl_workflow_contribution' and $params['valueName'] == 'contribution_offline_receipt':
        case $params['groupName'] == 'msg_tpl_workflow_membership'   and $params['valueName'] == 'membership_offline_receipt':

            # no info whatsoever supplied? that'll be one of the 'offline' templates then - we need to deduce everything
            # from the contact_id in those cases - happy days!
            if (!isset($params['tplParams']['contributionPageId']))
                $params['tplParams']['contributionPageId'] = pdfreceipt_get_last_contributionPageId_for_contactID($params['contactId']);

            # don't go any further if that failed
            if (!isset($params['tplParams']['contributionPageId']))
                return;

            # intentionally falls through to ..

        case $params['groupName'] == 'msg_tpl_workflow_membership'   and $params['valueName'] == 'membership_online_receipt':
        case $params['groupName'] == 'msg_tpl_workflow_contribution' and $params['valueName'] == 'contribution_online_receipt':

            # if no receipt format selected, exit the hook_civicrm_config
            if (!$template_class = pdfreceipt_get_format('ContributionPage', $params['tplParams']['contributionPageId'])) 
                return;
          
            # if a class name was returned, instantiate it
            $receipt = new $template_class;
            
            $ids = array('contact' => $params['contactId']);

            if (isset($params['tplParams']['membershipID']) and !empty($params['tplParams']['membershipID']))
                $ids['membership'] = $params['tplParams']['membershipID'];

            if (isset($params['tplParams']['contributionID']) and !empty($params['tplParams']['contributionID']))
                $ids['contribution'] = $params['tplParams']['contributionID'];

            # call 'create' method, supplying the necessary params       
            $pdf = $receipt->create(array(
                'ids'      => $ids,
                'filename' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . md5(time()) . '.pdf'
            ));

            # attach the new attachment ..
            $params['attachments'][] = array(
                'fullPath'  => $pdf['filename'],
                'mime_type' => 'application/pdf',
                'cleanName' => 'receipt.pdf'
            );

            break;
                
    }
    
}

/**
 * Implementation of hook_civicrm_buildForm
 */
function pdfreceipt_civicrm_buildForm($formName, &$form) {    
        
    # Load custom css/javascript on all ManageEvent and ContributionPage forms - we need to do this because of the ajax
    # form loading mechanism - ie: any of these forms could be the entry point via which the
    # 'Registration' or 'Thankyou' form is eventually viewed

    switch (array_slice(explode('_', $formName), 0, 4)) {
        
        # if ManageEvent or ContributionPage form ..
        case array('CRM', 'Event', 'Form', 'ManageEvent'):
        case array('CRM', 'Contribute', 'Form', 'ContributionPage'):
            
            # add custom resources
            $extension = end(explode(DIRECTORY_SEPARATOR, __DIR__));
            CRM_Core_Resources::singleton()->addStyleFile($extension, 'css/admin-form.css');
            CRM_Core_Resources::singleton()->addScriptFile($extension, 'js/admin-form.js');
                
            # if we're the 'Registration' (or 'Thankyou' in the case of contributions)
            # component of one of the above set of forms, apply form customizations 
            
            switch ($formName) {
                
                case 'CRM_Event_Form_ManageEvent_Registration':
                case 'CRM_Contribute_Form_ContributionPage_ThankYou':
                    
                    $id = $form->get('id');
                    
                    $config   = &CRM_Core_Config::singleton();
                    $template = &CRM_Core_Smarty::singleton();
        
                    # am doing away with this - todo: remove from template, then remove this
                    $template->assign('pdfEnabled', true);
                    
                    # get list of all receipt formats + current receipt format, if set         
                    $formats       = array(0 => '-- None --') + pdfreceipt_load_templates();
                    $entity_type   = (
                        $formName == 'CRM_Contribute_Form_ContributionPage_ThankYou' ?
                        'ContributionPage' : 'Event'
                    );
                    $selected_item = pdfreceipt_get_format($entity_type, $id);
                    
                    # add formats select box
                    $form->addElement('select', 'pdf_attach', ts('PDF Receipt Format'), 
                        $formats, array('onchange' => 'formatChange();')
                    );

                    # set defaults on select box
                    if ($selected_item)
                        $form->setDefaults(
                            array(
                                'pdf_attach' => $selected_item
                            )
                        );
                    
            }
             
            break;
                
    }

}

/**
 * Implementation of hook_civicrm_postProcess
 */
function pdfreceipt_civicrm_postProcess($formName, &$form) {

    # save pdf receipt format whenever ManageEvent Registration or 
    # ContributionPage Thankyou forms are saved
    switch ($formName) {
            
        case 'CRM_Event_Form_ManageEvent_Registration':
        case 'CRM_Contribute_Form_ContributionPage_ThankYou':

            # if pdf_attach not set, exit hook
            if (!isset($form->_submitValues['pdf_attach'])) 
                return;
            
            return pdfreceipt_save_format(
                $formName == 'CRM_Event_Form_ManageEvent_Registration' ? 'Event' : 'ContributionPage', 
                $form->get('id') ? $form->get('id') : $_POST['pdfreceipt_last_contributionpage_id'], 
                $form->_submitValues['pdf_attach']
            );

    }
    
}

/**
 * Implementation of hook_civicrm_config()
 */
function pdfreceipt_civicrm_config() {

    $ds          = DIRECTORY_SEPARATOR;
    $template    = &CRM_Core_Smarty::singleton();
    $templateDir = __DIR__ . $ds . 'templates' . $ds . pdfreceipt_getCRMVersion();
    
    # look for custom template dir named 'templates/' + <Civi version>. If it exists, add to template
    # override dirs
    if (is_dir($templateDir)) {
        if (is_array($template->template_dir))
            array_unshift($template->template_dir, $templateDir);
        else
            $template->template_dir = array($templateDir, $template->template_dir);
    } else {
        CRM_Core_Error::debug_log_message(ts(
            'PDF Receipt extension has no custom templates for this version of CiviCRM. ' . 
            'Have you checked the list of supported versions?'
        ), true);
    }
    
    # add our php directory to include path
    $include_path = __DIR__ . DIRECTORY_SEPARATOR . 'php' . PATH_SEPARATOR . get_include_path();
    set_include_path($include_path);

}

/*
 * Implementation of hook_civicrm_install()
 */
function pdfreceipt_civicrm_install() {
    
    # table for receipt format -> event page / contribution page mapping
    CRM_Core_DAO::executeQuery("
        CREATE TABLE IF NOT EXISTS `civicrm_pdf_receipt` (
          `entity` varchar(32) NOT NULL,
          `entity_id` int(11) unsigned NOT NULL,
          `template_class` varchar(255) NOT NULL,
          PRIMARY KEY (`entity`,`entity_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
    ");

}

/*
 * Implementation of hook_civicrm_uninstall()
 */
function pdfreceipt_civicrm_uninstall() {
    # delete table associating receipt formats with entities
    # CRM_Core_DAO::executeQuery('DROP TABLE civicrm_pdf_receipt');
}

/**
 * Implementation of hook_civicrm_xmlMenu()
 */
function pdfreceipt_civicrm_xmlMenu(&$files) {
    $files[] = __DIR__ . DIRECTORY_SEPARATOR . 'menu.xml';
}

/**
 * Various helpers to retrieve necessary info that doesn't get
 * passed into the 'offline' templates
 */

/**
 * Get the last contribution id for this contact
 * @param  $contact_id (int)
 * @return $participant_id (int) or null if not found
 */
function pdfreceipt_get_last_contributionID_for_contactID($contact_id) {
    
    return CRM_Core_DAO::singleValueQuery("
          SELECT id FROM civicrm_contribution
           WHERE contact_id = %1
        ORDER BY id DESC
           LIMIT 1
    ", array(
          1 => array($contact_id, 'Positive')
       )
    );

}

/**
 * Get the contribution page id of the last contribution for this contact
 * @param  $contact_id (int)
 * @return $contributionPageId (int) or null if not found
 */
function pdfreceipt_get_last_contributionPageId_for_contactID($contact_id) {
    
    if ($contribution_page_id = CRM_Core_DAO::singleValueQuery("
            SELECT ct.contribution_page_id
              FROM civicrm_contact c
        INNER JOIN civicrm_contribution ct ON ct.contact_id = c.id
             WHERE c.id = %1
          ORDER BY ct.receive_date DESC 
             LIMIT 1
    ", array(
          1 => array($contact_id, 'Positive')
       )
    ))
        return $contribution_page_id;

    # if we got to here, that didn't work - so next, see if the last contribution is for a membership,
    # and if so, get the membership_type_id
    if ($membership_type_id = CRM_Core_DAO::singleValueQuery("
            
            SELECT m.membership_type_id 
              FROM civicrm_contact c
        INNER JOIN civicrm_contribution ct ON ct.contact_id = c.id
        INNER JOIN civicrm_membership_payment mp ON mp.contribution_id = ct.id
        INNER JOIN civicrm_membership m ON m.id = mp.membership_id
             WHERE c.id = %1
          ORDER BY ct.receive_date DESC
           LIMIT 1 

    ", array(
          1 => array($contact_id, 'Positive')
       )

    )) {

        $dao = CRM_Core_DAO::executeQuery("
            SELECT entity_id, membership_types 
              FROM civicrm_membership_block 
             WHERE entity_table = 'civicrm_contribution_page'
        ");
        $membership_blocks = array();
        while ($dao->fetch())
            $membership_blocks[$dao->entity_id] 
                = array_keys(unserialize($dao->membership_types));

        foreach ($membership_blocks as $contribution_page_id => $membership_types)
            if (in_array($membership_type_id, $membership_types))
                return $contribution_page_id;

    }
    
    return null;

}

/**
 * Get the last participant for this contact
 * @param  $contact_id (int)
 * @return $participant_id (int) or null if not found
 */
function pdfreceipt_get_last_participantID_for_contactID($contact_id) {
    
    return CRM_Core_DAO::singleValueQuery("
          SELECT id FROM civicrm_participant
           WHERE contact_id = %1
        ORDER BY id DESC
           LIMIT 1
    ", array(
          1 => array($contact_id, 'Positive')
       )
    );

}

/**
 * Get the last participant for this contact
 * @param  $contact_id (int)
 * @return $participant_id (int) or null if not found
 */
function pdfreceipt_get_eventID_for_participantID($contact_id) {
    
    return CRM_Core_DAO::singleValueQuery("
          SELECT event_id FROM civicrm_participant
           WHERE id = %1
    ", array(
          1 => array($contact_id, 'Positive')
       )
    );

}

/**
 * Helper function to get Civi version as a floating point value
 * (allowing less than / greater than comparison)
 * @return float
 */
function pdfreceipt_getCRMVersion() {   
    $version = explode('.', ereg_replace('[^0-9\.]','', CRM_Utils_System::version()));
    return floatval($version[0] . '.' . $version[1]);
}

/**
 * Lookup receipt format for the specified entity
 * @param  entity    - entity type (string)
 * @param  entity_id - entity id (int)
 * @return string
 */
function pdfreceipt_get_format($entity, $entity_id) {
    
    return CRM_Core_DAO::singleValueQuery("
        SELECT template_class FROM civicrm_pdf_receipt
         WHERE entity = %1 AND entity_id = %2
    ", array(
          1 => array($entity, 'String'),
          2 => array($entity_id, 'Positive')
       )
    );

}

/**
 * Scan include paths for receipt format class files
 * @return array - an array of human-readable names keyed by class name
 */
function pdfreceipt_load_templates() {
    
    $include_paths = explode(PATH_SEPARATOR, get_include_path());
    $templates     = array();
    
    # iterate through include paths
    foreach ($include_paths as $include_path) {
        
        # check for the existence of a CRM/Contribute/Receipt dir under the current include path
        $path = implode(DIRECTORY_SEPARATOR, array($include_path, 'CRM', 'Contribute', 'Receipt'));
        
        if (!is_dir($path))
            continue;
        
        if (!$handle = opendir($path))
            continue;

        # iterate through files in dir and note the name of any php files
        while (false !== ($entry = readdir($handle))) {
            
            if ($entry != "." and $entry != ".." and substr($entry, -4) == '.php') {
                # 'not in array' check causes the first file found to be favoured where naming conflicts exist
                if (!in_array($entry, $templates)) {
                    # infer class name from filename and run 'name' method of that class 
                    $classname             = 'CRM_Contribute_Receipt_' . reset(explode('.', $entry));
                    $templates[$classname] = call_user_func(array($classname, 'name'));
                }
            
            }
        
        }

        closedir($handle);
               
    }
    
    return $templates;
    
}

/**
 * Lookup receipt format from the supplied entity type and id
 * @return true or trigger fatal error
 */
function pdfreceipt_save_format($entity, $entity_id, $template_class) {
    
    $missing_params = array();

    foreach (array('entity', 'entity_id', 'template_class') as $param)
        if (!$$param)
            $missing_params[] = $param;

    if ($missing_params)
        CRM_Core_Error::fatal(ts(
            "Missing required params when saving pdf: %1",
            array(
                1 => "'" . implode("', '", $missing_params) . "'"
            )
        ));

    CRM_Core_DAO::executeQuery("
        REPLACE INTO civicrm_pdf_receipt (entity, entity_id, template_class)
        VALUES (%1, %2, %3)
    ", array(
          1 => array($entity, 'String'),
          2 => array($entity_id, 'Positive'),
          3 => array($template_class, 'String')
       )
    );

    return true;

}
