<?php

/**
 * PDF Receipt Extension for CiviCRM - 2012-14
 *
 * @package com.uk.andyw.pdfreceipt
 * @author  andyw
 *
 * Distributed under GPL v2
 * https://www.gnu.org/licenses/gpl-2.0.html
 */

use CRM_PDF_Receipt_ExtensionUtil as E;

class PDF_Receipt_SOM extends CRM_Contribute_Receipt {

  const vat_rate = 20;

  public function __construct() {

    $this->format = array(
      'name'         => $this->name(),
      'paper-size'   => 'A4',
      'metric'       => 'mm',
      'margin-top'   => 15,
      'margin-left'  => 15,
      'margin-right' => 15,
      'font-size'    => 10
    );

    parent::__construct();

    // use setDebug() to display container outlines
    //$this->setDebug();

  }

  public function name() {
    return ts('Society of Occupational Medicine');
  }

  public function printBodyTop() {

    $pdf = &$this->pdf;

    $invoice_no   = '';
    $invoice_date = '';

    // the invoice id seems to be different based on the method of payment
    // this seems to be blank to begin with so provide the correct default value
    $prefixValue = Civi::settings()->get('contribution_invoice_settings');
    $invoice_no = CRM_Utils_Array::value('invoice_prefix', $prefixValue) . "" . $this->contribution['id'];
    if (isset($this->contribution['invoice_id']) && ($this->contribution['invoice_id'] !== '' && $this->contribution['invoice_id'] == $invoice_no)) {
      $invoice_no = $this->contribution['invoice_id'];
    } else {
      $prefixValue = Civi::settings()->get('contribution_invoice_settings');
      $invoice_no = CRM_Utils_Array::value('invoice_prefix', $prefixValue) . "" . $this->contribution['id'];
      CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_Contribution', $this->contribution['id'], 'invoice_id', $invoice_no);
    }

    if (isset($this->contribution['receive_date']))
      $invoice_date = date('d-M-y', strtotime($this->contribution['receive_date']));
    elseif (isset($this->participant['participant_register_date']))
      $invoice_date = date('d-M-y', strtotime($this->participant['participant_register_date']));

    $pdf->SetXY($pdf->marginLeft, $pdf->GetY() + 10);
    $pdf->SetFont('sourcesanspro', '', 10, true);

    $pdf->SetFillColor(255, 255, 255);
    $pdf->MultiCell(59.5, 0, "Invoice No", '', 'L', true, 1);
    // $pdf->MultiCell(59.5, 0, "Invoice Date", '', 'L', true, 1);

    $pdf->SetFont('sourcesanspro', '', 10, true);

    $pdf->MultiCell(59.5, 0, $invoice_no, '', 'L', false, 0);
    // $pdf->MultiCell(59.5, 0, $invoice_date, '', 'L', false, 0);
    $pdf->MultiCell(59.5, 0, "", '', 'C', false, 1);

    $pdf->SetXY($pdf->marginLeft, $pdf->GetY() + 4);
    $pdf->SetFont('sourcesanspro', '', 15, true);
    $pdf->MultiCell(0, 0, ($this->contribution['contribution_status_id'] == 1 ? "RECEIPT" : "INVOICE"), 0, 'C');

  }

