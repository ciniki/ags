<?php
//
// Description
// -----------
// This method will return the list of web profile and item update requests.
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function ciniki_ags_webupdatesList(&$ciniki) {
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.webupdatesList');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Get the list of profile updates
    //
    $strsql = "SELECT exhibitors.id, "
        . "exhibitors.customer_id, "
        . "exhibitors.code, "
        . "exhibitors.display_name, "
        . "customers.display_name AS customer_name, "
        . "exhibitors.requested_changes "
        . "FROM ciniki_ags_exhibitors AS exhibitors "
        . "LEFT JOIN ciniki_customers AS customers ON ("
            . "exhibitors.customer_id = customers.id "
            . "AND customers.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE exhibitors.requested_changes <> '' "
        . "AND exhibitors.requested_changes <> '{}' "
        . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "ORDER BY exhibitors.display_name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibitors', 'fname'=>'id', 
            'fields'=>array('id', 'customer_id', 'code', 'display_name', 'customer_name', 'requested_changes'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.368', 'msg'=>'Unable to load exhibitors', 'err'=>$rc['err']));
    }
    if( isset($rc['exhibitors']) ) {
        $exhibitors = $rc['exhibitors'];
        $exhibitors_ids = array();
        foreach($exhibitors as $k => $v) {
            $exhibitors_ids[] = $v['id'];
        }
    } else {
        $exhibitors = array();
        $exhibitors_ids = array();
    }
        

    //
    // Get the list of item updates
    //
    $strsql = "SELECT participants.id AS participant_id, "
        . "exhibitors.display_name AS exhibitor_name, "
        . "exhibits.id AS exhibit_id, "
        . "exhibits.name AS exhibit_name "
        . "FROM ciniki_ags_exhibit_items AS eitems "
        . "INNER JOIN ciniki_ags_items AS items ON ("
            . "eitems.item_id = items.id "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "INNER JOIN ciniki_ags_exhibitors AS exhibitors ON ("
            . "items.exhibitor_id = exhibitors.id "
            . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "INNER JOIN ciniki_ags_exhibits AS exhibits ON ("
            . "eitems.exhibit_id = exhibits.id "
            . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "INNER JOIN ciniki_ags_participants AS participants ON ("
            . "exhibits.id = participants.exhibit_id "
            . "AND items.exhibitor_id = participants.exhibitor_id "
            . "AND participants.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE (eitems.status = 30 OR eitems.pending_inventory <> 0 OR items.requested_changes <> '' ) "
        . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "ORDER BY exhibitor_name, exhibit_name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'items', 'fname'=>'participant_id', 
            'fields'=>array(
                'participant_id', 'exhibitor_name', 'exhibit_id', 'exhibit_name'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.369', 'msg'=>'Unable to load items', 'err'=>$rc['err']));
    }
    if( isset($rc['items']) ) {
        $items = $rc['items'];
        $items_ids = array();
        foreach($items as $k => $v) {
            $items_ids[] = $v['participant_id'];
        }
    } else {
        $items = array();
        $items_ids = array();
    }


    return array('stat'=>'ok', 'profileupdates'=>$exhibitors, 'itemupdates'=>$items);
}
?>
