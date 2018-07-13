<?php
//
// Description
// -----------
// This method will return the list of Items for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Item for.
//
// Returns
// -------
//
function ciniki_ags_itemList($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.itemList');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Get the list of items
    //
    $strsql = "SELECT ciniki_ags_items.id, "
        . "ciniki_ags_items.exhibitor_id, "
        . "ciniki_ags_items.exhibitor_code, "
        . "ciniki_ags_items.code, "
        . "ciniki_ags_items.name, "
        . "ciniki_ags_items.permalink, "
        . "ciniki_ags_items.status, "
        . "ciniki_ags_items.flags, "
        . "ciniki_ags_items.unit_amount, "
        . "ciniki_ags_items.unit_discount_amount, "
        . "ciniki_ags_items.unit_discount_percentage, "
        . "ciniki_ags_items.fee_percent, "
        . "ciniki_ags_items.taxtype_id "
        . "FROM ciniki_ags_items "
        . "WHERE ciniki_ags_items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'exhibitor_id', 'exhibitor_code', 'code', 'name', 'permalink', 'status', 'flags', 'unit_amount', 'unit_discount_amount', 'unit_discount_percentage', 'fee_percent', 'taxtype_id')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['items']) ) {
        $items = $rc['items'];
        $item_ids = array();
        foreach($items as $iid => $item) {
            $item_ids[] = $item['id'];
        }
    } else {
        $items = array();
        $item_ids = array();
    }

    return array('stat'=>'ok', 'items'=>$items, 'nplist'=>$item_ids);
}
?>
