<?php
//
// Description
// -----------
// This function will remove an item for an exhibitor when there are no sales or part of exhibits.
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_ags_wng_accountItemLoad(&$ciniki, $tnid, &$request, $args) {

    if( !isset($args['item_permalink']) || $args['item_permalink'] == '0' || $args['item_permalink'] == '' ) {
        $item = array(
            'id' => 0,
            'exhibitor_code' => '',
            'code' => '',
            'name' => '',
            'permalink' => '',
            'status' => 30,
            'flags' => 0,
            'unit_amount' => '',
            'unit_discount_amount' => '',
            'unit_discount_percentage' => '',
            'primary_image_id' => 0,
            'synopsis' => '',
            'description' => '',
            'creation_year' => '',
            'medium' => '',
            'size' => '',
            'framed_size' => '',
            'current_condition' => '',
            'notes' => '',
            'inventory' => '1',
            'pending_inventory' => '0',
            'categories' => '',
            'subcategories' => '',
            );
    } else {
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
            . "items.medium, "
            . "items.size, "
            . "items.framed_size, "
            . "items.creation_year, "
            . "items.current_condition, "
            . "items.notes, "
            . "items.requested_changes ";
        if( isset($args['exhibit_id']) && $args['exhibit_id'] != '' ) {
            $strsql .= ", "
                . "IFNULL(eitems.id, 0) AS eitem_id, "
                . "IFNULL(eitems.uuid, '') AS eitem_uuid, "
                . "IFNULL(eitems.inventory, 0) AS inventory, "
                . "IFNULL(eitems.pending_inventory, 0) AS pending_inventory, "
                . "IFNULL(eitems.status, 0) AS eitem_status, "
                . "IFNULL(eitems.fee_percent, 0) AS efee_percent ";
        }
        $strsql .= "FROM ciniki_ags_items AS items ";
        if( isset($args['exhibit_id']) && $args['exhibit_id'] != '' ) {
            $strsql .= "LEFT JOIN ciniki_ags_exhibit_items AS eitems ON ("
                    . "items.id = eitems.item_id "
                    . "AND eitems.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
                    . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                    . ") ";
        }
        $strsql .= ""
            . "LEFT JOIN ciniki_images AS images ON ("
                . "items.primary_image_id = images.id "
                . "AND images.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE items.permalink = '" . ciniki_core_dbQuote($ciniki, $args['item_permalink']) . "' "
            . "AND items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
//            . "AND items.status < 90 "  // Not archived
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "ORDER BY items.name "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.320', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
        }
        if( !isset($rc['item']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.321', 'msg'=>'Unable to find requested item'));
        }
        $item = $rc['item'];
        $item['unit_amount'] = number_format($item['unit_amount'], 2);
    
        if( $item['requested_changes'] != '' ) {
            $item['requested_changes'] = json_decode($item['requested_changes'], true);
            if( isset($item['requested_changes']['unit_amount']) ) {
                $item['requested_changes']['unit_amount'] = number_format($item['requested_changes']['unit_amount'], 2);
            }
        } else {
            $item['requested_changes'] = array();
        }

        //
        // Load the categories and subcategories
        //
        $strsql = "SELECT tag_type, tag_name AS names "
            . "FROM ciniki_ags_item_tags "
            . "WHERE item_id = '" . ciniki_core_dbQuote($ciniki, $item['id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
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
        $item['types'] = '';
        $item['categories'] = '';
        $item['subcategories'] = '';
        $item['tags'] = '';
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
    }

    return array('stat'=>'ok', 'item'=>$item);
}
?>
