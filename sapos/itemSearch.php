<?php
//
// Description
// ===========
// This function searches the exhibit items for sale.
//
// Arguments
// =========
// 
// Returns
// =======
//
function ciniki_ags_sapos_itemSearch($ciniki, $tnid, $args) {

    if( $args['start_needle'] == '' ) {
        return array('stat'=>'ok', 'items'=>array());
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki);

    //
    // Search by code, name 
    //
    $strsql = "SELECT items.id, "
        . "eitems.id AS exhibit_item_id, "
        . "items.code, "
        . "items.name AS description, "
        . "items.unit_amount, "
        . "items.unit_discount_amount, "
        . "items.unit_discount_percentage, "
        . "items.taxtype_id, "
        . "items.shipping_profile_id, "
        . "eitems.inventory AS num_available, "
        . "exhibits.name AS exhibit_name "
        . "FROM ciniki_ags_items AS items "
        . "INNER JOIN ciniki_ags_exhibit_items AS eitems ON ("
            . "items.id = eitems.item_id "
            . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "INNER JOIN ciniki_ags_exhibits AS exhibits ON ("
            . "eitems.exhibit_id = exhibits.id "
            . "AND exhibits.status < 90 "
//            . "AND (exhibits.end_date = '0000-00-00' OR exhibits.end_date > NOW()) "
            . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "INNER JOIN ciniki_ags_exhibitors AS exhibitors ON ("
            . "items.exhibitor_id = exhibitors.id "
            . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND (items.code LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR items.code LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR items.name LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR items.name LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR exhibitors.display_name LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR exhibitors.display_name LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . ") "
        . "ORDER BY items.code, items.name, eitems.inventory DESC, items.notes "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'items', 'fname'=>'id',
            'fields'=>array('id', 'exhibit_item_id', 'code', 'description', 
                'unit_amount', 'unit_discount_amount', 'unit_discount_percentage', 
                'taxtype_id', 'shipping_profile_id',
                'exhibit_name', 'num_available')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $items = array();
    if( isset($rc['items']) ) {
        foreach($rc['items'] as $item) {
            $item['flags'] = 0x02;
            $item['description'] = $item['exhibit_name'] . ' - ' . $item['description'];
            $item['object'] = 'ciniki.ags.exhibititem';
            $item['object_id'] = $item['exhibit_item_id'];
            $item['quantity'] = 1;
            $item['notes'] = '';
            $items[] = array('item'=>$item);
        }
    }

    return array('stat'=>'ok', 'items'=>$items);        
}
?>
