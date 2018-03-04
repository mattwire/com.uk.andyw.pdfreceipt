<?php

/**
 * Class to get PDF Receipt templates
 */

use CRM_PDF_Receipt_ExtensionUtil as E;

class CRM_Utils_PDF_Receipt_Template {

  /**
   * Gets the template class and includes the relevant class file based on template name
   * Currently this is hardcoded to expect a template to exist in the extension/receipt_tpl directory
   *   (eg. a template called SOM would resolve to receipt_tpl/SOM/SOM.php)
   * @param $templateName
   *
   * @return string|bool
   */
  public static function getTemplateClass($templateName) {
    $template_class = 'PDF_Receipt_' . $templateName;
    $receipt_filename = E::path('receipt_tpl' . DIRECTORY_SEPARATOR . $templateName . DIRECTORY_SEPARATOR . $templateName . '.php');
    if (file_exists($receipt_filename)) {
      require_once($receipt_filename);
    }

    if (empty($template_class) || !class_exists($template_class)) {
      return FALSE;
    }
    return $template_class;
  }

  public static function getTemplatePath($templateName) {
    return E::path('receipt_tpl' . DIRECTORY_SEPARATOR . $templateName);
  }
}
