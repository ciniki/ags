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
function ciniki_ags_wng_accountCatalogProcess(&$ciniki, $tnid, &$request, $item) {

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
    // Load the exhibitor items and the active exhibits they are attached to
    //
    $strsql = "SELECT items.id, "   
        . "items.exhibitor_code, "
        . "items.code, "
        . "items.name, "
        . "items.permalink, "
        . "items.status, "
        . "items.flags, "
        . "items.unit_amount, "
        . "items.unit_discount_amount, "
        . "items.unit_discount_percentage, "
        . "items.fee_percent, "
        . "items.taxtype_id, "
        . "items.sapos_category, "
        . "items.primary_image_id, "
        . "items.synopsis, "
        . "items.description, "
        . "items.size, "
        . "items.framed_size, "
        . "items.notes, "
        . "eitems.id AS eitem_id, "
        . "eitems.inventory AS inventory, "
        . "eitems.fee_percent AS efee_percent, "
        . "exhibits.id AS exhibit_id, "
        . "exhibits.name AS exhibit_name, "
        . "exhibits.status AS exhibit_status "
        . "FROM ciniki_ags_items AS items "
        . "LEFT JOIN ciniki_ags_exhibit_items AS eitems ON ("
            . "items.id = eitems.item_id "
            . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "LEFT JOIN ciniki_ags_exhibits AS exhibits ON ("
            . "eitems.exhibit_id = exhibits.id "
            . "AND exhibits.status < 90 "   // Not archived
//            . "AND (exhibits.flags&0x0100) = 0x0100 "
            . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $exhibitor['id']) . "' "
        . "AND items.status < 90 "  // Not archived
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "ORDER BY items.name, exhibits.name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'exhibitor_code', 'code', 'name', 'permalink', 'status', 'flags', 
                'unit_amount', 'unit_discount_amount', 'unit_discount_percentage', 'fee_percent', 
                'taxtype_id', 'sapos_category', 'primary_image_id', 'synopsis', 'description', 'size', 
                'framed_size', 'notes'),
            ),
        array('container'=>'exhibits', 'fname'=>'exhibit_id', 
            'fields' => array('id'=>'exhibit_id', 'name'=>'exhibit_name', 'status'=>'exhibit_status', 'inventory'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.317', 'msg'=>'Unable to load items', 'err'=>$rc['err']));
    }
    $items = isset($rc['items']) ? $rc['items'] : array();

    //
    // Process items to roll up exhibit names
    //
    foreach($items as $iid => $item) {
        $items[$iid]['exhibit_names'] = '';
        $items[$iid]['price'] = '$' . number_format($item['unit_amount'], 2);
        if( isset($item['exhibits']) && count($item['exhibits']) > 0 ) {
            foreach($item['exhibits'] as $exhibit) {
                $items[$iid]['exhibit_names'] .= ($items[$iid]['exhibit_names'] != '' ? ', ' : '')
                    . $exhibit['name'] . " ({$exhibit['inventory']})";
            }
        }
    }

    $blocks[] = array(
        'type' => 'table',
        'title' => 'Gallery Items',
        'class' => 'limit-width limit-width-80 fold-at-50',
        'headers' => 'yes',
        'columns' => array( 
            array('label'=>'Code', 'fold-label'=>'Code: ', 'field'=>'code', 'class'=>''),
            array('label'=>'Name', 'fold-label'=>'Name: ', 'field'=>'name', 'class'=>''),
            array('label'=>'Price', 'fold-label'=>'Price: ', 'field'=>'price', 'class'=>''),
            array('label'=>'Exhibits (Qty)', 'fold-label'=>'Exhibits (Qty): ', 'field'=>'exhibit_names', 'class'=>''),
            ),
        'rows' => $items,
        );



    return array('stat'=>'ok', 'blocks'=>$blocks);
}
?>
