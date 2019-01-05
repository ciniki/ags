<?php
//
// Description
// ===========
// This function returns a PDF of the price list for a market.
//
// Arguments
// ---------
// 
// Returns
// -------
//
function ciniki_ags_templates_salesReport(&$ciniki, $tnid, $args) {

    //
    // Load the tenant intl settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    //
    // Load TCPDF library
    //
    require_once($ciniki['config']['ciniki.core']['lib_dir'] . '/tcpdf/tcpdf.php');

    class MYPDF extends TCPDF {
        //Page header
        public $title = '';
        public $subtitle = '';
        public $footer_msg = '';

        public function Header() {
            //
            // Output the title
            //
            $this->SetFont('', 'B', 14);
            $this->Cell(190, 12, $this->title, 0, false, 'C', 0);
            $this->Ln(8);
            $this->SetFont('', '', 14);
            $this->Cell(190, 12, $this->subtitle, 0, false, 'C', 0);
        }

        // Page footer
        public function Footer() {
            // Position at 15 mm from bottom
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            if( isset($this->footer_msg) && $this->footer_msg != '' ) {
                $this->Cell(95, 10, $this->footer_msg,
                    0, false, 'L', 0, '', 0, false, 'T', 'M');
                $this->Cell(95, 10, 'Page ' . $this->getPageNumGroupAlias().'/'.$this->getPageGroupAlias(), 
                    0, false, 'R', 0, '', 0, false, 'T', 'M');
            } else {
                // Center the page number if no footer message.
                $this->Cell(0, 10, 'Page ' . $this->getPageNumGroupAlias().'/'.$this->getPageGroupAlias(), 
                    0, false, 'C', 0, '', 0, false, 'T', 'M');
            }
        }
    }

    //
    // Start a new document
    //
    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    //
    // Figure out the header tenant name and address information
    //
    if( isset($args['title']) ) {
        $pdf->title = $args['title'];
    }
    if( isset($args['subtitle']) ) {
        $pdf->subtitle = $args['subtitle'];
    }
    if( isset($args['footer']) ) {
        $pdf->footer_msg = $args['footer'];
    }

    //
    // Setup the PDF basics
    //
    $pdf->SetCreator('Ciniki');
    $pdf->SetAuthor($args['author']);
    $pdf->SetTitle($pdf->title);
    $pdf->SetSubject('');
    $pdf->SetKeywords('');

    // set margins
    $pdf->SetMargins(10, 30, 10);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    $num_seller = 0;
    foreach($args['exhibitors'] as $exhibitor) {
        if( !isset($exhibitor['items']) || count($exhibitor['items']) == 0 ) {
            continue;
        }
        // set font
        $pdf->SetFont('times', 'BI', 10);
        $pdf->SetCellPadding(2);

//        $pdf->title = $args['title']
        $pdf->subtitle = $exhibitor['display_name'];
        // add a page
        $pdf->startPageGroup();
        $pdf->AddPage();
        $pdf->SetFillColor(255);
        $pdf->SetTextColor(0);
        $pdf->SetDrawColor(51);
        $pdf->SetLineWidth(0.15);
        //
        // Add the items
        //
        $w = array(24, 74, 23, 9, 20, 20, 20);
        $pdf->SetFillColor(224);
        $pdf->SetFont('', 'B');
        $pdf->SetCellPaddings(1.5,2,1.5,2);
        $pdf->Cell($w[0], 6, 'Code', 1, 0, 'C', 1);
        $pdf->Cell($w[1], 6, 'Item', 1, 0, 'C', 1);
        $pdf->Cell($w[2], 6, 'Date', 1, 0, 'C', 1);
        $pdf->Cell($w[3], 6, 'Qty', 1, 0, 'C', 1);
        $pdf->Cell($w[4], 6, 'Total', 1, 0, 'C', 1);
        $pdf->Cell($w[5], 6, 'Fees', 1, 0, 'C', 1);
        $pdf->Cell($w[6], 6, 'Payout', 1, 0, 'C', 1);
        $pdf->Ln();
        $pdf->SetFillColor(236);
        $pdf->SetTextColor(0);
        $pdf->SetFont('');

        $total_amount = 0;
        $total_tenant_amount = 0;
        $total_exhibitor_amount = 0;
        $num_items = 0;
        $num = 0;
        $fill = 0;
        foreach($exhibitor['items'] as $item) {
            $lh = 6;
            $code = $item['code'];
            $name = $item['name'];
            $total_amount += $item['total_amount'];
            $total_tenant_amount += $item['tenant_amount'];
            $total_exhibitor_amount += $item['exhibitor_amount'];
            $num_items+=$item['quantity'];

            $nlines = $pdf->getNumLines($name, $w[1]);
            if( $nlines == 2 ) {
                $lh = 3+($nlines*5);
            } elseif( $nlines > 2 ) {
                $lh = 2+($nlines*5);
            }
            // Check if we need a page break

            $num_left = $num_items - $num;
            // If there is only 1 row left, then make sure there is enough room for the totals.
            if( $pdf->getY() > ($pdf->getPageHeight() - ($num_left>1?(20+($nlines*15)):60)) ) {
                $pdf->AddPage();
                $pdf->SetFillColor(224);
                $pdf->SetFont('', 'B');
                $pdf->Cell($w[0], 6, 'Code', 1, 0, 'C', 1);
                $pdf->Cell($w[1], 6, 'Item', 1, 0, 'C', 1);
                $pdf->Cell($w[2], 6, 'Date', 1, 0, 'C', 1);
                $pdf->Cell($w[3], 6, 'Qty', 1, 0, 'C', 1);
                $pdf->Cell($w[4], 6, 'Total', 1, 0, 'C', 1);
                $pdf->Cell($w[5], 6, 'Fees', 1, 0, 'C', 1);
                $pdf->Cell($w[6], 6, 'Payout', 1, 0, 'C', 1);
                $pdf->Ln();
                $fill = 0;
                $pdf->SetFillColor(236);
                $pdf->SetTextColor(0);
                $pdf->SetFont('');
            }
            $pdf->MultiCell($w[0], $lh, $code, 1, 'L', $fill, 
                0, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->MultiCell($w[1], $lh, $name, 1, 'L', $fill, 
                0, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->MultiCell($w[2], $lh, $item['sell_date'], 1, 'R', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->MultiCell($w[3], $lh, (int)$item['quantity'], 1, 'R', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->MultiCell($w[4], $lh, '$' . number_format($item['total_amount'], 2), 1, 'R', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->MultiCell($w[5], $lh, '$' . number_format($item['tenant_amount'], 2), 1, 'R', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->MultiCell($w[6], $lh, '$' . number_format($item['exhibitor_amount'], 2), 1, 'R', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->Ln(); 
            $fill=!$fill;
            $num++;
        }

        //
        // Add totals
        //
        $pdf->SetFillColor(224);
        $lh = 6;
        $pdf->SetFont('', 'B');
        $pdf->Cell($w[0] + $w[1] + $w[2], $lh, 'Totals', 1, 0, 'L', 1);
        $pdf->Cell($w[3], $lh, $num_items, 1, 0, 'R', 1);
        $pdf->Cell($w[4], $lh, '$' . number_format($total_amount, 2), 1, 0, 'R', 1);
        $pdf->Cell($w[5], $lh, '$' . number_format($total_tenant_amount, 2), 1, 0, 'R', 1);
        $pdf->Cell($w[5], $lh, '$' . number_format($total_exhibitor_amount, 2), 1, 0, 'R', 1);
        $num_seller++;
    }

    return array('stat'=>'ok', 'pdf'=>$pdf);
}
?>
