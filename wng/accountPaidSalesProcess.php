<?php
//
// Description
// -----------
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_ags_wng_accountPaidSalesProcess(&$ciniki, $tnid, &$request, $item) {

    $blocks = array();

    if( !isset($item['ref']) ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "Request error, please contact us for help..",
            )));
    }

    if( !isset($request['session']['customer']['id']) || $request['session']['customer']['id'] <= 0 ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "You must be logged in."
            )));
    }

    //
    // Load the tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    
    //
    // Load the exhibitor
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountExhibitorLoad');
    $rc = ciniki_ags_wng_accountExhibitorLoad($ciniki, $tnid, $request);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $exhibitor = $rc['exhibitor'];

    //
    // Load the paid sales
    //
    $strsql = "SELECT sales.id, "
        . "sales.exhibit_id, "
        . "exhibits.name AS exhibit_name, "
        . "sales.item_id, "
        . "sales.flags, "
        . "items.code, "
        . "items.name, "
        . "DATE_FORMAT(sales.sell_date, '%M %D, %Y') AS sell_date, "
        . "sales.tenant_amount, "
        . "sales.exhibitor_amount, "
        . "sales.total_amount, "
        . "sales.receipt_number "
        . "FROM ciniki_ags_items AS items "
        . "INNER JOIN ciniki_ags_item_sales AS sales ON ("
            . "items.id = sales.item_id "
            . "AND (sales.flags&0x02) = 0x02 "
            . "AND sales.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "LEFT JOIN ciniki_ags_exhibits AS exhibits ON ("
            . "sales.exhibit_id = exhibits.id "
            . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $exhibitor['id']) . "' "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "ORDER BY sales.sell_date ASC "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'sales', 'fname'=>'id', 'fields'=>array('id', 'exhibit_id', 'item_id', 'sell_date', 
            'code', 'name', 'exhibit_name',
            'flags', 'tenant_amount', 'exhibitor_amount', 'total_amount', 'receipt_number'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.315', 'msg'=>'Unable to load sales', 'err'=>$rc['err']));
    }
    $sales = isset($rc['sales']) ? $rc['sales'] : array();

    $totals = array(
        'tenant_amount' => 0,
        'exhibitor_amount' => 0,
        'total_amount' => 0,
        );
    foreach($sales as $sid => $sale) {
        $totals['tenant_amount'] += $sale['tenant_amount'];
        $totals['exhibitor_amount'] += $sale['exhibitor_amount'];
        $totals['total_amount'] += $sale['total_amount'];
        $sales[$sid]['tenant_amount'] = '$' . number_format($sale['tenant_amount'], 2);
        $sales[$sid]['exhibitor_amount'] = '$' . number_format($sale['exhibitor_amount'], 2);
        $sales[$sid]['total_amount'] = '$' . number_format($sale['total_amount'], 2);
    }

    $blocks[] = array(
        'type' => 'table',
        'title' => 'Gallery Paid Sales',
        'class' => 'limit-width limit-width-80 fold-at-50',
        'headers' => 'yes',
        'columns' => array( 
            array('label'=>'Item', 'fold-label'=>'Item: ', 'field'=>'name', 'class'=>''),
            array('label'=>'Exhibit', 'fold-label'=>'Exhibit: ', 'field'=>'exhibit_name', 'class'=>''),
            array('label'=>'Date', 'fold-label'=>'Date: ', 'field'=>'sell_date', 'class'=>''),
            array('label'=>'Fees', 'fold-label'=>'Fees: ', 'field'=>'tenant_amount', 'class'=>'alignright'),
            array('label'=>'Payout', 'fold-label'=>'Payout: ', 'field'=>'exhibitor_amount', 'class'=>'alignright'),
            array('label'=>'Total', 'fold-label'=>'Total: ', 'field'=>'total_amount', 'class'=>'alignright'),
            ),
        'rows' => $sales,
        'footer' => array(
            array('value'=>'<b>Totals:</b> ', 'colspan'=>'3', 'class'=>'alignright fold-alignleft'),
            array('fold-label'=>'Fees: ', 'value'=>'$' . number_format($totals['tenant_amount'], 2)),
            array('fold-label'=>'Payouts: ', 'value'=>'$' . number_format($totals['exhibitor_amount'], 2)),
            array('fold-label'=>'Sales: ', 'value'=>'$' . number_format($totals['total_amount'], 2)),
            ),
        );



    return array('stat'=>'ok', 'blocks'=>$blocks);
}
?>
