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
function ciniki_ags_sales($ciniki) {
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.sales');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Check if action is to mark item paid
    //
    if( isset($args['action']) && ($args['action'] == 'itempaid' || $args['action'] == 'itemnotpaid') 
        && isset($args['sale_id']) && $args['sale_id'] > 0 
        ) {
        $strsql = "SELECT id, exhibit_id, item_id, flags "
            . "FROM ciniki_ags_item_sales "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['sale_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.164', 'msg'=>'Unable to load sale', 'err'=>$rc['err']));
        }
        if( !isset($rc['item']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.165', 'msg'=>'Unable to find requested sale'));
        }
        $sale = $rc['item'];

        //
        // Load the exhibit
        //
        $strsql = "SELECT exhibits.id, "
            . "exhibits.flags, "
            . "IFNULL(eitems.inventory, '') AS inventory "
            . "FROM ciniki_ags_exhibits AS exhibits "
            . "LEFT JOIN ciniki_ags_exhibit_items AS eitems ON ("
                . "exhibits.id = eitems.exhibit_id "
                . "AND eitems.item_id = '" . ciniki_core_dbQuote($ciniki, $sale['item_id']) . "' "
                . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE exhibits.id = '" . ciniki_core_dbQuote($ciniki, $sale['exhibit_id']) . "' "
            . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibit');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.276', 'msg'=>'Unable to load exhibit', 'err'=>$rc['err']));
        }
        if( !isset($rc['exhibit']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.277', 'msg'=>'Unable to find requested exhibit'));
        }
        $exhibit = $rc['exhibit'];

        //
        // Mark sale as paid
        //
        if( $args['action'] == 'itempaid' && ($sale['flags']&0x02) == 0 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.ags.itemsale', $sale['id'], array('flags'=>($sale['flags']|0x02)), 0x07);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.175', 'msg'=>'', 'err'=>$rc['err']));
            }
            //
            // Check if should be removed from inventory
            //
            if( ($exhibit['flags']&0x1000) == 0x1000 && $exhibit['inventory'] != '' && $exhibit['inventory'] <= 0 ) {
                ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'exhibitItemRemove');
                $rc = ciniki_ags_exhibitItemRemove($ciniki, $args['tnid'], $sale['exhibit_id'], $sale['item_id']);
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.278', 'msg'=>'Unable to remove item', 'err'=>$rc['err']));
                }
            }
        }
        elseif( $args['action'] == 'itemnotpaid' && ($sale['flags']&0x02) == 0x02 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.ags.itemsale', $sale['id'], array('flags'=>($sale['flags']&0xFFFD)), 0x07);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.166', 'msg'=>'', 'err'=>$rc['err']));
            }
        }
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
        . "";
/*    if( isset($args['paid_status']) ) {
        if( $args['paid_status'] == 1 ) {
            $strsql .= "AND (sales.flags&0x02) = 0 ";
        } elseif( $args['paid_status'] == 2 ) {
            $strsql .= "AND (sales.flags&0x02) = 0x02 ";
        }
    }
    if( isset($args['exhibitor_id']) && $args['exhibitor_id'] != '' && $args['exhibitor_id'] > 0 ) {
        $strsql .= "AND items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' ";
    } */
    $strsql .= "ORDER BY sales.sell_date, items.code, items.name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'sales', 'fname'=>'id', 
            'fields'=>array('id', 'item_id', 'exhibitor_id', 'display_name', 'code', 'name', 'flags', 'sell_date', 'sell_date_display', 
                'quantity', 
                'tenant_amount', 'exhibitor_amount', 'total_amount',
                'tenant_amount_display', 'exhibitor_amount_display', 'total_amount_display', 'status_text'),
            'naprices'=>array('tenant_amount_display', 'exhibitor_amount_display', 'total_amount_display'),
            'utctotz'=>array('sell_date_display'=>array('format'=>$date_format, 'timezone'=>'UTC'),
            ),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.274', 'msg'=>'Unable to load exhibit sales', 'err'=>$rc['err']));
    }
    $sales = isset($rc['sales']) ? $rc['sales'] : array();

    $exhibitors = array('0'=>'All Exhibitors');
    $totals = array(
        'tenant_amount'=>0,
        'exhibitor_amount'=>0,
        'total_amount'=>0,
        );
    foreach($sales as $sid => $sale) {
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
