<?php
//
// Description
// -----------
// This method will add a new exhibit for the tenant.
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
function ciniki_ags_exhibitItemDelete(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'exhibit_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Exhibit'),
        'item_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Item'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitItemDelete');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Remove the item from the exhibit
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'exhibitItemRemove');
    $rc = ciniki_ags_exhibitItemRemove($ciniki, $args['tnid'], $args['exhibit_id'], $args['item_id']);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.292', 'msg'=>'', 'err'=>$rc['err']));
    }
    $rsp = $rc;

    //
    // Update the web index if enabled
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'hookExec');
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'wng', 'indexObject', array());

    return $rsp;
}
?>
