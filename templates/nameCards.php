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
function ciniki_ags_templates_nameCards(&$ciniki, $tnid, $args) {

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
    // Load the tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
    $rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_ags_settings', 'tnid', $tnid, 'ciniki.ags', 'settings', '');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.229', 'msg'=>'Unable to load settings', 'err'=>$rc['err']));
    }
    $settings = isset($rc['settings']) ? $rc['settings'] : array();
   

    //
    // Determine which template to use
    //
    $template = 'businesscards';
    if( isset($settings['namecards-template']) && $settings['namecards-template'] != '' ) {
        $template = $settings['namecards-template'];
    }

    //
    // Setup artist prefix
    //
    $artist_prefix = '';
    if( isset($settings['namecards-artist-prefix']) && $settings['namecards-artist-prefix'] != '' && $settings['namecards-artist-prefix'] != 'none' ) {
        $artist_prefix = $settings['namecards-artist-prefix'] . ' ';
    }

    //
    // Load TCPDF library
    //
    require_once($ciniki['config']['ciniki.core']['lib_dir'] . '/tcpdf/tcpdf.php');

    class MYPDF extends TCPDF {
        public $title;

        public function Header() {
        }
        public function Footer() {
        }
    }

    //
    // Start a new document
    //
    $pdf = new MYPDF('P', PDF_UNIT, 'LETTER', true, 'UTF-8', false);

    //
    // Setup the PDF basics
    //
    $pdf->SetCreator('Ciniki');
    $pdf->SetAuthor($args['author']);
    $pdf->SetTitle($pdf->title);
    $pdf->SetSubject('');
    $pdf->SetKeywords('');

    $font_title = TCPDF_FONTS::addTTFfont(__DIR__ . '/gothicbolditalic.ttf', '', '', 32);
    $font_other = TCPDF_FONTS::addTTFfont(__DIR__ . '/gothic.ttf', '', '', 32);

    // set margins
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(0);
    $pdf->SetAutoPageBreak(false, PDF_MARGIN_BOTTOM);
    $pdf->SetFillColor(255);
    $pdf->SetTextColor(0);
    $pdf->SetDrawColor(51);
    $pdf->SetLineWidth(0.15);
    $pdf->SetCellPaddings(1,1,1,1);

    $x_offset = 22;
    $x_margin = 0;
    $y_offset = 17;
    $y_margin = 0;
    $card_width = 89;
    $card_height = 51;
    $include_size = 'yes';
    $image_position = 'bottom-right';

    $cards_per_page = 10;
    if( $template == 'fourbythree' ) {
        $y_offset = 12;
        $cards_per_page = 6;
        $x_offset = 12;
        $card_width = 84;
        $x_margin = 24;
        $y_margin = 27;
        $card_height = 66;
    }

    if( isset($settings['namecards-include-size']) && $settings['namecards-include-size'] == 'no' ) {
        $include_size = 'no';
    }

    if( isset($settings['namecards-image']) && $settings['namecards-image'] != '' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'loadImage');
        $rc = ciniki_images_loadImage($ciniki, $tnid, $settings['namecards-image'], 'original');
        if( $rc['stat'] == 'ok' ) {
            $card_image = $rc['image'];
            $image_width = ($card_width/2)- 5;
            if( $template == 'fourbythree' ) {
                $image_width = $card_width;
            }
            $image_ratio = $card_image->getImageHeight()/$card_image->getImageWidth();

            $image_height = $image_width * $image_ratio;
            if( $image_height > 30 ) {
                $image_height = 30;
                $image_width = $image_height/$image_ratio;
            }
        }
    }

    $num_seller = 0;
    $card_number = 0;
    foreach($args['exhibitors'] as $exhibitor) {

        if( !isset($exhibitor['items']) || count($exhibitor['items']) == 0 ) {
            continue;
        }
        foreach($exhibitor['items'] as $item) {
            if( ($card_number % $cards_per_page) == 0 ) {
                $pdf->AddPage();
            }
            $page_card_number = $card_number % $cards_per_page;
            $x = ($page_card_number%2);
            $y = floor($page_card_number/2);

/*            $pdf->Rect($x_offset + ($x*$card_width) + ($x*$x_margin) - 1,
                $y_offset + ($y*$card_height) + ($y*$y_margin) - 1,
                $card_width + 2,
                $card_height + 2,
                ); */
            
            $pdf->SetY($y_offset + ($y*$card_height) + ($y*$y_margin));
            $pdf->SetX($x_offset + ($x*$card_width) + ($x*$x_margin));
            
            if( $template == 'fourbythree' && isset($card_image) ) {
                $pdf->Image('@'.$card_image->getImageBlob(), 
                    $x_offset + ($x*$card_width) + ($x*$x_margin) 
                        + (($card_width-$image_width) != 0 ? ($card_width-$image_width)/2 : 0),
                    $y_offset + ($y*$card_height) + ($y*$y_margin),
                    $image_width,
                    $image_height,
                    'JPEG', '', '', true, 150, '', false, false, 0, 'B');
                $pdf->SetY($y_offset + ($y*$card_height) + ($y*$y_margin) + $image_height + 5 );
                $pdf->SetX($x_offset + ($x*$card_width) + ($x*$x_margin));
            }

            $pdf->SetFont($font_title, 'BI', 14);
            $pdf->Cell($card_width, 8, $item['name'], 0, 1, 'L', 0, '', 1);

            $pdf->SetX($x_offset + ($x*$card_width) + ($x*$x_margin));
            $pdf->SetFont($font_other, '', 13);
            $pdf->Cell($card_width, 6, $artist_prefix . $exhibitor['display_name'], 0, 1, 'L', 0, '', 1);

            if( $item['size'] != '' && $include_size == 'yes' ) {
                $pdf->SetX($x_offset + ($x*$card_width) + ($x*$x_margin));
                $pdf->SetFont($font_other, '', 13);
                $pdf->Cell($card_width, 6, $item['size'], 0, 1, 'L', 0, '', 1);
            }

            if( $item['medium'] != '' ) {
                $pdf->SetX($x_offset + ($x*$card_width) + ($x*$x_margin));
                $pdf->SetFont($font_other, '', 13);
                $pdf->Cell($card_width, 6, $item['medium'], 0, 1, 'L', 0);
            }

            if( $template == 'fourbythree' && isset($card_image) ) {
                $pdf->SetY($y_offset + ($card_height-10) + ($y*$card_height) + ($y*$y_margin));
                $pdf->SetX($x_offset + ($x*$card_width) + ($x*$x_margin));
                if( ($item['flags']&0x01) == 0x01 && $item['unit_amount'] != 0 ) {
                    $pdf->SetFont($font_other, '', 13);
                    if( is_int($item['unit_amount']) ) {
                        $pdf->Cell(50, 6, '$' . number_format($item['unit_amount'], 0), 0, 1, 'L', 0);
                    } else {
                        $pdf->Cell(50, 6, '$' . number_format($item['unit_amount'], 2), 0, 1, 'L', 0);
                    }
                } else {
                    $pdf->Cell(50, 6, 'NFS', 0, 1, 'L', 0);
                }
                if( isset($settings['namecards-last-line']) && $settings['namecards-last-line'] != '' ) {
                    $pdf->SetX($x_offset + ($x*$card_width) + ($x*$x_margin));
                    $pdf->Cell($card_width-20, 6, $settings['namecards-last-line'], 0, 0, 'L', 0, '', 1);
                }
                if( $item['code'] != '' ) {
                    $pdf->SetY($y_offset + ($card_height-1) + ($y*$card_height) + ($y*$y_margin));
                    $pdf->SetX($x_offset + ($x*$card_width) + ($x*$x_margin) + $card_width - 25);
                    $pdf->SetFont($font_other, '', 9);
                    $pdf->Cell(25, 6, $item['code'], 0, 1, 'R', 0);
                }

            } else {
                $pdf->SetY($y_offset + ($card_height-20) + ($y*$card_height) + ($y*$y_margin));
                $pdf->SetX($x_offset + ($x*$card_width) + ($x*$x_margin));
                if( ($item['flags']&0x01) == 0x01 && $item['unit_amount'] != 0 ) {
                    $pdf->SetFont($font_other, '', 13);
                    if( is_int($item['unit_amount']) ) {
                        $pdf->Cell(75, 6, '$' . number_format($item['unit_amount'], 0), 0, 1, 'L', 0);
                    } else {
                        $pdf->Cell(75, 6, '$' . number_format($item['unit_amount'], 2), 0, 1, 'L', 0);
                    }
                } else {
                    $pdf->Cell(75, 6, 'NFS', 0, 1, 'L', 0);
                }
                if( $item['code'] != '' ) {
                    $pdf->SetX($x_offset + ($x*$card_width) + ($x*$x_margin));
                    $pdf->SetFont($font_other, '', 11);
                    $pdf->Cell(75, 6, $item['code'], 0, 1, 'L', 0);
                }

                if( isset($card_image) ) {
                    $pdf->Image('@'.$card_image->getImageBlob(), 
                        $x_offset + ($x*$card_width) + ($card_width - $image_width - 6),
                        $y_offset + ($y*$card_height) + ($card_height - $image_height - 6),
                        $image_width,
                        $image_height,
                        'JPEG', '', '', true, 150, '', false, false, 0, 'B');
                }
            }
        
            $card_number++;
        }
    }

    return array('stat'=>'ok', 'pdf'=>$pdf);
}
?>
