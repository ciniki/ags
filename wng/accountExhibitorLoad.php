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
function ciniki_ags_wng_accountExhibitorLoad(&$ciniki, $tnid, &$request) {

    //
    // Check the customer is logged in
    //
    if( !isset($request['session']['customer']['id']) || $request['session']['customer']['id'] <= 0 ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "You must be logged in."
            )));
    }

    //
    // Load the exhibitor details
    //
    $strsql = "SELECT exhibitors.id, "
        . "exhibitors.customer_id, "
        . "exhibitors.code, "
        . "exhibitors.status, "
        . "exhibitors.display_name_override, "
        . "exhibitors.display_name, "
        . "exhibitors.profile_name, "
        . "exhibitors.primary_image_id, "
        . "exhibitors.fullbio, "
        . "exhibitors.requested_changes "
        . "FROM ciniki_ags_exhibitors AS exhibitors "
        . "WHERE exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND exhibitors.customer_id = '" . ciniki_core_dbQuote($ciniki, $request['session']['customer']['id']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibitors', 'fname'=>'id', 
            'fields'=>array('id', 'customer_id', 'code', 'status', 'display_name_override', 'display_name',
                'profile_name', 'primary_image_id', 'fullbio', 'requested_changes'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.313', 'msg'=>'Exhibitor not found', 'err'=>$rc['err']));
    }
    if( !isset($rc['exhibitors'][0]) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.314', 'msg'=>'Unable to find Exhibitor'));
    }
    $exhibitor = $rc['exhibitors'][0];

    if( isset($exhibitor['requested_changes']) && $exhibitor['requested_changes'] != '' ) {
        $exhibitor['requested_changes'] = unserialize($exhibitor['requested_changes']);
    } else {
        $exhibitor['requested_changes'] = array();
    }

    return array('stat'=>'ok', 'exhibitor'=>$exhibitor);
}
?>
