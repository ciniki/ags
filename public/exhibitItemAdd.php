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
function ciniki_ags_exhibitItemAdd(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'exhibit_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Exhibit'),
        'item_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Item'),
        'quantity'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Quantity'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitItemAdd');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Set the default quantity to 1
    //
    if( !isset($args['quantity']) || $args['quantity'] == '' ) {
        $args['quantity'] = 1;
    }

    //
    // Get the details about the item
    //
    $strsql = "SELECT id, code, name, unit_amount, fee_percent "
        . "FROM ciniki_ags_items "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.20', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
    }
    if( !isset($rc['item']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.21', 'msg'=>'That item does not exist'));
    }
    $item = $rc['item'];
    
    //
    // Check if the item is already a part of the exhibit
    //
    $strsql = "SELECT id, item_id, exhibit_id, item_id, inventory, fee_percent "
        . "FROM ciniki_ags_exhibit_items "
        . "WHERE exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
        . "AND item_id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.19', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
    }
    $exhibititem = isset($rc['item']) ? $rc['item'] : null;

    //
    // Start transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.ags');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    
    //
    // If the item already exists, add 1 to the inventory
    //
    if( $exhibititem != null ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
        $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.ags.exhibititem', $exhibititem['id'], array(
            'inventory'=>$exhibititem['inventory'] + $args['quantity'],
            ), 0x04);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.151', 'msg'=>'Unable to add item', 'err'=>$rc['err']));
        }
        $exhibititem['code'] = $item['code'];
        $exhibititem['name'] = $item['name'];
        $exhibititem['unit_amount'] = $item['unit_amount'];
        $exhibititem['inventory'] += $args['quantity'];
    } else {
        $args['fee_percent'] = $item['fee_percent'];
        $args['inventory'] = $args['quantity'];
        $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.ags.exhibititem', $args, 0x04);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.179', 'msg'=>'Unable to add item', 'err'=>$rc['err']));
        }
        $exhibititem = array(
            'exhibit_item_id' => $rc['id'],
            'item_id' => $args['item_id'],
            'code' => $item['code'],
            'name' => $item['name'],
            'inventory' => $args['quantity'],
            'unit_amount' => $item['unit_amount'],
            'fee_percent' => $item['fee_percent'],
            );
    }

    //
    // Add Log entry
    //
    $dt = new DateTime('now', new DateTimezone('UTC'));
    $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.ags.itemlog', array(
        'item_id' => $args['item_id'],
        'action' => 10,
        'actioned_id' => $args['exhibit_id'],
        'quantity' => $args['quantity'],
        'log_date' => $dt->format('Y-m-d H:i:s'),
        'user_id' => $ciniki['session']['user']['id'],
        'notes' => '',
        ), 0x04);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.178', 'msg'=>'Unable to add log', 'err'=>$rc['err']));
    }

    $exhibititem['unit_amount_display'] = '$' . number_format($exhibititem['unit_amount'], 2);

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

    return array('stat'=>'ok', 'item'=>$exhibititem);
}
?>
