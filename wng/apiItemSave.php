<?php
//
// Description
// -----------
// Save the item
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function ciniki_ags_wng_apiItemSave(&$ciniki, $tnid, $request) {
    
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');

    //
    // Make sure customer is logged in
    //
    if( !isset($request['session']['customer']['id']) || $request['session']['customer']['id'] <= 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.326', 'msg'=>'Not signed in'));
    }

    //
    // Load settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQuery');
    $rc = ciniki_core_dbDetailsQuery($ciniki, 'ciniki_ags_settings', 'tnid', $tnid, 'ciniki.ags', 'settings', '');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $settings = isset($rc['settings']) ? $rc['settings'] : array();
  
    //
    // Load the exhibitor
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountExhibitorLoad');
    $rc = ciniki_ags_wng_accountExhibitorLoad($ciniki, $tnid, $request);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.330', 'msg'=>'Unable to load your account.'));
    }
    $exhibitor = $rc['exhibitor'];

    //
    // Load the item
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountItemLoad');
    $rc = ciniki_ags_wng_accountItemLoad($ciniki, $tnid, $request, array(
        'item_permalink' => $_POST['f-item_permalink'],
        'exhibit_id' => $_POST['f-exhibit_id'],
        'exhibitor_id' => $exhibitor['id'],
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.331', 'msg'=>'Unable to load item.'));
    }
    if( !isset($rc['item']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.332', 'msg'=>'Unable to load item.'));
    }
    $item = $rc['item'];
    if( !isset($item['requested_changes']) || !is_array($item['requested_changes']) ) {
        $item['requested_changes'] = array();
    }

    //
    // Make sure the action is either add or update
    //
    if( !isset($_POST['f-action']) || !in_array($_POST['f-action'], ['add', 'update']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.339', 'msg'=>'Form Error: No action specified.'));
    }

    if( isset($_POST['f-unit_amount']) ) {
        $_POST['f-unit_amount'] = number_format(preg_replace("/[^0-9\.]/", '', $_POST['f-unit_amount']), 2);
    }

    $update_args = array();
    $fields = array(
        'primary_image_id' => 'Item Image', 
        'name' => 'Item Name', 
        'exhibitor_code' => 'Your Code', 
        'unit_amount' => 'Price', 
        'categories' => 'Category', 
        'subcategories' => 'Subcategory', 
        'medium' => 'Medium', 
        'creation_year' => 'Creation Year', 
        'size' => 'Size', 
        'framed_size' => 'Framed Size', 
        'current_condition' => 'Current Condition', 
        'new_inventory' => 'Inventory',
        'synopsis' => 'Synopsis', 
        'description' => 'Description',
        );
    $form_errors = '';
    foreach($fields as $field => $label) {
        if( $field == 'new_inventory' && isset($_POST["f-new_inventory"]) ) {
            if( isset($_POST['f-action']) && $_POST['f-action'] == 'add' ) {
                $item['new_pending_inventory'] = $_POST["f-{$field}"];
            } else {
                $item['new_pending_inventory'] = $_POST["f-{$field}"] - $item['inventory'];
            }
        }
        elseif( isset($settings["web-updater-item-{$field}"]) 
            && $settings["web-updater-item-{$field}"] != 'hidden' 
            && $field != 'primary_image_id'
//            && $field != 'new_inventory'
            && isset($_POST["f-{$field}"])
            ) {
            $_POST["f-{$field}"] = preg_replace("/\r/", '', $_POST["f-{$field}"]);
            if( $item[$field] != $_POST["f-{$field}"] ) {
                if( isset($_POST['f-action']) && $_POST['f-action'] == 'add' ) {
                    $item[$field] = $_POST["f-{$field}"];
                } else {
                    $item['requested_changes'][$field] = $_POST["f-{$field}"];
                }
            } elseif( isset($item['requested_changes'][$field]) ) {
                unset($item['requested_changes'][$field]);
            }
        }
        //
        // Check for required fields, based on if this is an add or update
        //
        if( isset($settings["web-updater-item-{$field}"]) 
            && $settings["web-updater-item-{$field}"] == 'required' 
            && $field == 'primary_image_id'
            ) {
            if( isset($_POST['f-action']) && $_POST['f-action'] == 'add'
                && (!isset($_FILES["f-{$field}"]) || $_FILES["f-{$field}"]['tmp_name'] == '')
                ) {
                $form_errors .= ($form_errors != '' ? "\n" : '') . "You must upload an {$label}.";
            }
            elseif( isset($_POST['f-action']) && $_POST['f-action'] == 'update'
                && $item['primary_image_id'] == 0 
                && (!isset($_FILES["f-{$field}"]) || $_FILES["f-{$field}"]['tmp_name'] == '')
                && $item[$field] == 0
                ) {
                $form_errors .= ($form_errors != '' ? "\n" : '') . "You need to upload an {$label}.";
            }
        }
        elseif( isset($settings["web-updater-item-{$field}"]) 
            && $settings["web-updater-item-{$field}"] == 'required' 
            && isset($_POST['f-action']) && $_POST['f-action'] == 'add'
            && $item[$field] == '' 
            ) {
            $form_errors .= ($form_errors != '' ? "\n" : '') . "You must specifiy the {$label}.";
        }
        elseif( isset($settings["web-updater-item-{$field}"]) 
            && $settings["web-updater-item-{$field}"] == 'required' 
            && isset($_POST['f-action']) && $_POST['f-action'] == 'update'
            && isset($item['requested_changes'][$field])
            && $item['requested_changes'][$field] == ''
            ) {
            $form_errors .= ($form_errors != '' ? "\n" : '') . "You must specifiy the {$label}.";
        }
    }
    if( $form_errors != '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.338', 'msg'=>"{$form_errors}"));
    }

    //
    // Check if adding a new item
    //
    if( isset($_POST['f-action']) && $_POST['f-action'] == 'add' ) {

        //
        // Assign and code
        //
        $strsql = "SELECT MAX(code) AS num "
            . "FROM ciniki_ags_items "
            . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $exhibitor['id']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.335', 'msg'=>'Unable to load code', 'err'=>$rc['err']));
        }
        if( isset($rc['item']['num']) ) {
            $max_num = preg_replace("/[^0-9]/", '', $rc['item']['num']);
            $item['code'] = $exhibitor['code'] . '-' . sprintf("%04d", ($max_num + 1));
        } else {
            $item['code'] = $exhibitor['code'] . '-0001';
        }

        $item['exhibitor_id'] = $exhibitor['id'];

        //
        // Setup the permalink
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'makePermalink');
        $item['permalink'] = ciniki_core_makePermalink($ciniki, $item['code'] . '-' . $item['name']);

        //
        // Make sure the permalink is unique
        //
        $strsql = "SELECT id, name, permalink "
            . "FROM ciniki_ags_items "
            . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $exhibitor['id']) . "' "
            . "AND ("
                . "permalink = '" . ciniki_core_dbQuote($ciniki, $item['permalink']) . "' "
                . "OR name = '" . ciniki_core_dbQuote($ciniki, $item['name']) . "' "
                . ") "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( $rc['num_rows'] > 0 ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.336', 'msg'=>'You already have a item with that name, please choose another.'));
        }

        //
        // Setup the default fee percent
        //
        $item['fee_percent'] = isset($settings['defaults-item-fee-percent']) ? ($settings['defaults-item-fee-percent']/100) : 0;

        //
        // Start a transaction
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
        // Add the item
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
        $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.ags.item', $item, 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.334', 'msg'=>'Unable to save item', 'err'=>$rc['err']));
        }
        $item['id'] = $rc['id'];

        //
        // Check for an image
        //
        if( isset($_FILES['f-primary_image_id']['tmp_name']) && $_FILES['f-primary_image_id']['tmp_name'] != '' ) {
            //
            // Save the image
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'insertFromUpload');
            $rc = ciniki_images_insertFromUpload($ciniki, $tnid, -3, $_FILES['f-primary_image_id'], 1, '', '', 'no');
            // Duplicates allowed, reuse image id
            if( $rc['stat'] != 'ok' && $rc['err']['code'] != 'ciniki.images.66' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.342', 'msg'=>'Unable to save image', 'err'=>$rc['err']));
            }
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.item', $item['id'], array(
                'primary_image_id' => $rc['id'],
                ), 0x04);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.343', 'msg'=>'Unable to save image', 'err'=>$rc['err']));
            }
        }

        //
        // Check for categories
        //
        if( isset($_POST['f-categories']) && $_POST['f-categories'] != '' ) {
            $categories = str_getcsv($_POST['f-categories']);
            if( count($categories) > 0 ) {
                ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'tagsUpdate');
                $rc = ciniki_core_tagsUpdate($ciniki, 'ciniki.ags', 'itemtag', $tnid,
                    'ciniki_ags_item_tags', 'ciniki_ags_history',
                    'item_id', $item['id'], 20, $categories);
                if( $rc['stat'] != 'ok' ) {
                    ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                    return $rc;
                }
            }
        }

        //
        // Check for subcategories
        //
        if( isset($_POST['f-subcategories']) && $_POST['f-subcategories'] != '' ) {
            $subcategories = str_getcsv($_POST['f-subcategories']);
            if( count($subcategories) > 0 ) {
                ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'tagsUpdate');
                $rc = ciniki_core_tagsUpdate($ciniki, 'ciniki.ags', 'itemtag', $tnid,
                    'ciniki_ags_item_tags', 'ciniki_ags_history',
                    'item_id', $item['id'], 30, $subcategories);
                if( $rc['stat'] != 'ok' ) {
                    ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                    return $rc;
                }
            }
        }

        //
        // Add exhibit item
        //
        if( isset($_POST['f-exhibit_id']) && $_POST['f-exhibit_id'] > 0 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
            $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.ags.exhibititem', array(
                'exhibit_id' => $_POST['f-exhibit_id'],
                'item_id' => $item['id'],
                'status' => 30,
                'inventory' => 0,
                'pending_inventory' => isset($item['new_pending_inventory']) ? $item['new_pending_inventory'] : 1,
                'fee_percent' => isset($settings['defaults-item-fee-percent']) ? ($settings['defaults-item-fee-percent']/100) : 0,
                ), 0x04);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.333', 'msg'=>'Unable to add the exhibititem', 'err'=>$rc['err']));
            }
        }
        
        //
        // Commit the transaction
        //
        $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.ags');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }

        return array('stat'=>'ok', 'item_id'=>$item['id']);
    }
    //
    // Check if updating an existing item
    //
    elseif( isset($_POST['f-action']) && $_POST['f-action'] == 'update' ) {
        //
        // Start a transaction
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
        // Check for an image
        //
        if( isset($_FILES['f-primary_image_id']['tmp_name']) && $_FILES['f-primary_image_id']['tmp_name'] != '' ) {
            //
            // Save the image
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'insertFromUpload');
            $rc = ciniki_images_insertFromUpload($ciniki, $tnid, -3, $_FILES['f-primary_image_id'], 1, '', '', 'no');
            if( $rc['stat'] != 'ok' && $rc['err']['code'] != 'ciniki.images.66' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.342', 'msg'=>'Unable to save image', 'err'=>$rc['err']));
            }
            $item['requested_changes']['primary_image_id'] = $rc['id'];
        }
        
        if( is_array($item['requested_changes']) && count($item['requested_changes']) > 0 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            if( $item['status'] == 30 ) {
                $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.item', $item['id'], $item['requested_changes'], 0x04);
            } else {
                $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.item', $item['id'], array(
                    'requested_changes' => serialize($item['requested_changes']),
                    ), 0x04);
            }
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.341', 'msg'=>'Unable to update the item', 'err'=>$rc['err']));
            }
        } elseif( is_array($item['requested_changes']) && count($item['requested_changes']) == 0 && $item['status'] > 30 ) {
            $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.item', $item['id'], array(
                'requested_changes' => '',
                ), 0x04);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.341', 'msg'=>'Unable to update the item', 'err'=>$rc['err']));
            }
        }

        //
        // Check if categories need updating
        //
        if( isset($_POST['f-categories']) && $_POST['f-categories'] != $item['categories'] ) {
            $categories = str_getcsv($_POST['f-categories']);
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'tagsUpdate');
            $rc = ciniki_core_tagsUpdate($ciniki, 'ciniki.ags', 'itemtag', $tnid,
                'ciniki_ags_item_tags', 'ciniki_ags_history',
                'item_id', $item['id'], 20, $categories);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                return $rc;
            }
        }
        
        //
        // Check if categories need updating
        //
        if( isset($_POST['f-subcategories']) && $_POST['f-subcategories'] != $item['subcategories'] ) {
            $subcategories = str_getcsv($_POST['f-subcategories']);
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'tagsUpdate');
            $rc = ciniki_core_tagsUpdate($ciniki, 'ciniki.ags', 'itemtag', $tnid,
                'ciniki_ags_item_tags', 'ciniki_ags_history',
                'item_id', $item['id'], 30, $subcategories);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                return $rc;
            }
        }

        //
        // Check if inventory is changing 
        //
        if( isset($item['new_pending_inventory']) && $item['new_pending_inventory'] != $item['pending_inventory'] ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.exhibititem', $item['eitem_id'], array(
                'pending_inventory' => $item['new_pending_inventory'],
                ), 0x04);
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

        return array('stat'=>'ok');
    }


    //
    // Should never reach this point
    //
    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.337', 'msg'=>'Unknown error.'));
}
?>
