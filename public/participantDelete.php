<?php
//
// Description
// -----------
// This method will delete an participant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:            The ID of the tenant the participant is attached to.
// participant_id:            The ID of the participant to be removed.
//
// Returns
// -------
//
function ciniki_ags_participantDelete(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'participant_id'=>array('required'=>'yes', 'blank'=>'yes', 'name'=>'Participant'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.participantDelete');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Get the current settings for the participant
    //
    $strsql = "SELECT id, uuid, exhibit_id, exhibitor_id "
        . "FROM ciniki_ags_participants "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND id = '" . ciniki_core_dbQuote($ciniki, $args['participant_id']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'participant');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['participant']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.105', 'msg'=>'Participant does not exist.'));
    }
    $participant = $rc['participant'];

    //
    // Check for any dependencies before deleting
    //
    // Exhibit Items
    $strsql = "SELECT COUNT(eitems.id) AS num_items "
        . "FROM ciniki_ags_exhibit_items AS eitems, ciniki_ags_items AS items "
        . "WHERE eitems.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $participant['exhibit_id']) . "' "
        . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND eitems.item_id = items.id "
        . "AND items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $participant['exhibitor_id']) . "' "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbSingleCount');
    $rc = ciniki_core_dbSingleCount($ciniki, $strsql, 'ciniki.ags', 'num');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( $rc['num'] > 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.181', 'msg'=>'There are exhibit items for this participant and cannot be removed.'));
    }
    // Sales
    $strsql = "SELECT COUNT(sales.id) AS num_items "
        . "FROM ciniki_ags_item_sales AS sales, ciniki_ags_items AS items "
        . "WHERE sales.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $participant['exhibit_id']) . "' "
        . "AND sales.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND sales.item_id = items.id "
        . "AND items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $participant['exhibitor_id']) . "' "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbSingleCount');
    $rc = ciniki_core_dbSingleCount($ciniki, $strsql, 'ciniki.ags', 'num');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( $rc['num'] > 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.169', 'msg'=>'There are exhibit sales for this participant and cannot be removed.'));
    }

    //
    // Check if any modules are currently using this object
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectCheckUsed');
    $rc = ciniki_core_objectCheckUsed($ciniki, $args['tnid'], 'ciniki.ags.participant', $args['participant_id']);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.106', 'msg'=>'Unable to check if the participant is still being used.', 'err'=>$rc['err']));
    }
    if( $rc['used'] != 'no' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.107', 'msg'=>'The participant is still in use. ' . $rc['msg']));
    }

    //
    // Start transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDelete');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.ags');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Remove the participant
    //
    $rc = ciniki_core_objectDelete($ciniki, $args['tnid'], 'ciniki.ags.participant',
        $args['participant_id'], $participant['uuid'], 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
        return $rc;
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

    //
    // Update the web index if enabled
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'hookExec');
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'wng', 'indexObject', array());

    return array('stat'=>'ok');
}
?>
