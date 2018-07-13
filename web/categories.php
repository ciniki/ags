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
function ciniki_ags_web_categories($ciniki, $settings, $tnid, $args) {

    //
    // Get the list of category names
    //
    $rsp = array('stat'=>'ok');
    $strsql = "SELECT DISTINCT locations.category "
        . "FROM ciniki_ags_exhibits AS exhibits, ciniki_ags_locations AS locations "
        . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND exhibits.status = 50 "
        . "AND (exhibits.flags&0x01) = 0x02 "
        . "AND exhibits.location_id = locations.id "
        . "AND locations.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'categories', 'fname'=>'permalink',
            'fields'=>array('permalink', 'name')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['categories']) ) {
        $rsp['categories'] = $rc['categories'];
    }

    return $rsp;
}
?>
