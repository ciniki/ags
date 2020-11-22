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
function ciniki_ags_itemUpdate(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'item_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Item'),
        'exhibitor_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Exhibitor'),
        'exhibitor_code'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Code'),
        'code'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Code'),
        'name'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Name'),
        'permalink'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Permalink'),
        'status'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Status'),
        'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
        'unit_amount'=>array('required'=>'no', 'blank'=>'no', 'type'=>'number', 'name'=>'Price'),
        'unit_discount_amount'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'number', 'name'=>'Discount Amount'),
        'unit_discount_percentage'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'number', 'name'=>'Discount Percent'),
        'fee_percent'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'percent', 'name'=>'Fee Percent'),
        'taxtype_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Tax'),
        'shipping_profile_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Shipping Profile'),
        'primary_image_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Image'),
        'synopsis'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Synopsis'),
        'description'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Description'),
        'creation_year'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Creation Year'),
        'medium'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Medium'),
        'size'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Size'),
        'current_condition'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Condition'),
        'tag_info'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Tag Info'),
        'notes'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Notes'),
        'types'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'list', 'delimiter'=>'::', 'name'=>'Types'),
        'categories'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'list', 'delimiter'=>'::', 'name'=>'Categories'),
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.itemUpdate');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Get the existing details
    //
    $strsql = "SELECT id, code, name, permalink "
        . "FROM ciniki_ags_items "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.51', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
    }
    if( !isset($rc['item']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.52', 'msg'=>'Unable to find requested item'));
    }
    $item = $rc['item'];

    //
    // If code or name is changed, then redo permalink
    //
    if( isset($args['code']) || isset($args['name']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'makePermalink');
        $codename = (isset($args['code']) ? $args['code'] : $item['code']) . '-' . (isset($args['name']) ? $args['name'] : $item['name']);
        $args['permalink'] = ciniki_core_makePermalink($ciniki, $codename);
        if( $args['permalink'] != $item['permalink'] ) {
            //
            // Make sure the permalink is unique
            //
            $strsql = "SELECT id, name, permalink "
                . "FROM ciniki_ags_items "
                . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . "AND permalink = '" . ciniki_core_dbQuote($ciniki, $args['permalink']) . "' "
                . "AND id <> '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            if( $rc['num_rows'] > 0 ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.82', 'msg'=>'You already have an item with this name, please choose another.'));
            }
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
    // Update the Item in the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.ags.item', $args['item_id'], $args, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
        return $rc;
    }

    //
    // Update the types
    //
    if( isset($args['types']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'tagsUpdate');
        $rc = ciniki_core_tagsUpdate($ciniki, 'ciniki.ags', 'itemtag', $args['tnid'],
            'ciniki_ags_item_tags', 'ciniki_ags_history',
            'item_id', $args['item_id'], 10, $args['types']);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return $rc;
        }
    }

    //
    // Update the categories
    //
    if( isset($args['categories']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'tagsUpdate');
        $rc = ciniki_core_tagsUpdate($ciniki, 'ciniki.ags', 'itemtag', $args['tnid'],
            'ciniki_ags_item_tags', 'ciniki_ags_history',
            'item_id', $args['item_id'], 20, $args['categories']);
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
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'ciniki.ags.item', 'object_id'=>$args['item_id']));

    return array('stat'=>'ok');
}
?>
