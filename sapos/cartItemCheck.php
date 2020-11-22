<?php
//
// Description
// ===========
// This function verifies the items are still available before payment.
//
// Arguments
// =========
// 
// Returns
// =======
//
function ciniki_ags_sapos_cartItemCheck($ciniki, $tnid, $customer, $args) {

    if( !isset($args['object']) || $args['object'] == '' 
        || !isset($args['object_id']) || $args['object_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.218', 'msg'=>'No item specified.'));
    }

    //
    // Lookup the item and verify inventory
    //
    if( $args['object'] == 'ciniki.ags.exhibititem' ) {
        $strsql = "SELECT "
            . "eitems.id AS object_id, "
            . "eitems.inventory, "
            . "items.code, "
            . "items.name AS description, "
            . "items.unit_amount, "
            . "items.unit_discount_amount, "
            . "items.unit_discount_percentage, "
            . "items.taxtype_id, "
            . "eitems.inventory AS inventory_current_num, "
            . "exhibits.name AS exhibit_name "
            . "FROM ciniki_ags_exhibit_items AS eitems "
            . "INNER JOIN ciniki_ags_items AS items ON ("
                . "eitems.item_id = items.id "
                . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "INNER JOIN ciniki_ags_exhibits AS exhibits ON ("
                . "eitems.exhibit_id = exhibits.id "
                . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND eitems.id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' "
            . "ORDER BY items.code, items.name, items.notes "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['item']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.217', 'msg'=>'Unable to find item'));
        }
        $item = $rc['item'];
        if( $item['inventory'] <= 0 ) {
            return array('stat'=>'unavailable', 'err'=>array('code'=>'ciniki.ags.219', 'msg'=>"I'm sorry but " . $item['description'] . " are now sold out."));
        }

        return array('stat'=>'ok');
    }

    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.events.87', 'msg'=>'No event specified.'));
}
?>
