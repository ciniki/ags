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
function ciniki_ags_templates_pricelist(&$ciniki, $tnid, $args) {

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
            $this->SetFont('', 'B', 14);
            $this->Cell(180, 12, $this->title, 0, false, 'C', 0);
        }

        // Page footer
        public function Footer() {
            // Position at 15 mm from bottom
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            if( isset($this->footer_msg) && $this->footer_msg != '' ) {
                $this->Cell(90, 10, $this->footer_msg,
                    0, false, 'L', 0, '', 0, false, 'T', 'M');
                $this->Cell(90, 10, 'Page ' . $this->pageNo().'/'.$this->getAliasNbPages(), 
                    0, false, 'R', 0, '', 0, false, 'T', 'M');
            } else {
                // Center the page number if no footer message.
                $this->Cell(0, 10, 'Page ' . $this->pageNo().'/'.$this->getAliasNbPages(), 
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
    $pdf->header_height = 20;
    $pdf->header_height = 30;
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
    $pdf->SetMargins(PDF_MARGIN_LEFT, 19, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // set font
    $pdf->SetFont('times', 'BI', 10);
    $pdf->SetCellPadding(2);

    // add a page
    $pdf->AddPage();
    $pdf->SetFillColor(255);
    $pdf->SetTextColor(0);
    $pdf->SetDrawColor(51);
    $pdf->SetLineWidth(0.15);

    //
    // Add the items
    //
    $w = array(25, 130, 25);
    $pdf->SetFillColor(224);
    $pdf->SetFont('', 'B');
    $pdf->SetCellPadding(2);
    $pdf->Cell($w[0], 6, 'Code', 1, 0, 'C', 1);
    $pdf->Cell($w[1], 6, 'Item', 1, 0, 'C', 1);
    $pdf->Cell($w[2], 6, 'Price', 1, 0, 'C', 1);
    $pdf->Ln();
    $pdf->SetFillColor(236);
    $pdf->SetTextColor(0);
    $pdf->SetFont('');

    $fill=0;
    $lh = 6;
    foreach($args['items'] as $item) {
        $code = $item['code'];
        $name = $item['name'];
        if( ($item['flags']&0x01) == 0 ) {
            $price = 'NFS';
        } else {
            $price = '$' . number_format($item['unit_amount'], 2);
        }
        $nlines = $pdf->getNumLines($name, $w[1]);
        if( $nlines == 2 ) {
            $lh = 3+($nlines*5);
        } elseif( $nlines > 2 ) {
            $lh = 2+($nlines*5);
        }
        // Check if we need a page break
        if( $pdf->getY() > ($pdf->getPageHeight() - 26) ) {
            $pdf->AddPage();
            $pdf->SetFillColor(224);
            $pdf->SetFont('', 'B');
            $pdf->Cell($w[0], 6, 'Code', 1, 0, 'C', 1);
            $pdf->Cell($w[1], 6, 'Item', 1, 0, 'C', 1);
            $pdf->Cell($w[2], 6, 'Price', 1, 0, 'C', 1);
            $pdf->Ln();
            $pdf->SetFillColor(236);
            $pdf->SetTextColor(0);
            $pdf->SetFont('');
        }
        $pdf->MultiCell($w[0], $lh, $code, 1, 'L', $fill, 0, '', '', true, 0, false, true, 0, 'T', false);
        $pdf->MultiCell($w[1], $lh, $name, 1, 'L', $fill, 
            0, '', '', true, 0, false, true, 0, 'T', false);
        $pdf->MultiCell($w[2], $lh, $price, 1, 'R', $fill, 
            0, '', '', true, 0, false, true, 0, 'T', false);
        $pdf->Ln(); 
        $fill=!$fill;
    }

    return array('stat'=>'ok', 'pdf'=>$pdf);
}
?>
