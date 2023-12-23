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
    $tab = array(
        'id' => 'ciniki.ags.exhibits',
        'label' => 'Exhibits',
        'sections' => $sections,
        );

    //
    // Check for personal donations
    //
    $strsql = "SELECT items.id, "
        . "IFNULL(sales.item_id, 0) AS sales_id, "
        . "items.exhibitor_id, "
        . "items.code, "
        . "items.name, "
        . "items.flags AS item_flags, "
        . "items.unit_amount AS value, "
        . "items.unit_amount AS value_display, "
        . "IFNULL(sales.flags, '') AS flags, "
        . "IFNULL(sales.sell_date, '') AS sell_date, "
        . "IFNULL(sales.sell_date, '') AS sell_date_display, "
        . "IFNULL(sales.quantity, '') AS quantity, "
        . "IF((sales.flags&0x02)=0x02, 'Paid', 'Pending Payout') AS status_text, "
        . "IFNULL(sales.tenant_amount, '') AS tenant_amount, "
        . "IFNULL(sales.exhibitor_amount, '') AS exhibitor_amount, "
        . "IFNULL(sales.total_amount, '') AS total_amount, "
        . "IFNULL(sales.receipt_number, '') AS receipt_number, "
        . "IFNULL(sales.tenant_amount, '') AS tenant_amount_display, "
        . "IFNULL(sales.exhibitor_amount, '') AS exhibitor_amount_display, "
        . "IFNULL(sales.total_amount, '') AS total_amount_display, "
        . "IFNULL(exhibits.name, '') AS exhibit_name "
        . "FROM ciniki_ags_items AS items "
        . "LEFT JOIN ciniki_ags_item_sales AS sales ON ("
            . "sales.item_id = items.id "
            . "AND sales.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "LEFT JOIN ciniki_ags_exhibits AS exhibits ON ("
            . "sales.exhibit_id = exhibits.id "
            . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
            . "";
    if( isset($args['customer_id']) ) {
        $strsql .= "WHERE items.donor_customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' ";
    } elseif( isset($args['customer_ids']) && count($args['customer_ids']) > 0 ) {
        $strsql .= "WHERE items.donor_customer_id IN (" . ciniki_core_dbQuoteIDs($ciniki, $args['customer_ids']) . ") ";
    } else {
        return array('stat'=>'ok');
    }
    $strsql .= "AND (items.flags&0x60) > 0 "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "ORDER BY sales.receipt_number, sales.sell_date, items.code, items.name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'sales_id', 'exhibitor_id', 'code', 'name', 'item_flags',
                'flags', 'sell_date', 'sell_date_display', 'value', 'value_display', 
                'quantity', 'exhibit_name', 
                'tenant_amount', 'exhibitor_amount', 'total_amount',
                'tenant_amount_display', 'exhibitor_amount_display', 'total_amount_display', 'status_text', 'receipt_number'),
            'naprices'=>array('value_display', 'tenant_amount_display', 'exhibitor_amount_display', 'total_amount_display'),
            'utctotz'=>array('sell_date_display'=>array('format'=>$date_format, 'timezone'=>'UTC'),
            ),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.367', 'msg'=>'', 'err'=>$rc['err']));
    }
    if( isset($rc['items']) && count($rc['items']) > 0 ) {
        $tab['sections']['ciniki.ags.donations'] = array(
                'label' => 'Donated Items',
                'type' => 'simplegrid', 
                'num_cols' => 5,
                'headerValues' => array('Item', 'Value', 'Exhibit', 'Sold For', 'Date'),
                'cellClasses' => array('', '', '', ''),
                'noData' => 'No donated items',
                'data' => $rc['items'],
                'cellValues' => array(
                    '0' => 'd.name;',
                    '1' => 'd.value_display;',
                    '2' => 'd.exhibit_name;',
                    '3' => 'd.total_amount_display;',
                    '4' => 'd.sell_date_display;',
                    ),
                );

        //
        // Add a tab the customer UI data screen with the donation list
        //
//        $rsp['tabs'][] = array(
//            'id' => 'ciniki.ags.exhibits',
//            'label' => 'Donations',
//            'sections' => $sections,
//            );
    }

    //
    // Check for business sponsor donations
    //

    $rsp['tabs'][] = $tab;
    return $rsp;
}
?>
