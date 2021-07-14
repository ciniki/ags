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
function ciniki_ags_exhibitAdd(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'name'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Name'),
        'permalink'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Permalink'),
        'location_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Location'),
        'status'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Status'),
        'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
        'start_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'Start Date'),
        'end_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'date', 'name'=>'End Date'),
        'reception_info'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Reception Info'),
        'primary_image_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Image'),
        'synopsis'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Synopsis'),
        'description'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Description'),
        'types'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'list', 'delimiter'=>'::', 'name'=>'Types'),
        'webcollections'=>array('required'=>'no', 'default'=>'', 'blank'=>'yes', 'type'=>'idlist', 'name'=>'Web Collections'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitAdd');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Setup permalink
    //
    if( !isset($args['permalink']) || $args['permalink'] == '' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'makePermalink');
        $permalink_name = $args['name'];
        if( isset($args['start_date']) && $args['start_date'] != '' ) {
            $dt = new DateTime($args['start_date'], new DateTimezone('UTC'));
            if( strstr($permalink_name, $dt->format('Y')) === false ) {
                $permalink_name .= '-' . $dt->format('M-d-Y');
            }
        }
        $args['permalink'] = ciniki_core_makePermalink($ciniki, $permalink_name);
    }

    //
    // Make sure the permalink is unique
    //
    $strsql = "SELECT id, name, permalink "
        . "FROM ciniki_ags_exhibits "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND permalink = '" . ciniki_core_dbQuote($ciniki, $args['permalink']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( $rc['num_rows'] > 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.58', 'msg'=>'You already have a exhibit with that name, please choose another.'));
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
    // Add the exhibit to the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.ags.exhibit', $args, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
        return $rc;
    }
    $exhibit_id = $rc['id'];

    //
    // Update the types
    //
    if( isset($args['types']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'tagsUpdate');
        $rc = ciniki_core_tagsUpdate($ciniki, 'ciniki.ags', 'exhibittag', $args['tnid'],
            'ciniki_ags_exhibit_tags', 'ciniki_ags_history',
            'exhibit_id', $exhibit_id, 20, $args['types']);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return $rc;
        }
    }

    //
    // If exhibition was added ok, Check if any web collections to add
    //
    if( isset($args['webcollections'])
        && isset($ciniki['tenant']['modules']['ciniki.web']) 
        && ciniki_core_checkModuleFlags($ciniki, 'ciniki.web', 0x08)
        ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'web', 'hooks', 'webCollectionUpdate');
        $rc = ciniki_web_hooks_webCollectionUpdate($ciniki, $args['tnid'], array(
            'object'=>'ciniki.ags.exhibit', 
            'object_id'=>$exhibit_id, 
            'collection_ids'=>$args['webcollections'],
            ));
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return $rc;
        }
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
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'ciniki.ags.exhibit', 'object_id'=>$exhibit_id));

    return array('stat'=>'ok', 'id'=>$exhibit_id);
}
?>
