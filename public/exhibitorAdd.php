<?php
//
// Description
// -----------
// This method will add a new exhibitor for the tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to add the Exhibitor to.
//
// Returns
// -------
//
function ciniki_ags_exhibitorAdd(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'customer_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Customer'),
        'display_name_override'=>array('required'=>'no', 'blank'=>'yes', 'trim'=>'yes', 'name'=>'Override Name'),
        'display_name'=>array('required'=>'no', 'blank'=>'yes', 'trim'=>'yes', 'name'=>'Name'),
        'permalink'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Permalink'),
        'code'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Code'),
        'status'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Status'),
        'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
        'synopsis'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Synopsis'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitorAdd');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Uppercase the code
    //
    $args['code'] = strtoupper($args['code']);

    //
    // Lookup the customer
    //
    $strsql = "SELECT display_name "
        . "FROM ciniki_customers "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'customer');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.103', 'msg'=>'Unable to load customer', 'err'=>$rc['err']));
    }
    if( !isset($rc['customer']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.104', 'msg'=>'Unable to find requested customer'));
    }
    $customer = $rc['customer'];
    
    //
    // Check if the display_name_override was passed with a value
    //
    if( isset($args['display_name_override']) && $args['display_name_override'] != '' && $args['display_name_override'] != $customer['display_name'] ) {
        $args['display_name'] = $args['display_name_override'];
    } else {
        $args['display_name_override'] = '';
        $args['display_name'] = $customer['display_name'];
    }

    //
    // Check the code does not already exist
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'exhibitorCodeCheck');
    $rc = ciniki_ags_exhibitorCodeCheck($ciniki, $args['tnid'], $args['code']);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.25', 'msg'=>'Code already exists, please choose another.', 'err'=>$rc['err']));
    }
    
    //
    // Setup permalink
    //
    if( !isset($args['permalink']) || $args['permalink'] == '' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'makePermalink');
        $args['permalink'] = ciniki_core_makePermalink($ciniki, $args['display_name']);
    }

    //
    // Make sure the permalink is unique
    //
    $strsql = "SELECT id, display_name, permalink "
        . "FROM ciniki_ags_exhibitors "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND permalink = '" . ciniki_core_dbQuote($ciniki, $args['permalink']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( $rc['num_rows'] > 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.70', 'msg'=>'You already have a exhibitor with that name, please choose another.'));
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
    // Add the exhibitor to the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.ags.exhibitor', $args, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
        return $rc;
    }
    $exhibitor_id = $rc['id'];

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
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'ciniki.ags.exhibitor', 'object_id'=>$exhibitor_id));

    return array('stat'=>'ok', 'id'=>$exhibitor_id);
}
?>
