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
function ciniki_ags_templates_riskManagementReport(&$ciniki, $tnid, $args) {

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
        public $footer_msg = '';

        public function Header() {
            //
            // Output the title
            //
            $this->SetFont('', 'B', 12);
            $this->Cell(278, 12, $this->title, 0, false, 'C', 0);
        }

        // Page footer
        public function Footer() {
            // Position at 15 mm from bottom
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            if( isset($this->footer_msg) && $this->footer_msg != '' ) {
                $this->Cell(144, 10, $this->footer_msg,
                    0, false, 'L', 0, '', 0, false, 'T', 'M');
                $this->Cell(144, 10, 'Page ' . $this->getPageNumGroupAlias().'/'.$this->getPageGroupAlias(), 
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
    $pdf = new MYPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    //
    // Figure out the header tenant name and address information
    //
    if( isset($args['title']) ) {
        $pdf->title = $args['title'];
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
    $pdf->SetMargins(10, 19, 10);
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

        $pdf->title = $args['title'] . ' - ' . $exhibitor['display_name'];
        if( $args['start_date'] != '' && $args['end_date'] != '' ) {
            $pdf->title = $args['title'] . ' - ' . $exhibitor['display_name'] . ' - ' . $args['start_date'] . ' to ' . $args['end_date'];
        } elseif( $args['start_date'] != '' ) {
            $pdf->title = $args['title'] . ' - ' . $exhibitor['display_name'] . ' - Starting on ' . $args['start_date'];
        }
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
        $w = array(10, 25, 88, 15, 40, 20, 20, 45, 15);
        $pdf->SetFillColor(224);
        $pdf->SetFont('', 'B');
        $pdf->SetCellPaddings(1.5,2,1.5,2);
        $pdf->Cell($w[0], 6, '', 1, 0, 'C', 1);
        $pdf->Cell($w[1], 6, 'Code', 1, 0, 'C', 1);
        $pdf->Cell($w[2], 6, 'Item', 1, 0, 'C', 1);
        $pdf->Cell($w[3], 6, 'Year', 1, 0, 'C', 1);
        $pdf->Cell($w[4], 6, 'Medium', 1, 0, 'C', 1);
        $pdf->Cell($w[5], 6, 'Size', 1, 0, 'C', 1);
        $pdf->Cell($w[6], 6, 'Price', 1, 0, 'C', 1);
        $pdf->Cell($w[7], 6, 'Condition', 1, 0, 'C', 1);
        $pdf->Cell($w[8], 6, 'Qty', 1, 0, 'C', 1);
        $pdf->Ln();
        $pdf->SetFillColor(236);
        $pdf->SetTextColor(0);
        $pdf->SetFont('');

        $total_amount = 0;
        $total_tenant_amount = 0;
        $total_exhibitor_amount = 0;
        $num_items = count($exhibitor['items']);
        $num = 0;
        $fill = 0;
        foreach($exhibitor['items'] as $item) {
            $lh = 6;
            $code = $item['code'];
            $name = $item['name'];
            //$total_amount += $item['total_amount'];
            //$total_tenant_amount += $item['tenant_amount'];
            //$total_exhibitor_amount += $item['exhibitor_amount'];
            //$num_items+=$item['quantity'];

            $nlines = $pdf->getNumLines($name, $w[2]);
            if( $pdf->getNumLines($item['medium'], $w[4]) > $nlines ) {
                $nlines = $pdf->getNumLines($item['medium'], $w[4]);
            }
            if( $pdf->getNumLines($item['current_condition'], $w[7]) > $nlines ) {
                $nlines = $pdf->getNumLines($item['current_condition'], $w[7]);
            }
            if( $nlines == 2 ) {
                $lh = 3+($nlines*5);
            } elseif( $nlines > 2 ) {
                $lh = 2+($nlines*5);
            }
            // Check if we need a page break

            $num_left = $num_items - $num;
            // If there is only 1 row left, then make sure there is enough room for the totals.
            //if( $pdf->getY() > ($pdf->getPageHeight() - ($num_left>1?(10+($nlines*10)):80)) ) {
            if( $pdf->getY() > ($pdf->getPageHeight() - ($num_left>1?(25+($nlines*10)):85)) ) {
                $pdf->AddPage();
                $pdf->SetFillColor(224);
                $pdf->SetFont('', 'B');
                $pdf->Cell($w[0], 6, '', 1, 0, 'C', 1);
                $pdf->Cell($w[1], 6, 'Code', 1, 0, 'C', 1);
                $pdf->Cell($w[2], 6, 'Item', 1, 0, 'C', 1);
                $pdf->Cell($w[3], 6, 'Year', 1, 0, 'C', 1);
                $pdf->Cell($w[4], 6, 'Medium', 1, 0, 'C', 1);
                $pdf->Cell($w[5], 6, 'Size', 1, 0, 'C', 1);
                $pdf->Cell($w[6], 6, 'Price', 1, 0, 'C', 1);
                $pdf->Cell($w[7], 6, 'Condition', 1, 0, 'C', 1);
                $pdf->Cell($w[8], 6, 'Qty', 1, 0, 'C', 1);
                $pdf->Ln();
                $fill = 0;
                $pdf->SetFillColor(236);
                $pdf->SetTextColor(0);
                $pdf->SetFont('');
            }
            $pdf->MultiCell($w[0], $lh, ($num+1), 1, 'R', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->MultiCell($w[1], $lh, $code, 1, 'L', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->MultiCell($w[2], $lh, $name, 1, 'L', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->MultiCell($w[3], $lh, $item['creation_year'], 1, 'L', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->MultiCell($w[4], $lh, $item['medium'], 1, 'L', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->MultiCell($w[5], $lh, $item['size'], 1, 'L', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->MultiCell($w[6], $lh, '$' . number_format($item['unit_amount'], 2), 1, 'R', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->MultiCell($w[7], $lh, $item['current_condition'], 1, 'R', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->MultiCell($w[8], $lh, (int)$item['inventory'], 1, 'R', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->Ln(); 
            $fill=!$fill;
            $num++;
        }

        //
        // Add signature lines
        //
        $fill = 0;
        $pdf->Ln(15);
        $lh = 6;
        $pdf->SetCellPaddings(1.5,0.5,1.5,2);
        $pdf->MultiCell(100, $lh, 'Artists Signature', 'T', 'L', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
        $pdf->MultiCell(5, $lh, '', 0, 'L', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
        $pdf->MultiCell(50, $lh, 'Date', 'T', 'L', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
        $pdf->Ln(15);
        $pdf->MultiCell(100, $lh, 'Establishment Signing Authority (' . $args['author'] . ')', 'T', 'L', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
        $pdf->MultiCell(5, $lh, '', 0, 'L', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
        $pdf->MultiCell(50, $lh, 'Date', 'T', 'L', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
        $pdf->Ln(15);
        $pdf->MultiCell(100, $lh, 'Establishment Signing Authority (' . $args['location'] . ')', 'T', 'L', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
        $pdf->MultiCell(5, $lh, '', 0, 'L', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
        $pdf->MultiCell(50, $lh, 'Date', 'T', 'L', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
        $pdf->SetCellPaddings(1.5,2,1.5,2);
    }


    return array('stat'=>'ok', 'pdf'=>$pdf);
}
?>