  public function printBody($params) {

    $pdf       = &$this->pdf;
    $lineItems = [];

    if (isset($this->contribution['id']))
      $lineItems = $this->getLineItemsByContributionID($this->contribution['id']);

    $is_paid      = ($this->contribution['contribution_status_id'] == 1);
    $total_amount = $this->contribution['total_amount'];

    $pdf->SetXY($pdf->marginLeft, $pdf->GetY() + 4.5);
    $pdf->SetFont('sourcesanspro', '', 8, true);

    $col_widths = array(
      'title'      => 119,
      'unit_price' => 14.625,
      'quantity'   => 14.625,
      'vat'        => 10,
      'net_value'  => 19.25
    );

    # helper to provide vertical padding (empty rows of a certain height)
    $padding = function($height) use ($pdf, $col_widths) {

      $pdf->MultiCell($col_widths['title'], $height, "", '', 'L', false, 0);
      $pdf->MultiCell($col_widths['unit_price'], $height, "", '', 'C', false, 0);
      $pdf->MultiCell($col_widths['quantity'], $height, "", '', 'C', false, 0);
      $pdf->MultiCell($col_widths['vat'], $height, "", '', 'C', false, 0);
      $pdf->MultiCell($col_widths['net_value'], $height, "", '', 'C', false, 1);

    };

    $pdf->SetFillColor(255, 255, 255);
    /*
    # begin main itemized purchases section
    $pdf->MultiCell($col_widths['title'], 0, ts("Item"), '', 'L', true, 0);
    $pdf->MultiCell($col_widths['unit_price'], 0, ts("Unit Price"), '', 'C', true, 0);
    $pdf->MultiCell($col_widths['quantity'], 0, ts("Quantity"), '', 'C', true, 0);
    $pdf->MultiCell($col_widths['vat'], 0, ts("VAT %"), '', 'C', true, 0);
    $pdf->MultiCell($col_widths['net_value'], 0, ts("Net Value"), '', 'C', true, 1);

    $padding(5);

    foreach ($lineItems as $lineItem) {

        $item_description = $lineItem['field_title']; # or $lineItem['label']
        $unit_price       = CRM_Utils_Money::format($this->netPrice($lineItem['unit_price']));
        $line_total       = CRM_Utils_Money::format($this->netPrice($lineItem['line_total']));
        $quantity         = $lineItem['qty'];

        $row_height       = $pdf->getStringHeight($col_widths['title'], $item_description);

        $pdf->SetFont('sourcesanspro', '', 9, true);
        $pdf->MultiCell($col_widths['title'], $row_height, $item_description, 'LR', 'L', false, 0);

        $pdf->SetFont('sourcesanspro', '', 9, true);
        $pdf->MultiCell($col_widths['unit_price'], $row_height, $unit_price, '', 'R', false, 0);
        $pdf->MultiCell($col_widths['quantity'], $row_height, $quantity, '', 'C', false, 0);
        $pdf->MultiCell($col_widths['vat'], $row_height, self::vat_rate . "%", '', 'C', false, 0);
        $pdf->MultiCell($col_widths['net_value'], $row_height, $line_total, '', 'R', false, 1);

    }
    */
    $padding(3);
    $pdf->SetFont('sourcesanspro', '', 10, true);

    $item_details = 'Item';
    if (!empty($params['description'])) {
      $item_details = $params['description'];
    }
    else {
      if (isset($this->contact['primary']['display_name'])) {
        $item_details = "Appraisal Fees: " . $this->contact['primary']['display_name'];
      }
    }

    $row_height = $pdf->getStringHeight($col_widths['title'], $item_details);

    $pdf->MultiCell($col_widths['title'], $row_height, $item_details , '', 'L', false, 0);

    $pdf->MultiCell($col_widths['unit_price'], $row_height, "", '', 'R', false, 0);
    $pdf->MultiCell($col_widths['quantity'], $row_height, "", '', 'C', false, 0);
    $pdf->MultiCell($col_widths['vat'], $row_height, "", '', 'C', false, 0);
    $pdf->MultiCell($col_widths['net_value'], $row_height, "", '', 'R', false, 1);

    $padding(6.5);

    $pdf->SetFont('sourcesanspro', '', 7.5, true);

    $payee_info = "Please make cheques payable to Society of Occupational Medicine or arrange BACS transfer with the details below:\n" .
      "To pay by bank transer (BACS): Lloyds Bank, PO Box 1000, BX1 1LT\n" .
      "Society of Occupational Medicine: 30-94-73 A/C: 17399260\n" .
      "Overseas Payments: IBAN: GB86LOYD30947317399260 BIC: LOYDGB21421\n" .
      "Cheques should be sent to SOM, 20 Little Britain, London, EC1A 7DH";

    $row_height = $pdf->getStringHeight($col_widths['title'], $payee_info) + 1;

    $pdf->MultiCell($col_widths['title'], $row_height, '', '', 'C', false, 0);
    $pdf->MultiCell($col_widths['unit_price'], $row_height, "", '', 'R', false, 0);
    $pdf->MultiCell($col_widths['quantity'], $row_height, "", '', 'C', false, 0);
    $pdf->MultiCell($col_widths['vat'], $row_height, "", '', 'C', false, 0);
    $pdf->MultiCell($col_widths['net_value'], $row_height, "", '', 'R', false, 1);

    # total amount section
    $label_width = $col_widths['unit_price'] + $col_widths['quantity'];
    $row_height  = 4.7;

    $total_net   = $this->netPrice($this->contribution['total_amount']);
    $total_vat   = $this->getVATFromGross($this->contribution['total_amount']);
    $total_gross = $this->contribution['total_amount'];

    $pdf->SetXY($pdf->GetX() + $col_widths['title'], $pdf->GetY());
    $pdf->SetFont('sourcesanspro', '', 9, true);
    // $pdf->MultiCell($label_width, $row_height, ts('TOTAL NET VALUE'), '', 'L', false, 0);
    $pdf->SetFont('sourcesanspro', '', 9, true);
    // $pdf->MultiCell($col_widths['vat'], $row_height, '£', '', 'R', false, 0);
    // $pdf->MultiCell($col_widths['net_value'], $row_height, $total_net, '', 'R', false, 1);

    $pdf->SetXY($pdf->GetX() + $col_widths['title'] + $label_width, $pdf->GetY());
    $pdf->MultiCell($col_widths['vat'] + $col_widths['net_value'], $row_height, '', '', 'R', false, 1);

    $pdf->SetXY($pdf->GetX() + $col_widths['title'], $pdf->GetY());
    $pdf->SetFont('sourcesanspro', '', 9, true);
    // $pdf->MultiCell($label_width, $row_height, ts('TOTAL VAT'), '', 'L', false, 0);
    $pdf->SetFont('sourcesanspro', '', 9, true);
    // $pdf->MultiCell($col_widths['vat'], $row_height, '£', '', 'R', false, 0);
    // $pdf->MultiCell($col_widths['net_value'], $row_height, $total_vat, '', 'R', false, 1);
    $pdf->MultiCell($col_widths['net_value'], '', '', '', 'R', false, 1);


    $pdf->SetXY($pdf->GetX() + $col_widths['title'], $pdf->GetY());
    $pdf->SetFont('sourcesanspro', '', 9, true);
    $pdf->MultiCell($label_width, $row_height, $is_paid ? ts('Amount Due') : ts('Total Amount'), '', 'L', false, 0);
    $pdf->SetFont('sourcesanspro', '', 9, true);
    $pdf->MultiCell($col_widths['vat'], $row_height, '£', '', 'R', false, 0);
    $pdf->MultiCell($col_widths['net_value'], $row_height, $total_gross, '', 'R', false, 1);

    $pdf->SetXY($pdf->GetX() + $col_widths['title'] + $label_width, $pdf->GetY());
    $pdf->MultiCell($col_widths['vat'] + $col_widths['net_value'], $row_height * 1.2, '', '', 'R', false, 1);

    $pdf->SetXY($pdf->GetX() + $col_widths['title'], $pdf->GetY());
    $pdf->SetFont('sourcesanspro', '', 9, true);
    $pdf->MultiCell($label_width, $row_height, ts('Amount Paid'), '', 'L', false, 0);
    $pdf->SetFont('sourcesanspro', '', 9, true);
    $pdf->MultiCell($col_widths['vat'], $row_height, '£', '', 'R', false, 0);
    $pdf->MultiCell($col_widths['net_value'], $row_height, $is_paid ? $total_gross : '0.00', '', 'R', false, 1);

    // $pdf->SetXY($pdf->GetX(), $pdf->GetY() - 16);
    $pdf->SetFont('sourcesanspro', '', 9, true);
    // $pdf->MultiCell(80, $row_height, ts('Thank you for your order'), '', 'C', false, 1);
    $pdf->SetFont('sourcesanspro', '', 8, true);


  }

