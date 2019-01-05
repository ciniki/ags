<?php
//
// Description
// -----------
// This method will return the list of marketplaces for a tenant.  It is restricted
// to tenant owners and sysadmins.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:     The ID of the tenant to get marketplaces for.
//
// Returns
// -------
//
function ciniki_ags_unpaidSalesPDF($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'start_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Start'),
        'end_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'End'),
        'exhibit_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Exhibit'),
        'exhibitor_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Exhibit'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];
    
    //  
    // Check access to tnid as owner, or sys admin. 
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.unpaidSalesPDF');
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

    //
    // Load the tenant intl settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');
    $mysql_date_format = ciniki_users_dateFormat($ciniki, 'mysql');

    if( (!isset($args['exhibit_id']) || $args['exhibit_id'] == 0 || $args['exhibit_id'] == '')
        && (!isset($args['exhibitor_id']) || $args['exhibitor_id'] == 0 || $args['exhibitor_id'] == '') 
        && (!isset($args['start_date']) || $args['start_date'] == '') 
        ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.140', 'msg'=>'An exhibit or exhibitor must be specified'));
    }

    //
    // Load tenant details
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'tenantDetails');
    $rc = ciniki_tenants_tenantDetails($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['details']) && is_array($rc['details']) ) {
        $tenant_details = $rc['details'];
    } else {
        $tenant_details = array();
    }

    //
    // Get the exhibit name
    //
    $report_title = 'Unpaid Sales';
    if( isset($args['exhibit_id']) && $args['exhibit_id'] > 0 ) {
        $strsql = "SELECT name "
            . "FROM ciniki_ags_exhibits "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibit');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.142', 'msg'=>'Unable to load exhibit', 'err'=>$rc['err']));
        }
        if( !isset($rc['exhibit']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.143', 'msg'=>'Unable to find requested exhibit'));
        }
        $exhibit = $rc['exhibit'];
        $report_title = $exhibit['name'] . ' - Unpaid Sales';
    }
    if( isset($args['start_date']) && $args['start_date'] != '' ) {
        $dt = new DateTime($args['start_date'], new DateTimezone($intl_timezone));
        if( !isset($args['end_date']) ) {
            $edt = new DateTime('now', new DateTimezone($intl_timezone));
            $args['end_date'] = $dt->format('Y-m-d');
        } else {
            $edt = new DateTime($args['end_date'], new DateTimezone($intl_timezone));
        }
        $report_title .= ' from ' . $dt->format($date_format) . ' to ' . $edt->format($date_format);
    }

    $strsql = "SELECT sales.id AS sales_id, "
        . "exhibitors.display_name, "
        . "items.exhibitor_id, "
        . "items.code, "
        . "items.name, "
        . "sales.quantity, "
        . "DATE_FORMAT(sales.sell_date, '" . ciniki_core_dbQuote($ciniki, $mysql_date_format) . "') AS sell_date, "
        . "sales.tenant_amount, "
        . "sales.exhibitor_amount, "
        . "sales.total_amount "
        . "FROM ciniki_ags_item_sales AS sales "
        . "INNER JOIN ciniki_ags_items AS items ON ("
            . "sales.item_id = items.id "
            . (isset($args['exhibitor_id']) && $args['exhibitor_id'] > 0 ? "AND items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' " : '')
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "INNER JOIN ciniki_ags_exhibitors AS exhibitors ON ("
            . "items.exhibitor_id = exhibitors.id "
            . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE sales.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND (sales.flags&0x02) = 0 ";
    if( isset($args['exhibit_id']) && $args['exhibit_id'] > 0 ) {
        $strsql .= "AND sales.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' ";
    }
    if( isset($args['start_date']) && $args['start_date'] != '' ) {
        $strsql .= "AND sales.sell_date >= '" . ciniki_core_dbQuote($ciniki, $args['start_date']) . "' ";
    }
    if( isset($args['end_date']) && $args['end_date'] != '' ) {
        $strsql .= "AND sales.sell_date <= '" . ciniki_core_dbQuote($ciniki, $args['end_date']) . "' ";
    }
    $strsql .= "ORDER by exhibitors.display_name, items.code, items.name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibitors', 'fname'=>'exhibitor_id', 
            'fields'=>array('display_name')),
        array('container'=>'items', 'fname'=>'sales_id', 
            'fields'=>array('code', 'name', 'quantity', 'sell_date', 'tenant_amount', 'exhibitor_amount', 'total_amount')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.141', 'msg'=>'Unable to load exhibitors', 'err'=>$rc['err']));
    }
    if( isset($rc['exhibitors']) ) {
        $exhibitors = $rc['exhibitors'];
    } else {
        $exhibitors = array();
    }
    
    $today = new DateTime('now', new DateTimezone($intl_timezone));

    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'templates', 'salesReport');
    $rc = ciniki_ags_templates_salesReport($ciniki, $args['tnid'], array(
        'title'=>$report_title,
        'author'=>$tenant_details['name'],
        'footer'=>$today->format('M d, Y'),
        'exhibitors'=>$exhibitors,
        ));
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }
    $pdf = $rc['pdf'];

    //
    // Output the pdf
    //
    $filename = $report_title . ' - ' . $today->format('M d, Y');
    $filename = preg_replace('/[^A-Za-z0-9\-]/', '', $filename) . '.pdf';
    $pdf->Output($filename, 'D');

    return array('stat'=>'exit');
}
?>
