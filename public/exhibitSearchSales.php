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
function ciniki_ags_exhibitSearchSales($ciniki) {
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitSearchSales');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load the date format strings for the user
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    //
    // Load maps
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'maps');
    $rc = ciniki_ags_maps($ciniki);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];
   
    $strsql = "SELECT sales.id, "
        . "sales.item_id, "
        . "items.exhibitor_id, "
        . "items.code, "
        . "items.name, "
        . "sales.flags, "
        . "sales.sell_date, "
        . "sales.sell_date AS sell_date_display, "
        . "sales.quantity, "
        . "IF((sales.flags&0x02)=0x02, 'Paid', 'Pending Payout') AS status_text, "
        . "sales.tenant_amount AS tenant_amount_display, "
        . "sales.exhibitor_amount AS exhibitor_amount_display, "
        . "sales.total_amount AS total_amount_display, "
        . "IFNULL(exhibitors.display_name, '') AS display_name "
        . "FROM ciniki_ags_item_sales AS sales "
        . "INNER JOIN ciniki_ags_items AS items ON ("
            . "sales.item_id = items.id "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "INNER JOIN ciniki_ags_exhibitors AS exhibitors ON ("
            . "items.exhibitor_id = exhibitors.id "
            . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE sales.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
        . "AND ("
            . "items.name LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR items.name LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR items.code LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR items.code LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR items.code LIKE '%-" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR items.code LIKE '%-0" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR items.code LIKE '%-00" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR items.code LIKE '%-000" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR exhibitors.display_name LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR exhibitors.display_name LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
        . ") "
        . "AND sales.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "ORDER BY sales.sell_date, items.code, items.name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'sales', 'fname'=>'id', 
            'fields'=>array('id', 'item_id', 'exhibitor_id', 'display_name', 'code', 'name', 'flags', 'sell_date', 'sell_date_display', 
                'quantity', 'tenant_amount_display', 'exhibitor_amount_display', 'total_amount_display', 'status_text'),
            'naprices'=>array('tenant_amount_display', 'exhibitor_amount_display', 'total_amount_display'),
            'utctotz'=>array('sell_date_display'=>array('format'=>$date_format, 'timezone'=>'UTC'),
            ),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.149', 'msg'=>'Unable to load exhibit sales', 'err'=>$rc['err']));
    }
    $sales = isset($rc['sales']) ? $rc['sales'] : array();

    return array('stat'=>'ok', 'items'=>$sales);
}
?>
