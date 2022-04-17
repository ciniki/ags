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
    // Load the module settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
    $rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_ags_settings', 'tnid', $args['tnid'], 'ciniki.ags', 'settings', '');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.158', 'msg'=>'Unable to load settings', 'err'=>$rc['err']));
    }
    $settings = isset($rc['settings']) ? $rc['settings'] : array();

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
    $mysql_date_format = ciniki_users_dateFormat($ciniki, 'mysql');

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
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.153', 'msg'=>'Unable to load sale', 'err'=>$rc['err']));
        }
        if( !isset($rc['item']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.154', 'msg'=>'Unable to find requested sale'));
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
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.239', 'msg'=>'Unable to load exhibit', 'err'=>$rc['err']));
        }
        if( !isset($rc['exhibit']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.240', 'msg'=>'Unable to find requested exhibit'));
        }
        $exhibit = $rc['exhibit'];

        //
        // Mark sale as paid
        //
        if( $args['action'] == 'itempaid' && ($sale['flags']&0x02) == 0 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.ags.itemsale', $sale['id'], array('flags'=>($sale['flags']|0x02)), 0x07);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.173', 'msg'=>'', 'err'=>$rc['err']));
            }
            //
            // Check if should be removed from inventory
            //
            if( ($exhibit['flags']&0x1000) == 0x1000 && $exhibit['inventory'] != '' && $exhibit['inventory'] <= 0 ) {
                ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'exhibitItemRemove');
                $rc = ciniki_ags_exhibitItemRemove($ciniki, $args['tnid'], $sale['exhibit_id'], $sale['item_id']);
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.241', 'msg'=>'Unable to remove item', 'err'=>$rc['err']));
                }
            }
        }
        elseif( $args['action'] == 'itemnotpaid' && ($sale['flags']&0x02) == 0x02 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.ags.itemsale', $sale['id'], array('flags'=>($sale['flags']&0xFFFD)), 0x07);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.155', 'msg'=>'', 'err'=>$rc['err']));
            }
        }
    }

    //
    // Check if already a participant
    //
    if( $args['participant_id'] == 0 
        && isset($args['exhibit_id']) && $args['exhibit_id'] > 0
        && isset($args['customer_id']) && $args['customer_id'] > 0 
        ) {
        $strsql = "SELECT participants.id "
            . "FROM ciniki_ags_participants AS participants, ciniki_ags_exhibitors AS exhibitors "
            . "WHERE participants.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
            . "AND participants.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND participants.exhibitor_id = exhibitors.id "
            . "AND exhibitors.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
            . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'participant');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.188', 'msg'=>'Unable to load participant', 'err'=>$rc['err']));
        }
        if( isset($rc['participant']) ) {
            $args['participant_id'] = $rc['participant']['id'];
        }
    }

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
            'message'=>'',
            'notes'=>'',
            'synopsis'=>'',
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
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.30', 'msg'=>'Unable to get customer code', 'err'=>$rc['err']));
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
            . "participants.message, "
            . "participants.notes, "
            . "exhibitors.synopsis "
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
                    'status', 'status_text', 'flags', 'message', 'notes', 'synopsis'),
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
        $participant['display_name_override'] = $participant['display_name'];

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
            'pending_payouts' => array(
                'num_items' => 0,
                'tenant_amount' => 0,
                'exhibitor_amount' => 0,
                'total_amount' => 0,
                ),
            'paid_sales' => array(
                'num_items' => 0,
                'tenant_amount' => 0,
                'exhibitor_amount' => 0,
                'total_amount' => 0,
                ),
            );

        //
        // Get the participant exhibit items and inventory
        //
        $strsql = "SELECT items.id AS item_id, "
            . "IFNULL(exhibit.id, 0) AS exhibit_item_id, "
            . "items.code, "
            . "items.name, "
            . "items.tag_info, "
            . "items.status, "
            . "items.flags, "
            . "items.flags AS flags_text, "
            . "(items.flags&0x06) AS online_flags_text, "
            . "items.unit_amount, "
            . "items.taxtype_id, "
            . "items.primary_image_id, "
            . "IFNULL(exhibit.fee_percent, items.fee_percent) AS fee_percent, "
            . "IFNULL(exhibit.inventory, 0) AS inventory, "
            . "IFNULL(tags.tag_name, '') AS categories "
            . "FROM ciniki_ags_items AS items "
            . "LEFT JOIN ciniki_ags_exhibit_items AS exhibit ON ("
                . "items.id = exhibit.item_id "
                . "AND exhibit.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $participant['exhibit_id']) . "' "
                . "AND exhibit.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "LEFT JOIN ciniki_ags_item_tags AS tags ON ("
                . "items.id = tags.item_id "
                . "AND tags.tag_type = 20 "
                . "AND tags.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $participant['exhibitor_id']) . "' "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'items', 'fname'=>'item_id', 
                'fields'=>array('item_id', 'exhibit_item_id', 'primary_image_id', 'code', 'name', 'status', 
                    'flags', 'flags_text', 'online_flags_text', 'unit_amount', 'fee_percent', 'taxtype_id', 'tag_info', 'inventory', 'categories'),
                'dlists'=>array('categories'=>', '),
                'flags'=>array('flags_text'=>$maps['item']['flags'], 
                    'online_flags_text'=>$maps['item']['flags']),
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
                $num_exhibit_items++;
            } else {
                $available[$iid]['unit_amount_display'] = '$' . number_format($item['unit_amount'], 2);
            }
        }
        $rsp['inventory'] = $inventory;
        $rsp['available'] = $available;

        $rsp['participant_details'][] = array('label'=>'# Items', 'value'=>$num_exhibit_items);

        //
        // Get the participant sales
        //
        $strsql = "SELECT sales.id, "
            . "items.code, "
            . "items.name, "
            . "sales.exhibit_id, "
            . "sales.flags, "
            . "sales.quantity, "
            . "DATE_FORMAT(sales.sell_date, '" . ciniki_core_dbQuote($ciniki, $mysql_date_format) . "') AS sell_date, "
            . "sales.tenant_amount, "
            . "sales.exhibitor_amount, "
            . "sales.total_amount, "
            . "sales.receipt_number, "
            . "IFNULL(invoices.billing_name, '') AS billing_name "
            . "FROM ciniki_ags_items AS items "
            . "INNER JOIN ciniki_ags_item_sales AS sales ON ("
                . "items.id = sales.item_id "
                . "AND sales.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $participant['exhibit_id']) . "' "
                . "AND sales.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "LEFT JOIN ciniki_sapos_invoices AS invoices ON ("
                . "sales.invoice_id = invoices.id "
                . "AND invoices.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $participant['exhibitor_id']) . "' "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'sales', 'fname'=>'id', 'fields'=>array('id', 'exhibit_id', 'sell_date', 'code', 'name', 'quantity',
                'flags', 'tenant_amount', 'exhibitor_amount', 'total_amount', 'receipt_number', 'billing_name'),
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
                $totals['paid_sales']['num_items'] += $sale['quantity'];
                $totals['paid_sales']['tenant_amount'] += $sale['tenant_amount'];
                $totals['paid_sales']['exhibitor_amount'] += $sale['exhibitor_amount'];
                $totals['paid_sales']['total_amount'] += $sale['total_amount'];
            } else {
                $rsp['pending_payouts'][] = $sale;
                $totals['pending_payouts']['num_items'] += $sale['quantity'];
                $totals['pending_payouts']['tenant_amount'] += $sale['tenant_amount'];
                $totals['pending_payouts']['exhibitor_amount'] += $sale['exhibitor_amount'];
                $totals['pending_payouts']['total_amount'] += $sale['total_amount'];
            }
        }

        //
        // Format totals
        //
        $totals['paid_sales']['tenant_amount_display'] = '$' . number_format($totals['paid_sales']['tenant_amount'], 2);
        $totals['paid_sales']['exhibitor_amount_display'] = '$' . number_format($totals['paid_sales']['exhibitor_amount'], 2);
        $totals['paid_sales']['total_amount_display'] = '$' . number_format($totals['paid_sales']['total_amount'], 2);
        $totals['pending_payouts']['tenant_amount_display'] = '$' . number_format($totals['pending_payouts']['tenant_amount'], 2);
        $totals['pending_payouts']['exhibitor_amount_display'] = '$' . number_format($totals['pending_payouts']['exhibitor_amount'], 2);
        $totals['pending_payouts']['total_amount_display'] = '$' . number_format($totals['pending_payouts']['total_amount'], 2);
        $rsp['totals'] = $totals;

        //
        // Get the participant history
        //
        $strsql = "SELECT logs.id, "
            . "DATE_FORMAT(DATE(logs.log_date), '" . ciniki_core_dbQuote($ciniki, $mysql_date_format) . "') AS log_date, "
            . "users.display_name, "
            . "logs.action, "
            . "logs.quantity, "
            . "logs.item_id, "
            . "items.code, "
            . "items.name AS item_name, "
            . "exhibits.name AS exhibit_name "
            . "FROM ciniki_ags_item_logs AS logs "
            . "INNER JOIN ciniki_ags_items AS items ON (" 
                . "logs.item_id = items.id "
                . "AND items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $participant['exhibitor_id']) . "' "
                . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "LEFT JOIN ciniki_users AS users ON (" 
                . "logs.user_id = users.id "
                . ") "
            . "LEFT JOIN ciniki_ags_exhibits AS exhibits ON (" 
                . "logs.actioned_id = exhibits.id "
                . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE logs.actioned_id = '" . ciniki_core_dbQuote($ciniki, $participant['exhibit_id']) . "' "
            . "AND logs.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "ORDER BY logs.log_date DESC, logs.item_id, logs.action "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
