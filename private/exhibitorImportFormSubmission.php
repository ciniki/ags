<?php
//
// Description
// -----------
// Import the items for an exhibitor from a form submission
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function ciniki_ags_exhibitorImportFormSubmission(&$ciniki, $tnid, $args) {

    //
    // Make sure required variables are passed
    //
    if( !isset($args['exhibit_id']) || $args['exhibit_id'] == 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.251', 'msg'=>'No exhibit specified'));
    }
    if( !isset($args['exhibitor_id']) || $args['exhibitor_id'] == 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.252', 'msg'=>'No exhibitor specified'));
    }
    if( !isset($args['submission_id']) || $args['submission_id'] == 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.253', 'msg'=>'No submissions specified'));
    }
    if( !ciniki_core_checkModuleActive($ciniki, 'ciniki.forms') ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.267', 'msg'=>'Forms not enabled'));
    }

    //
    // Load the form submission
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'formSubmissionParse');
    $rc = ciniki_ags_formSubmissionParse($ciniki, $tnid, $args['submission_id']);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.256', 'msg'=>'Unable to load submission', 'err'=>$rc['err']));
    }
    if( !isset($rc['exhibit']['items']) || count($rc['exhibit']['items']) == 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.257', 'msg'=>'No items found in submission'));
    }
    $exhibit = $rc['exhibit'];
    $items = $rc['exhibit']['items'];

    //
    // Setup the defaults for the item
    //
    foreach($items as $iid => $item) {
        if( !isset($items[$iid]['synopsis']) ) {
            $items[$iid]['synopsis'] = '';
        }
        if( !isset($items[$iid]['description']) ) {
            $items[$iid]['description'] = '';
        }
        if( !isset($items[$iid]['flags']) ) {
            $items[$iid]['flags'] = 0;
        }
        if( isset($args['fee_percent']) ) {
            $items[$iid]['fee_percent'] = preg_replace("/[^0-9\.]/", '', $args['fee_percent']);
        }
        if( isset($item['primary_image_id']) && $items[$iid]['primary_image_id'] > 0 ) {
            $items[$iid]['flags'] |= 0x02;  // Visible
        }
        if( isset($args['item_flags3']) && $args['item_flags3'] == 'yes' && ($items[$iid]['flags']&0x01) == 0x01 ) { // Sell Online
            $items[$iid]['flags'] |= 0x04; 
        }
        if( isset($args['item_flags5']) && $args['item_flags5'] == 'yes' ) {    // Tagged
            $items[$iid]['flags'] |= 0x10;
        }
        if( isset($args['item_synopsis']) ) {
            $items[$iid]['synopsis'] .= ($items[$iid]['synopsis'] != '' ? "\n\n" : '') . $args['item_synopsis'];
        }
        if( isset($args['item_description']) ) {
            $items[$iid]['description'] .= ($items[$iid]['description'] != '' ? "\n\n" : '') . $args['item_description'];
        }
        if( $exhibit['taxtype_id'] > 0 ) {
            $items[$iid]['taxtype_id'] = $exhibit['taxtype_id'];
        }
        $items[$iid]['exhibitor_id'] = $args['exhibitor_id'];
        $items[$iid]['exhibitor_code'] = $args['code'];
    }

    //
    // Load the existing items for the exhibitor
    //
    $strsql = "SELECT items.id, "
        . "IFNULL(exhibit.id, 0) AS exhibit_item_id, "
        . "items.code, "
        . "items.name, "
        . "items.tag_info, "
        . "items.status, "
        . "items.flags, "
        . "items.unit_amount, "
        . "items.taxtype_id, "
        . "items.primary_image_id, "
        . "IFNULL(exhibit.fee_percent, items.fee_percent) AS fee_percent, "
        . "IFNULL(exhibit.inventory, 0) AS inventory "
        . "FROM ciniki_ags_items AS items "
        . "LEFT JOIN ciniki_ags_exhibit_items AS exhibit ON ("
            . "items.id = exhibit.item_id "
            . "AND exhibit.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
            . "AND exhibit.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'exhibit_item_id', 'primary_image_id', 'code', 'name', 'status', 
                'flags', 'unit_amount', 'fee_percent', 'taxtype_id', 'tag_info', 'inventory'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.264', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
    }
    $exhibitor_items = isset($rc['items']) ? $rc['items'] : array();


    //
    // Check if items already exist
    //
    foreach($items as $item) {
        $item_found = null;
        foreach($exhibitor_items as $eitem) {
            if( $eitem['primary_image_id'] == $item['primary_image_id'] ) {
                $item_found = $eitem;
                break;
            }
            elseif( $eitem['name'] == $item['name'] ) {
                $item_found = $eitem;
                break;
            }
        }

        //
        // Add the item if not found as part of exhibitor inventory
        //
        if( $item_found == null ) {
            //
            // Get next available code for exhibitor
            //
            $strsql = "SELECT MAX(code) AS num "
                . "FROM ciniki_ags_items "
                . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . "AND exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.261', 'msg'=>'Unable to load code', 'err'=>$rc['err']));
            }
            if( isset($rc['item']['num']) ) {
                $max_num = preg_replace("/[^0-9]/", '', $rc['item']['num']);
                $item['code'] = $args['code'] . '-' . sprintf("%04d", ($max_num + 1));
            } else {
                $item['code'] = $args['code'] . '-0001';
            }
            
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'makePermalink');
            $item['permalink'] = ciniki_core_makePermalink($ciniki, $item['code'] . '-' . $item['name']);

            //
            // Make sure permalink is unique
            //
            $strsql = "SELECT id, name, permalink "
                . "FROM ciniki_ags_items "
                . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . "AND permalink = '" . ciniki_core_dbQuote($ciniki, $item['permalink']) . "' "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            if( $rc['num_rows'] > 0 ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.258', 'msg'=>'You already have a item with that name, please choose another.'));
            }

            //
            // Add the item to the database
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
            $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.ags.item', $item, 0x04);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                return $rc;
            }
            $item_id = $rc['id'];
            $item['id'] = $rc['id'];
            $item_found = $item;
        }

        //
        // Make sure item is part of exhibit
        //
        if( $item_found != null && (!isset($item_found['exhibit_item_id']) || $item_found['exhibit_item_id'] == 0) ) {
            $exhibit_item = array(
                'exhibit_id' => $args['exhibit_id'],
                'item_id' => $item_found['id'],
                'inventory' => 1,
                'fee_percent' => isset($item_found['fee_percent']) ? $item_found['fee_percent'] : 0,
                );
            $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.ags.exhibititem', $exhibit_item, 0x04);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.259', 'msg'=>'Unable to add item to the exhibit', 'err'=>$rc['err']));
            }
            //
            // Add Log entry
            //
            $dt = new DateTime('now', new DateTimezone('UTC'));
            $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.ags.itemlog', array(
                'item_id' => $item_found['id'],
                'action' => 10,
                'actioned_id' => $args['exhibit_id'],
                'quantity' => 1,
                'log_date' => $dt->format('Y-m-d H:i:s'),
                'user_id' => $ciniki['session']['user']['id'],
                'notes' => '',
                ), 0x04);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.260', 'msg'=>'Unable to add log', 'err'=>$rc['err']));
            }
        }
    }

    return array('stat'=>'ok');
}
?>
