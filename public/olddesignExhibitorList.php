<?php
//
// Description
// -----------
// This method will return the list of Exhibitors for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Exhibitor for.
//
// Returns
// -------
//
function ciniki_ags_exhibitorList($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'exhibitor_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Exhibitor'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitorList');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Get the list of exhibitors
    //
    $strsql = "SELECT ciniki_ags_exhibitors.id, "
        . "ciniki_ags_exhibitors.customer_id, "
        . "ciniki_ags_exhibitors.display_name_override, "
        . "ciniki_ags_exhibitors.display_name, "
        . "ciniki_ags_exhibitors.permalink, "
        . "ciniki_ags_exhibitors.code, "
        . "ciniki_ags_exhibitors.status, "
        . "ciniki_ags_exhibitors.flags "
        . "FROM ciniki_ags_exhibitors "
        . "WHERE ciniki_ags_exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibitors', 'fname'=>'id', 
            'fields'=>array('id', 'customer_id', 'display_name_override', 'display_name', 'permalink', 'code', 'status', 'flags')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['exhibitors']) ) {
        $exhibitors = $rc['exhibitors'];
        $exhibitor_ids = array();
        foreach($exhibitors as $iid => $exhibitor) {
            $exhibitor_ids[] = $exhibitor['id'];
        }
    } else {
        $exhibitors = array();
        $exhibitor_ids = array();
    }

    $rsp = array('stat'=>'ok', 'exhibitors'=>$exhibitors, 'nplist'=>$exhibitor_ids);

    //
    // Check if exhibitor informations should be loaded
    //
    if( isset($args['exhibitor_id']) && $args['exhibitor_id'] > 0 ) {
        //
        // Get the exhibitor details
        //
        $strsql = "SELECT customer_id, "
            . "display_name, "
            . "code, "
            . "status, "
            . "flags "
            . "FROM ciniki_ags_exhibitors "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibitor');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.30', 'msg'=>'Unable to load exhibitor', 'err'=>$rc['err']));
        }
        if( !isset($rc['exhibitor']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.31', 'msg'=>'Unable to find requested exhibitor'));
        }
        $exhibitor = $rc['exhibitor'];
        
        //
        // Get the customer details
        //
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerDetails');
        $rc = ciniki_customers_hooks_customerDetails($ciniki, $args['tnid'], 
            array('customer_id'=>$exhibitor['customer_id'], 'phone'=>'yes', 'emails'=>'yes', 'address'=>'yes'));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $rsp['customer'] = $rc['customer'];
        $rsp['customer_details'] = $rc['details'];
        
        //
        // Get the list of exhibits the exhibitor has been a part of
        //
        $strsql = "SELECT ciniki_ags_exhibits.id, "    
            . "ciniki_ags_exhibits.name, "
            . "COUNT(ciniki_ags_exhibit_items.id) AS num_items "
            . "FROM ciniki_ags_items, ciniki_ags_exhibit_items, ciniki_ags_exhibits "
            . "WHERE ciniki_ags_items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
            . "AND ciniki_ags_items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_ags_items.id = ciniki_ags_exhibit_items.item_id "
            . "AND ciniki_ags_exhibit_items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_ags_exhibit_items.exhibit_id = ciniki_ags_exhibits.id "
            . "AND ciniki_ags_exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "GROUP BY ciniki_ags_exhibits.id "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'exhibits', 'fname'=>'id', 
                'fields'=>array('id', 'name', 'num_items'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.32', 'msg'=>'Unable to load ', 'err'=>$rc['err']));
        }
        $rsp['exhibits'] = isset($rc['exhibits']) ? $rc['exhibits'] : array();

        //
        // Get the catalog items for the exhibitor
        //
        $strsql = "SELECT ciniki_ags_exhibits.id, "    
            . "ciniki_ags_exhibits.name, "
            . "COUNT(ciniki_ags_exhibit_items.id) AS num_items "
            . "FROM ciniki_ags_items, ciniki_ags_exhibit_items, ciniki_ags_exhibits "
            . "WHERE ciniki_ags_items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
            . "AND ciniki_ags_items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_ags_items.id = ciniki_ags_exhibit_items.item_id "
            . "AND ciniki_ags_exhibit_items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_ags_exhibit_items.exhibit_id = ciniki_ags_exhibits.id "
            . "AND ciniki_ags_exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "GROUP BY ciniki_ags_exhibits.id "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'exhibits', 'fname'=>'id', 
                'fields'=>array('id', 'name', 'num_items'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.32', 'msg'=>'Unable to load ', 'err'=>$rc['err']));
        }
        $rsp['exhibits'] = isset($rc['exhibits']) ? $rc['exhibits'] : array();

        //
        // Get the sales for the exhibitor
        //
        $strsql = "SELECT sales.id, "    
            . "sales.item_id, "
            . "IFNULL(items.code, '') AS item_code, "
            . "IFNULL(items.name, '') AS item_name, "
            . "sales.exhibit_id, "
            . "exhibits.name AS exhibit_name, "
            . "sales.invoice_id, "
            . "IFNULL(invoices.invoice_number, '') AS invoice_number, "
            . "sales.status, "
            . "sales.sell_date, "
            . "sales.total_amount, "
            . "sales.customer_amount, "
            . "sales.tenant_amount "
            . "FROM ciniki_ags_items AS items "
            . "INNER JOIN ciniki_ags_item_sales AS sales ON ("
                . "items.id = sales.item_id "
                . "AND sales.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "LEFT JOIN ciniki_ags_exhibits AS exhibits ON ("
                . "sales.exhibit_id = exhibits.id "
                . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "LEFT JOIN ciniki_sapos_invoices AS invoices ON ("
                . "sales.invoice_id = invoices.id "
                . "AND invoices.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "ORDER BY sell_date "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'sales', 'fname'=>'id', 
                'fields'=>array('id', 'item_id', 'item_code', 'item_name', 'exhibit_id', 'exhibit_name', 
                    'invoice_id', 'invoice_number', 'status', 'sell_date', 'total_amount', 'customer_amount', 'tenant_amount'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.34', 'msg'=>'Unable to load ', 'err'=>$rc['err']));
        }
        $rsp['sales'] = isset($rc['sales']) ? $rc['sales'] : array();
    }

    return $rsp;
}
?>
