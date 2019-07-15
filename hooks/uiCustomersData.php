<?php
//
// Description
// -----------
// This function will return the data for customer(s) to be displayed in the IFB display panel.
// The request might be for 1 individual, or multiple customer ids for a family.
//
// Arguments
// ---------
// ciniki:
// tnid:     The ID of the tenant to get events for.
//
// Returns
// -------
//
function ciniki_ags_hooks_uiCustomersData($ciniki, $tnid, $args) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');
    //
    // Get the time information for tenant and user
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    //
    // Load the date format strings for the user
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    //
    // Load the status maps for the text description of each status
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'maps');
    $rc = ciniki_ags_maps($ciniki);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];
    
    //
    // Setup current date in tenant timezone
    //
    $cur_date = new DateTime('now', new DateTimeZone($intl_timezone));

    //
    // Default response
    //
    $rsp = array('stat'=>'ok', 'tabs'=>array());

    //
    // Get the list of exhibits
    //
    $strsql = "SELECT exhibitors.id, "
        . "exhibits.name "
        . "FROM ciniki_ags_exhibitors AS exhibitors "
        . "LEFT JOIN ciniki_ags_participants AS participants ON ("
            . "exhibitors.id = participants.exhibitor_id "
            . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "LEFT JOIN ciniki_ags_exhibits AS exhibits ON ("
            . "exhibits.id = participants.exhibit_id "
            . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    if( isset($args['customer_id']) ) {
        $strsql .= "AND exhibitors.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' ";
    } elseif( isset($args['customer_ids']) && count($args['customer_ids']) > 0 ) {
        $strsql .= "AND exhibitors.customer_id IN (" . ciniki_core_dbQuoteIDs($ciniki, $args['customer_ids']) . ") ";
    } else {
        return array('stat'=>'ok');
    }
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibits', 'fname'=>'id', 
            'fields'=>array('id', 'name')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.185', 'msg'=>'Unable to load exhibitors', 'err'=>$rc['err']));
    }
    $exhibits = isset($rc['exhibits']) ? $rc['exhibits'] : array();

    $sections = array(
        'ciniki.ags.exhibits' => array(
            'label' => 'Exhibits',
            'type' => 'simplegrid', 
            'num_cols' => 1,
            'headerValues' => array('Exhibit'),
            'cellClasses' => array('', ''),
            'noData' => 'No exhibits',
            'data' => $exhibits,
            'cellValues' => array(
                '0' => 'd.name;',
                ),
            ),
        );

    //
    // Add a tab the customer UI data screen with the certificate list
    //
    $rsp['tabs'][] = array(
        'id' => 'ciniki.ags.exhibits',
        'label' => 'Exhibits',
        'sections' => $sections,
        );

    return $rsp;
}
?>
