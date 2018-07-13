<?php
//
// Description
// ===========
// This method will return all the information about an participant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the participant is attached to.
// participant_id:          The ID of the participant to get the details for.
//
// Returns
// -------
//
function ciniki_ags_participantGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'participant_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Participant'),
        'exhibit_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Exhibit'),
        'customer_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Customer'),
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.participantGet');
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
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    //
    // Return default for new Participant
    //
    if( $args['participant_id'] == 0 ) {
        $participant = array('id'=>0,
            'exhibit_id'=>(isset($args['exhibit_id']) ? $args['exhibit_id'] : $args['exhibit_id']),
            'customer_id'=>(isset($args['customer_id']) ? $args['customer_id'] : $args['customer_id']),
            'exhibitor_id'=>0,
            'status'=>'10',
            'flags'=>'0',
            'notes'=>'',
        );

        //
        // Get the customer details
        //
        if( isset($args['customer_id']) ) {
            $strsql = "SELECT id, display_name, code "
                . "FROM ciniki_ags_exhibitors "
                . "WHERE customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
                . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibitor');
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.99', 'msg'=>'Unable to load exhibitor', 'err'=>$rc['err']));
            }
            if( isset($rc['exhibitor']['display_name']) ) {
                $participant['exhibitor_id'] = $rc['exhibitor']['id'];
                $participant['display_name_override'] = $rc['exhibitor']['display_name'];
                $participant['code'] = $rc['exhibitor']['code'];
            } else {
                $participant['exhibitor_id'] = 0;
                $strsql = "SELECT display_name "
                    . "FROM ciniki_customers "
                    . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
                    . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                    . "";
                $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'customer');
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.112', 'msg'=>'Unable to load customer', 'err'=>$rc['err']));
                }
                if( !isset($rc['customer']) ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.113', 'msg'=>'Unable to find requested customer'));
                }
                $participant['display_name_override'] = $rc['customer']['display_name'];
               
                //
                // Figure out what the code should be for the customer
                //
                ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'exhibitorCode');
                $rc = ciniki_ags_exhibitorCode($ciniki, $args['tnid'], $args['customer_id']);
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.16', 'msg'=>'Unable to get customer code', 'err'=>$rc['err']));
                }
                $participant['code'] = $rc['code'];
            }
        } 
        
        $rsp = array('stat'=>'ok', 'participant'=>$participant);
    }

    //
    // Get the details for an existing Participant
    //
    else {
        $strsql = "SELECT participants.id, "
            . "participants.exhibit_id, "
            . "participants.exhibitor_id, "
            . "exhibitors.customer_id, "
            . "exhibitors.code, "
            . "exhibitors.display_name, "
            . "participants.status, "
            . "participants.status AS status_text, "
            . "participants.flags, "
            . "participants.notes "
            . "FROM ciniki_ags_participants AS participants "
            . "LEFT JOIN ciniki_ags_exhibitors AS exhibitors ON ("
                . "participants.exhibitor_id = exhibitors.id "
                . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE participants.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND participants.id = '" . ciniki_core_dbQuote($ciniki, $args['participant_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'participants', 'fname'=>'id', 
                'fields'=>array('id', 'exhibit_id', 'exhibitor_id', 'customer_id', 'display_name', 'code',
                    'status', 'status_text', 'flags', 'notes'),
                'maps'=>array('status_text'=>$maps['participant']['status']),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.108', 'msg'=>'Participant not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['participants'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.109', 'msg'=>'Unable to find Participant'));
        }
        $participant = $rc['participants'][0];

        $rsp = array('stat'=>'ok', 'participant'=>$participant);

        //
        // Setup the participant details
        //
        $rsp['participant_details'] = array(
            array('label'=>'Name', 'value'=>$participant['display_name']),
            array('label'=>'Status', 'value'=>$participant['status_text']),
            );
        $num_exhibit_items = 0;

        //
        // Get the exhibit name
        //
        $strsql = "SELECT name "
            . "FROM ciniki_ags_exhibits "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $participant['exhibit_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibit');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.17', 'msg'=>'Unable to load exhibit', 'err'=>$rc['err']));
        }
        if( !isset($rc['exhibit']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.18', 'msg'=>'Unable to find requested exhibit'));
        }
        $rsp['exhibit_details'] = array(array('label'=>'Name', 'value'=>$rc['exhibit']['name'])); 

        //
        // Get the participant contact details
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerDetails2');
        $rc = ciniki_customers_hooks_customerDetails2($ciniki, $args['tnid'], 
            array('customer_id'=>$participant['customer_id'], 'name'=>'no', 'phones'=>'yes', 'emails'=>'yes', 'addresses'=>'yes'));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $rsp['customer'] = $rc['customer'];
        $rsp['contact_details'] = $rc['details'];

        //
        // Setup totals columns
        //
        $totals = array(
            'num_inventory_items' => 0,
            'num_available_items' => 0,
            'pending_payouts_tenant_amount' => 0,
            'pending_payouts_exhibitor_amount' => 0,
            'pending_payouts_total_amount' => 0,
            'paid_sales_tenant_amount' => 0,
            'paid_sales_exhibitor_amount' => 0,
            'paid_sales_total_amount' => 0,
            );

        //
        // Get the participant exhibit items and inventory
        //
        $strsql = "SELECT items.id AS item_id, "
            . "IFNULL(exhibit.id, 0) AS exhibit_item_id, "
            . "items.code, "
            . "items.name, "
            . "items.status, "
            . "items.flags, "
            . "items.unit_amount, "
            . "IFNULL(exhibit.fee_percent, items.fee_percent) AS fee_percent, "
            . "IFNULL(exhibit.inventory, 0) AS inventory "
            . "FROM ciniki_ags_items AS items "
            . "LEFT JOIN ciniki_ags_exhibit_items AS exhibit ON ("
                . "items.id = exhibit.item_id "
                . "AND exhibit.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $participant['exhibit_id']) . "' "
                . "AND exhibit.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $participant['exhibitor_id']) . "' "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'items', 'fname'=>'item_id', 
                'fields'=>array('item_id', 'exhibit_item_id', 'code', 'name', 'status', 'flags', 'unit_amount', 'fee_percent', 'inventory'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.14', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
        }
        $available = isset($rc['items']) ? $rc['items'] : array();
        $inventory = array();
        foreach($available as $iid => $item) {  
            if( $item['exhibit_item_id'] > 0 ) {
                $item['unit_amount_display'] = '$' . number_format($item['unit_amount'], 2);
                $inventory[] = $item;
                unset($available[$iid]);
            } else {
                $available[$iid]['unit_amount_display'] = '$' . number_format($item['unit_amount'], 2);
                $num_exhibit_items++;
            }
        }
        $rsp['inventory'] = $inventory;
        $rsp['available'] = $available;

        $rsp['participant_details'][] = array('label'=>'# Items', 'value'=>$num_exhibit_items);

        //
        // Get the participant sales
        //
        $strsql = "SELECT sales.id, "
            . "sales.exhibit_id, "
            . "sales.flags, "
            . "sales.sell_date, "
            . "sales.tenant_amount, "
            . "sales.exhibitor_amount, "
            . "sales.total_amount "
            . "FROM ciniki_ags_items AS items "
            . "INNER JOIN ciniki_ags_item_sales AS sales ON ("
                . "items.id = sales.item_id "
                . "AND sales.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $participant['exhibit_id']) . "' "
                . "AND sales.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $participant['exhibitor_id']) . "' "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'sales', 'fname'=>'id', 'fields'=>array('id', 'exhibit_id', 'sell_date', 
                'flags', 'tenant_amount', 'exhibitor_amount', 'total_amount'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.15', 'msg'=>'Unable to load sales', 'err'=>$rc['err']));
        }
        $sales = isset($rc['sales']) ? $rc['sales'] : array();
        $rsp['pending_payouts'] = array();
        $rsp['paid_sales'] = array();
        foreach($sales as $sid => $sale) {
            $sale['tenant_amount_display'] = '$' . number_format($sale['tenant_amount'], 2);
            $sale['exhibitor_amount_display'] = '$' . number_format($sale['exhibitor_amount'], 2);
            $sale['total_amount_display'] = '$' . number_format($sale['total_amount'], 2);
            //
            // Add to either the paid sales or pending sales tables
            //
            if( ($sale['flags']&0x02) == 0x02 ) {
                $rsp['paid_sales'][] = $sale;
                $totals['paid_sales_tenant_amount'] += $sale['tenant_amount'];
                $totals['paid_sales_exhibitor_amount'] += $sale['exhibitor_amount'];
                $totals['paid_sales_total_amount'] += $sale['total_amount'];
            } else {
                $rsp['pending_payouts'][] = $sale;
                $totals['pending_payouts_tenant_amount'] += $sale['tenant_amount'];
                $totals['pending_payouts_exhibitor_amount'] += $sale['exhibitor_amount'];
                $totals['pending_payouts_total_amount'] += $sale['total_amount'];
            }
        }

        //
        // Format totals
        //
        $totals['paid_sales_tenant_amount_display'] = '$' . number_format($totals['paid_sales_tenant_amount'], 2);
        $totals['paid_sales_exhibitor_amount_display'] = '$' . number_format($totals['paid_sales_exhibitor_amount'], 2);
        $totals['paid_sales_total_amount_display'] = '$' . number_format($totals['paid_sales_total_amount'], 2);
        $totals['pending_payouts_tenant_amount_display'] = '$' . number_format($totals['pending_payouts_tenant_amount'], 2);
        $totals['pending_payouts_exhibitor_amount_display'] = '$' . number_format($totals['pending_payouts_exhibitor_amount'], 2);
        $totals['pending_payouts_total_amount_display'] = '$' . number_format($totals['pending_payouts_total_amount'], 2);

    }

    return $rsp;
}
?>
