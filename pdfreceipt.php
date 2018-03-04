<?php

/*----------------------------------------------------------------------+
 | CiviCRM version 4.7                                                  |
+-----------------------------------------------------------------------+
 | PDF Receipt Extension for CiviCRM - Circle Interactive (c) 2004-2017 |
+-----------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +-------------------------------------------------------------------*/

require_once 'pdfreceipt.civix.php';
use CRM_PDF_Receipt_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function pdfreceipt_civicrm_config(&$config) {
  _pdfreceipt_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function pdfreceipt_civicrm_xmlMenu(&$files) {
  _pdfreceipt_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function pdfreceipt_civicrm_install() {
  // table for receipt format -> event page / contribution page mapping
  CRM_Core_DAO::executeQuery("
        CREATE TABLE IF NOT EXISTS `civicrm_pdf_receipt` (
          `entity` varchar(32) NOT NULL,
          `entity_id` int(11) unsigned NOT NULL,
          `template_class` varchar(255) NOT NULL,
          PRIMARY KEY (`entity`,`entity_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
    ");
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function pdfreceipt_civicrm_uninstall() {
  _pdfreceipt_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function pdfreceipt_civicrm_enable() {
  _pdfreceipt_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function pdfreceipt_civicrm_disable() {
  _pdfreceipt_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function pdfreceipt_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _pdfreceipt_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function pdfreceipt_civicrm_managed(&$entities) {
  _pdfreceipt_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function pdfreceipt_civicrm_caseTypes(&$caseTypes) {
  _pdfreceipt_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function pdfreceipt_civicrm_angularModules(&$angularModules) {
  _pdfreceipt_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function pdfreceipt_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _pdfreceipt_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implementation of hook_civicrm_alterMailParams
 */
function pdfreceipt_civicrm_alterMailParams(&$params) {
  static $sillyFlag = 0;
  if (!$sillyFlag++) {
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

        // if no receipt format selected, exit the hook
        if (!$template_class = pdfreceipt_get_format('Event', $params['tplParams']['event']['id'])) {
          return;
        }

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

        # fix for SOM - remove the attachment that Civi is adding
        $params['attachments'] = [];

        # attach the new attachment ..
        $params['attachments'][] = array(
          'fullPath'  => $pdf['filename'],
          'mime_type' => 'application/pdf',
          'cleanName' => 'receipt.pdf'
        );

        break;

      case $params['groupName'] == 'msg_tpl_workflow_contribution' and $params['valueName'] == 'contribution_offline_receipt':
      case $params['groupName'] == 'msg_tpl_workflow_membership'   and $params['valueName'] == 'membership_offline_receipt':

        // if no receipt format selected, exit the hook
        // FIXME: Where do we get an entity_id/format from?
        if (!$template_class = pdfreceipt_get_format('Contribution', NULL)) {
          return;
        }
        $receipt = new $template_class;

        $ids = array('contact' => $params['contactId']);

        $ids['contribution'] = $params['contributionId'];
        try {
          $ids['membership'] = civicrm_api3('MembershipPayment', 'getvalue', array(
            'sequential'  =>  1,
            'return'      =>  'membership_id',
            'contribution_id' =>  $ids['contribution'],
          ));
        } catch (CiviCRM_API3_Exception $e) {
        }

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

        CRM_Core_Error::debug_log_message('PDFReceipt exiting: ' . print_r($params, true));

        break;

      case $params['groupName'] == 'msg_tpl_workflow_membership'   and $params['valueName'] == 'membership_online_receipt':
      case $params['groupName'] == 'msg_tpl_workflow_contribution' and $params['valueName'] == 'contribution_online_receipt':

        // if no receipt format selected, exit the hook_civicrm_config
        if (!$template_class = pdfreceipt_get_format('ContributionPage', $params['tplParams']['contributionPageId'])) {
          return;
        }

        // if status not completed, suppress the receipt and let Civi send its invoice.
        if (isset($params['tplParams']['contributionID'])) {
          try {
            $status_id = civicrm_api3('contribution', 'getvalue', [
              'id'     => $params['tplParams']['contributionID'],
              'return' => "contribution_status_id"
            ]);
          }
          catch (CiviCRM_API3_Exception $e) {
            $status_id = NULL;
          }
          if ($status_id !== CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed')) {
            return;
          }
        }

        // if a class name was returned, instantiate it
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
 * Save pdf receipt format whenever ManageEvent Registration or
 * ContributionPage Thankyou forms are saved
 *
 * @param $formName
 * @param $form
 */
function pdfreceipt_civicrm_postProcess($formName, &$form) {
  switch ($formName) {
    case 'CRM_Event_Form_ManageEvent_Registration':
    case 'CRM_Contribute_Form_ContributionPage_ThankYou':

      // if pdf_attach not set, exit hook
      if (!isset($form->_submitValues['pdf_attach']))
        return;

      pdfreceipt_save_format(
        $formName == 'CRM_Event_Form_ManageEvent_Registration' ? 'Event' : 'ContributionPage',
        $form->get('id') ? $form->get('id') : $_POST['pdfreceipt_last_contributionpage_id'],
        $form->_submitValues['pdf_attach']
      );
  }
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
 * Return the receipt format for the entity
 * TODO: Remove hardcoded class name
 * @param string $entity
 * @param int $entity_id
 * @param string $templateName
 *
 * @return bool|string
 */
function pdfreceipt_get_format($entity, $entity_id, $templateName = NULL) {
  if ($templateName === NULL) {
    // TODO: This is a temporary fix, this should be looked up based on entity_id and entity
    $templateName = 'SOM';
  }
  $templateClass = CRM_Utils_PDF_Receipt_Template::getTemplateClass($templateName);

  if (!$templateClass) {
    Civi::log()->error('PDFReceipt: Template ' . $templateName . ' not found.');
    return FALSE;
  }
  return $templateClass;
  /*
  return CRM_Core_DAO::singleValueQuery("
      SELECT template_class FROM civicrm_pdf_receipt
       WHERE entity = %1 AND entity_id = %2
  ", array(
        1 => array($entity, 'String'),
        2 => array($entity_id, 'Positive')
     )
  );
  */
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
 *
 * @param $entity
 * @param $entity_id
 * @param $template_class
 *
 * @return bool
 */
function pdfreceipt_save_format($entity, $entity_id, $template_class) {
  $missing_params = array();

  foreach (array('entity', 'entity_id', 'template_class') as $param)
    if (!$$param)
      $missing_params[] = $param;

  if ($missing_params)
    return FALSE; // fail silently for now, as this is causing problems on the Thankyou Page configuration tab
  /*
  CRM_Core_Error::fatal(ts(
      "Missing required params when saving pdf: %1",
      array(
          1 => "'" . implode("', '", $missing_params) . "'"
      )
  ));
  */

  CRM_Core_DAO::executeQuery("
        REPLACE INTO civicrm_pdf_receipt (entity, entity_id, template_class)
        VALUES (%1, %2, %3)
    ", array(
      1 => array($entity, 'String'),
      2 => array($entity_id, 'Positive'),
      3 => array($template_class, 'String')
    )
  );

  return TRUE;
}
