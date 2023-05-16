<?php
//
// Description
// ===========
// This method will return all the information about an item.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the item is attached to.
// item_id:          The ID of the item to get the details for.
//
// Returns
// -------
//
function ciniki_ags_itemGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'item_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Item'),
        'exhibitor_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Exhibitor'),
        'images'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Images'),
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.itemGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load the module settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
    $rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_ags_settings', 'tnid', $args['tnid'], 'ciniki.ags', 'settings', '');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.158', 'msg'=>'Unable to load settings', 'err'=>$rc['err']));
    }
    $settings = isset($rc['settings']) ? $rc['settings'] : array();

    //
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');
    $mysql_date_format = ciniki_users_dateFormat($ciniki, 'mysql');

    //
    // Return default for new Item
    //
    if( $args['item_id'] == 0 ) {
        //
        // Get the next code for an item
        //
        $code = '';
        if( isset($args['exhibitor_id']) && $args['exhibitor_id'] != '' ) {
            //
            // Get the exhibitor code
            //
            $strsql = "SELECT id, customer_id, code, display_name "
                . "FROM ciniki_ags_exhibitors "
                . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
                . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibitor');
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.156', 'msg'=>'Unable to load exhibitor', 'err'=>$rc['err']));
            }
            if( !isset($rc['exhibitor']) ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.157', 'msg'=>'Unable to find requested exhibitor'));
            }
            $exhibitor = $rc['exhibitor'];
            $donor_customer_id = $rc['exhibitor']['customer_id'];

            //
            // Get the next number
            //
            $strsql = "SELECT MAX(code) AS num "
                . "FROM ciniki_ags_items "
                . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . "AND exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.90', 'msg'=>'Unable to load code', 'err'=>$rc['err']));
            }
            if( isset($rc['item']['num']) ) {
                $max_num = preg_replace("/[^0-9]/", '', $rc['item']['num']);
                $code = $exhibitor['code'] . '-' . sprintf("%04d", ($max_num + 1));
            } else {
                $code = $exhibitor['code'] . '-0001';
            }

        }
        $item = array(
            'id' => 0,
            'exhibitor_id' => (isset($args['exhibitor_id']) ? $args['exhibitor_id'] : ''),
            'exhibitor_code' => '',
            'code' => $code,
            'name' => '',
            'permalink' => '',
            'status' => '50',
            'flags' => 0x10,
            'unit_amount' => '',
            'unit_discount_amount' => '0',
            'unit_discount_percentage' => '0',
            'fee_percent' => (isset($settings['defaults-item-fee-percent']) ? $settings['defaults-item-fee-percent'] : ''),
            'taxtype_id' => '',
            'shipping_profile_id' => 0,
            'sapos_category' => '',
            'primary_image_id' => '0',
            'synopsis' => '',
            'description' => '',
            'quantity' => 1,
            'notes' => '',
            'donor_customer_id' => (isset($donor_customer_id) ? $donor_customer_id : 0),
        );

        //
        // Check if donor info should be loaded
        //
        if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.ags', 0x0100) && isset($donor_customer_id) && $donor_customer_id > 0 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerDetails2');
            $rc = ciniki_customers_hooks_customerDetails2($ciniki, $args['tnid'], array(
                'customer_id' => $donor_customer_id, 
                'name' => 'yes', 
                'addresses' => 'billing',
                ));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            if( isset($rc['customer']) ) {
                $item['donor_details'][0] = array(
                    'label' => 'Name',
                    'value' => $rc['customer']['display_name'],
                    );
                if( isset($rc['customer']['addresses'][0]['joined']) ) {
                    $item['donor_details'][1] = array(
                        'label' => 'Address',
                        'value' => $rc['customer']['addresses'][0]['joined'],
                        );
                }
            }
        }
    }

    //
    // Get the details for an existing Item
    //
    else {
        $strsql = "SELECT ciniki_ags_items.id, "
            . "ciniki_ags_items.exhibitor_id, "
            . "ciniki_ags_items.exhibitor_code, "
            . "ciniki_ags_items.code, "
            . "ciniki_ags_items.name, "
            . "ciniki_ags_items.permalink, "
            . "ciniki_ags_items.status, "
            . "ciniki_ags_items.flags, "
            . "ciniki_ags_items.unit_amount, "
            . "ciniki_ags_items.unit_discount_amount, "
            . "ciniki_ags_items.unit_discount_percentage, "
            . "ciniki_ags_items.fee_percent, "
            . "ciniki_ags_items.taxtype_id, "
            . "ciniki_ags_items.shipping_profile_id, "
            . "ciniki_ags_items.sapos_category, "
            . "ciniki_ags_items.donor_customer_id, "
            . "ciniki_ags_items.primary_image_id, "
            . "ciniki_ags_items.synopsis, "
            . "ciniki_ags_items.description, "
            . "ciniki_ags_items.tag_info, "
            . "ciniki_ags_items.creation_year, "
            . "ciniki_ags_items.medium, "
            . "ciniki_ags_items.size, "
            . "ciniki_ags_items.framed_size, "
            . "ciniki_ags_items.current_condition, "
            . "ciniki_ags_items.notes, "
            . "ciniki_ags_items.requested_changes "
            . "FROM ciniki_ags_items "
            . "WHERE ciniki_ags_items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_ags_items.id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'items', 'fname'=>'id', 
                'fields'=>array('exhibitor_id', 'exhibitor_code', 'code', 'name', 'permalink', 'status', 'flags', 
                    'unit_amount', 'unit_discount_amount', 'unit_discount_percentage', 'fee_percent', 
                    'taxtype_id', 'shipping_profile_id', 'sapos_category', 'donor_customer_id',
                    'primary_image_id', 'synopsis', 'description', 'tag_info', 
                    'creation_year', 'medium', 'size', 'framed_size', 'current_condition', 'notes', 'requested_changes'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.201', 'msg'=>'Item not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['items'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.202', 'msg'=>'Unable to find Item'));
        }
        $item = $rc['items'][0];
        $item['unit_amount'] = '$' . number_format($item['unit_amount'], 2);
        $item['fee_percent'] = (float)($item['fee_percent']*100) . '%';

        if( $item['requested_changes'] != '' ) {
            $item['requested_changes'] = unserialize($item['requested_changes']);
        }

        //
        // if the donor is not zero, load the details
        //
        $item['donor_details'] = array();
        if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.ags', 0x0100) ) {
            $donor_customer_id = $item['donor_customer_id'];
            if( $donor_customer_id == 0 ) {
                $strsql = "SELECT customer_id "
                    . "FROM ciniki_ags_exhibitors "
                    . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                    . "AND id = '" . ciniki_core_dbQuote($ciniki, $item['exhibitor_id']) . "' "
                    . "";
                $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'customer');
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.242', 'msg'=>'Unable to load customer', 'err'=>$rc['err']));
                }
                if( isset($rc['customer']['customer_id']) ) {
                    $donor_customer_id = $rc['customer']['customer_id'];
                }
            }
            //
            // Load the customer details
            //
            if( $donor_customer_id > 0 ) {
                ciniki_core_loadMethod($ciniki, 'ciniki', 'customers', 'hooks', 'customerDetails2');
                $rc = ciniki_customers_hooks_customerDetails2($ciniki, $args['tnid'], array(
                    'customer_id' => $donor_customer_id, 
                    'name' => 'yes', 
                    'addresses' => 'billing',
                    ));
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
                if( isset($rc['customer']) ) {
                    $item['donor_details'][0] = array(
                        'label' => 'Name',
                        'value' => $rc['customer']['display_name'],
                        );
                    if( isset($rc['customer']['addresses'][0]['joined']) ) {
                        $item['donor_details'][1] = array(
                            'label' => 'Address',
                            'value' => $rc['customer']['addresses'][0]['joined'],
                            );
                    }
                }
            }
        }

        //
        // Get the categories
        //
        $strsql = "SELECT tag_type, tag_name AS names "
            . "FROM ciniki_ags_item_tags "
            . "WHERE item_id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "ORDER BY tag_type, tag_name "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.lapt', array(
            array('container'=>'tags', 'fname'=>'tag_type', 
                'fields'=>array('tag_type', 'names'), 'dlists'=>array('names'=>'::')),
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['tags']) ) {
            foreach($rc['tags'] as $tags) {
                if( $tags['tag_type'] == 10 ) {
                    $item['types'] = $tags['names'];
                } elseif( $tags['tag_type'] == 20 ) {
                    $item['categories'] = $tags['names'];
                } elseif( $tags['tag_type'] == 30 ) {
                    $item['subcategories'] = $tags['names'];
                } elseif( $tags['tag_type'] == 60 ) {
                    $item['tags'] = $tags['names'];
                }
            }
        }

        //
        // Load the images
        //
        if( isset($args['images']) && $args['images'] == 'yes' ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'loadCacheThumbnail');
            $strsql = "SELECT ciniki_ags_item_images.id, "
                . "ciniki_ags_item_images.image_id, "
                . "ciniki_ags_item_images.name, "
                . "ciniki_ags_item_images.sequence, "
                . "ciniki_ags_item_images.description "
                . "FROM ciniki_ags_item_images "
                . "WHERE ciniki_ags_item_images.item_id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
                . "AND ciniki_ags_item_images.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . "ORDER BY ciniki_ags_item_images.sequence, ciniki_ags_item_images.date_added, "
                    . "ciniki_ags_item_images.name "
                . "";
            $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.blog', array(
                array('container'=>'images', 'fname'=>'id', 'name'=>'image',
                    'fields'=>array('id', 'image_id', 'name', 'sequence', 'description')),
                ));
            if( $rc['stat'] != 'ok' ) { 
                return $rc;
            }
            if( isset($rc['images']) ) {
                $item['images'] = $rc['images'];
                foreach($item['images'] as $img_id => $img) {
                    if( isset($img['image_id']) && $img['image_id'] > 0 ) {
                        $rc = ciniki_images_loadCacheThumbnail($ciniki, $args['tnid'], $img['image_id'], 75);
                        if( $rc['stat'] != 'ok' ) {
                            return $rc;
                        }
                        $item['images'][$img_id]['image_data'] = 'data:image/jpg;base64,' . base64_encode($rc['image']);
                    }
                }
            } else {
                $item['images'] = array();
            }
        }

        //
        // Get inventory from exhibits for this item
        //
        $strsql = "SELECT items.id, "
            . "items.exhibit_id, "
            . "exhibits.name AS exhibit_name, "
            . "items.inventory "
            . "FROM ciniki_ags_exhibit_items AS items "
            . "INNER JOIN ciniki_ags_exhibits AS exhibits ON ("
                . "items.exhibit_id = exhibits.id "
                . "AND exhibits.status = 50 "
                . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                .") "
            . "WHERE items.item_id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'items', 'fname'=>'exhibit_id', 'fields'=>array('id', 'exhibit_id', 'exhibit_name', 'inventory'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.111', 'msg'=>'Unable to load items', 'err'=>$rc['err']));
        }
        $item['inventory'] = isset($rc['items']) ? $rc['items'] : array();

        //
        // Get the item history
        //
        $strsql = "SELECT logs.id, "
            . "DATE_FORMAT(DATE(logs.log_date), '" . ciniki_core_dbQuote($ciniki, $mysql_date_format) . "') AS log_date, "
            . "users.display_name, "
            . "logs.action, "
            . "logs.quantity, "
            . "logs.item_id, "
            . "items.code, "
            . "items.name AS item_name, "
            . "exhibits.name AS exhibit_name "
            . "FROM ciniki_ags_item_logs AS logs "
            . "INNER JOIN ciniki_ags_items AS items ON (" 
                . "logs.item_id = items.id "
                . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "LEFT JOIN ciniki_ags_exhibits AS exhibits ON (" 
                . "logs.actioned_id = exhibits.id "
                . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "LEFT JOIN ciniki_users AS users ON (" 
                . "logs.user_id = users.id "
                . ") "
            . "WHERE logs.item_id = '" . ciniki_core_dbQuote($ciniki, $args['item_id']) . "' "
            . "AND logs.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "ORDER BY logs.log_date DESC, logs.item_id, logs.action "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
