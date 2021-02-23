<?php
//
// Description
// -----------
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function ciniki_ags_web_formatPrice(&$ciniki, $tnid, $item) {

    $display_price = 'Not for sale';
    if( isset($item['inventory']) && $item['inventory'] < 1 ) {
        $display_price = 'Sold Out';
    }
    elseif( ($item['flags']&0x08) == 0x08 ) {   
        $display_price = '';
    }
    elseif( ($item['flags']&0x09) == 0x01 ) {
        $display_price = '';
        $final_amount = $item['unit_amount'];
        if( $item['unit_discount_amount'] > 0 ) {
            $final_amount = $final_amount - $item['unit_amount'];
            $display_price = '<strike>$' . number_format($item['unit_amount'], 2) . '</strike>';
        }
        if( $item['unit_discount_percentage'] > 0 ) {
            $percentage = bcdiv($item['unit_discount_percentage'], 100, 4);
            $final_amount = $final_amount - ($final_amount * $percentage);
            $display_price = '<strike>$' . number_format($item['unit_amount'], 2) . '</strike>';
        }
        $display_price .= ($display_price != '' ? '&nbsp;' : '') 
            . '$' . number_format($final_amount, 2);
    }

    return array('stat'=>'ok', 'display_price'=>$display_price);
}
?>
