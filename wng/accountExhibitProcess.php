<?php
//
// Description
// -----------
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_ags_wng_accountExhibitProcess(&$ciniki, $tnid, &$request, $item) {

    $blocks = array();

    if( !isset($item['ref']) ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "Request error, please contact us for help..",
            )));
    }

    if( !isset($request['session']['customer']['id']) || $request['session']['customer']['id'] <= 0 ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "You must be logged in."
            )));
    }

    //
    // Build base url
    //
    $base_url = '';
    for($i = 0; $i <= $request['cur_uri_pos'];$i++) {
        $base_url .= '/' . $request['uri_split'][$i];
    }

    //
    // Get the permalink
    //
    if( !isset($request['uri_split'][($request['cur_uri_pos'])])
        && $request['uri_split'][($request['cur_uri_pos'])] == ''
        ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "No exhibit specified."
            )));
    }
    $exhibit_permalink = $request['uri_split'][($request['cur_uri_pos'])];

    //
    // Check if action on item being taken
    //
    if( isset($request['uri_split'][($request['cur_uri_pos']+2)])
        && in_array($request['uri_split'][($request['cur_uri_pos']+1)], ['remove', 'cancel', 'add'])
        && $request['uri_split'][($request['cur_uri_pos']+2)] != ''
        ) {
        $action = $request['uri_split'][($request['cur_uri_pos']+1)];
        $item_permalink = $request['uri_split'][($request['cur_uri_pos']+2)];
    }

    //
    // Load the tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "Internal."
            )));
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    
    //
    // Load the exhibitor
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountExhibitorLoad');
    $rc = ciniki_ags_wng_accountExhibitorLoad($ciniki, $tnid, $request);
    if( $rc['stat'] != 'ok' && $rc['err']['code'] == 'ciniki.ags.314' ) {
        $item['apply'] = 'yes';
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountProfileProcess');
        return ciniki_ags_wng_accountProfileProcess($ciniki, $tnid, $request, $item);
    } elseif( $rc['stat'] != 'ok' ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "Internal Error, please try again."
            )));
    }
    $exhibitor = $rc['exhibitor'];
    
    //
    // Load the exhibit
    //
    $strsql = "SELECT exhibits.id, "
        . "exhibits.name, "
        . "exhibits.permalink, "
        . "exhibits.status, "
        . "exhibits.flags, "
        . "exhibits.primary_image_id, "
        . "exhibits.synopsis, "
        . "exhibits.description, "
        . "exhibits.application_description, "
        . "IFNULL(participants.id, 0) AS participant_id, "
        . "IFNULL(participants.status, 0) AS participant_status "
        . "FROM ciniki_ags_exhibits AS exhibits "
        . "LEFT JOIN ciniki_ags_participants AS participants ON ("
            . "exhibits.id = participants.exhibit_id "
            . "AND participants.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $exhibitor['id']) . "' "
            . "AND participants.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND exhibits.permalink = '" . ciniki_core_dbQuote($ciniki, $exhibit_permalink) . "' "
        . "AND exhibits.status < 90 "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibit');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "Unable to load exhibit."
            )));
    }
    if( !isset($rc['exhibit']) ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "Exhibit not found."
            )));
    }
    $exhibit = $rc['exhibit'];

    //
    // Check if item requested from this exhibit
    //
    if( isset($request['uri_split'][($request['cur_uri_pos']+2)])
        && $request['uri_split'][($request['cur_uri_pos']+1)] == 'item'
        && $request['uri_split'][($request['cur_uri_pos']+2)] != ''
        && ($exhibit['flags']&0x0100) == 0x0100 // Web Updates Enabled
        ) {
        $request['cur_uri_pos']+=2;
        $request['return-url'] = $base_url;
        $item['exhibitor'] = $exhibitor;
        $item['exhibit_id'] = $exhibit['id'];
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountItemProcess');
        return ciniki_ags_wng_accountItemProcess($ciniki, $tnid, $request, $item);
    }
    
    $blocks[] = array(
        'type' => 'title',
        'class' => 'limit-width limit-width-80',
        'title' => $exhibit['name'],
        );
    if( $exhibit['status'] == 30 ) {
        if( $exhibit['application_description'] != '' ) {
            $blocks[] = array(
                'type' => 'text',
                'class' => 'limit-width limit-width-80',
                'content' => $exhibit['application_description'],
                );
        } else {
            $blocks[] = array(
                'type' => 'text',
                'class' => 'limit-width limit-width-80',
                'content' => $exhibit['description'],
                );
        }
    }
    elseif( $exhibit['status'] == 50 
        && ($exhibit['flags']&0x0300) == 0x0300 
        && $exhibit['application_description'] != '' 
        && $exhibit['participant_status'] <= 30 
        ) {
        $blocks[] = array(
            'type' => 'text',
            'class' => 'limit-width limit-width-80',
            'content' => $exhibit['application_description'],
            );
    }

    //
    // Load the exhibitor items in the exhibit
    //
    $strsql = "SELECT items.id, "   
        . "items.uuid, "
        . "items.exhibitor_code, "
        . "items.code, "
        . "items.name, "
        . "items.permalink, "
        . "items.status, "
        . "items.flags, "
        . "items.unit_amount, "
        . "items.unit_discount_amount, "
        . "items.unit_discount_percentage, "
        . "items.fee_percent, "
        . "items.taxtype_id, "
        . "items.sapos_category, "
        . "items.primary_image_id, "
        . "items.synopsis, "
        . "items.description, "
        . "items.size, "
        . "items.framed_size, "
        . "items.notes, "
        . "items.requested_changes, "
        . "IFNULL(eitems.id, 0) AS eitem_id, "
        . "IFNULL(eitems.uuid, '') AS eitem_uuid, "
        . "IFNULL(eitems.inventory, 0) AS inventory, "
        . "IFNULL(eitems.pending_inventory, 0) AS pending_inventory, "
        . "IFNULL(eitems.status, 0) AS eitem_status, "
        . "IFNULL(eitems.fee_percent, 0) AS efee_percent "
        . "FROM ciniki_ags_items AS items "
        . "LEFT JOIN ciniki_ags_exhibit_items AS eitems ON ("
            . "items.id = eitems.item_id "
            . "AND eitems.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $exhibit['id']) . "' "
            . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $exhibitor['id']) . "' "
        . "AND items.status < 90 "  // Not archived
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "ORDER BY items.name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'items', 'fname'=>'permalink', 
            'fields'=>array('id', 'uuid', 'exhibitor_code', 'code', 'name', 'permalink', 'status', 'flags', 
                'unit_amount', 'unit_discount_amount', 'unit_discount_percentage', 'fee_percent', 
                'taxtype_id', 'sapos_category', 'primary_image_id', 'synopsis', 'description', 'size', 
                'framed_size', 'notes', 
                'eitem_id', 'eitem_uuid', 'eitem_status', 'inventory', 'pending_inventory', 'efee_percent',
                'requested_changes',
                ),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.329', 'msg'=>'Unable to load items', 'err'=>$rc['err']));
    }
    $items = isset($rc['items']) ? $rc['items'] : array();

    //
    // Check for actions to take on items
    //
    if( isset($action) && isset($item_permalink) && !isset($items[$item_permalink]) ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "Item not found."
            )));
    } elseif( isset($action) && isset($item_permalink) ) {
        $ags_item = $items[$item_permalink];
        //
        // Cancel handles removing pending item
        //
        if( $action == 'cancel' ) {
            //
            // Item has pending inventory update
            //
            if( $ags_item['eitem_status'] == 50 ) {
                //
                // Remove pending inventory change
                //
                if( $ags_item['pending_inventory'] != 0 ) {
                    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
                    $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.exhibititem', $ags_item['eitem_id'], array(
                        'pending_inventory' => 0,
                        ), 0x04);
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'ok', 'blocks'=>array(array(
                            'type' => 'msg', 
                            'level' => 'error',
                            'content' => "Error removing item, unable to update."
                            )));
                    }
                }
                if( $ags_item['requested_changes'] != '' ) {
                    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
                    $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.item', $ags_item['id'], array(
                        'requested_changes' => '',
                        ), 0x04);
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'ok', 'blocks'=>array(array(
                            'type' => 'msg', 
                            'level' => 'error',
                            'content' => "Error removing item, unable to update."
                            )));
                    }
                }
            }
            //
            // Item is pending to be added to exhibit
            //
            elseif( $ags_item['eitem_status'] == 30 ) {
                //
                // Delete from exhibit
                //
                ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
                $rc = ciniki_core_objectDelete($ciniki, $tnid, 'ciniki.ags.exhibititem',
                    $ags_item['eitem_id'], $ags_item['eitem_uuid'], 0x04);
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
                if( $ags_item['status'] == 30 ) {   // New Item
                    //
                    // Delete a new item that has not been accepted yet
                    //
                    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountItemDelete');
                    $rc = ciniki_ags_wng_accountItemDelete($ciniki, $tnid, $request, $ags_item);
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'ok', 'blocks'=>array(array(
                            'type' => 'msg', 
                            'level' => 'error',
                            'content' => "Error removing item, unable to update."
                            )));
                    }
                }
            }
            else {
                return array('stat'=>'ok', 'blocks'=>array(array(
                    'type' => 'msg', 
                    'level' => 'error',
                    'content' => "Item not part of exhibit."
                    )));
            }
        }
        //
        // Delete will remove from exhibit
        //
        elseif( $action == 'remove' ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.exhibititem', $ags_item['eitem_id'], array(
                'pending_inventory' => '-' . $ags_item['inventory'],
                ), 0x04);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'ok', 'blocks'=>array(array(
                    'type' => 'msg', 
                    'level' => 'error',
                    'content' => "Error removing item, unable to update."
                    )));
            }

        }
        //
        // Add a catalog item to the exhibit
        //
        elseif( $action == 'add' ) {
            //
            // Check to make sure exhibitor is part of this exhibit
            //
            if( $exhibit['participant_id'] == 0 ) {
                ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
                $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.ags.participant', array(
                    'exhibit_id' => $exhibit['id'],
                    'exhibitor_id' => $exhibitor['id'],
                    'status' => 30,
                    ), 0x04);
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'ok', 'blocks'=>array(array(
                        'type' => 'msg', 
                        'level' => 'error',
                        'content' => "Unable to submit application."
                        )));
                }
            }

            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
            $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.ags.exhibititem', array(
                'exhibit_id' => $exhibit['id'],
                'item_id' => $ags_item['id'],
                'status' => 30,
                'inventory' => 0,
                'pending_inventory' => 1,
                'fee_percent' => $ags_item['fee_percent'],
                ), 0x04);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'ok', 'blocks'=>array(array(
                    'type' => 'msg', 
                    'level' => 'error',
                    'content' => "Item could not be added to the exhibit."
                    )));
            }
        }
        //
        // Redirect back to main page
        //
        header("Location: $base_url");
        return array('stat'=>'exit');
    }

    //
    // Process items to exhibit items and catalog items
    //
    $inventory_items = array();
    $pending_items = array();
    $catalog_items = array();
    foreach($items as $iid => $itm) {
        //
        // Update with any requested changes
        //
        if( $itm['requested_changes'] != '' ) {
            $itm['requested_changes'] = unserialize($itm['requested_changes']);
            foreach($itm['requested_changes'] as $k => $v) {
                if( isset($items[$iid][$k]) ) {
                    $itm[$k] = $v;
                    $items[$iid][$k] = $v;
                }
            }
        }
        $itm['price'] = '$' . number_format($itm['unit_amount'], 2);
        if( $itm['eitem_id'] > 0 ) {
            if( $itm['eitem_status'] == 50 && $itm['requested_changes'] != '' ) {
                $itm['action'] = "<a class='button' href='{$base_url}/item/{$itm['permalink']}'>Edit</a>";
                $itm['action'] .= "<a class='button' href='{$base_url}/cancel/{$itm['permalink']}'>Cancel</a>";
                if( $itm['pending_inventory'] == 0 ) {
                    $itm['action_text'] = "Update Item";
                    $itm['pending_inventory'] = '';
                } else {
                    $itm['action_text'] = "Update Item & "
                        . ($itm['pending_inventory'] > 0 ? 'Add' : 'Remove') . ' Inventory';
                }
                $pending_items[] = $itm;
            }
            elseif( $itm['eitem_status'] == 50 && $itm['pending_inventory'] < 0 ) {
                $itm['action'] = "<a class='button' href='{$base_url}/item/{$itm['permalink']}'>Edit</a>";
                $itm['action'] .= "<a class='button' href='{$base_url}/cancel/{$itm['permalink']}'>Cancel</a>";
                $itm['action_text'] = "Remove Inventory";
                $itm['pending_inventory'] = abs($itm['pending_inventory']);
                $pending_items[] = $itm;
            }
            elseif( $itm['eitem_status'] == 50 && $itm['pending_inventory'] > 0 ) {
                $itm['action'] = "<a class='button' href='{$base_url}/item/{$itm['permalink']}'>Edit</a>";
                $itm['action'] .= "<a class='button' href='{$base_url}/cancel/{$itm['permalink']}'>Cancel</a>";
                $itm['action_text'] = "Add Inventory";
                $itm['pending_inventory'] = abs($itm['pending_inventory']);
                $pending_items[] = $itm;
            }
            // Not currently part of exhibit, or part of exhibit with new inventory pending
            elseif( $itm['eitem_status'] != 50 ) {
                $itm['action'] = "<a class='button' href='{$base_url}/item/{$itm['permalink']}'>Edit</a>";
                $itm['action'] .= "<a class='button' href='{$base_url}/cancel/{$itm['permalink']}'>Cancel</a>";
                $itm['pending_inventory'] = abs($itm['pending_inventory']);
                $itm['action_text'] = "New Item";
                $pending_items[] = $itm;
            }
            // Not currently part of exhibit, or part of exhibit with new inventory pending
            elseif( $itm['eitem_status'] != 50 || $itm['pending_inventory'] > 0 ) {
                $itm['action'] = "<a class='button' href='{$base_url}/item/{$itm['permalink']}'>Edit</a>";
                $itm['action'] .= "<a class='button' href='{$base_url}/cancel/{$itm['permalink']}'>Cancel</a>";
                $itm['pending_inventory'] = abs($itm['pending_inventory']);
                $itm['action_text'] = "Add";
                $pending_items[] = $itm;
            }
            elseif( $itm['eitem_status'] == 50 ) {
                $itm['action'] = "<a class='button' href='{$base_url}/item/{$itm['permalink']}'>Edit</a>";
                $itm['action'] .= "<a class='button' href='{$base_url}/remove/{$itm['permalink']}'>Remove</a>";
                $inventory_items[] = $itm;
            } 
        } else {
            $itm['action'] = "<a class='button' href='{$base_url}/add/{$itm['permalink']}'>Add</a>";
            $catalog_items[] = $itm;
        }
    }

    //
    // Output the blocks to show the inventory/pending/catalog
    //
    $message = '';
    if( count($inventory_items) > 0 ) {
        $blocks[] = array(
            'type' => 'table',
            'title' => 'Inventory Items',
            'class' => 'limit-width limit-width-80 fold-at-50',
            'headers' => 'yes',
            'columns' => array( 
                array('label'=>'Code', 'fold-label'=>'Code: ', 'field'=>'code', 'class'=>''),
                array('label'=>'Name', 'fold-label'=>'Name: ', 'field'=>'name', 'class'=>''),
                array('label'=>'Price', 'fold-label'=>'Price: ', 'field'=>'price', 'class'=>'alignright'),
                array('label'=>'Quantity', 'fold-label'=>'Quantity: ', 'field'=>'inventory', 'class'=>'alignright'),
                array('label'=>'', 'field'=>'action', 'class'=>'alignright buttons'),
                ),
            'rows' => $inventory_items,
            );
    } elseif( count($pending_items) == 0 && count($catalog_items) > 0 && $exhibit['status'] == 30 ) {
        $message = "If you would like to apply for this exhibit, you can add items from your catalog below you use the Add Item button to add new items.";
    } elseif( count($pending_items) == 0 && count($catalog_items) == 0 && $exhibit['status'] == 30 ) {
        $message = "If you would like to apply for this exhibit, please use the Add New Item button to get started.";
    } elseif( count($pending_items) == 0 && count($catalog_items) > 0 ) {
        $message = "You do not have any items in this exhibit. You can add items from your catalog below or use the Add Item button to add new items.";
    } elseif( count($pending_items) == 0 ) {
        $message = "You do not have any items in this exhibit. Use the Add Item button to add new items.";
    }
    if( $message != '' ) {
        $blocks[] = array(
            'type' => 'msg',
            'level' => 'neutral',
            'content' => $message,
            );
    }
    if( count($pending_items) > 0 ) {
        $blocks[] = array(
            'type' => 'table',
            'title' => 'Pending Items',
            'class' => 'limit-width limit-width-80 fold-at-50',
            'headers' => 'yes',
            'columns' => array( 
                array('label'=>'Code', 'fold-label'=>'Code: ', 'field'=>'code', 'class'=>''),
                array('label'=>'Name', 'fold-label'=>'Name: ', 'field'=>'name', 'class'=>''),
                array('label'=>'Price', 'fold-label'=>'Price: ', 'field'=>'price', 'class'=>'alignright'),
                array('label'=>'Action', 'fold-label'=>'Action: ', 'field'=>'action_text', 'class'=>'alignright'),
                array('label'=>'Quantity', 'fold-label'=>'Quantity: ', 'field'=>'pending_inventory', 'class'=>'alignright'),
                array('label'=>'', 'field'=>'action', 'class'=>'alignright buttons'),
                ),
            'rows' => $pending_items,
            );
    }
    $blocks[] = array(
        'type' => 'buttons', 
        'class' => 'aligncenter',
        'list' => array(
            array('text'=>'Add New Item', 'url'=>$base_url . '/item/0'),
            ),
        );
    if( count($catalog_items) > 0 ) {
        $blocks[] = array(
            'type' => 'table',
            'title' => 'Items not in ' . $exhibit['name'],
            'class' => 'limit-width limit-width-80 fold-at-50',
            'headers' => 'yes',
            'columns' => array( 
                array('label'=>'Code', 'fold-label'=>'Code: ', 'field'=>'code', 'class'=>''),
                array('label'=>'Name', 'fold-label'=>'Name: ', 'field'=>'name', 'class'=>''),
                array('label'=>'Price', 'fold-label'=>'Price: ', 'field'=>'price', 'class'=>'alignright'),
                array('label'=>'', 'field'=>'action', 'class'=>'alignright buttons'),
                ),
            'rows' => $catalog_items,
            );
    }

    return array('stat'=>'ok', 'blocks'=>$blocks);
}
?>
