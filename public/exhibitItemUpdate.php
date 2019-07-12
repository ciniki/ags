<?php
//
// Description
// ===========
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_ags_exhibitItemUpdate(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'exhibit_item_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Exhibit Item'),
        'inventory'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Inventory'),
        'fee_percent'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Fee'),
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitItemUpdate');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Check if the item is already a part of the exhibit
    //
    $strsql = "SELECT id, exhibit_id, item_id, inventory "
        . "FROM ciniki_ags_exhibit_items "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_item_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.176', 'msg'=>'Item is not part of this exhibit', 'err'=>$rc['err']));
    }
    $exhibititem = isset($rc['item']) ? $rc['item'] : null;

    //
    // Start transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.ags');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the Exhibit Item in the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.ags.exhibititem', $args['exhibit_item_id'], $args, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
        return $rc;
    }

    if( isset($args['inventory']) ) {
        //
        // Add Log entry
        //
        $dt = new DateTime('now', new DateTimezone('UTC'));
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
        $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.ags.itemlog', array(
            'item_id' => $exhibititem['item_id'],
            'action' => 50,
            'actioned_id' => $exhibititem['exhibit_id'],
            'quantity' => ($args['inventory'] - $exhibititem['quantity']),
            'log_date' => $dt->format('Y-m-d H:i:s'),
            'user_id' => $ciniki['session']['user']['id'],
            'notes' => '',
            ), 0x04);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.152', 'msg'=>'Unable to add log', 'err'=>$rc['err']));
        }
    }

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.ags');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $args['tnid'], 'ciniki', 'ags');

    return array('stat'=>'ok');
}
?>
