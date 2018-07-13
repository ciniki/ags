<?php
//
// Description
// ===========
// This function will generate a sheet of labels.
//
// Arguments
// ---------
// 
// Returns
// -------
// <rsp stat='ok' id='34' />
//
function ciniki_ags_templates_barcodesPDF(&$ciniki, $tnid, $args) {

    //
    // Check that the barcodes were passed
    //
    if( !isset($args['barcodes']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.95', 'msg'=>'No barcodes specified', 'err'=>$rc['err']));
    }

    //
    // Load the tenant details
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'tenantDetails');
    $rc = ciniki_tenants_tenantDetails($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['details']) && is_array($rc['details']) ) {    
        $tenant_details = $rc['details'];
    } else {
        $tenant_details = array();
    }

    //
    // Load the label definitions
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'labels');
    $rc = ciniki_ags_labels($ciniki, $tnid, 'all');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $labels = $rc['labels'];

    //
    // Load TCPDF library
    //
    $rsp = array('stat'=>'ok');
    require_once($ciniki['config']['ciniki.core']['lib_dir'] . '/tcpdf/tcpdf.php');

    class MYPDF extends TCPDF {
        public $left_margin = 7;
        public $right_margin = 7;
        public $top_margin = 0;

        public function Header() {
        }

        // Page footer
        public function Footer() {
        }
    }

    //
    // Start a new document
    //
    $pdf = new MYPDF('P', PDF_UNIT, 'LETTER', true, 'UTF-8', false);

    $pdf->tenant_details = $tenant_details;

    //
    // Setup the PDF basics
    //
    $pdf->SetCreator('Ciniki');
    $pdf->SetAuthor($tenant_details['name']);
    $pdf->SetTitle($args['title']);
    $pdf->SetSubject('');
    $pdf->SetKeywords('');

    // set margins
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetHeaderMargin(0);
    $pdf->SetAutoPageBreak(false);

    // set font
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetCellPadding(2);
    $pdf->SetFillColor(255);
    $pdf->SetTextColor(0);
    $pdf->SetDrawColor(125);
    $pdf->SetLineWidth(0.05);

    // Set barcode style
    $style = array(
        'position' => '',
        'align' => 'C',
        'stretch' => false,
        'fitwidth' => true,
        'cellfitalign' => '',
        'border' => false,
        'hpadding' => 'auto',
        'vpadding' => 'auto',
        'fgcolor' => array(0,0,0),
        'bgcolor' => false,
        'text' => true,
        'font' => 'helvetica',
        'fontsize' => 8,
        'stretchtext' => 4,
        );

    if( !isset($labels[$args['label']]) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.98', 'msg'=>'Label not specified', 'err'=>$rc['err']));
    }
    $label = $labels[$args['label']];

    //
    // Setup defaults if not specified
    //
    if( !isset($args['start_row']) || $args['start_row'] == '' ) {
        $args['start_row'] = 0;
    }
    if( !isset($args['start_col']) || $args['start_col'] == '' ) {
        $args['start_col'] = 0;
    }

    //
    // Get the last row and column
    //
    end($label['rows']);
    $last_row = key($label['rows']);
    end($label['cols']);
    $last_col = key($label['cols']);
    reset($label['rows']);
    reset($label['cols']);
    $total_number = count($args['barcodes']);

    $count = 0;
    while( $count < $total_number ) {
        foreach($label['rows'] as $rownum => $row) {
            $pdf->SetY($row['y']);
            if( isset($args['start_row']) && $args['start_row'] > $rownum ) {
                continue;
            }
//            if( isset($args['end_row']) && $args['end_row'] > 0 && $args['end_row'] < $rownum ) {
//                break;
//            }
//            if( isset($args['labels']) && count($args['labels']) <= $count ) {
//                break;
//            }
            foreach($label['cols'] as $colnum => $col) {
                $pdf->SetX($col['x']);
                if( isset($args['start_row']) && $args['start_row'] == $rownum && isset($args['start_col']) && $args['start_col'] > 0 && $args['start_col'] > $colnum ) {
                    continue;
                }
//                if( isset($args['end_row']) && $args['end_row'] > 0 && $args['end_row'] == $rownum && isset($args['end_col']) && $args['end_col'] > 0 && $args['end_col'] < $colnum ) {
//                    break;
//                }
//                if( isset($args['number']) && $args['number'] > 0 && $args['number'] <= $count ) {
//                    break;
//                }
//                if( isset($args['labels']) && count($args['labels']) <= $count ) {
//                    break;
//                }

                if( $count < $total_number ) {
                    $pdf->write1DBarcode($args['barcodes'][$count]['code'], 'C39', $col['x'], $row['y']+2, $label['cell']['width'], 17, 0.3, $style, 'N');
                }
                $count++;
            }
        }
        if( $total_number > $count && $rownum == $last_row && $colnum == $last_col ) {
            reset($label['rows']);
            reset($label['cols']);
            $args['start_row'] = 0;
            $args['start_col'] = 0;
            $pdf->AddPage();
        }
    }

    return array('stat'=>'ok', 'pdf'=>$pdf);
}
?>
