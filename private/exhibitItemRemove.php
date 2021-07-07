<?php
//
// Description
// -----------
// This function will remove an item from an exhibit
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to add the Exhibit to.
//
// Returns
// -------
//
function ciniki_ags_exhibitItemRemove(&$ciniki, $tnid, $exhibit_id, $item_id) {

    //
    // Check if the item is already a part of the exhibit
    //
    $strsql = "SELECT id, exhibit_id, item_id, inventory "
        . "FROM ciniki_ags_exhibit_items "
        . "WHERE exhibit_id = '" . ciniki_core_dbQuote($ciniki, $exhibit_id) . "' "
        . "AND item_id = '" . ciniki_core_dbQuote($ciniki, $item_id) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.24', 'msg'=>'Item is not part of this exhibit', 'err'=>$rc['err']));
    }
    $exhibititem = isset($rc['item']) ? $rc['item'] : null;

    //
    // Check if item is part of any incomplete sales
    //
    $strsql = "SELECT invoices.invoice_number "
        . "FROM ciniki_sapos_invoice_items AS items "
        . "INNER JOIN ciniki_sapos_invoices AS invoices ON ("
            . "items.invoice_id = invoices.id "
            . "AND invoices.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE items.object = 'ciniki.ags.exhibititem' "
        . "AND items.object_id = '" . ciniki_core_dbQuote($ciniki, $exhibititem['id']) . "' "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND invoices.invoice_type IN (10, 20, 30, 40) "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'invoices', 'fname'=>'invoice_number', 'fields'=>array('invoice_number')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.186', 'msg'=>'Unable to load invoices', 'err'=>$rc['err']));
    }
    if( isset($rc['invoices']) ) {
        $invoice_list = '';
        foreach($rc['invoices'] as $invoice) {
            $invoice_list .= ($invoice_list != '' ? ', ' : '') . '#' . $invoice['invoice_number'];
        }
        if( $invoice_list != '' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.187', 'msg'=>'Item must first be removed from the following invoice(s): ' . $invoice_list));
        }
    }

    //
    // Get the details about the item
    //
    $strsql = "SELECT id AS item_id, code, name, unit_amount, fee_percent "
        . "FROM ciniki_ags_items "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $item_id) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.27', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
    }
    if( !isset($rc['item']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.110', 'msg'=>'That item does not exist'));
    }
    $item = $rc['item'];
    $item['unit_amount_display'] = '$' . number_format($item['unit_amount'], 2);
    
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
    $rc = ciniki_core_objectDelete($ciniki, $tnid, 'ciniki.ags.exhibititem', $exhibititem['id'], 0x04);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.26', 'msg'=>'Unable to remove item', 'err'=>$rc['err']));
    }

    //
    // Add Log entry
    //
    $dt = new DateTime('now', new DateTimezone('UTC'));
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.ags.itemlog', array(
        'item_id' => $exhibititem['item_id'],
        'action' => 90,
        'actioned_id' => $exhibititem['exhibit_id'],
        'quantity' => $exhibititem['inventory'],
        'log_date' => $dt->format('Y-m-d H:i:s'),
        'user_id' => $ciniki['session']['user']['id'],
        'notes' => '',
        ), 0x04);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.177', 'msg'=>'Unable to add log', 'err'=>$rc['err']));
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $tnid, 'ciniki', 'ags');

    return array('stat'=>'ok', 'item'=>$item);
}
?>
