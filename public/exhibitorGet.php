<?php
//
// Description
// ===========
// This method will return all the information about an exhibitor.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the exhibitor is attached to.
// exhibitor_id:          The ID of the exhibitor to get the details for.
//
// Returns
// -------
//
function ciniki_ags_exhibitorGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'exhibitor_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Exhibitor'),
        'customer_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Customer'),
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitorGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
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
    // Load tenant settings
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

    $webupdates = array();

    //
    // Check if action is to mark item paid
    //
    if( isset($args['action']) && ($args['action'] == 'itempaid' || $args['action'] == 'itemnotpaid') 
        && isset($args['sale_id']) && $args['sale_id'] > 0 
        ) {
        $strsql = "SELECT id, flags "
            . "FROM ciniki_ags_item_sales "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['sale_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.137', 'msg'=>'Unable to load sale', 'err'=>$rc['err']));
        }
        if( !isset($rc['item']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.138', 'msg'=>'Unable to find requested sale'));
        }
        $sale = $rc['item'];

        //
        // Mark sale as paid
        //
        if( $args['action'] == 'itempaid' && ($sale['flags']&0x02) == 0 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.ags.itemsale', $sale['id'], array('flags'=>($sale['flags']|0x02)), 0x07);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.171', 'msg'=>'', 'err'=>$rc['err']));
            }
        }
        elseif( $args['action'] == 'itemnotpaid' && ($sale['flags']&0x02) == 0x02 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.ags.itemsale', $sale['id'], array('flags'=>($sale['flags']&0xFFFD)), 0x07);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.139', 'msg'=>'', 'err'=>$rc['err']));
            }
        }
    }

    //
    // Return default for new Exhibitor
    //
    if( $args['exhibitor_id'] == 0 ) {
        //
        // Lookup the customer
        //
        if( isset($args['customer_id']) ) {
            $strsql = "SELECT display_name "
                . "FROM ciniki_customers "
                . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
                . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'customer');
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.28', 'msg'=>'Unable to load customer', 'err'=>$rc['err']));
            }
            if( !isset($rc['customer']) ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.29', 'msg'=>'Unable to find requested customer'));
            }
            $customer = $rc['customer'];
           
            //
            // Figure out what the code should be for the customer
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'exhibitorCode');
            $rc = ciniki_ags_exhibitorCode($ciniki, $args['tnid'], $args['customer_id']);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.16', 'msg'=>'Unable to get customer code', 'err'=>$rc['err']));
            }
            $customer['code'] = $rc['code'];
        }
        
        $exhibitor = array('id'=>0,
            'customer_id'=> (isset($args['customer_id']) ? $args['customer_id'] : 0),
            'display_name_override' => (isset($customer['display_name']) ? $customer['display_name'] : ''),
            'display_name' => (isset($customer['display_name']) ? $customer['display_name'] : ''),
            'permalink'=>'',
            'code' => (isset($customer['code']) ? $customer['code'] : ''),
            'barcode_message' => '',
            'status'=>'30',
            'flags'=>'0',
        );
    }

    //
    // Get the details for an existing Exhibitor
    //
    else {
        
        $strsql = "SELECT ciniki_ags_exhibitors.id, "
            . "ciniki_ags_exhibitors.customer_id, "
            . "ciniki_ags_exhibitors.display_name_override, "
            . "ciniki_ags_exhibitors.display_name, "
            . "ciniki_ags_exhibitors.profile_name, "
            . "ciniki_ags_exhibitors.permalink, "
            . "ciniki_ags_exhibitors.code, "
            . "ciniki_ags_exhibitors.barcode_message, "
            . "ciniki_ags_exhibitors.status, "
            . "ciniki_ags_exhibitors.flags, "
            . "ciniki_ags_exhibitors.primary_image_id, "
            . "ciniki_ags_exhibitors.synopsis, "
            . "ciniki_ags_exhibitors.fullbio, "
            . "ciniki_ags_exhibitors.requested_changes "
            . "FROM ciniki_ags_exhibitors "
            . "WHERE ciniki_ags_exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_ags_exhibitors.id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'exhibitors', 'fname'=>'id', 
                'fields'=>array('id', 'customer_id', 'display_name_override', 'display_name', 'profile_name',
                    'permalink', 'code', 'barcode_message',
                    'status', 'flags', 'primary_image_id', 'synopsis', 'fullbio', 'requested_changes'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.101', 'msg'=>'Exhibitor not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['exhibitors'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.102', 'msg'=>'Unable to find Exhibitor'));
        }
        $exhibitor = $rc['exhibitors'][0];

        if( $exhibitor['display_name_override'] == '' ) {
            $exhibitor['display_name_override'] = $exhibitor['display_name'];
        }
        if( $exhibitor['requested_changes'] != '' && $exhibitor['requested_changes'] != '{}' ) {
            $webupdates = array(array(
                'id' => $exhibitor['id'],
                'code' => '',
                'name' => 'Exhibitor Profile',
                'actioncode' => 'profileupdate',
                'unit_amount' => '',
                'fee_percent' => '',
                'status_text' => 'Updates',
                ));
            $exhibitor['requested_changes'] = json_decode($exhibitor['requested_changes'], true);
        }
    }

    $rsp = array('stat'=>'ok', 'exhibitor'=>$exhibitor);

    //
    // Setup the exhibitor details
    //
    $rsp['exhibitor_details'] = array(
        array('label' => 'Name', 'value' => $exhibitor['display_name']),
        array('label' => 'Code', 'value' => $exhibitor['code']),
        );

    //
    // Lookup the details of the customer
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerDetails');
    $rc = ciniki_customers_hooks_customerDetails($ciniki, $args['tnid'], array(
        'customer_id' => $exhibitor['customer_id'], 
        'name' => 'no', 
        'phones' => 'yes', 
        'emails' => 'yes', 
        'addresses' => 'yes',
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $rsp['customer'] = $rc['customer'];
    $rsp['customer_details'] = $rc['details'];

    //
    // Get the exhibits the exhibitor has been a part of
    //
    $strsql = "SELECT exhibits.id, "
        . "exhibits.name, "
        . "COUNT(eitems.id) AS num_items "
        . "FROM ciniki_ags_items AS items "
        . "INNER JOIN ciniki_ags_exhibit_items AS eitems ON ("
            . "items.id = eitems.item_id "
            . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "INNER JOIN ciniki_ags_exhibits AS exhibits ON ("
            . "eitems.exhibit_id = exhibits.id "
            . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibits', 'fname'=>'id', 'fields'=>array('id', 'name', 'num_items')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.36', 'msg'=>'Unable to load exhibits', 'err'=>$rc['err']));
    }
    $exhibits = isset($rc['exhibits']) ? $rc['exhibits'] : array();

    //
    // Setup each exhibit with totals
    //
    foreach($exhibits as $eid => $exhibit) {
        $exhibits[$eid]['num_sales'] = 0;
        $exhibits[$eid]['tenant_amount'] = 0;
        $exhibits[$eid]['exhibitor_amount'] = 0;
        $exhibits[$eid]['total_amount'] = 0;
    }

    //
    // Get the list of items currently in their catalog
    //
    $strsql = "SELECT items.id, "
        . "items.code, "
        . "items.name, "
        . "items.status, "
        . "items.flags, "
        . "items.unit_amount, "
        . "items.fee_percent, "
        . "items.requested_changes "
        . "FROM ciniki_ags_items AS items "
        . "WHERE items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "ORDER BY items.code, items.name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'code', 'name', 'status', 'flags', 
                'unit_amount', 'fee_percent', 'requested_changes',
                ),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.40', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
    }
    $items = isset($rc['items']) ? $rc['items'] : array();
    foreach($items as $iid => $item) {
        $items[$iid]['num_sold'] = 0;
        $items[$iid]['tenant_amount'] = 0;
        $items[$iid]['exhibitor_amount'] = 0;
        $items[$iid]['total_amount'] = 0;
    }

    //
    // Get the list of sales
    //
    $strsql = "SELECT sales.id, "
        . "sales.exhibit_id, "
        . "sales.item_id, "
        . "sales.flags, "
        . "exhibits.name AS exhibit_name, "
        . "items.code, "
        . "items.name, "
        . "DATE_FORMAT(sales.sell_date, '" . ciniki_core_dbQuote($ciniki, $mysql_date_format) . "') AS sell_date, "
        . "sales.tenant_amount, "
        . "sales.exhibitor_amount, "
        . "sales.total_amount, "
        . "sales.receipt_number "
        . "FROM ciniki_ags_items AS items "
        . "INNER JOIN ciniki_ags_item_sales AS sales ON ("
            . "items.id = sales.item_id "
            . "AND sales.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "LEFT JOIN ciniki_ags_exhibits AS exhibits ON ("
            . "sales.exhibit_id = exhibits.id "
            . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "ORDER BY sell_date DESC "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'sales', 'fname'=>'id', 'fields'=>array('id', 'exhibit_id', 'item_id', 'sell_date', 
            'code', 'name',  'exhibit_name', 
            'flags', 'tenant_amount', 'exhibitor_amount', 'total_amount', 'receipt_number'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.37', 'msg'=>'Unable to load sales', 'err'=>$rc['err']));
    }
    $sales = isset($rc['sales']) ? $rc['sales'] : array();

    //
    // Add the sales to the exhibits list
    //
    $rsp['pending_payouts'] = array();
    $rsp['paid_sales'] = array();
    $rsp['paid_sales_totals'] = array(
        'tenant_amount' => 0,
        'exhibitor_amount' => 0,
        'total_amount' => 0,
        );
    $rsp['pending_payouts_totals'] = array(
        'tenant_amount' => 0,
        'exhibitor_amount' => 0,
        'total_amount' => 0,
        );
    foreach($sales as $sid => $sale) {
        if( isset($exhibits[$sale['exhibit_id']]) ) {
            $exhibits[$sale['exhibit_id']]['num_sales']++;
            $exhibits[$sale['exhibit_id']]['tenant_amount'] += $sale['tenant_amount'];
            $exhibits[$sale['exhibit_id']]['exhibitor_amount'] += $sale['exhibitor_amount'];
            $exhibits[$sale['exhibit_id']]['total_amount'] += $sale['total_amount'];
//            $sale['exhibit_name'] = $exhibits[$sale['exhibit_id']]['name'];
        }
        if( isset($items[$sale['item_id']]) ) {
            $items[$sale['item_id']]['num_sold']++;
            $items[$sale['item_id']]['tenant_amount'] += $sale['tenant_amount'];
            $items[$sale['item_id']]['exhibitor_amount'] += $sale['exhibitor_amount'];
            $items[$sale['item_id']]['total_amount'] += $sale['total_amount'];
        }
        $sale['tenant_amount_display'] = '$' . number_format($sale['tenant_amount'], 2);
        $sale['exhibitor_amount_display'] = '$' . number_format($sale['exhibitor_amount'], 2);
        $sale['total_amount_display'] = '$' . number_format($sale['total_amount'], 2);
        if( ($sale['flags']&0x02) == 0x02 ) {
            $rsp['paid_sales'][] = $sale;
            $rsp['paid_sales_totals']['tenant_amount'] += $sale['tenant_amount'];
            $rsp['paid_sales_totals']['exhibitor_amount'] += $sale['exhibitor_amount'];
            $rsp['paid_sales_totals']['total_amount'] += $sale['total_amount'];
        } else {
            $rsp['pending_payouts'][] = $sale;
            $rsp['pending_payouts_totals']['tenant_amount'] += $sale['tenant_amount'];
            $rsp['pending_payouts_totals']['exhibitor_amount'] += $sale['exhibitor_amount'];
            $rsp['pending_payouts_totals']['total_amount'] += $sale['total_amount'];
        }
    }
    //
    // Format numbers for the totals in sales
    //
    $rsp['pending_payouts_totals']['tenant_amount_display'] = ($rsp['pending_payouts_totals']['tenant_amount'] != 0 ? '$' . number_format($rsp['pending_payouts_totals']['tenant_amount'], 2) : '');
    $rsp['pending_payouts_totals']['exhibitor_amount_display'] = ($rsp['pending_payouts_totals']['exhibitor_amount'] != 0 ? '$' . number_format($rsp['pending_payouts_totals']['exhibitor_amount'], 2) : '');
    $rsp['pending_payouts_totals']['total_amount_display'] = ($rsp['pending_payouts_totals']['total_amount'] != 0 ? '$' . number_format($rsp['pending_payouts_totals']['total_amount'], 2) : '');
    $rsp['paid_sales_totals']['tenant_amount_display'] = ($rsp['paid_sales_totals']['tenant_amount'] != 0 ? '$' . number_format($rsp['paid_sales_totals']['tenant_amount'], 2) : '');
    $rsp['paid_sales_totals']['exhibitor_amount_display'] = ($rsp['paid_sales_totals']['exhibitor_amount'] != 0 ? '$' . number_format($rsp['paid_sales_totals']['exhibitor_amount'], 2) : '');
    $rsp['paid_sales_totals']['total_amount_display'] = ($rsp['paid_sales_totals']['total_amount'] != 0 ? '$' . number_format($rsp['paid_sales_totals']['total_amount'], 2) : '');

    //
    // Format numbers for exhibits
    //
    foreach($exhibits as $eid => $exhibit) {
        if( $exhibit['tenant_amount'] > 0 ) {
            $exhibits[$eid]['tenant_amount_display'] = '$' . number_format($exhibit['tenant_amount'], 2);
        } else {
            $exhibits[$eid]['tenant_amount_display'] = '';
        }
        if( $exhibit['exhibitor_amount'] > 0 ) {
            $exhibits[$eid]['exhibitor_amount_display'] = '$' . number_format($exhibit['exhibitor_amount'], 2);
        } else {
            $exhibits[$eid]['exhibitor_amount_display'] = '';
        }
        if( $exhibit['total_amount'] > 0 ) {
            $exhibits[$eid]['total_amount_display'] = '$' . number_format($exhibit['total_amount'], 2);
        } else {
            $exhibits[$eid]['total_amount_display'] = '';
        }
    }
    //
    // Format numbers for items
    //
    foreach($items as $iid => $item) {
        $items[$iid]['status_text'] = $maps['item']['status'][$item['status']];
        $items[$iid]['unit_amount_display'] = '$' . number_format($item['unit_amount'], 2);
        $items[$iid]['fee_percent_display'] = (float)($item['fee_percent']*100) . '%';

        if( $item['tenant_amount'] > 0 ) {
            $items[$iid]['tenant_amount_display'] = '$' . number_format($item['tenant_amount'], 2);
        } else {
            $items[$iid]['tenant_amount_display'] = '';
        }
        if( $item['exhibitor_amount'] > 0 ) {
            $items[$iid]['exhibitor_amount_display'] = '$' . number_format($item['exhibitor_amount'], 2);
        } else {
            $items[$iid]['exhibitor_amount_display'] = '';
        }
        if( $item['total_amount'] > 0 ) {
            $items[$iid]['total_amount_display'] = '$' . number_format($item['total_amount'], 2);
        } else {
            $items[$iid]['total_amount_display'] = '';
        }
        if( $item['status'] == 30 || $item['requested_changes'] != '' ) {
            if( $items[$iid]['status'] > 30 ) {
                $items[$iid]['status_text'] = 'Updates';
            }
            $webupdates[] = $items[$iid];
            unset($items[$iid]);
        }
    }
    $rsp['exhibits'] = array_values($exhibits);
    $rsp['items'] = array_values($items);
    if( count($webupdates) > 0 ) {
        $rsp['webupdates'] = $webupdates;
    }

    return $rsp;
}
?>
