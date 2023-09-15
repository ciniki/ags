<?php
//
// Description
// ===========
// This method will return the PDF of barcodes to be printed for the exhibitor.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the exhibitor is attached to.
// exhibitor_id:          The ID of the exhibitor to get the details for.
//
// Returns
// -------
//
function ciniki_ags_barcodesPDF($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'exhibitor_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Exhibitor'),
//        'label'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Label'),
        'exhibit_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Exhibit'),
        'start_code'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Start Code'),
        'end_code'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Start End'),
        'codes'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'list', 'name'=>'Codes'),
        'start_col'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Start Column'),
        'start_row'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Start Row'),
        'inventory_days'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Recent Inventory'),
        'tag_info_price'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Tag Info & Price'),
        'halfsize'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Half Size'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    $args['label'] = 'avery5167';

    //
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitorGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load the settings for the barcodes
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
    $rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_ags_settings', 'tnid', $args['tnid'], 'ciniki.ags', 'settings', 'barcodes');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.290', 'msg'=>'Unable to load settings', 'err'=>$rc['err']));
    }
    $settings = isset($rc['settings']) ? $rc['settings'] : array();

    //
    // Check to make sure either exhibitor_id or exhibit_id is specified
    //
    if( !isset($args['exhibit_id']) && !isset($args['exhibitor_id']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.245', 'msg'=>'No exhibit or exhibitor specified.'));
    }

    //
    // Get the exhibitor details
    //
    if( isset($args['exhibitor_id']) ) {
        $strsql = "SELECT exhibitors.id, "
            . "exhibitors.customer_id, "
            . "exhibitors.display_name_override, "
            . "exhibitors.display_name, "
            . "exhibitors.permalink, "
            . "exhibitors.code, "
            . "exhibitors.barcode_message, "
            . "exhibitors.status, "
            . "exhibitors.flags "
            . "FROM ciniki_ags_exhibitors AS exhibitors "
            . "WHERE exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND exhibitors.id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'exhibitors', 'fname'=>'id', 
                'fields'=>array('customer_id', 'display_name_override', 'display_name', 'permalink', 'code', 'barcode_message', 'status', 'flags'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.74', 'msg'=>'Exhibitor not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['exhibitors'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.75', 'msg'=>'Unable to find Exhibitor'));
        }
        $exhibitor = $rc['exhibitors'][0];
        $args['title'] = $exhibitor['display_name'] . ' Barcodes';
    } else {
        $args['title'] = "Barcodes";
    }

    //
    // Get the codes that need to be printed
    //
    $strsql = "SELECT items.id, "
        . "items.code, "
        . "items.name, "
        . "items.exhibitor_code, "
        . "items.status, "
        . "items.flags, "
        . "items.tag_info, "
        . "items.unit_amount, "
        . "items.fee_percent, "
        . "items.taxtype_id "
        . "";
    if( isset($args['exhibit_id']) && $args['exhibit_id'] > 0 ) {
        if( isset($args['inventory_days']) && $args['inventory_days'] > 0 ) {
            $strsql .= ", SUM(logs.quantity) AS quantity "
                . "FROM ciniki_ags_exhibit_items AS exhibit "
                . "INNER JOIN ciniki_ags_items AS items ON ("
                    . "exhibit.item_id = items.id "
                    . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                    . ") ";
            $dt = new DateTime('now', new DateTimezone('UTC'));
            $dt->sub(new DateInterval('P' . $args['inventory_days'] . 'D'));
            $strsql .= "INNER JOIN ciniki_ags_item_logs AS logs ON ("
                . "items.id = logs.item_id "
                . "AND (logs.action = 10 OR (logs.action = 50 AND logs.quantity > 0))"
                . "AND logs.actioned_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
                . "AND logs.log_date >= '" . $dt->format('Y-m-d') . "' "
                . "AND logs.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") ";
        } else {
            $strsql .= ", exhibit.inventory AS quantity "
                . "FROM ciniki_ags_exhibit_items AS exhibit "
                . "INNER JOIN ciniki_ags_items AS items ON ("
                    . "exhibit.item_id = items.id "
                    . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                    . ") ";
        }
        if( isset($args['exhibitor_id']) ) {
            $strsql .= "WHERE items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
                . "AND exhibit.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
                . "AND exhibit.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . "";
        } else {
            $strsql .= "WHERE exhibit.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
                . "AND exhibit.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . "";
        }
    } elseif( isset($args['exhibitor_id']) ) {
        $strsql .= ", 1 AS quantity "
            . "FROM ciniki_ags_items AS items "
            . "WHERE items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
    }
    if( isset($args['start_code']) && $args['start_code'] != '' ) {
        $strsql .= "AND items.code >= '" . ciniki_core_dbQuote($ciniki, $args['start_code']) . "' ";
    }
    if( isset($args['end_code']) && $args['end_code'] != '' ) {
        $strsql .= "AND items.code <= '" . ciniki_core_dbQuote($ciniki, $args['end_code']) . "' ";
    }
    if( isset($args['codes']) && count($args['codes']) > 0 && $args['codes'][0] != "" ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteList');
        $strsql .= "AND items.code IN (" . ciniki_core_dbQuoteList($ciniki, $args['codes']) . ") ";
    } else {
        //
        // Only tagged items if no specific codes requested
        //
        $strsql .= "AND (items.flags&0x10) = 0x10 ";
    }
    if( isset($args['exhibit_id']) && $args['exhibit_id'] > 0 && isset($args['inventory_days']) && $args['inventory_days'] > 0 ) {
        $strsql .= "GROUP BY items.code ";
    }
    $strsql .= "ORDER BY items.code ";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'code', 'name', 'exhibitor_code', 'status', 'flags', 'tag_info', 'unit_amount', 'fee_percent', 'quantity', 'taxtype_id'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.159', 'msg'=>'Unable to load items', 'err'=>$rc['err']));
    }
    $args['barcodes'] = array();
    if( isset($rc['items']) ) {
        foreach($rc['items'] as $item) {
            if( isset($exhibitor['barcode_message']) && $exhibitor['barcode_message'] != '' ) {
                $item['barcode_message'] = $exhibitor['barcode_message'];
            }
            if( $item['quantity'] > 1 ) {
                for($i=0;$i<$item['quantity'];$i++) {
                    if( isset($args['halfsize']) && $args['halfsize'] == 'yes' ) {
                        $item['label_type'] = 'halfsize';
                    } else {
                        $item['label_type'] = 'barcode';
                    }
                    $args['barcodes'][] = $item;
                    if( isset($args['tag_info_price']) && $args['tag_info_price'] == 'yes' ) {
                        if( isset($settings['barcodes-label-format']) && $settings['barcodes-label-format'] == 'taginfo' ) {
                            // Only add if tag info otherwise would be blank label with no price printed
                            if( $item['tag_info'] != '' ) {
                                $item['label_type'] = 'info';
                                $args['barcodes'][] = $item;
                            }
                        } else {
                            // Always add info tag because price will be printed
                            $item['label_type'] = 'info';
                            $args['barcodes'][] = $item;
                        }
                    }
                }
            } else {
                if( isset($args['halfsize']) && $args['halfsize'] == 'yes' ) {
                    $item['label_type'] = 'halfsize';
                } else {
                    $item['label_type'] = 'barcode';
                }
                $args['barcodes'][] = $item;
                if( isset($args['tag_info_price']) && $args['tag_info_price'] == 'yes' ) {
                    if( isset($settings['barcodes-label-format']) && $settings['barcodes-label-format'] == 'taginfo' ) {
                        // Only add if tag info otherwise would be blank label with no price printed
                        if( $item['tag_info'] != '' ) {
                            $item['label_type'] = 'info';
                            $args['barcodes'][] = $item;
                        }
                    } else {
                        // Always add info tag because price will be printed
                        $item['label_type'] = 'info';
                        $args['barcodes'][] = $item;
                    }
                }
            }
        }
    }
    if( count($args['barcodes']) < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.96', 'msg'=>'No items found'));
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'templates', 'barcodesPDF');
    $rc = ciniki_ags_templates_barcodesPDF($ciniki, $args['tnid'], $args);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.97', 'msg'=>'Error generating PDF', 'err'=>$rc['err']));
    }

    if( isset($rc['pdf']) ) {
        if( isset($exhibitor) ) {
            $filename = 'Labels-' . preg_replace('/[^a-zA-Z0-9_]/', '', preg_replace('/ /', '-', $exhibitor['display_name']));
        } else {
            $filename = 'Labels';
        }
        $rc['pdf']->Output($filename . '.pdf', 'D');
    }

    return array('stat'=>'exit');
}
?>
