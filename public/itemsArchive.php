<?php
//
// Description
// -----------
// This method will archive a selected list of items for an exhibitor.
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function ciniki_ags_itemsArchive(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'exhibitor_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Exhibitor'),
        'codes'=>array('required'=>'yes', 'blank'=>'no', 'type'=>'list', 'name'=>'Codes'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.itemsArchive');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteList');

    $strsql = "SELECT items.id, "
        . "items.status "
        . "FROM ciniki_ags_items AS items "
        . "WHERE items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
        . "AND items.code IN (" . ciniki_core_dbQuoteList($ciniki, $args['codes']) . ") "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'items', 'fname'=>'id', 'fields'=>array('id', 'status')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.363', 'msg'=>'Unable to load items', 'err'=>$rc['err']));
    }
    $items = isset($rc['items']) ? $rc['items'] : array();

    //
    // Archive the items
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    foreach($items as $item) {
        $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.ags.item', $item['id'], array(           'status' => 90,
            ), 0x04);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.363', 'msg'=>'Unable to update the item', 'err'=>$rc['err']));
        }
    }

    return array('stat'=>'ok');
}
?>
