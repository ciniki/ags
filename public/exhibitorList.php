<?php
//
// Description
// -----------
// This method will return the list of Exhibitors for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Exhibitor for.
//
// Returns
// -------
//
function ciniki_ags_exhibitorList($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'exhibitor_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Exhibitor'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitorList');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Get the number of items
    //
    $strsql = "SELECT exhibitor_id, COUNT(id) AS num_items "
        . "FROM ciniki_ags_items "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "GROUP BY exhibitor_id "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQueryList2');
    $rc = ciniki_core_dbQueryList2($ciniki, $strsql, 'ciniki.ags', 'exhibitors');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.33', 'msg'=>'Unable to get list of items', 'err'=>$rc['err']));
    }
    $exhibitor_items = isset($rc['exhibitors']) ? $rc['exhibitors'] : array();
    
    //
    // Get the number of exhibits
    //
    $strsql = "SELECT items.exhibitor_id, COUNT(DISTINCT eitems.exhibit_id) AS num_items "
        . "FROM ciniki_ags_exhibit_items AS eitems, ciniki_ags_items AS items "
        . "WHERE eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND eitems.item_id = items.id "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "GROUP BY items.exhibitor_id "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQueryList2');
    $rc = ciniki_core_dbQueryList2($ciniki, $strsql, 'ciniki.ags', 'exhibitors');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.34', 'msg'=>'Unable to get list of exhibits', 'err'=>$rc['err']));
    }
    $exhibitor_exhibits = isset($rc['exhibitors']) ? $rc['exhibitors'] : array();

    //
    // Get the number of sales
    //
    $strsql = "SELECT items.exhibitor_id, COUNT(sitems.exhibit_id) AS num_items "
        . "FROM ciniki_ags_item_sales AS sitems, ciniki_ags_items AS items "
        . "WHERE sitems.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND sitems.item_id = items.id "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "GROUP BY items.exhibitor_id "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQueryList2');
    $rc = ciniki_core_dbQueryList2($ciniki, $strsql, 'ciniki.ags', 'exhibitors');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.35', 'msg'=>'Unable to get list of sales', 'err'=>$rc['err']));
    }
    $exhibitor_sales = isset($rc['exhibitors']) ? $rc['exhibitors'] : array();

    //
    // Get the list of exhibitors
    //
    $strsql = "SELECT exhibitors.id, "
        . "exhibitors.customer_id, "
        . "exhibitors.display_name_override, "
        . "exhibitors.display_name, "
        . "exhibitors.permalink, "
        . "exhibitors.code, "
        . "exhibitors.status, "
        . "exhibitors.flags "
        . "FROM ciniki_ags_exhibitors AS exhibitors "
        . "WHERE exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibitors', 'fname'=>'id', 
            'fields'=>array('id', 'customer_id', 'display_name_override', 'display_name', 'permalink', 'code', 'status', 'flags')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['exhibitors']) ) {
        $exhibitors = $rc['exhibitors'];
        $exhibitor_ids = array();
        foreach($exhibitors as $iid => $exhibitor) {
            $exhibitor_ids[] = $exhibitor['id'];
            $exhibitors[$iid]['num_items'] = isset($exhibitor_items[$exhibitor['id']]) ? $exhibitor_items[$exhibitor['id']] : 0;
            $exhibitors[$iid]['num_exhibits'] = isset($exhibitor_exhibits[$exhibitor['id']]) ? $exhibitor_exhibits[$exhibitor['id']] : 0;
            $exhibitors[$iid]['num_sales'] = isset($exhibitor_sales[$exhibitor['id']]) ? $exhibitor_sales[$exhibitor['id']] : 0;
        }
    } else {
        $exhibitors = array();
        $exhibitor_ids = array();
    }

    return array('stat'=>'ok', 'exhibitors'=>$exhibitors, 'nplist'=>$exhibitor_ids);
}
?>
