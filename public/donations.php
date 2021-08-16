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
function ciniki_ags_donations($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'start_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Start'),
        'end_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'End'),
        'paid_status'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Paid Status'),
        'exhibitor_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Exhibitor'),
        'action'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Action'),
        'sale_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Sale Item'),
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.donations');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load the date format strings for the user
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    //
    // Load the tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    
    //
    // Set the start/end date for the last 7 days if not specified
    //
    $dt = new DateTime('now', new DateTimezone($intl_timezone));
    if( !isset($args['end_date']) || $args['end_date'] == '' || $args['end_date'] == '0000-00-00' ) {
        $args['end_date'] = $dt->format('Y-m-d');
        $end_date_formatted = $dt->format($date_format);
    } else {
        $dt1 = new DateTime($args['end_date'], new DateTimezone($intl_timezone));
        $end_date_formatted = $dt1->format($date_format);
    }
    if( !isset($args['start_date']) || $args['start_date'] == '' || $args['start_date'] == '0000-00-00' ) {
        $dt->sub(new DateInterval('P7D'));
        $args['start_date'] = $dt->format('Y-m-d');
        $start_date_formatted = $dt->format($date_format);
    } else {
        $dt1 = new DateTime($args['start_date'], new DateTimezone($intl_timezone));
        $start_date_formatted = $dt1->format($date_format);
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
    // Query the sales for the date range
    //
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
        . "sales.tenant_amount, "
        . "sales.exhibitor_amount, "
        . "sales.total_amount, "
        . "sales.receipt_number, "
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
        . "WHERE sales.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND sales.sell_date >= '" . ciniki_core_dbQuote($ciniki, $args['start_date']) . "' "
        . "AND sales.sell_date <= '" . ciniki_core_dbQuote($ciniki, $args['end_date']) . "' "
        . "AND sales.receipt_number <> '' "
        . "";
    $strsql .= "ORDER BY sales.receipt_number, sales.sell_date, items.code, items.name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'sales', 'fname'=>'id', 
            'fields'=>array('id', 'item_id', 'exhibitor_id', 'display_name', 'code', 'name', 'flags', 'sell_date', 'sell_date_display', 
                'quantity', 
                'tenant_amount', 'exhibitor_amount', 'total_amount',
                'tenant_amount_display', 'exhibitor_amount_display', 'total_amount_display', 'status_text', 'receipt_number'),
            'naprices'=>array('tenant_amount_display', 'exhibitor_amount_display', 'total_amount_display'),
            'utctotz'=>array('sell_date_display'=>array('format'=>$date_format, 'timezone'=>'UTC'),
            ),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.167', 'msg'=>'Unable to load exhibit sales', 'err'=>$rc['err']));
    }
    $sales = isset($rc['sales']) ? $rc['sales'] : array();

    $exhibitors = array('0'=>'All Exhibitors');
    $totals = array(
        'tenant_amount'=>0,
        'exhibitor_amount'=>0,
        'total_amount'=>0,
        );
    $sales_ids = array();
    foreach($sales as $sid => $sale) {
        $sales_ids[] = $sale['id'];
        $exhibitors[$sale['exhibitor_id']] = $sale['display_name'];
        if( isset($args['paid_status']) ) {
            if( $args['paid_status'] == 1 && ($sale['flags']&0x02) == 0x02 ) {
                unset($sales[$sid]);
                continue;
            }
            if( $args['paid_status'] == 2 && ($sale['flags']&0x02) == 0 ) {
                unset($sales[$sid]);
                continue;
            }
        }
        if( isset($args['exhibitor_id']) && $args['exhibitor_id'] != '' && $args['exhibitor_id'] > 0 && $sale['exhibitor_id'] != $args['exhibitor_id']) {
            unset($sales[$sid]);
            continue;
        }
        $totals['tenant_amount'] += $sale['tenant_amount'];
        $totals['exhibitor_amount'] += $sale['exhibitor_amount'];
        $totals['total_amount'] += $sale['total_amount'];
    }
    $totals['tenant_amount_display'] = '$' . number_format($totals['tenant_amount'], 2);
    $totals['exhibitor_amount_display'] = '$' . number_format($totals['exhibitor_amount'], 2);
    $totals['total_amount_display'] = '$' . number_format($totals['total_amount'], 2);
    asort($exhibitors);

    if( isset($args['action']) && $args['action'] == 'receiptspdf' ) {
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
        // Load the invoice settings
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
        $rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_sapos_settings', 'tnid', $args['tnid'], 'ciniki.sapos', 'settings', '');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['settings']) ) {
            $sapos_settings = $rc['settings'];
        } else {
            $sapos_settings = array();
        }

        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'templates', 'donationreceipt');
        $rc = ciniki_ags_templates_donationreceipt($ciniki, $args['tnid'], $sales_ids, $tenant_settings, $sapos_settings);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.244', 'msg'=>'Unable to generate receipts', 'err'=>$rc['err']));
        }
    }

    $rsp = array('stat'=>'ok', 
        'start_date'=>$start_date_formatted, 
        'end_date'=>$end_date_formatted, 
        'exhibitor_id'=>(isset($args['exhibitor_id']) ? $args['exhibitor_id'] : 0), 
        'paid_status'=>(isset($args['paid_status']) ? $args['paid_status'] : 0), 
        'exhibitors'=>$exhibitors, 
        'sales'=>$sales,
        'totals'=>$totals,
        );
    return $rsp;
}
?>
