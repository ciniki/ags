<?php
//
// Description
// ===========
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_ags_exhibitorUpdate(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'exhibitor_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Exhibitor'),
        'customer_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Customer'),
        'display_name_override'=>array('required'=>'no', 'blank'=>'yes', 'trim'=>'yes', 'name'=>'Override Name'),
        'display_name'=>array('required'=>'no', 'blank'=>'yes', 'trim'=>'yes', 'name'=>'Name'),
        'profile_name'=>array('required'=>'no', 'blank'=>'yes', 'trim'=>'yes', 'name'=>'Profile Name'),
        'permalink'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Permalink'),
        'code'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Code'),
        'barcode_message'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Message'),
        'status'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Status'),
        'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
        'primary_image_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Image'),
        'synopsis'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Synopsis'),
        'fullbio'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Full Bio'),
        'requested_changes'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Requested Changes'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitorUpdate');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Uppercase the code
    //
    if( isset($args['code']) ) {
        $args['code'] = strtoupper($args['code']);
    }

    //
    // Lookup the exhibitor
    //
    $strsql = "SELECT id, customer_id "
        . "FROM ciniki_ags_exhibitors "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibitor');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.9', 'msg'=>'Unable to load exhibitor', 'err'=>$rc['err']));
    }
    if( !isset($rc['exhibitor']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.10', 'msg'=>'Unable to find requested exhibitor'));
    }
    $exhibitor = $rc['exhibitor'];

    //
    // Lookup the customer
    //
    $strsql = "SELECT display_name "
        . "FROM ciniki_customers "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $exhibitor['customer_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'customer');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.42', 'msg'=>'Unable to load customer', 'err'=>$rc['err']));
    }
    if( !isset($rc['customer']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.43', 'msg'=>'Unable to find requested customer'));
    }
    $customer = $rc['customer'];
      
    //
    // Check if the display_name_override was passed.
    //
    if( isset($args['display_name_override']) ) {
        //
        // Override contains characters and is different than customers display_name.
        //
        if( $args['display_name_override'] != '' && $args['display_name_override'] != $customer['display_name'] ) {
            $args['display_name'] = $args['display_name_override'];
        } elseif( $args['display_name_override'] == $customer['display_name'] ) {
            // Reset override, if it's the same then it's not needed
            $args['display_name_override'] = '';
            $args['display_name'] = $customer['display_name'];
        } else {
            $args['display_name'] = $customer['display_name'];
        }
    }

    //
    // Start transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.ags');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the Exhibitor in the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.ags.exhibitor', $args['exhibitor_id'], $args, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
        return $rc;
    }

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.ags');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $args['tnid'], 'ciniki', 'ags');

    //
    // Update the web index if enabled
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'hookExec');
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'ciniki.ags.exhibitor', 'object_id'=>$args['exhibitor_id']));
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'wng', 'indexObject', array('object'=>'ciniki.ags.exhibitor', 'object_id'=>$args['exhibitor_id']));

    return array('stat'=>'ok');
}
?>
