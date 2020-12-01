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
function ciniki_ags_web_customerExhibits($ciniki, $tnid, $args) {
error_log('test');
    //
    // Get the list of exhibits and items that are public and the
    // customer has visible items in
    //
    $strsql = "SELECT exhibits.id AS exhibit_id, "
        . "exhibits.permalink AS exhibit_permalink, "
        . "exhibits.name AS exhibit_name, "
        . "IFNULL(tags.permalink, '') AS type_permalink, "
        . "eitems.inventory, "
        . "items.id, "
        . "items.name, "
        . "items.permalink, "
        . "items.status, "
        . "items.flags, "
        . "items.unit_amount, "
        . "items.unit_discount_amount, "
        . "items.unit_discount_percentage, "
        . "items.primary_image_id, "
        . "items.synopsis "
        . "FROM ciniki_ags_exhibitors AS exhibitors "
        . "INNER JOIN ciniki_ags_items AS items ON ("
            . "exhibitors.id = items.exhibitor_id "
            . "AND (items.flags&0x02) = 0x02 "  // Visible online
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "INNER JOIN ciniki_ags_exhibit_items AS eitems ON ("
            . "items.id = eitems.item_id "
            . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "INNER JOIN ciniki_ags_exhibits AS exhibits ON ("
            . "eitems.exhibit_id = exhibits.id "
            . "AND exhibits.status = 50 "   // Active
            . "AND (exhibits.flags&0x05) = 0x05 " // Visible online and items visible online
            . "AND (exhibits.end_date = '0000-00-00' OR exhibits.end_date > UTC_TIMESTAMP()) "
            . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "LEFT JOIN ciniki_ags_exhibit_tags AS tags ON ("
            . "tags.id = ("
                . "SELECT id FROM ciniki_ags_exhibit_tags AS b "
                . "WHERE b.exhibit_id = exhibits.id "
                . "AND b.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . "LIMIT 1"
                . ") "
            . ") "
        . "WHERE exhibitors.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
        . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibits', 'fname'=>'exhibit_id', 
            'fields'=>array('permalink'=>'exhibit_permalink', 'name'=>'exhibit_name', 'type_permalink')),
        array('container'=>'items', 'fname'=>'permalink', 
            'fields'=>array('id', 'name', 'permalink', 'inventory', 'status', 'flags',
                'unit_amount', 'unit_discount_amount', 'unit_discount_percentage',
                'image_id'=>'primary_image_id', 'synopsis')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $exhibits = isset($rc['exhibits']) ? $rc['exhibits'] : array();
    
    foreach($exhibits as $eid => $exhibit) {
        // Get the base url of the customers module
        $exhibit_base_url = '';
        ciniki_core_loadMethod($ciniki, 'ciniki', 'web', 'private', 'indexModuleBaseURL');
        if( $exhibit['type_permalink'] != '' ) {
            $rc = ciniki_web_indexModuleBaseURL($ciniki, $tnid, 'ciniki.ags.' . $exhibit['type_permalink']);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.208', 'msg'=>'Unable to get members base URL', 'err'=>$rc['err']));
            }
            $exhibit_base_url = $ciniki['request']['domain_base_url'] . (isset($rc['base_url']) ? $rc['base_url'] : '');
        }
        if( $exhibit_base_url == '' ) {
            $rc = ciniki_web_indexModuleBaseURL($ciniki, $tnid, 'ciniki.ags.exhibits');
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.207', 'msg'=>'Unable to get members base URL', 'err'=>$rc['err']));
            }
            $exhibit_base_url = $ciniki['request']['domain_base_url'] . (isset($rc['base_url']) ? $rc['base_url'] : '');
        }
        $exhibits[$eid]['base_url'] = $exhibit_base_url . '/' . $exhibit['permalink'] . '/item';
        if( isset($exhibit['items']) ) {
            foreach($exhibit['items'] as $iid => $item) {
                ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'web', 'formatPrice');
                $rc = ciniki_ags_web_formatPrice($ciniki, $tnid, $item);
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.198', 'msg'=>'Unable to format price', 'err'=>$rc['err']));
                }
                $exhibits[$eid]['items'][$iid]['display_price'] = $rc['display_price'];
            }
        }
    }
        
    return array('stat'=>'ok', 'exhibits'=>$exhibits);
}
?>
