<?php
//
// Description
// -----------
// This method will return the list of Item Images for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Item Image for.
//
// Returns
// -------
//
function ciniki_ags_itemImageList($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.itemImageList');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Get the list of itemimages
    //
    $strsql = "SELECT ciniki_ags_item_images.id, "
        . "ciniki_ags_item_images.item_id, "
        . "ciniki_ags_item_images.name, "
        . "ciniki_ags_item_images.permalink, "
        . "ciniki_ags_item_images.flags, "
        . "ciniki_ags_item_images.sequence "
        . "FROM ciniki_ags_item_images "
        . "WHERE ciniki_ags_item_images.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'itemimages', 'fname'=>'id', 
            'fields'=>array('id', 'item_id', 'name', 'permalink', 'flags', 'sequence')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['itemimages']) ) {
        $itemimages = $rc['itemimages'];
        $itemimage_ids = array();
        foreach($itemimages as $iid => $itemimage) {
            $itemimage_ids[] = $itemimage['id'];
        }
    } else {
        $itemimages = array();
        $itemimage_ids = array();
    }

    return array('stat'=>'ok', 'itemimages'=>$itemimages, 'nplist'=>$itemimage_ids);
}
?>
