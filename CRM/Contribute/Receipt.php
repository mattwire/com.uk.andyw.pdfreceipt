<?php

/**
 * PDF Receipt Extension for CiviCRM
 * @package com.uk.andyw.pdfreceipt
 * @author  andyw
 *
 * Distributed under the GNU Affero General Public License, version 3
 * http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_PDF_Receipt_ExtensionUtil as E;

abstract class CRM_Contribute_Receipt {

  # force extending classes to provide a human-readable name
  # ie: return ts('Name of template');
  abstract public function name();

  protected $format,
    $pdf,
    $image_dir,
    $ext_dir,
    $header_height,
    $debug,
    $border;

  protected $currentY;

  # entities we may need for building receipt
  protected $contact,
    $contribution,
    $event,
    $membership,
    $participant;


  public function __construct() {
    $config = &CRM_Core_Config::singleton();
    $this->image_dir = $config->customFileUploadDir;

    // set some basic defaults if $format not populated
    $this->format = array_merge(
      array(
        'paper-size'   => 'A4',
        'metric'       => 'mm',
        'margin-left'  => 15,
        'margin-top'   => 15,
        'margin-right' => 15,
        'font-size'    => 10
      ),
      $this->format
    );
  }

  # build address string from a passed in array
  protected function buildAddress($address) {
    $output = array();

    if (isset($address['country_id']) and !empty($address['country_id']))
      $address['country'] = CRM_Core_PseudoConstant::country($address['country_id']);

    if (isset($address['state_province_id']) and !empty($address['state_province_id']))
      $address['state_province'] = CRM_Core_PseudoConstant::stateProvince($address['state_province_id']);

    foreach (
      array(
        'street_address',
        'supplemental_address_1',
        'supplemental_address_2',
        'city',
        'postal_code',
        'country'
      ) as $field)
      if (isset($address[$field]) and !empty($address[$field]))
        $output[] = $address[$field];

    return implode("\n", $output);
  }

  final public function create($params) {
    # initialize tcpdf instance ..
    $pdf = &$this->pdf;
    $pdf = new CRM_Utils_PDF_Receipt($this->format, $this->format['metric']);

    $this->preview = isset($params['preview']) ? $params['preview'] : false;

    # set basic defaults
    $pdf->open();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();

    $font = E::path('fonts' . DIRECTORY_SEPARATOR . 'SourceSansPro-Regular.ttf');
    TCPDF_FONTS::addTTFfont($font, 'TrueType', 'ansi', 32);
    $font = E::path('fonts' . DIRECTORY_SEPARATOR . 'SourceSansPro-Bold.ttf');
    TCPDF_FONTS::addTTFfont($font, 'TrueType', 'ansi', 32);

    $pdf->SetFont('sourcesanspro', '', $this->fontSize, true);
    $pdf->SetFont('sourcesanspro', '', $this->fontSize, true);
    $pdf->SetGenerator($this, "generateReceipt");

    # now call 'initialize', allowing custom receipt templates to override
    # the above defaults if they wish to
    $this->initialize();

    // Get details for receipt
    // If so, retrieve all related ids
    if (isset($params['ids']['participant']) and !empty($params['ids']['participant'])) {

      $dao = CRM_Core_DAO::executeQuery("
                        SELECT ct.id AS contribution_id, bc.id AS billing_contact_id, pc.id AS primary_contact_id, e.id AS event_id
                          FROM civicrm_participant_payment pp
                    INNER JOIN civicrm_contribution ct ON ct.id = pp.contribution_id
                    INNER JOIN civicrm_contact bc ON ct.contact_id = bc.id
                    INNER JOIN civicrm_participant p ON p.id = pp.participant_id
                    INNER JOIN civicrm_contact pc ON pc.id = p.contact_id
                    INNER JOIN civicrm_event e ON e.id = p.event_id
                         WHERE pp.participant_id = %1
                ", array(
          1 => array($params['ids']['participant'], 'Integer')
        )
      );

      if ($dao->fetch()) {
        $params['ids']['contact'] = array(
          'billing' => $dao->billing_contact_id,
          'primary' => $dao->primary_contact_id
        );
        $params['ids']['contribution'] = $dao->contribution_id;
        $params['ids']['event']        = $dao->event_id;
      }

    } elseif (isset($params['ids']['membership']) and !empty($params['ids']['membership'])) {
      # online membership signup hook provides a membership_id but no contribution_id -
      # so lookup the contribution_id
      try {
        $membershipPaymentDetails = civicrm_api3('MembershipPayment', 'get', array(
          'return' => array(
            "contribution_id",
            "membership_id.membership_type_id.name",
            "membership_id.contact_id"
          ),
          'membership_id' => $params['ids']['membership'],
          'options' => array('limit' => 1, 'sort' => "id DESC"),
        ));
        if (!empty($membershipPaymentDetails['id'])) {
          $contributionId = $membershipPaymentDetails['values'][$membershipPaymentDetails['id']]['contribution_id'];
          $contactId = $membershipPaymentDetails['values'][$membershipPaymentDetails['id']]['membership_id.contact_id'];
          $params['description'] = 'Membership Fees: ' . $membershipPaymentDetails['values'][$membershipPaymentDetails['id']]['membership_id.membership_type_id.name'];
        }
        else {
          # ok, but that doesn't work for pay laters, as it creates a contribution and a membership, but doesn't
          # link them together with a MembershipPayment record - please just kill me now :(

          # dunno - get the last contribution for this contact or something?
          $contributionId = CRM_Core_DAO::singleValueQuery(
            "SELECT id FROM civicrm_contribution WHERE contact_id = %1 ORDER BY id DESC LIMIT 1",
            array(
              1 => array($params['ids']['contact'], 'Positive')
            )
          );
        }
      }
      catch (Exception $e) {
        $contributionId = NULL;
      }
      if (!$contributionId) {
        Civi::log()->error(ts(
          'Not enough data to construct invoice in %1::%2',
          array(
            1 => __CLASS__,
            2 => __METHOD__
          )
        ));
        return $params;
      }

      // We are now getting contact ID from membershipPayment api so don't need it to be passed in.
      if (empty($params['ids']['contact'])) {
        $params['ids']['contact'] = $contactId;
      }
      $params['ids']['contact'] = array(
        'billing' => $params['ids']['contact'],
        'primary' => $params['ids']['contact']
      );
      $params['ids']['contribution'] = $contributionId;
    }
    elseif (isset($params['ids']['contact']) and !empty($params['ids']['contact'])) {
      # get most recent contribution for contact, and associated membership id if applicable - this
      # is not an ideal way to look up the contribution, but a contact_id is all we get passed in the case of
      # some templates

      $dao = CRM_Core_DAO::executeQuery("
                        SELECT ct.id AS contribution_id, m.id AS membership_id
                          FROM civicrm_contact c
                    INNER JOIN civicrm_contribution ct ON ct.contact_id = c.id
                     LEFT JOIN civicrm_membership_payment mp ON mp.contribution_id = ct.id
                     LEFT JOIN civicrm_membership m ON m.id = mp.membership_id
                         WHERE c.id = %1
                      ORDER BY ct.receive_date DESC
                         LIMIT 1
                ", array(
          1 => array($params['ids']['contact'], 'Integer')
        )
      );
      if ($dao->fetch()) {
        $params['ids']['contact'] = array(
          'billing' => $params['ids']['contact'],
          'primary' => $params['ids']['contact']
        );
        $params['ids']['contribution'] = $dao->contribution_id;
        $params['ids']['membership']   = $dao->membership_id;
      }

    }
    else {
      Civi::log()->warning('PDFReceipt: No parameters found to generate receipt');
      return $params;
    }

    # load required entities - I think I may have been on drugs when I wrote this
    # todo: rewrite sensibly
    if (isset($params['ids'])) {
      foreach ($params['ids'] as $entity => $id) {
        if (method_exists($this, 'get' . ucfirst($entity))) {
          if (is_array($id)) {
            $array = array();
            foreach ($id as $subtype => $subtype_id)
              $array[$subtype] = call_user_func_array(array($this, 'get' . ucfirst($entity)), array($subtype_id));
            $this->$entity = $array;
          } else {
            $this->$entity = call_user_func_array(array($this, 'get' . ucfirst($entity)), array($id));
          }
        }
      }
    }

    watchdog('andyw', 'end params = <pre>' . print_r($params, true) . '</pre>');

    # run main invoice generation code
    $this->generateReceipt($params);

    # output to temp file or output inline data if we're in preview mode ..
    $pdf->Output($params['filename'], $this->preview ? 'I' : 'F');

    # delete temporary pdf file when script shuts down
    register_shutdown_function(function($pdf_file) { @unlink($pdf_file); }, $params['filename']);

    return $params;

  }

  final public function createContributionSet(&$params) {
  }

  final public function createParticipantSet(&$params) {
  }

  public function generateReceipt(&$params) {
    $this->printHeaderTop();
    $this->printHeaderBottom();
    $this->printBodyTop();
    $this->printBody($params);
    $this->printBodyBottom();
    $this->printFooter();
  }

  // Get (primary or billing) address for contact id
  protected function getAddress($contact_id, $type='primary') {
    $params = array(
      'version'    => '3',
      'contact_id' => $contact_id,
    );
    if ($type == 'primary')
      $params['is_primary'] = 1;
    elseif ($type == 'billing')
      $params['is_billing'] = 1;

    $result = civicrm_api('address', 'get', $params);
    if (!$result['is_error'])
      return reset($result['values']);

    return array();
  }

  protected function getBillingContactDetails() {
    # attempt to get billing address for the billing contact ..
    $billing_address = '';
    if ($address = $this->getAddress($this->contact['billing']['id'], 'billing')) {
      if (isset($address['street_address']) and !empty($address['street_address']) and
        isset($address['city']) and !empty($address['city'])) {
        $billing_address = $this->buildAddress($address);
      }
    }

    # if that turns out to be empty, get their primary address
    if (empty($billing_address)) {
      if ($address = $this->getAddress($this->contact['billing']['id'], 'primary'))
        $billing_address = $this->buildAddress($address);
    }

    # prepend either organization name or display name to contact details
    if ($this->contact['billing']['contact_type'] == 'Organization')
      return $this->contact['billing']['organization_name'] . "\n" . $billing_address;

    return $billing_address = $this->contact['billing']['display_name'] . "\n" . $billing_address;
  }

  // Get contact from contact id
  protected function getContact($id) {
    // if id of -1 supplied (pdf preview), return contact id 1
    if ($id == -1)
      $id = 1;

    $result = civicrm_api('contact', 'get',
      array(
        'version' => '3',
        'id'      => $id
      )
    );
    if (!$result['is_error'])
      return reset($result['values']);
    return array();

  }

  // Get contribution from contribution id
  protected function getContribution($id) {
    // if id of -1 supplied, return dummy contribution details (for previewing templates)
    if ($id == -1 or !$id)
      return array();

    $result = civicrm_api('contribution', 'get',
      array(
        'version' => '3',
        'id'      => $id
      )
    );
    if (!$result['is_error'])
      return reset($result['values']);
    return array();
  }

  // Get event from event id
  protected function getEvent($id) {
    $result = civicrm_api('event', 'get',
      array(
        'version' => '3',
        'id'      => $id
      )
    );
    if (!$result['is_error'])
      return reset($result['values']);
    return array();

  }

  protected function getLineItems($entity_type, $entity_id) {
    return CRM_Price_BAO_LineItem::getLineItems($entity_id, $entity_type);
  }

  protected function getLineItemsByContributionID($contribution_id) {
    try {
      $result = civicrm_api3('LineItem', 'get', [
        'contribution_id' => $contribution_id
      ]);
    } catch (CiviCRM_API3_Exception $e) {
      Civi::log()->error('Line item get failed in ' . __CLASS__ . '::' . __METHOD__ . '(): ' . $e->getMessage());
    }

    if (isset($result['values']))
      return $result['values'];

    return [];
  }

  protected function getLogo() {
    $ds = DIRECTORY_SEPARATOR;

    // Check if a logo.xxx file exists in files/civicrm/custom, or default
    // to the Civi logo included with the extension
    switch (true) {
      case file_exists($file = $this->image_dir . $ds . 'logo.jpg'):
      case file_exists($file = $this->image_dir . $ds . 'logo.gif'):
      case file_exists($file = $this->image_dir . $ds . 'logo.png'):
      case file_exists($file = $this->ext_dir . $ds . 'images' . $ds . 'civicrm-logo.png'):
        break;
      default:
        return new Stdclass;
    }

    $meta = @getimagesize($file) or $meta = array(0, 0);

    return (object)array(
      'file'   => $file,
      'width'  => array_shift($meta),
      'height' => array_shift($meta)
    );

  }

  // Get membership from membership id
  protected function getMembership($id) {
    if ($id == -1)
      return array(

      );

    $result = civicrm_api('membership', 'get',
      array(
        'version' => '3',
        'id'      => $id
      )
    );
    if (!$result['is_error'])
      return reset($result['values']);
    return array();

  }

  // Get organization domain details
  protected function getOrganizationDetails() {
    $result = civicrm_api('domain', 'get',
      array(
        'version' => '3',
        'id'      => CIVICRM_DOMAIN_ID
      )
    );
    if (!$result['is_error'])
      return reset($result['values']);
    return array();
  }

  // Get participant from participant id
  protected function getParticipant($id) {
    if ($id == -1)
      return array(

      );

    $result = civicrm_api('participant', 'get',
      array(
        'version' => '3',
        'id'      => $id
      )
    );
    if (!$result['is_error'])
      return reset($result['values']);
    return array();

  }

  // Get the primary address of the primary contact
  protected function getPrimaryContactDetails() {
    if ($address = $this->getAddress($this->contact['primary']['id'], 'primary'))
      $primary_address = $this->buildAddress($address);

    // Prepend either organization name or display name to contact details
    if ($this->contact['primary']['contact_type'] == 'Organization')
      return $this->contact['primary']['organization_name'] . "\n" . $primary_address;

    return $primary_address = $this->contact['primary']['display_name'] . "\n" . $primary_address;
  }

  public function initialize() {
    // This method is intentionally left empty so child classes can override it if necessary.
    // Do not remove it.
  }

  // page callback for receipt preview - output pdf data inline
  public function preview() {
    $templateName = CRM_Utils_Request::retrieve('tpl_name', 'String');
    $participantId = CRM_Utils_Request::retrieve('pid', 'Integer');
    $membershipId = CRM_Utils_Request::retrieve('mid', 'Integer');
    $contactId = CRM_Utils_Request::retrieve('cid', 'Integer');

    $template_class = CRM_Utils_PDF_Receipt_Template::getTemplateClass($templateName);
    if (!$template_class) {
      CRM_Core_Error::statusBounce('You must specify a valid "tpl_name" as parameter');
    }
    $receipt = new $template_class;

    $receiptParams = array(
      'filename' => 'receipt.pdf',
      'preview'  => true
    );

    if (!empty($participantId)) {
      $receiptParams['ids']['participant'] = $participantId;
    }
    if (!empty($membershipId)) {
      $receiptParams['ids']['membership'] = $membershipId;
    }
    if (!empty($contactId)) {
      $receiptParams['ids']['contact'] = $contactId;
    }

    $receipt->create($receiptParams);

    CRM_Utils_System::civiExit();
  }

  // 'Print' methods to generate various portions of a receipt / invoice
  // These are the primary methods to override when creating custom receipt templates
  public function printBackground() {
  }

  public function printBodyBottom() {
  }

  public function printBodyTop() {
    $pdf = &$this->pdf;

    // Print billing contact details
    $billingContactDetails = $this->getBillingContactDetails();
    $label                 = ts('Sold to');
    $address_height_1      = $pdf->getStringHeight(0, $label) + $pdf->getStringHeight(0, $billingContactDetails);

    $pdf->SetXY($pdf->marginLeft, $this->currentY + 4);
    $pdf->SetFont('sourcesanspro', '', $pdf->fontSize, true);
    $pdf->Cell(50, 0, $label . ':', $this->border, 1);
    $pdf->SetFont('sourcesanspro', '', $pdf->fontSize, true);
    $pdf->MultiCell(50, 0, $billingContactDetails, $this->border, 'L', false, 1, '', '', true, 0, false, true, 0, 'M');

    // Print primary contact details
    $primaryContactDetails = $this->getPrimaryContactDetails();
    $label                 = ts('Ship to');
    $address_height_2      = $pdf->getStringHeight(0, $label) + $pdf->getStringHeight(0, $primaryContactDetails);
    $offsetX               = $pdf->getPageWidth() - $pdf->marginRight - 50;

    $pdf->SetXY($offsetX, $this->currentY + 4);
    $pdf->SetFont('sourcesanspro', '', $pdf->fontSize, true);
    $pdf->Cell(0, 0, $label . ':', $this->border, 1);
    $pdf->SetFont('sourcesanspro', '', $pdf->fontSize, true);
    $pdf->SetXY($offsetX, $this->currentY + 4 + $pdf->getStringHeight(0, $label));
    $pdf->MultiCell(0, 0, $primaryContactDetails, $this->border, 'L', false, 1, '', '', true, 0, false, true, 0, 'M');

    // Set currentY to the bottom of the longest address
    $this->currentY += ($address_height_1 > $address_height_2 ? $address_height_1 : $address_height_2) + 4;
  }

  public function printBody($params) {
    $pdf               = &$this->pdf;
    $td_border_full    = 'text-align:center; border-right:1px solid black; border-bottom:1px solid black;';
    $td_border_partial = 'border-right:1px solid black;';
    $table_style       = 'border-left:1px solid black; border-top:1px solid black; margin:0; width:100%;';

    // temp ..
    $footer_text = "If making a transfer, we request a remittance advice that quotes the above invoice number.<br />" .
      "All cheques must be in GB pounds. Please make cheques payable to 'Circle Interactive'.<br />" .
      "Transfers can be made to Pretend Bank PLC. Sort code: 00-00-00 Account no. 0000000";

    // Construct upper table
    ob_start();

    ?>

    <table cellpadding="2" style="<?php echo $table_style; ?>">
      <tr>
        <td colspan="2" style="height:28px; width:140px; <?php echo $td_border_full; ?>"></td>
        <td valign="middle" style="width:70px; <?php echo $td_border_full; ?>"></td>
        <td rowspan="2" style="width:150px; font-size:42px; <?php echo $td_border_full; ?>"><strong>Terms</strong><br />Strictly <u>30</u> Days</td>
        <td style="width:150px; <?php echo $td_border_full; ?>"></td>
      </tr>
      <tr>
        <td colspan="2" style="height:15px; <?php echo $td_border_full; ?>"><?php echo $purchase_order_no; ?></td>
        <td style="<?php echo $td_border_full; ?>"><?php echo isset($dd_date) ? $dd_date : date('d/m/Y', strtotime($this->participant['participant_register_date'])); ?></td>
        <td style="<?php echo $td_border_full; ?>"><?php echo isset($dd_date) ? $dd_date : date('d/m/Y', strtotime($this->participant['participant_register_date'])); ?></td>
      </tr>
    </table>

    <?php

    $html_upper = ob_get_clean();

    # print upper table
    $tableBeginY = $this->currentY + 10;
    $pdf->setXY($pdf->marginLeft - 1.0, $tableBeginY);
    $pdf->WriteHTMLCell(0, '', '', '', $html_upper, 0/*$this->border*/, 1, false, true, '', false);

    $main_top = $pdf->GetY();

    $pdf->setXY(24, $tableBeginY + 1);
    $pdf->Cell(10, 8, 'Purchase Order No');
    $pdf->setXY(67.5, $tableBeginY + 1);
    $pdf->Cell(25, 8, 'Order Date');
    $pdf->setXY(158, $tableBeginY + 1);
    $pdf->Cell(30, 8, 'Invoice Date');

    # construct main table
    $lineItems    = $this->getLineItems('participant', $this->participant['id']);
    $is_paid      = ($this->contribution['contribution_status_id'] == 1);
    $total_amount = $this->contribution['total_amount'];

    if ($this->contact['primary']['contact_type'] == 'Organization')
      $primary_contact_name = $this->contact['primary']['organization_name'];
    else
      $primary_contact_name = $this->contact['primary']['display_name'];

    ob_start();

    ?>
    <table cellpadding="2" style="<?php echo $table_style; ?>">
      <tr>
        <td style="height:26px; width:70px; <?php echo $td_border_full; ?>">Qty<br />Ordered</td>
        <td colspan="3" style="width:276px; <?php echo $td_border_full; ?>"></td>
        <td style="width:82px; <?php echo $td_border_full; ?>">Unit<br />Price</td>
        <td style="width:82px; <?php echo $td_border_full; ?>">Extended<br />Price</td>
      </tr>

      <?php
      foreach ($lineItems as $item) {
        ?>
        <tr>
          <td style="height:3px; <?php echo $td_border_partial ?>"></td>
          <td colspan="3" style="<?php echo $td_border_partial ?>"></td>
          <td style="<?php echo $td_border_partial ?>"></td>
          <td style="<?php echo $td_border_partial ?>"></td>
        </tr>
        <tr>
          <td style="height:10px; text-align:center; <?php echo $td_border_partial ?>"><?php echo $item['qty']; ?></td>
          <td colspan="3" style="<?php echo $td_border_partial ?>"><?php echo '&nbsp;' . $item['field_title'] . ' - ' . $item['label'] . '<br />' . $primary_contact_name; ?></td>
          <td style="text-align:center; <?php echo $td_border_partial ?>"><?php echo CRM_Utils_Money::format($item['unit_price']); ?></td>
          <td style="text-align:center; <?php echo $td_border_partial ?>"><?php echo CRM_Utils_Money::format($item['line_total']); ?></td>
        </tr>
        <?php
      }
      ?>
      <tr>
        <td style="height:160px; border-bottom:1px solid black; <?php echo $td_border_partial ?>"></td>
        <td colspan="3" style="border-bottom:1px solid black; <?php echo $td_border_partial ?>"></td>
        <td style="border-bottom:1px solid black; <?php echo $td_border_partial ?>"></td>
        <td style="border-bottom:1px solid black; <?php echo $td_border_partial ?>"></td>
      </tr>
      <tr>
        <td style="width:70px; <?php echo $td_border_full; ?>">Line Item<br />Total</td>
        <td style="width:70px; <?php echo $td_border_full; ?>"></td>
        <td style="width:70px; <?php echo $td_border_full; ?>"></td>
        <td style="width:136px; <?php echo $td_border_full; ?>"></td>
        <td style="width:82px; <?php echo $td_border_full; ?>">Amount<br />Received</td>
        <td style="width:82px; <?php echo $td_border_full; ?>">Amount<br />Due</td>
      </tr>
      <tr>
        <td style="width:70px; <?php echo $td_border_full; ?>"><?php echo CRM_Utils_Money::format($total_amount); ?></td>
        <td style="<?php echo $td_border_full; ?>"></td>
        <td style="<?php echo $td_border_full; ?>"></td>
        <td style="<?php echo $td_border_full; ?>"><?php echo CRM_Utils_Money::format($total_amount); ?></td>
        <td style="width:82px; <?php echo $td_border_full; ?>"><?php if ($is_paid)  echo CRM_Utils_Money::format($total_amount); else echo '0.00'; ?></td>
        <td style="width:82px; <?php echo $td_border_full; ?>"><?php if (!$is_paid) echo CRM_Utils_Money::format($total_amount); else echo '0.00'; ?></td>
      </tr>
    </table>

    <?php
    $html_main = ob_get_clean();

    //$pdf->setXY($pdf->marginLeft, /*$main_top + 5*/ 95);
    $pdf->WriteHTMLCell(0, 100, $pdf->marginLeft - 1.0, $tableBeginY + 20, $html_main, 0, 1, false, true, '', false);

    $footer_pos = array(
      'x' => $pdf->getX(),
      'y' => $pdf->getY()
    );

    $pdf->setXY($pdf->marginLeft, $tableBeginY + 125);
    $pdf->WriteHTMLCell(0, 0, $pdf->marginLeft - 1.0, $tableBeginY + 125, $footer_text, 0, 1);

  }

  public function printFooter() {
  }

  public function printHeaderBottom() {
    $pdf = &$this->pdf;

    // Print document type, ie: RECEIPT or INVOICE
    $pdf->SetFontSize($pdf->fontSize * 1.8);

    $label        = $this->contribution['is_pay_later'] ? ts('INVOICE') : ts('RECEIPT');
    $ypos         = $pdf->getImageRBY() + 4;
    $label_height = $pdf->getStringHeight($label);

    $pdf->SetXY($pdf->marginLeft + 90, $ypos);
    $pdf->MultiCell(0, 0, $label, $this->border, 'R');

    //$ypos += $label_height;

    // Print invoice number
    $pdf->SetXY($pdf->marginLeft + 90, $ypos + $label_height);
    $pdf->SetFontSize($pdf->fontSize);

    $label         = ts('Invoice no') . ': ' . $this->contribution['invoice_id'];
    $label_height += $pdf->getStringHeight($label);

    $pdf->MultiCell(0, 0, $label, $this->border, 'R');

    // Update currentY to ypos of whichever column is taller
    if (($ypos + $label_height) > ($pdf->marginTop + $this->header_height))
      $this->currentY = $ypos + $label_height;
    else
      $this->currentY = $pdf->marginTop + $this->header_height;
  }

  public function printHeaderTop() {
    // Print organization details and logo
    $this->printOrganizationDetails();
    $this->printLogo();

  }

  public function printLogo() {
    $pdf          = &$this->pdf;
    $logo         = $this->getLogo();
    $scale        = $pdf->getImageScale();
    $height       = $this->header_height;

    $pdf->Image(
      $logo->file,     // file
      '',              // x
      $pdf->marginTop, // y
      65,              // w
      $height,         // h
      '',              // type
      '',              // link
      '',              // align
      true,            // resize
      300,             // dpi
      'R',             // palign
      false,           // ismask
      false,           // imgmask
      $this->border,   // border
      true,            // fitbox
      false,           // hidden
      false,           // fitonpage
      false,           // alt
      array()          // altimgs
    );
  }

  public function printOrganizationDetails() {
    $pdf          = &$this->pdf;
    $organization = $this->getOrganizationDetails();
    $address      = &$organization['domain_address'];
    $details      = array();

    // Assemble organization details
    $details  = $organization['name'];
    $details .= "\n" . $this->buildAddress($address);

    // get the height when printed - this determines max height of the logo image
    $this->header_height = $pdf->getStringHeight(0, $details);

    // Print the domain organization name and address
    $pdf->MultiCell(50, 20, $details, $this->border, 'L', false, 1, '', '', true, 0, false, true, 0, 'M');

  }

  protected function setDebug($debug = true) {
    if (!$debug) {
      $this->debug  = false;
      $this->border = 0;
    } else {
      $this->debug  = true;
      $this->border = "LTRB";
    }
  }

};

?>
