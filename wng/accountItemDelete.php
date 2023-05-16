<?php
//
// Description
// -----------
// This function will remove an item for an exhibitor when there are no sales or part of exhibits.
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_ags_wng_accountItemDelete(&$ciniki, $tnid, &$request, $ags_item) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');

    //
    // Check for item sales
    //
    $strsql = "SELECT COUNT(id) AS num_items "
        . "FROM ciniki_ags_item_sales "
        . "WHERE item_id = '" . ciniki_core_dbQuote($ciniki, $ags_item['id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbSingleCount');
    $rc = ciniki_core_dbSingleCount($ciniki, $strsql, 'ciniki.ags', 'num');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "Unable to remove item.",
            )));
    }
    if( $rc['num'] > 0 ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "There are sales for this item and it cannot be removed."
            )));
    }

    //
    // Check if item is part of other exhibits
    //
    $strsql = "SELECT COUNT(id) AS num_items "
        . "FROM ciniki_ags_exhibit_items "
        . "WHERE item_id = '" . ciniki_core_dbQuote($ciniki, $ags_item['id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbSingleCount');
    $rc = ciniki_core_dbSingleCount($ciniki, $strsql, 'ciniki.ags', 'num');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "Unable to remove item.",
            )));
    }
    if( $rc['num'] > 0 ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "This item is part of other exhibits and cannot be removed."
            )));
    }

    //
    // Load additional images for item
    //
    $strsql = "SELECT id, uuid "
        . "FROM ciniki_ags_item_images "
        . "WHERE item_id = '" . ciniki_core_dbQuote($ciniki, $ags_item['id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.324', 'msg'=>'Unable to load images', 'err'=>$rc['err']));
    }
    $rows = isset($rc['rows']) ? $rc['rows'] : array();
    foreach($rows as $row) {
        $rc = ciniki_core_objectDelete($ciniki, $tnid, 'ciniki.ags.itemimage', $row['id'], $row['uuid'], 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return $rc;
        }
    }

    //
    // Load the item logs
    //
    $strsql = "SELECT id, uuid "
        . "FROM ciniki_ags_item_logs "
        . "WHERE item_id = '" . ciniki_core_dbQuote($ciniki, $ags_item['id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.325', 'msg'=>'Unable to load images', 'err'=>$rc['err']));
    }
    $rows = isset($rc['rows']) ? $rc['rows'] : array();
    foreach($rows as $row) {
        $rc = ciniki_core_objectDelete($ciniki, $tnid, 'ciniki.ags.itemlog', $row['id'], $row['uuid'], 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return $rc;
        }
    }

    //
    // Load the item tags
    //
    $strsql = "SELECT id, uuid "
        . "FROM ciniki_ags_item_tags "
        . "WHERE item_id = '" . ciniki_core_dbQuote($ciniki, $ags_item['id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.319', 'msg'=>'Unable to load images', 'err'=>$rc['err']));
    }
    $rows = isset($rc['rows']) ? $rc['rows'] : array();
    foreach($rows as $row) {
        $rc = ciniki_core_objectDelete($ciniki, $tnid, 'ciniki.ags.itemtag', $row['id'], $row['uuid'], 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return $rc;
        }
    }

    //
    // Remove the item
    //
    $rc = ciniki_core_objectDelete($ciniki, $tnid, 'ciniki.ags.item', $ags_item['id'], $ags_item['uuid'], 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
        return $rc;
    }

    return array('stat'=>'ok', 'blocks'=>$blocks);
}
?>
