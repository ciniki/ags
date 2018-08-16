<?php
//
// Description
// ===========
// This method will return all the information about an exhibit.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the exhibit is attached to.
// exhibit_id:          The ID of the exhibit to get the details for.
//
// Returns
// -------
//
function ciniki_ags_exhibitSearchParticipants($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'exhibit_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Exhibit'),
        'start_needle'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Search String'),
        'limit'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Limit'),
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitSearchParticipants');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load maps
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'maps');
    $rc = ciniki_ags_maps($ciniki);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];
    
    //
    // Get the list of participants
    //
    $strsql = "SELECT participants.id, "
        . "exhibitors.id AS exhibitor_id, "
        . "exhibitors.display_name, "
        . "participants.status, "
        . "participants.status AS status_text "
        . "FROM ciniki_ags_participants AS participants "
        . "INNER JOIN ciniki_ags_exhibitors AS exhibitors ON ("
            . "participants.exhibitor_id = exhibitors.id "
            . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE participants.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
        . "AND participants.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND ("
            . "exhibitors.display_name LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR exhibitors.display_name LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . ") "
        . "ORDER BY exhibitors.display_name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'participants', 'fname'=>'exhibitor_id', 
            'fields'=>array('id', 'exhibitor_id', 'display_name', 'status', 'status_text'),
            'maps'=>array('status_text'=>$maps['participant']['status']),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.148', 'msg'=>'Unable to load participants', 'err'=>$rc['err']));
    }
    $participants = isset($rc['participants']) ? $rc['participants'] : array();

    return array('stat'=>'ok', 'participants'=>$participants);
}
?>
