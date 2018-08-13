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
function ciniki_ags_exhibitGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'exhibit_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Exhibit'),
        'locations'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Locations'),
        'types'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Types'),
        'details'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Extra Details'),
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

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
    // Load maps
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'maps');
    $rc = ciniki_ags_maps($ciniki);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];
    
    //
    // Return default for new Exhibit
    //
    if( $args['exhibit_id'] == 0 ) {
        $exhibit = array('id'=>0,
            'name'=>'',
            'permalink'=>'',
            'location_id'=>'0',
            'status'=>'50',
            'flags'=>0x01,
            'start_date'=>'',
            'end_date'=>'',
            'primary_image_id'=>'0',
            'synopsis'=>'',
            'description'=>'',
        );
    }

    //
    // Get the details for an existing Exhibit
    //
    else {
        $strsql = "SELECT exhibits.id, "
            . "exhibits.name, "
            . "exhibits.permalink, "
            . "exhibits.location_id, "
            . "IFNULL(locations.name, '') AS location_name, "
            . "exhibits.status, "
            . "exhibits.status AS status_text, "
            . "exhibits.flags, "
            . "exhibits.start_date, "
            . "exhibits.end_date, "
            . "exhibits.primary_image_id, "
            . "exhibits.synopsis, "
            . "exhibits.description "
            . "FROM ciniki_ags_exhibits AS exhibits "
            . "LEFT JOIN ciniki_ags_locations AS locations ON ("
                . "exhibits.location_id = locations.id "
                . "AND locations.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND exhibits.id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'exhibits', 'fname'=>'id', 
                'fields'=>array('name', 'permalink', 'location_id', 'location_name', 'status', 'status_text', 
                    'flags', 'start_date', 'end_date', 'primary_image_id', 'synopsis', 'description'),
                'maps'=>array('status_text'=>$maps['exhibit']['status']),
                'utctotz'=>array('start_date'=>array('timezone'=>'UTC', 'format'=>$date_format),
                    'end_date'=>array('timezone'=>'UTC', 'format'=>$date_format)),                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.62', 'msg'=>'Exhibit not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['exhibits'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.63', 'msg'=>'Unable to find Exhibit'));
        }
        $exhibit = $rc['exhibits'][0];

        //
        // Get the types
        //
        if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.ags', 0x01) ) {
            $strsql = "SELECT tag_type, tag_name AS lists "
                . "FROM ciniki_ags_exhibit_tags "
                . "WHERE exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
                . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . "ORDER BY tag_type, tag_name "
                . "";
            $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
                array('container'=>'tags', 'fname'=>'tag_type', 'name'=>'tags',
                    'fields'=>array('tag_type', 'lists'), 'dlists'=>array('lists'=>'::')),
                ));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            if( isset($rc['tags']) ) {
                foreach($rc['tags'] as $tags) {
                    if( $tags['tag_type'] == 20 ) {
                        $exhibit['types'] = $tags['lists'];
                    }
                }
            }
        }
    }

    $rsp = array('stat'=>'ok', 'exhibit'=>$exhibit);

    //
    // Build the list of exhibit details
    //
    $rsp['exhibit_details'] = array(
        array('label'=>'Exhibit', 'value'=>$exhibit['name']),
        );
    if( $exhibit['end_date'] != '' ) {
        $rsp['exhibit_details'][] = array('label'=>'Dates', 'value'=>$exhibit['start_date'] . ' - ' . $exhibit['end_date']);
    } else {
        $rsp['exhibit_details'][] = array('label'=>'Dates', 'value'=>$exhibit['start_date']);
    }
    if( isset($exhibit['location_name']) ) {
        $rsp['exhibit_details'][] = array('label'=>'Location', 'value'=>$exhibit['location_name']);
    }
    if( isset($exhibit['types']) ) {
        $types = explode('::', $exhibit['types']);
        if( count($types) > 1 ) {
            $rsp['exhibit_details'][] = array('label'=>'Types', 'value'=>implode(',', $types));
        } elseif( count($types) == 1 ) {
            $rsp['exhibit_details'][] = array('label'=>'Type', 'value'=>$exhibit['types']);
        }
    }

    //
    // Get the list of locations
    //
    if( isset($args['locations']) && $args['locations'] == 'yes' ) {
        $strsql = "SELECT id, name "
            . "FROM ciniki_ags_locations "
            . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "ORDER BY name "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'locations', 'fname'=>'id', 'fields'=>array('id', 'name')),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.91', 'msg'=>'Unable to load locations', 'err'=>$rc['err']));
        }
        $rsp['locations'] = isset($rc['locations']) ? $rc['locations'] : array();
    }

    //
    // Check if all tags should be returned
    //
    if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.ags', 0x01) && isset($args['types']) && $args['types'] == 'yes' ) {
        //
        // Get the available tags
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'tagsList');
        $rc = ciniki_core_tagsList($ciniki, 'ciniki.ags', $args['tnid'], 'ciniki_ags_exhibit_tags', 20);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.94', 'msg'=>'Unable to get list of types', 'err'=>$rc['err']));
        }
        if( isset($rc['tags']) ) {
            $rsp['types'] = $rc['tags'];
        }
    }

    //
    // Get the list of web collections, and which ones this exhibit is attached to
    //
    if( isset($ciniki['tenant']['modules']['ciniki.web']) && ciniki_core_checkModuleFlags($ciniki, 'ciniki.web', 0x08)) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'web', 'hooks', 'webCollectionList');
        $rc = ciniki_web_hooks_webCollectionList($ciniki, $args['tnid'], array('object'=>'ciniki.ags.exhibit', 'object_id'=>$args['exhibit_id']));
        if( $rc['stat'] != 'ok' ) { 
            return $rc;
        }
        if( isset($rc['collections']) ) {
            $rsp['exhibit']['_webcollections'] = $rc['collections'];
            $rsp['exhibit']['webcollections'] = $rc['selected'];
            $rsp['exhibit']['webcollections_text'] = $rc['selected_text'];
            $rsp['exhibit_details'][] = array('label'=>'Web Collections', 'value'=>$rc['selected_text']);
        }
    }

    //
    // Load the participant list, inventory and sales
    //
    if( isset($args['details']) && $args['details'] == 'yes' ) {
        //
        // Get the list of participants
        //
        $strsql = "SELECT participants.id, "
            . "exhibitors.id AS exhibitor_id, "
            . "exhibitors.display_name, "
            . "participants.status, "
            . "participants.status AS status_text "
            . "FROM ciniki_ags_participants AS participants "
            . "INNER JOIN ciniki_ags_exhibitors AS exhibitors ON ("
                . "participants.exhibitor_id = exhibitors.id "
                . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE participants.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
            . "AND participants.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "ORDER BY exhibitors.display_name "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
        $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'participants', 'fname'=>'exhibitor_id', 
                'fields'=>array('id', 'exhibitor_id', 'display_name', 'status', 'status_text'),
                'maps'=>array('status_text'=>$maps['participant']['status']),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.11', 'msg'=>'Unable to load participants', 'err'=>$rc['err']));
        }
        $participants = isset($rc['participants']) ? $rc['participants'] : array();

        //
        // Get the inventory
        //
        $strsql = "SELECT exhibit.id, "
            . "items.id AS item_id, "
            . "items.exhibitor_id, "
            . "items.code, "
            . "items.name, "
            . "items.unit_amount, "
            . "exhibit.fee_percent, "
            . "exhibit.inventory "
            . "FROM ciniki_ags_exhibit_items AS exhibit "
            . "INNER JOIN ciniki_ags_items AS items ON ("
                . "exhibit.item_id = items.id "
                . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE exhibit.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
            . "AND exhibit.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "ORDER BY items.code, items.name "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'items', 'fname'=>'id', 
                'fields'=>array('id', 'item_id', 'exhibitor_id', 'code', 'name', 'unit_amount', 'fee_percent', 'inventory'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.12', 'msg'=>'Unable to load exhibit inventory', 'err'=>$rc['err']));
        }
        $inventory = isset($rc['items']) ? $rc['items'] : array();

        //
        // Get the sales
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
            . "sales.tenant_amount, "
            . "sales.exhibitor_amount, "
            . "sales.total_amount "
            . "FROM ciniki_ags_item_sales AS sales "
            . "INNER JOIN ciniki_ags_items AS items ON ("
                . "sales.item_id = items.id "
                . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE sales.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
            . "AND sales.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "ORDER BY sales.sell_date, items.code, items.name "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
        $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'sales', 'fname'=>'id', 
                'fields'=>array('id', 'item_id', 'exhibitor_id', 'code', 'name', 'flags', 'sell_date', 'sell_date_display', 
                    'quantity', 'tenant_amount', 'exhibitor_amount', 'total_amount'),
                'utctotz'=>array('sell_date_display'=>array('format'=>$date_format, 'timezone'=>'UTC')),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.12', 'msg'=>'Unable to load exhibit sales', 'err'=>$rc['err']));
        }
        $sales = isset($rc['sales']) ? $rc['sales'] : array();

        //
        // Column totals for display in UI
        //
        $totals = array(
            'participants' => array(
                'tenant_amount' => 0,
                'exhibitor_amount' => 0,
                'total_amount' => 0,
                'num_items' => 0,
                ),
            'pending_payouts' => array(
                'tenant_amount' => 0,
                'exhibitor_amount' => 0,
                'total_amount' => 0,
                ),
            'paid_sales' => array(
                'tenant_amount' => 0,
                'exhibitor_amount' => 0,
                'total_amount' => 0,
                ),
            );

        //
        // Set the defaults for each participant
        //
        foreach($participants as $pid => $participant) {    
            $participants[$pid]['num_items'] = 0;
            $participants[$pid]['tenant_amount'] = 0;
            $participants[$pid]['exhibitor_amount'] = 0;
            $participants[$pid]['total_amount'] = 0;
        }
       
        //
        // Calculate the number of items for participants, and format fee percent
        //
        foreach($inventory as $iid => $item) {
            if( isset($participants[$item['exhibitor_id']]) ) {
                $participants[$item['exhibitor_id']]['num_items']++;
                $inventory[$iid]['display_name'] = $participants[$item['exhibitor_id']]['display_name'];
            } else {
                $inventory[$iid]['display_name'] = '';
            }
            $inventory[$iid]['fee_percent_display'] = (float)$item['fee_percent'] . '%';
            $inventory[$iid]['unit_amount_display'] = '$' . number_format($item['unit_amount'], 2);
        }

        //
        // Calculate the sales amount for each participant, and format sales numbers
        //
        $paid_sales = array();
        $pending_payouts = array();
        foreach($sales as $sid => $sale) {
            if( isset($participants[$sale['exhibitor_id']]) ) {
                $participants[$sale['exhibitor_id']]['tenant_amount'] += $sale['tenant_amount'];
                $participants[$sale['exhibitor_id']]['exhibitor_amount'] += $sale['exhibitor_amount'];
                $participants[$sale['exhibitor_id']]['total_amount'] += $sale['total_amount'];
                $sale['display_name'] = $participants[$sale['exhibitor_id']]['display_name'];
            } else {
                $sale['display_name'] = '';
            }
            $totals['participants']['tenant_amount'] += $sale['tenant_amount'];
            $totals['participants']['exhibitor_amount'] += $sale['exhibitor_amount'];
            $totals['participants']['total_amount'] += $sale['total_amount'];
            $sale['tenant_amount_display'] = '$' . number_format($sale['tenant_amount'], 2);
            $sale['exhibitor_amount_display'] = '$' . number_format($sale['exhibitor_amount'], 2);
            $sale['total_amount_display'] = '$' . number_format($sale['total_amount'], 2);
            if( ($sale['flags']&0x02) == 0x02 ) {
                $paid_sales[] = $sale;
                $totals['paid_sales']['tenant_amount'] += $sale['tenant_amount'];
                $totals['paid_sales']['exhibitor_amount'] += $sale['exhibitor_amount'];
                $totals['paid_sales']['total_amount'] += $sale['total_amount'];
            } else {
                $pending_payouts[] = $sale;
                $totals['pending_payouts']['tenant_amount'] += $sale['tenant_amount'];
                $totals['pending_payouts']['exhibitor_amount'] += $sale['exhibitor_amount'];
                $totals['pending_payouts']['total_amount'] += $sale['total_amount'];
            }
        }
        //
        // Format the totals numbers for the participants
        //
        foreach($participants as $pid => $participant) {    
            $totals['participants']['num_items'] += $participant['num_items'];
            $participants[$pid]['tenant_amount_display'] = '$' . number_format($participant['tenant_amount'], 2);
            $participants[$pid]['exhibitor_amount_display'] = '$' . number_format($participant['exhibitor_amount'], 2);
            $participants[$pid]['total_amount_display'] = '$' . number_format($participant['total_amount'], 2);
        }

        $totals['participants']['tenant_amount_display'] = '$' . number_format($totals['participants']['tenant_amount'], 2);
        $totals['participants']['exhibitor_amount_display'] = '$' . number_format($totals['participants']['exhibitor_amount'], 2);
        $totals['participants']['total_amount_display'] = '$' . number_format($totals['participants']['total_amount'], 2);
        $totals['pending_payouts']['tenant_amount_display'] = '$' . number_format($totals['pending_payouts']['tenant_amount'], 2);
        $totals['pending_payouts']['exhibitor_amount_display'] = '$' . number_format($totals['pending_payouts']['exhibitor_amount'], 2);
        $totals['pending_payouts']['total_amount_display'] = '$' . number_format($totals['pending_payouts']['total_amount'], 2);
        $totals['paid_sales']['tenant_amount_display'] = '$' . number_format($totals['paid_sales']['tenant_amount'], 2);
        $totals['paid_sales']['exhibitor_amount_display'] = '$' . number_format($totals['paid_sales']['exhibitor_amount'], 2);
        $totals['paid_sales']['total_amount_display'] = '$' . number_format($totals['paid_sales']['total_amount'], 2);

        //
        // Remove keys from ID arrays and sort
        //
        $participants = array_values($participants);
        uasort($participants, function($a, $b) {
            return strcmp($a['display_name'], $b['display_name']);
            });
        $rsp['participants'] = $participants;
        $rsp['inventory'] = $inventory;
        $rsp['pending_payouts'] = $pending_payouts;
        $rsp['paid_sales'] = $paid_sales;
        $rsp['totals'] = $totals;
    }

    return $rsp;
}
?>
