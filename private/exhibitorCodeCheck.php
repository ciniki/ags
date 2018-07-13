<?php
//
// Description
// -----------
// This function will check the database to see if the exhibitor code already exists.
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_ags_exhibitorCodeCheck(&$ciniki, $tnid, $code) {

    $strsql = "SELECT id, display_name "
        . "FROM ciniki_ags_exhibitors "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND code = '" . ciniki_core_dbQuote($ciniki, $code) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibitor');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.23', 'msg'=>'Unable to check for exhibitor code', 'err'=>$rc['err']));
    }
    if( count($rc['rows']) > 0 ) {
        return array('stat'=>'exists');
    }
    return array('stat'=>'ok');
}
?>