  public function printBodyBottom() {

    $pdf = &$this->pdf;

    # payment info section
    $pdf->SetXY($pdf->marginLeft, $pdf->GetY() + 10.5);
    $pdf->SetFont('sourcesanspro', '', 7.5, true);

    $pdf->SetFillColor(255, 255, 255);
    // $pdf->MultiCell(177.5, 0,
    // "Please make cheques payable to Society of Occupational Medicine or arrange BACS transfer with the details below:\n
    // To pay by bank transer (BACS): Lloyds Bank, PO Box 1000, BX1 1LT\n
    // Society of Occupational Medicine: 30-94-73 A/C: 17399260\n
    // Overseas Payments:\n
    // IBAN: GB86LOYD30947317399260\n
    // BIC: LOYDGB21421\n
    // Cheques should be sent to Society of Occupational Medicine, 20 Little Britain, London, EC1A 7DH",
    //     '', 'C', true, 1
    // );

    # vat no + registered office info
    $pdf->SetXY($pdf->marginLeft, $pdf->GetY() + 10);
    $pdf->SetFont('sourcesanspro', '', 7, true);
    $pdf->MultiCell(177.5, '', 'Society of Occupational Medicine, 20 Little Britain, London, EC1A 7DH', '', 'C');
    $pdf->SetXY($pdf->marginLeft, $pdf->GetY() + 0.5);
    $pdf->MultiCell(177.5, '', 'Registered Charity England and Wales: 268555 Registered Charity Scotland: SC041935', '', 'C');


  }