//            array('container'=>'logdates', 'fname'=>'log_date', 
//                'fields'=>array('log_date'),
//                ),
            array('container'=>'logs', 'fname'=>'id', 
                'fields'=>array('display_name', 'log_date', 'action', 'quantity', 'item_id', 'code', 'item_name', 'exhibit_name'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.182', 'msg'=>'Unable to load logs', 'err'=>$rc['err']));
        }
        $item['logs'] = isset($rc['logs']) ? $rc['logs'] : array();
        foreach($item['logs'] as $lid => $log) {
            if( $log['action'] == 10 ) {
                $item['logs'][$lid]['action_text'] = 'Added';
            }
            elseif( $log['action'] == 50 ) {
                if( $log['quantity'] < 0 ) {
                    $item['logs'][$lid]['action_text'] = 'Update';
                } else {
                    $item['logs'][$lid]['action_text'] = 'Update';
                }
            }
            elseif( $log['action'] == 60 ) {
                $item['logs'][$lid]['action_text'] = 'Sold';
            }
            elseif( $log['action'] == 90 ) {
                $item['logs'][$lid]['action_text'] = 'Removed';
            }
        }
    }
    $rsp = array('stat'=>'ok', 'item'=>$item);

    //
    // Get the list of call types
    //
    $strsql = "SELECT DISTINCT tag_type, tag_name AS names "
        . "FROM ciniki_ags_item_tags "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "ORDER BY tag_type, tag_name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'tags', 'fname'=>'tag_type', 'fields'=>array('type'=>'tag_type', 'names'), 
            'dlists'=>array('names'=>'::')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $rsp['types'] = array();
    $rsp['categories'] = array();
    $rsp['subcategories'] = array();
    $rsp['tags'] = array();
    if( isset($rc['tags']) ) {
        foreach($rc['tags'] as $tid => $type) {
            if( $type['type'] == 10 ) {
                $rsp['types'] = explode('::', $type['names']);
            } elseif( $type['type'] == 20 ) {
                $rsp['categories'] = explode('::', $type['names']);
            } elseif( $type['type'] == 30 ) {
                $rsp['subcategories'] = explode('::', $type['names']);
            } elseif( $type['type'] == 60 ) {
                $rsp['tags'] = explode('::', $type['names']);
            }
        }
    }

    //
    // Get the shipping profiles from sapos if enabled
    //
    if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.sapos', 0x40) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'sapos', 'hooks', 'shippingProfiles');
        $rc = ciniki_sapos_hooks_shippingProfiles($ciniki, $args['tnid'], array());
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.216', 'msg'=>'Unable to load shipping profiles.', 'err'=>$rc['err']));
        }
        $rsp['shippingprofiles'] = isset($rc['profiles']) ? $rc['profiles'] : array();
        array_unshift($rsp['shippingprofiles'], array('id'=>0, 'name'=>'No Shipping or Pickup'));
    }

    //
    // Get the list of taxes if enabled
    //
    if( ciniki_core_checkModuleActive($ciniki, 'ciniki.sapos') ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'taxes', 'hooks', 'taxTypes');
        $rc = ciniki_taxes_hooks_taxTypes($ciniki, $args['tnid'], array('notax'=>'yes'));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $rsp['taxtypes'] = $rc['types'];
    }
    
    return $rsp;
}
?>