//            array('container'=>'logdates', 'fname'=>'log_date', 
//                'fields'=>array('log_date'),
//                ),
            array('container'=>'logs', 'fname'=>'id', 
                'fields'=>array('log_date', 'action', 'quantity', 'display_name', 'item_id', 'code', 'item_name', 'exhibit_name'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.170', 'msg'=>'Unable to load logs', 'err'=>$rc['err']));
        }
        $rsp['logs'] = isset($rc['logs']) ? $rc['logs'] : array();
        foreach($rsp['logs'] as $lid => $log) {
            if( $log['action'] == 10 ) {
                $rsp['logs'][$lid]['action_text'] = 'Item Added';
            }
            elseif( $log['action'] == 50 ) {
                if( $log['quantity'] < 0 ) {
                    $rsp['logs'][$lid]['action_text'] = 'Update Inventory';
                } else {
                    $rsp['logs'][$lid]['action_text'] = 'Update Inventory';
                }
            }
            elseif( $log['action'] == 60 ) {
                $rsp['logs'][$lid]['action_text'] = 'Sold';
            }
            elseif( $log['action'] == 90 ) {
                $rsp['logs'][$lid]['action_text'] = 'Item Removed';
            }
        }
    }

    //
    // Check if form submissions should be loaded
    //
    if( ciniki_core_checkModuleActive($ciniki, 'ciniki.forms') 
        && isset($participant['customer_id']) 
        && $participant['customer_id'] > 0 
        ) {
        $strsql = "SELECT submissions.id, "
            . "forms.name "
            . "FROM ciniki_form_submissions AS submissions "
            . "INNER JOIN ciniki_forms AS forms ON ("
                . "submissions.form_id = forms.id "
                . "AND forms.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE submissions.customer_id = '" . ciniki_core_dbQuote($ciniki, $participant['customer_id']) . "' "
            . "AND submissions.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND submissions.status = 90 "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'submissions', 'fname'=>'id', 'fields'=>array('id', 'name')),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.248', 'msg'=>'Unable to load submissions', 'err'=>$rc['err']));
        }
        $rsp['participant']['submissions'] = isset($rc['submissions']) ? $rc['submissions'] : array();

        //
        // Also load the default fee percent
        //
        $rsp['participant']['fee_percent'] = (isset($settings['defaults-item-fee-percent']) ? $settings['defaults-item-fee-percent'] : '');
    }

    return $rsp;
}
?>
