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
function ciniki_ags_web_itemDetails($ciniki, $settings, $tnid, $item_id) {

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
        . "ciniki_ags_items.primary_image_id, "
        . "ciniki_ags_items.synopsis, "
        . "ciniki_ags_items.description, "
        . "ciniki_ags_items.tag_info, "
        . "ciniki_ags_items.creation_year, "
        . "ciniki_ags_items.medium, "
        . "ciniki_ags_items.size, "
        . "ciniki_ags_items.current_condition, "
        . "ciniki_ags_items.notes "
        . "FROM ciniki_ags_items "
        . "WHERE ciniki_ags_items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND ciniki_ags_items.id = '" . ciniki_core_dbQuote($ciniki, $item_id) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'exhibitor_id', 'exhibitor_code', 'code', 'name', 'permalink', 'status', 'flags', 
                'unit_amount', 'unit_discount_amount', 'unit_discount_percentage', 'fee_percent', 'taxtype_id', 
                'primary_image_id', 'synopsis', 'description', 'tag_info', 
                'creation_year', 'medium', 'size', 'current_condition', 'notes'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.80', 'msg'=>'Item not found', 'err'=>$rc['err']));
    }
    if( !isset($rc['items'][0]) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.81', 'msg'=>'Unable to find Item'));
    }
    $item = $rc['items'][0];
    $item['unit_amount'] = '$' . number_format($item['unit_amount'], 2);

    //
    // Get the categories
    //
    $strsql = "SELECT id, "
        . "permalink, "
        . "tag_name "
        . "FROM ciniki_ags_item_tags "
        . "WHERE item_id = '" . ciniki_core_dbQuote($ciniki, $item['id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND tag_type = 20 "
        . "ORDER BY permalink, tag_name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.lapt', array(
        array('container'=>'categories', 'fname'=>'permalink', 
            'fields'=>array('permalink', 'tag_name')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $item['categories'] = isset($rc['categories']) ? $rc['categories'] : array();

    //
    // Load the images
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'loadCacheThumbnail');
    $strsql = "SELECT ciniki_ags_item_images.id, "
        . "ciniki_ags_item_images.image_id, "
        . "ciniki_ags_item_images.name, "
        . "ciniki_ags_item_images.sequence, "
        . "ciniki_ags_item_images.description "
        . "FROM ciniki_ags_item_images "
        . "WHERE ciniki_ags_item_images.item_id = '" . ciniki_core_dbQuote($ciniki, $item['id']) . "' "
        . "AND ciniki_ags_item_images.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
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
                $rc = ciniki_images_loadCacheThumbnail($ciniki, $tnid, $img['image_id'], 75);
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
                $item['images'][$img_id]['image_data'] = 'data:image/jpg;base64,' . base64_encode($rc['image']);
            }
        }
    } else {
        $item['images'] = array();
    }

    return array('stat'=>'ok', 'item'=>$item);
}
?>
