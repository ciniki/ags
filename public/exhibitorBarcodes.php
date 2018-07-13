<?php
//
// Description
// ===========
// This method will return the PDF of barcodes to be printed for the exhibitor.
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
function ciniki_ags_exhibitorBarcodes($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'exhibitor_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Exhibitor'),
//        'label'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Label'),
        'exhibit_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Exhibit'),
        'start_code'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Start Code'),
        'end_code'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Start End'),
        'start_col'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Start Column'),
        'start_row'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Start Row'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    $args['label'] = 'avery5167';

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
    // Get the exhibitor details
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
        . "AND ciniki_ags_exhibitors.id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibitors', 'fname'=>'id', 
            'fields'=>array('customer_id', 'display_name_override', 'display_name', 'permalink', 'code', 'status', 'flags'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.74', 'msg'=>'Exhibitor not found', 'err'=>$rc['err']));
    }
    if( !isset($rc['exhibitors'][0]) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.75', 'msg'=>'Unable to find Exhibitor'));
    }
    $exhibitor = $rc['exhibitors'][0];
    $args['title'] = $exhibitor['display_name'] . ' Barcodes';

    //
    // Get the codes that need to be printed
    //
    $strsql = "SELECT items.id, "
        . "items.code, "
        . "items.name, "
        . "items.status, "
        . "items.flags, "
        . "items.unit_amount, "
        . "items.fee_percent "
        . "";
    if( isset($args['exhibit_id']) && $args['exhibit_id'] > 0 ) {
        $strsql .= ", exhibit.inventory AS quantity "
            . "FROM ciniki_ags_exhibit_items AS exhibit "
            . "INNER JOIN ciniki_ags_items AS items ON ("
                . "exhibit.item_id = items.id "
                . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
    } else {
        $strsql .= ", 1 AS quantity "
            . "FROM ciniki_ags_items AS items "
            . "WHERE items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
    }
    if( isset($args['start_code']) && $args['start_code'] != '' ) {
        $strsql .= "AND items.code >= '" . ciniki_core_dbQuote($ciniki, $args['start_code']) . "' ";
    }
    if( isset($args['end_code']) && $args['end_code'] != '' ) {
        $strsql .= "AND items.code <= '" . ciniki_core_dbQuote($ciniki, $args['end_code']) . "' ";
    }
    $strsql .= "ORDER BY items.code ";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'code', 'name', 'status', 'flags', 'unit_amount', 'fee_percent', 'quantity'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.40', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
    }
    $args['barcodes'] = array();
    foreach($rc['items'] as $item) {
        if( $item['quantity'] > 1 ) {
            for($i=0;$i<$item['quantity'];$i++) {
                $args['barcodes'][] = $item;
            }
        } else {
            $args['barcodes'][] = $item;
        }
    }
    if( count($args['barcodes']) < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.96', 'msg'=>'No items found'));
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'templates', 'barcodesPDF');
    $rc = ciniki_ags_templates_barcodesPDF($ciniki, $args['tnid'], $args);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.97', 'msg'=>'Error generating PDF', 'err'=>$rc['err']));
    }

    if( isset($rc['pdf']) ) {
        $filename = 'Labels-' . preg_replace('/[^a-zA-Z0-9_]/', '', preg_replace('/ /', '-', $exhibitor['display_name']));
        $rc['pdf']->Output($filename . '.pdf', 'D');
    }

    return array('stat'=>'exit');
}
?>
