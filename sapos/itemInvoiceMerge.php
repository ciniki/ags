<?php
//
// Description
// ===========
// This method will be called whenever a item is updated in an invoice.
//
// Arguments
// =========
// 
// Returns
// =======
//
function ciniki_ags_sapos_itemInvoiceMerge($ciniki, $tnid, $item, $primary_invoice_id, $secondary_invoice_id) {

    //
    // Update registrations with new invoice id
    //
    if( isset($item['object']) && $item['object'] == 'ciniki.ags.itemsale' && isset($item['object_id']) ) {
        //
        // Check the ags sale exists
        //
        $strsql = "SELECT id, uuid, festival_id, invoice_id "
            . "FROM ciniki_ags_item_sales "
            . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND id = '" . ciniki_core_dbQuote($ciniki, $item['object_id']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'itemsale');
        if( $rc['stat'] != 'ok' ) { 
            return $rc;
        }
        if( !isset($rc['itemsale']) ) {
            // Don't worry if can't find existing reg, probably database error
            return array('stat'=>'ok');
        }
        $itemsale = $rc['itemsale'];

        //
        // Update the item
        //
        if( $itemsale['invoice_id'] != $primary_invoice_id ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.itemsale', $item['object_id'], [
                'invoice_id' => $primary_invoice_id,
                ], 0x04);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.373', 'msg'=>'Unable to update the item sale', 'err'=>$rc['err']));
            }
        }

        return array('stat'=>'ok');
    }

    return array('stat'=>'ok');
}
?>