  public function printHeaderTop() {
    $pdf = &$this->pdf;
    $invoice_title = '';

    if (isset($this->participant['source']))
      $invoice_title = $this->participant['source'];
    if (isset($this->event['title']))
      $invoice_title = $this->event['title'];
    elseif (isset($this->contribution['source']))
      $invoice_title = $this->contribution['source'];

    $font = E::path('fonts' . DIRECTORY_SEPARATOR . 'SourceSansPro-Bold.ttf');
    TCPDF_FONTS::addTTFfont($font, 'TrueType', 'ansi', 32);

    $this->printLogo();

    $pdf->SetFont('sourcesanspro', '', 10, true);
    $pdf->SetXY($pdf->marginLeft, $this->marginTop + 48);
    $pdf->MultiCell(0, 0, "Society of Occupational Medicine, 20 Little Britain, London, EC1A 7DH\nRegistered Charity England and Wales: 268555 Registered Charity Scotland: SC04193\nTelephone: 0203 478 1042", $this->border, 'C');

    $pdf->SetXY($pdf->marginLeft, $this->marginTop + 52);
    $pdf->SetFont('sourcesanspro', '', 18, true);

    $pdf->MultiCell(0, 0, $invoice_title, $this->border, 'C');
  }

  public function printHeaderBottom() {

    $pdf = &$this->pdf;

    $address_text = '';
    $address = $this->getAddress($this->contact['billing']['id'], 'primary');

    if (isset($this->contact['billing']['display_name']) and !empty($this->contact['billing']['display_name']))
      $address_text .= $this->contact['billing']['display_name'] . "\n";

    if ($this->contact['billing']['job_title'])
      $address_text .= $this->contact['billing']['job_title'] . "\n";

    if ($address)
      $address_text .= $this->buildAddress($address);

    $pdf->SetFont('sourcesanspro', '', 10, true);
    $pdf->SetXY($pdf->marginLeft, $pdf->GetY() + 4);
    $pdf->MultiCell(59.5, 0, "", '', 'L');
    $pdf->SetFont('sourcesanspro', '', 10, true);

    $date = date('d/m/Y');
    $pdf->MultiCell(175, 0, $date, '', 'R');
    $pdf->MultiCell(59.5, 0, $address_text, '', 'L');

  }

  public function printLogo() {
    $logo = CRM_Utils_PDF_Receipt_Template::getTemplatePath('SOM') . DIRECTORY_SEPARATOR . 'som-logo-rt.jpg';
    # if ever there was an argument for named arguments (heh, get it?) ..
    $this->pdf->Image(
      $logo,                         # file
      0,                             # x
      0,                             # y
      110,                           # w
      '',                            # h
      '',                            # type
      '',                            # link
      '',                            # align
      true,                          # resize
      300,                           # dpi
      'C',                           # palign
      false,                         # ismask
      false,                         # imgmask
      $this->border,                 # border
      true,                          # fitbox
      false,                         # hidden
      false,                         # fitonpage
      false,                         # alt
      array()                        # altimgs
    );
  }

  # calculate price minus vat
  protected function netPrice($amount) {
    $multiplier = 100 / self::vat_rate;
    $divisor    = $multiplier + 1;
    return sprintf('%.2f', $amount / $divisor * $multiplier);
  }

  # calculate vat from gross
  protected function getVATFromGross($amount) {
    $divisor = (100 / self::vat_rate) + 1;
    return sprintf('%.2f', $amount / $divisor);
  }

}

