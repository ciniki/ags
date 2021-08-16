<?php
//
// Description
// -----------
// This method will add a new item for the tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to add the Item to.
//
// Returns
// -------
//
function ciniki_ags_itemAdd(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'exhibitor_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Exhibitor'),
        'exhibitor_code'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Code'),
        'code'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Code'),
        'name'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Name'),
        'permalink'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Permalink'),
        'status'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Status'),
        'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
        'unit_amount'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'number', 'name'=>'Price'),
        'unit_discount_amount'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'number', 'name'=>'Discount Amount'),
        'unit_discount_percentage'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'number', 'name'=>'Discount Percent'),
        'fee_percent'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'percent', 'name'=>'Fee Percent'),
        'taxtype_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Tax'),
        'shipping_profile_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Shipping Profile'),
        'donor_customer_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Donor'),
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
        'exhibit_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Exhibit'),
        'quantity'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Inventory'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.itemAdd');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Setup permalink
    //
    if( !isset($args['permalink']) || $args['permalink'] == '' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'makePermalink');
        $args['permalink'] = ciniki_core_makePermalink($ciniki, $args['code'] . '-' . $args['name']);
    }

    //
    // Make sure the permalink is unique
    //
    $strsql = "SELECT id, name, permalink "
        . "FROM ciniki_ags_items "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND permalink = '" . ciniki_core_dbQuote($ciniki, $args['permalink']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( $rc['num_rows'] > 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.76', 'msg'=>'You already have a item with that name, please choose another.'));
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
    // Add the item to the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.ags.item', $args, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
        return $rc;
    }
    $item_id = $rc['id'];

    //
    // Update the types
    //
    if( isset($args['types']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'tagsUpdate');
        $rc = ciniki_core_tagsUpdate($ciniki, 'ciniki.ags', 'itemtag', $args['tnid'],
            'ciniki_ags_item_tags', 'ciniki_ags_history',
            'item_id', $item_id, 10, $args['types']);
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
            'item_id', $item_id, 20, $args['categories']);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return $rc;
        }
    }

    //
    // If the exhibit is specified add to the exhibit
    //
    if( isset($args['exhibit_id']) && $args['exhibit_id'] != '' && $args['exhibit_id'] > 0 ) {
        $exhibit_item = array(
            'exhibit_id' => $args['exhibit_id'],
            'item_id' => $item_id,
            'inventory' => (isset($args['quantity']) && $args['quantity'] != 0 ? $args['quantity'] : 0),
            'fee_percent' => (isset($args['fee_percent']) ? $args['fee_percent'] : 0),
            );
        $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.ags.exhibititem', $exhibit_item, 0x04);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.123', 'msg'=>'Unable to add item to the exhibit', 'err'=>$rc['err']));
        }
        //
        // Add Log entry
        //
        $dt = new DateTime('now', new DateTimezone('UTC'));
        $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.ags.itemlog', array(
            'item_id' => $item_id,
            'action' => 10,
            'actioned_id' => $args['exhibit_id'],
            'quantity' => $args['quantity'],
            'log_date' => $dt->format('Y-m-d H:i:s'),
            'user_id' => $ciniki['session']['user']['id'],
            'notes' => '',
            ), 0x04);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.232', 'msg'=>'Unable to add log', 'err'=>$rc['err']));
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
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'ciniki.ags.item', 'object_id'=>$item_id));

    return array('stat'=>'ok', 'id'=>$item_id);
}
?>
