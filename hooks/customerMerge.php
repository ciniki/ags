<?php
//
// Description
// -----------
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_ags_hooks_customerMerge($ciniki, $tnid, $args) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');

    if( !isset($args['primary_customer_id']) || $args['primary_customer_id'] == '' 
        || !isset($args['secondary_customer_id']) || $args['secondary_customer_id'] == '' ) {
        return array('stat'=>'ok');
    }

    //
    // Keep track of how many items we've updated
    //
    $updated = 0;

    //
    // Check if the exhibitor already exists
    //
    $strsql = "SELECT id "
        . "FROM ciniki_ags_exhibitors "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND customer_id = '" . ciniki_core_dbQuote($ciniki, $args['primary_customer_id']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'participant');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.191', 'msg'=>'Unable to find exhibitors', 'err'=>$rc['err']));
    }
    if( isset($rc['participant']['id']) ) {
        $primary_exhibitor_id = $rc['participant']['id'];
    } 

    //
    // Get the secondary customer exhibitor record
    //
    $strsql = "SELECT id "
        . "FROM ciniki_ags_exhibitors "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND customer_id = '" . ciniki_core_dbQuote($ciniki, $args['secondary_customer_id']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.183', 'msg'=>'Unable to find exhibitors', 'err'=>$rc['err']));
    }
    $items = $rc['rows'];
    if( isset($rc['rows'][0]['id']) ) {
        $secondary_exhibitor_id = $rc['rows'][0]['id'];
        //
        // If primary_customer_id already exists as exhibitor, move the items from secondary to primary.
        //
        if( isset($primary_exhibitor_id) ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'exhibitorMerge');
            $rc = ciniki_ags_exhibitorMerge($ciniki, $tnid, $primary_exhibitor_id, $secondary_exhibitor_id);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.194', 'msg'=>'Unable to merge exhibitors', 'err'=>$rc['err']));
            }
            $updated += (isset($rc['updated']) ? $rc['updated'] : 0);
    return array('stat'=>'ok', 'updated'=>$updated);
        } 
        // 
        // Update exhibitor to move to new customer record
        //
        else {
            $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.exhibitor', $secondary_exhibitor_id, array('customer_id'=>$args['primary_customer_id']), 0x04);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.184', 'msg'=>'Unable to update exhibitors.', 'err'=>$rc['err']));
            }
            $updated++;
        }
    }

    if( $updated > 0 ) {
        //
        // Update the last_change date in the tenant modules
        // Ignore the result, as we don't want to stop user updates if this fails.
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
        ciniki_tenants_updateModuleChangeDate($ciniki, $tnid, 'ciniki', 'ags');
    }

    return array('stat'=>'ok', 'updated'=>$updated);
}
?>
