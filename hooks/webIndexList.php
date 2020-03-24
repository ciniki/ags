<?php
//
// Description
// -----------
// This function returns the list of objects and object_ids that should be indexed on the website.
//
// Arguments
// ---------
// ciniki:
// tnid:     The ID of the tenant to get ags for.
//
// Returns
// -------
//
function ciniki_ags_hooks_webIndexList($ciniki, $tnid, $args) {

    $objects = array();

    //
    // Get the list of exhibits that are to be shown in the index
    //
    $strsql = "SELECT CONCAT('ciniki.ags.exhibit.', id) AS oid, 'ciniki.ags.exhibit' AS object, id AS object_id "
        . "FROM ciniki_ags_exhibits "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND (flags&0x01) = 0x01 "
        . "";
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'objects', 'fname'=>'oid', 'fields'=>array('object', 'object_id')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['objects']) ) {
        $objects = $rc['objects'];
    }

    //
    // Get the list of items that are visible online
    //
    $strsql = "SELECT CONCAT('ciniki.ags.item.', items.id) AS oid, 'ciniki.ags.item' AS object, items.id AS object_id "
        . "FROM ciniki_ags_exhibits AS exhibits "
        . "INNER JOIN ciniki_ags_exhibit_items AS eitems ON ("
            . "exhibits.id = eitems.exhibit_id "
            . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "INNER JOIN ciniki_ags_items AS items ON ("
            . "eitems.item_id = items.id "
            . "AND (items.flags&0x02) = 0x02 "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND (exhibits.flags&0x01) = 0x01 " // Exhibit visible
        . "";
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'objects', 'fname'=>'oid', 'fields'=>array('object', 'object_id')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['objects']) ) {
        $objects = array_merge($objects, $rc['objects']);
    }

    return array('stat'=>'ok', 'objects'=>$objects);
}
?>
