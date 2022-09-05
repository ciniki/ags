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
function ciniki_ags_participantUpdate(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'participant_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Participant'),
        'exhibit_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Exhibit'),
        'exhibitor_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Exhibitor'),
        'status'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Status'),
        'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
        'message'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Message'),
        'notes'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Notes'),
        'primary_image_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Image'),
        'synopsis'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Synopsis'),
        'fullbio'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Full Bio'),
        'display_name_override'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Name'),
        'code'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Code'),
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.participantUpdate');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

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
    // Check if updating exhibitor name or code
    //
    if( isset($args['display_name_override']) 
        || isset($args['code']) 
        || isset($args['primary_image_id']) 
        || isset($args['synopsis']) 
        || isset($args['fullbio']) 
        ) {
        $strsql = "SELECT participants.id, "
            . "participants.exhibitor_id "
            . "FROM ciniki_ags_participants AS participants "
            . "WHERE participants.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND participants.id = '" . ciniki_core_dbQuote($ciniki, $args['participant_id']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'participant');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.32', 'msg'=>'Unable to load participant', 'err'=>$rc['err']));
        }
        if( !isset($rc['participant']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.168', 'msg'=>'Unable to find requested participant'));
        }
        $participant = $rc['participant'];
        $ciniki['request']['args']['exhibitor_id'] = $participant['exhibitor_id'];
       
        //
        // Update any exhibitor settings
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'public', 'exhibitorUpdate');
        $rc = ciniki_ags_exhibitorUpdate($ciniki);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return $rc;
        }
    }

    //
    // Update the Participant in the database
    //
    if( isset($args['status']) || isset($args['message']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
        $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.ags.participant', $args['participant_id'], $args, 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return $rc;
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

    //
    // Update the web index if enabled
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'hookExec');
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'ciniki.ags.participant', 'object_id'=>$args['participant_id']));

    return array('stat'=>'ok');
}
?>
