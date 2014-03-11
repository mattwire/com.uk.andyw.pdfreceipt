<?php

/**
 * PDF Receipt Extension for CiviCRM
 *
 * @package com.uk.andyw.pdfreceipt
 * @author  andyw
 *
 * Distributed under GPL v2
 * https://www.gnu.org/licenses/gpl-2.0.html
 */

class CRM_Contribute_Receipt_HPMA extends CRM_Contribute_Receipt {
    
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
        return ts('HPMA');
    }
    
    public function printBodyTop() {
        
        $pdf = &$this->pdf;

        $invoice_no   = '';
        $invoice_date = '';

        if (isset($this->contribution['invoice_id']))
            $invoice_no = $this->contribution['invoice_id'];

        if (isset($this->contribution['receive_date']))
            $invoice_date = date('d-M-y', strtotime($this->contribution['receive_date']));
        elseif (isset($this->participant['participant_register_date']))
            $invoice_date = date('d-M-y', strtotime($this->participant['participant_register_date']));

        $pdf->SetXY($pdf->marginLeft, $pdf->GetY() + 10);
        $pdf->SetFont('sourcesansprosemib', '', 10, true);

        $pdf->SetFillColor(200, 200, 200);
        $pdf->MultiCell(59.5, 0, "Invoice No", 'LTR', 'C', true, 0);
        $pdf->MultiCell(59.5, 0, "Invoice Date", 'TR', 'C', true, 0);
        $pdf->MultiCell(59.5, 0, "PO Number", 'TR', 'C', true, 1);

        $pdf->SetFont('sourcesanspro', '', 10, true);

        $pdf->MultiCell(59.5, 0, $invoice_no, 'LTB', 'C', false, 0);
        $pdf->MultiCell(59.5, 0, $invoice_date, 'TB', 'C', false, 0);
        $pdf->MultiCell(59.5, 0, "", 'TBR', 'C', false, 1);

        $pdf->SetXY($pdf->marginLeft, $pdf->GetY() + 4);
        $pdf->SetFont('sourcesansprosemib', '', 11, true);
        $pdf->MultiCell(0, 0, "If you have any queries relating to this invoice you should call the accounts team on\n0208 334 4530, or e-mail admin@hpma.org.uk", 0, 'C');

    }

    public function printBody() {

        $pdf = &$this->pdf;

        if (isset($this->participant['id']))
            $lineItems = $this->getLineItems('participant', $this->participant['id']);
        else
            $lineItems = $this->getLineItems('contribution', $this->contribution['id']);

        $is_paid      = ($this->contribution['contribution_status_id'] == 1);
        $total_amount = $this->contribution['total_amount'];

        $pdf->SetXY($pdf->marginLeft, $pdf->GetY() + 4.5);
        $pdf->SetFont('sourcesansprosemib', '', 8, true);

        $col_widths = array(
            'title'      => 119,
            'unit_price' => 14.625,
            'quantity'   => 14.625,
            'vat'        => 10,
            'net_value'  => 19.25
        );

        # helper to provide vertical padding (empty rows of a certain height)
        $padding = function($height) use ($pdf, $col_widths) {
            
            $pdf->MultiCell($col_widths['title'], $height, "", 'LR', 'L', false, 0);
            $pdf->MultiCell($col_widths['unit_price'], $height, "", 'R', 'C', false, 0);
            $pdf->MultiCell($col_widths['quantity'], $height, "", 'R', 'C', false, 0);
            $pdf->MultiCell($col_widths['vat'], $height, "", 'R', 'C', false, 0);  
            $pdf->MultiCell($col_widths['net_value'], $height, "", 'R', 'C', false, 1); 

        };

        $pdf->SetFillColor(205, 205, 205);
        
        # begin main itemized purchases section
        $pdf->MultiCell($col_widths['title'], 0, ts("Title"), 'LTRB', 'L', true, 0);
        $pdf->MultiCell($col_widths['unit_price'], 0, ts("Unit Price"), 'TRB', 'C', true, 0);
        $pdf->MultiCell($col_widths['quantity'], 0, ts("Quantity"), 'TRB', 'C', true, 0);
        $pdf->MultiCell($col_widths['vat'], 0, ts("VAT %"), 'TRB', 'C', true, 0);  
        $pdf->MultiCell($col_widths['net_value'], 0, ts("Net Value"), 'TRB', 'C', true, 1);

        $padding(5);

        foreach ($lineItems as $lineItem) {

            $item_description = $lineItem['field_title'];
            if (isset($this->membership))
                $item_description .= ' - ' . $lineItem['label'];

            $item_description = html_entity_decode($item_description);

            $unit_price       = CRM_Utils_Money::format($lineItem['unit_price']);
            $line_total       = CRM_Utils_Money::format($lineItem['line_total']);
            $quantity         = $lineItem['qty'];

            $row_height       = $pdf->getStringHeight($col_widths['title'], $item_description);

            $pdf->SetFont('sourcesansprosemib', '', 9, true);        
            $pdf->MultiCell($col_widths['title'], $row_height, $item_description , 'LR', 'L', false, 0);
    
            $pdf->SetFont('sourcesanspro', '', 9, true);
            $pdf->MultiCell($col_widths['unit_price'], $row_height, $unit_price, 'R', 'R', false, 0);
            $pdf->MultiCell($col_widths['quantity'], $row_height, $quantity, 'R', 'C', false, 0);
            $pdf->MultiCell($col_widths['vat'], $row_height, "0%", 'R', 'C', false, 0);
            $pdf->MultiCell($col_widths['net_value'], $row_height, $line_total, 'R', 'R', false, 1);      

        }
        
        if (isset($this->membership)) {
            
            watchdog('andyw', 'membership = <pre>' . print_r($this->membership, true) . '</pre>');

            $pdf->SetFont('sourcesanspro', '', 8, true);
            
            $item_details = '';
            $item_details = "From " . date('d/m/Y', strtotime($this->membership['start_date'])) . 
                            " to " . date('d/m/Y', strtotime($this->membership['end_date']));

            $row_height = $pdf->getStringHeight($col_widths['title'], $item_details);
            
            $pdf->MultiCell($col_widths['title'], $row_height, $item_details , 'LR', 'L', false, 0);
            
            $pdf->MultiCell($col_widths['unit_price'], $row_height, "", 'R', 'R', false, 0);
            $pdf->MultiCell($col_widths['quantity'], $row_height, "", 'R', 'C', false, 0);
            $pdf->MultiCell($col_widths['vat'], $row_height, "", 'R', 'C', false, 0);
            $pdf->MultiCell($col_widths['net_value'], $row_height, "", 'R', 'R', false, 1);   

        }

        $padding(6.5);
        $pdf->SetFont('sourcesanspro', '', 7.5, true);

        $payee_info = "Please make cheques payable to HPMA or arrange your BACS transfer with the following details:\n" . 
                      "Sterling Payments S/C: 30-96-00 A/C: 03380534 Bank: Lloyds TSB, Quote: HPMA13\n" . 
                      "Cheques should be sent to HPMA, The Old Candlemakers, West Street, Lewes, BN7 2NZ";

        $row_height = $pdf->getStringHeight($col_widths['title'], $payee_info) + 1;
        
        $pdf->MultiCell($col_widths['title'], $row_height, $payee_info, 'LRB', 'C', false, 0);
        $pdf->MultiCell($col_widths['unit_price'], $row_height, "", 'RB', 'R', false, 0);
        $pdf->MultiCell($col_widths['quantity'], $row_height, "", 'RB', 'C', false, 0);
        $pdf->MultiCell($col_widths['vat'], $row_height, "", 'RB', 'C', false, 0);
        $pdf->MultiCell($col_widths['net_value'], $row_height, "", 'RB', 'R', false, 1);    

        # total amount section
        $label_width = $col_widths['unit_price'] + $col_widths['quantity'];
        $row_height  = 4.7;

        $total_net   = $this->contribution['total_amount'];
        $total_vat   = 0;
        $total_gross = $this->contribution['total_amount']; 

        $pdf->SetXY($pdf->GetX() + $col_widths['title'], $pdf->GetY() + 3);
        $pdf->SetFont('sourcesansprosemib', '', 9, true);
        $pdf->MultiCell($label_width, $row_height, ts('TOTAL NET VALUE'), '', 'L', false, 0);
        $pdf->SetFont('sourcesanspro', '', 9, true);
        $pdf->MultiCell($col_widths['vat'], $row_height, '£', 'LTB', 'R', false, 0);
        $pdf->MultiCell($col_widths['net_value'], $row_height, $total_net, 'TRB', 'R', false, 1);

        $pdf->SetXY($pdf->GetX() + $col_widths['title'] + $label_width, $pdf->GetY());
        $pdf->MultiCell($col_widths['vat'] + $col_widths['net_value'], $row_height, '', 'LR', 'R', false, 1);

        $pdf->SetXY($pdf->GetX() + $col_widths['title'], $pdf->GetY());
        $pdf->SetFont('sourcesansprosemib', '', 9, true);
        $pdf->MultiCell($label_width, $row_height, ts('TOTAL VAT'), '', 'L', false, 0);
        $pdf->SetFont('sourcesanspro', '', 9, true);
        $pdf->MultiCell($col_widths['vat'], $row_height, '£', 'LTB', 'R', false, 0);
        $pdf->MultiCell($col_widths['net_value'], $row_height, $total_vat, 'TRB', 'R', false, 1);

        $pdf->SetXY($pdf->GetX() + $col_widths['title'], $pdf->GetY());
        $pdf->SetFont('sourcesansprosemib', '', 9, true);
        $pdf->MultiCell($label_width, $row_height, $is_paid ? ts('AMOUNT PAID') : ts('AMOUNT DUE'), '', 'L', false, 0);
        $pdf->SetFont('sourcesanspro', '', 9, true);
        $pdf->MultiCell($col_widths['vat'], $row_height, '£', 'LB', 'R', false, 0);
        $pdf->MultiCell($col_widths['net_value'], $row_height, $total_gross, 'RB', 'R', false, 1);

        $pdf->SetXY($pdf->GetX() + $col_widths['title'] + $label_width, $pdf->GetY());
        $pdf->MultiCell($col_widths['vat'] + $col_widths['net_value'], $row_height * 1.2, '', 'LR', 'R', false, 1);

        $pdf->SetXY($pdf->GetX() + $col_widths['title'], $pdf->GetY());
        $pdf->SetFont('sourcesansprosemib', '', 9, true);
        $pdf->MultiCell($label_width, $row_height, ts('TOTAL Sterling'), '', 'L', false, 0);
        $pdf->SetFont('sourcesanspro', '', 9, true);
        $pdf->MultiCell($col_widths['vat'], $row_height, '£', 'LB', 'R', false, 0);
        $pdf->MultiCell($col_widths['net_value'], $row_height, $total_gross, 'RB', 'R', false, 1);

        $pdf->SetXY($pdf->GetX(), $pdf->GetY() - 10);
        $pdf->SetFont('sourcesansprosemib', '', 9, true);
        $pdf->MultiCell(80, $row_height, ts('Thankyou for your order       Terms Strictly 14 Days'), 'LTRB', 'C', false, 1);

    }

    public function printBodyBottom() {

        $pdf = &$this->pdf;

        # payment info section
        $pdf->SetXY($pdf->marginLeft, $pdf->GetY() + 10);
        $pdf->SetFont('sourcesansprosemib', '', 7.5, true);

        $pdf->SetFillColor(200, 200, 200);
        $pdf->MultiCell(177.5, 0,        
            "Please make cheques payable to HPMA or arrange your BACS transfer with the following details:\n" . 
            "Sterling Payments S/C: 30-96-00 A/C: 03380534 Bank: Lloyds TSB, Quote: HPMA13\n" . 
            "Cheques should be sent to HPMA, The Old Candlemakers, West Street, Lewes, BN7 2NZ",
            'LTRB', 'C', true, 1
        );

        # vat no + registered office info
        $pdf->SetXY($pdf->marginLeft, $pdf->GetY() + 10);
        $pdf->SetFont('sourcesanspro', '', 7, true);
        $pdf->MultiCell(177.5, '', 'VAT Reg NO. GB 668212232', '', 'L');
        $pdf->SetXY($pdf->marginLeft, $pdf->GetY() + 0.5);
        $pdf->MultiCell(177.5, '', 'Registered Office: The Old Candlemakers, West Street, Lewes, BN7 2NZ', '', 'L');


    }

    public function printHeaderTop() {

        $pdf = &$this->pdf;
        
        $invoice_title = '';

        if (isset($this->participant['source']))
            $invoice_title = $this->participant['source'];
        elseif (isset($this->event['title']))
            $invoice_title = $this->event['title'];
        elseif (isset($this->membership))
            $invoice_title = ts(
                "HPMA %1-%2 Membership %3",
                array(
                    1 => date('Y', strtotime($this->membership['start_date'])),
                    2 => date('Y', strtotime($this->membership['end_date'])),
                    3 => ($this->contribution['contribution_status_id'] == 1 ? ts('Receipt') : ts('Invoice'))
                )
            );
        elseif (isset($this->contribution['source']))
            $invoice_title = $this->contribution['source'];

        $pdf->addTTFfont($this->fonts_dir . DIRECTORY_SEPARATOR . 'SourceSansPro-Bold.ttf', 'TrueType', 'ansi', 32);

        $this->printLogo();

        $pdf->SetXY($pdf->marginLeft, $this->marginTop + 52);
        $pdf->SetFont('sourcesansprob', '', 18, true);
        
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

        $pdf->SetFont('sourcesansprosemib', '', 9, true);
        $pdf->SetXY($pdf->marginLeft, $pdf->GetY() + 4);
        $pdf->MultiCell(59.5, 0, "Contact:", 'LTR', 'L');
        $pdf->SetFont('sourcesanspro', '', 8, true);

        $pdf->MultiCell(59.5, 0, $address_text, 'LBR', 'L');

    }

    public function printLogo() {
                   
        $this->pdf->Image(
            __DIR__ . DIRECTORY_SEPARATOR . 'hpma-header.png',  # file
            45.2,                          # x
            $pdf->marginTop + 22,          # y
            180,                           # w
            70,                            # h
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

}

