<?php
//
// Description
// -----------
// This function will return the menu items for the Volunteers module in the account
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_ags_wng_accountMenuItems($ciniki, $tnid, $request, $args) {

    $items = array();

    $settings = isset($request['site']['settings']) ? $request['site']['settings'] : array();
    $base_url = isset($args['base_url']) ? $args['base_url'] : '';

    //
    // Check if web updater is enabled for AGS module
    //
    if( !ciniki_core_checkModuleFlags($ciniki, 'ciniki.ags', 0x08) ) {
        return array('stat'=>'ok');
    }
    
    //
    // Check to make sure customer is setup as an exhibitor
    //
    $strsql = "SELECT exhibitors.id, "
        . "exhibitors.status "
        . "FROM ciniki_ags_exhibitors AS exhibitors "
        . "WHERE customer_id = '" . ciniki_core_dbQuote($ciniki, $request['session']['customer']['id']) . "' "
//        . "AND status < 90 "  // If inactive, they want to reactivate
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . ""; 
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibitor');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.297', 'msg'=>'Unable to load exhibitor', 'err'=>$rc['err']));
    }
    if( !isset($rc['exhibitor']) ) {
        //
        // Setup default exhibitor details if not currently an exhibitor
        //
        $exhibitor = array(
            'id' => 0,
            'status' => 10,
            );
    } else {
        $exhibitor = $rc['exhibitor'];
    }

    //
    // Load the exhibits that are open for submissions
    //
    $strsql = "SELECT exhibits.id, "
        . "exhibits.name, "
        . "exhibits.permalink, "
        . "IFNULL(participants.id, 0) AS pid, "
        . "IFNULL(participants.status, 30) AS pstatus "
        . "FROM ciniki_ags_exhibits AS exhibits "
        . "LEFT JOIN ciniki_ags_participants AS participants ON ("
            . "exhibits.id = participants.exhibit_id "
            . "AND participants.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $exhibitor['id']) . "' "
            . "AND participants.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE exhibits.status <= 50 "             // Open for submissions
        . "AND (exhibits.flags&0x0300) = 0x0300 "  // Open for web updates and web applications
        . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "HAVING pid = 0 OR pstatus = 30 "
        . "ORDER BY exhibits.name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibits', 'fname'=>'id', 
            'fields'=>array('id', 'name', 'permalink', 'pid', 'pstatus')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.328', 'msg'=>'Unable to load exhibits', 'err'=>$rc['err']));
    }
    $applications = isset($rc['exhibits']) ? $rc['exhibits'] : array();

    //
    // Load the exhibits the participant is a part of
    //
    $strsql = "SELECT exhibits.id, "
        . "exhibits.name, "
        . "exhibits.permalink "
        . "FROM ciniki_ags_exhibits AS exhibits "
        . "INNER JOIN ciniki_ags_participants AS participants ON ("
            . "exhibits.id = participants.exhibit_id "
            . "AND participants.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $exhibitor['id']) . "' "
            . "AND participants.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE exhibits.status <= 50 "
        . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "ORDER BY exhibits.name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibits', 'fname'=>'id', 
            'fields'=>array('id', 'name', 'permalink')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.318', 'msg'=>'Unable to load exhibits', 'err'=>$rc['err']));
    }
    $participating = isset($rc['exhibits']) ? $rc['exhibits'] : array();
    
    //
    // Build the options for the exhibitor
    //
    $options = array();
    if( $exhibitor['id'] > 0 ) {
        $options[] = array(
            'title' => 'Exhibitor Profile',
            'ref' => 'ciniki.ags.profile',
            'url' => $base_url . '/gallery/profile',
            );
    }
    if( $exhibitor['status'] > 10 ) {
        $options[] = array(
            'title' => 'Pending Payouts',
            'ref' => 'ciniki.ags.pendingpayouts',
            'url' => $base_url . '/gallery/pendingpayouts',
            );
        $options[] = array(
            'title' => 'Paid Sales',
            'ref' => 'ciniki.ags.paidsales',
            'url' => $base_url . '/gallery/paidsales',
            );
        $options[] = array(
            'title' => 'Catalog',
            'ref' => 'ciniki.ags.catalog',
            'url' => $base_url . '/gallery/catalog',
            );
        foreach($participating as $exhibit) {
            $options[] = array(
                'title' => 'Manage inventory in ' . $exhibit['name'],
                'ref' => 'ciniki.ags.exhibit',
                'url' => $base_url . '/gallery/exhibit/' . $exhibit['permalink'],
                );
        }
    }
    foreach($applications as $exhibit) {
        $options[] = array(
            'title' => 'Apply to ' . $exhibit['name'],
            'ref' => 'ciniki.ags.exhibit',
            'url' => $base_url . '/gallery/exhibit/' . $exhibit['permalink'],
            );
    }
/*    $options[] = array(
        'title' => 'Call for Submissions',
        'ref' => 'ciniki.ags.applications',
        'url' => $base_url . '/gallery/applications',
        );
    $options[] = array(
        'title' => 'Exhibits',
        'ref' => 'ciniki.ags.exhibits',
        'url' => $base_url . '/gallery/exhibits',
        ); */

    if( count($options) > 0 ) {
        $items[] = array(
            'title' => 'Gallery', 
            'priority' => 800, 
            'selected' => isset($args['selected']) && $args['selected'] == 'gallery' ? 'yes' : 'no',
            'ref' => 'ciniki.ags.main',
            'url' => $base_url . '/gallery',
            'items' => $options
            );
    }


    return array('stat'=>'ok', 'items'=>$items);
}
?>
