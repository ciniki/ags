<?php
//
// Description
// -----------
// Return the report of exhibitors that have requested changes.
//
// Arguments
// ---------
// ciniki:
// tnid:         The ID of the tenant to get the birthdays for.
// args:                The options for the query.
//
// Additional Arguments
// --------------------
// days:                The number of days past to look for new members.
// 
// Returns
// -------
//
function ciniki_ags_reporting_blockRequestedChanges(&$ciniki, $tnid, $args) {
    //
    // Get the tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'mysql');
    $php_date_format = ciniki_users_dateFormat($ciniki, 'php');

    //
    // Load maps
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'private', 'maps');
    $rc = ciniki_customers_maps($ciniki);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];

    //
    // Store the report block chunks
    //
    $chunks = array();

    //
    // Get the participants that have requested changes to their profile or items
    //
    $strsql = "SELECT exhibitors.id, "
        . "exhibitors.customer_id, "
        . "exhibitors.display_name, "
        . "IF(exhibitors.requested_changes <> '', 'yes', 'no') AS profileupdates, "
        . "IF(IFNULL(items.id, '') <> '', 'yes', 'no') AS itemupdates "
        . "FROM ciniki_ags_exhibitors AS exhibitors "
        . "LEFT JOIN ciniki_ags_items AS items ON ("
            . "exhibitors.id = items.exhibitor_id "
            . "AND items.requested_changes <> '' "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE exhibitors.requested_changes <> '' "
        . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibitors', 'fname'=>'id', 
            'fields'=>array(
                'id', 'customer_id', 'display_name', 'profileupdates', 'itemupdates'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.359', 'msg'=>'Unable to load exhibitors', 'err'=>$rc['err']));
    }
    $exhibitors = isset($rc['exhibitors']) ? $rc['exhibitors'] : array();

    //
    // Create the report blocks
    //
    if( count($exhibitors) > 0 ) {
        $chunks[] = array(
            'type' => 'table',
            'columns' => array(
                array('label'=>'Name', 'pdfwidth'=>'50%', 'field'=>'display_name'),
                array('label'=>'Profiles Updated', 'pdfwidth'=>'25%', 'field'=>'profileupdates'),
                array('label'=>'Items Updated', 'pdfwidth'=>'25%', 'field'=>'itemupdates'),
                ),
            'data' => $exhibitors,
            'editApp' => array('app'=>'ciniki.ags.main', 'args'=>array('exhibitor_id'=>'d.id', 'customer_id'=>'d.customer_id')),
            'textlist' => '',
            );
    }
    
    return array('stat'=>'ok', 'chunks'=>$chunks);
}
?>
