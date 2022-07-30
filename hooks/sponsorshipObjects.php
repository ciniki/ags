<?php
//
// Description
// -----------
// Return the list of objects and ids available for sponsorship.
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function ciniki_ags_hooks_sponsorshipObjects(&$ciniki, $tnid, $args) {

    $objects = array();
    
    //
    // Get the list of exhibits that are upcoming for adding a sponsorship package to
    //
    $strsql = "SELECT exhibits.id, "
        . "exhibits.name, "
        . "exhibits.start_date, "
        . "DATE_FORMAT(exhibits.start_date, '%b %e, %Y') AS exhibit "
        . "FROM ciniki_ags_exhibits AS exhibits "
        . "WHERE exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND (exhibits.start_date > NOW() "
        . "";
    if( isset($args['object']) && $args['object'] == 'ciniki.exhibits.exhibit' && isset($args['object_id']) ) {
        $strsql .= "OR exhibits.id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' ";
    }
    $strsql .= ") "
        . "ORDER BY name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.exhibits', array(
        array('container'=>'exhibits', 'fname'=>'name', 'fields'=>array('id', 'name', 'start_date', 'exhibit_date')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.exhibits.92', 'msg'=>'Unable to load exhibits', 'err'=>$rc['err']));
    }
    $exhibits = isset($rc['exhibits']) ? $rc['exhibits'] : array();

    //
    // Create the object array
    //
    foreach($exhibits as $eid => $exhibit) {
        if( $exhibit['exhibit_date'] != '' && $exhibit['start_date'] != '0000-00-00' ) {
            $exhibit['name'] = $exhibit['name'] . ' - ' . $exhibit['exhibit_date'];
        }
        $objects["ciniki.exhibits.exhibit.{$exhibit['id']}"] = array(
            'id' => 'ciniki.exhibits.exhibit.' . $exhibit['id'],
            'object' => 'ciniki.exhibits.exhibit',
            'object_id' => $exhibit['id'],
            'full_name' => 'Exhibit - ' . $exhibit['name'],
            'name' => $exhibit['name'],
            );
    }

    return array('stat'=>'ok', 'objects'=>$objects);
}
?>
