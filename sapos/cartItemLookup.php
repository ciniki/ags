<?php
//
// Description
// ===========
// This function will be a callback when an item is added to ciniki.sapos.
//
// Arguments
// =========
// 
// Returns
// =======
//
function ciniki_ags_sapos_cartItemLookup($ciniki, $tnid, $customer, $args) {

    if( !isset($args['object']) || $args['object'] == ''
        || !isset($args['object_id']) || $args['object_id'] == '' 
        ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.216', 'msg'=>'No item specified.'));
    }

    //
    // Find the item
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
        $item['status'] = 0;
        $item['flags'] = 0x42;
        if( $item['inventory'] == 1 ) {
            $item['flags'] |= 0x08;     // Single item, quantity always 1
        }
        $item['price_id'] = 0;
        $item['object'] = 'ciniki.ags.exhibititem';
        $item['limited_units'] = 'yes';
        $item['units_available'] = $item['inventory'];
        $item['notes'] = '';

        return array('stat'=>'ok', 'item'=>$item);
    }

    return array('stat'=>'ok');
}
?>
