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
function ciniki_ags_web_exhibitDetails($ciniki, $settings, $tnid, $permalink) {

    $strsql = "SELECT exhibits.id, "
        . "exhibits.name, "
        . "exhibits.location_id, "
        . "exhibits.permalink, "
        . "exhibits.flags, "
        . "DATE_FORMAT(exhibits.start_date, '%b %e, %Y') AS start_date, "
        . "DATE_FORMAT(exhibits.end_date, '%b %e, %Y') AS end_date, "
        . "DATE_FORMAT(exhibits.start_date, '%M') AS start_month, "
        . "DATE_FORMAT(exhibits.start_date, '%D') AS start_day, "
        . "DATE_FORMAT(exhibits.start_date, '%Y') AS start_year, "
        . "IF(exhibits.end_date = '0000-00-00', '', DATE_FORMAT(end_date, '%M')) AS end_month, "
        . "IF(exhibits.end_date = '0000-00-00', '', DATE_FORMAT(end_date, '%D')) AS end_day, "
        . "IF(exhibits.end_date = '0000-00-00', '', DATE_FORMAT(end_date, '%Y')) AS end_year, "
        . "exhibits.synopsis, "
        . "exhibits.description, "
        . "exhibits.primary_image_id, "
        . "locations.id AS location_id, "
        . "locations.name AS location_name, "
        . "locations.name AS location_permalink, "
        . "locations.address1, "
        . "locations.address2, "
        . "locations.city, "
        . "locations.province, "
        . "locations.postal, "
        . "locations.country, "
        . "locations.latitude, "
        . "locations.longitude "
        . "FROM ciniki_ags_exhibits AS exhibits "
        . "LEFT JOIN ciniki_ags_locations AS locations ON ("
            . "exhibits.location_id = locations.id "
            . "AND locations.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND exhibits.permalink = '" . ciniki_core_dbQuote($ciniki, $permalink) . "' "
        // Check the exhibit is visible on the website
        . "AND exhibits.status = 50 "
        . "AND (exhibits.flags&0x01) = 0x01 "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibit');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.128', 'msg'=>'Unable to load exhibit', 'err'=>$rc['err']));
    }
    if( !isset($rc['exhibit']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.129', 'msg'=>'Unable to find requested exhibit'));
    }
    $exhibit = $rc['exhibit'];
   
    $exhibit['images'] = array();
    $exhibit['categories'] = array();

    //
    // Only show the items if the exhibit is to show items online
    //
    if( ($exhibit['flags']&0x04) == 0x04 && ($exhibit['flags']&0x12) > 0 ) {
        //
        // Load the items and images
        //
        $strsql = "SELECT tags.permalink AS tag_permalink, "
            . "tags.tag_name, "
            . "IFNULL(image.detail_value, 0) AS tag_image_id, "
            . "eitems.id AS exhibit_item_id, "
            . "eitems.inventory, "
            . "items.id, "
            . "items.name, "
            . "items.permalink, "
            . "items.status, "
            . "items.flags, "
            . "items.unit_amount, "
            . "items.unit_discount_amount, "
            . "items.unit_discount_percentage, "
            . "items.primary_image_id, "
            . "items.synopsis, "
            . "exhibitors.customer_id, "
            . "exhibitors.display_name "
            . "FROM ciniki_ags_exhibit_items AS eitems "
            . "INNER JOIN ciniki_ags_items AS items ON ("
                . "eitems.item_id = items.id "
                . "AND (items.flags&0x02) = 0x02 "
                . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "INNER JOIN ciniki_ags_item_tags AS tags ON ("
                . "items.id = tags.item_id "
                . "AND tags.tag_type = 20 "
                . "AND tags.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "INNER JOIN ciniki_ags_exhibitors AS exhibitors ON ("
                . "items.exhibitor_id = exhibitors.id "
                . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "LEFT JOIN ciniki_ags_settings AS image ON ("
                . "image.detail_key = CONCAT('category-', tags.permalink, '-image') "
                . "AND image.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE eitems.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $exhibit['id']) . "' "
            . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "ORDER BY tags.tag_name, eitems.date_added DESC " //, images.name "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
        $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'categories', 'fname'=>'tag_permalink', 
                'fields'=>array('permalink'=>'tag_permalink', 'name'=>'tag_name', 'image_id'=>'tag_image_id')),
            array('container'=>'items', 'fname'=>'permalink', 
                'fields'=>array('id', 'exhibit_item_id', 'name', 'permalink', 'inventory', 'status', 'flags',
                    'unit_amount', 'unit_discount_amount', 'unit_discount_percentage',
                    'image_id'=>'primary_image_id', 'synopsis', 
                    'customer_id', 'subname'=>'display_name')),
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $exhibit['categories'] = isset($rc['categories']) ? $rc['categories'] : array();
    } elseif( ($exhibit['flags']&0x04) == 0x04 && ($exhibit['flags']&0x12) == 0 ) {
        $strsql = "SELECT "
            . "eitems.id AS exhibit_item_id, "
            . "eitems.inventory, "
            . "items.id, "
            . "items.name, "
            . "items.permalink, "
            . "items.status, "
            . "items.flags, "
            . "items.unit_amount, "
            . "items.unit_discount_amount, "
            . "items.unit_discount_percentage, "
            . "items.primary_image_id, "
            . "items.synopsis, "
            . "exhibitors.customer_id, "
            . "exhibitors.display_name "
            . "FROM ciniki_ags_exhibit_items AS eitems "
            . "INNER JOIN ciniki_ags_items AS items ON ("
                . "eitems.item_id = items.id "
                . "AND (items.flags&0x02) = 0x02 "
                . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "INNER JOIN ciniki_ags_exhibitors AS exhibitors ON ("
                . "items.exhibitor_id = exhibitors.id "
                . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE eitems.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $exhibit['id']) . "' "
            . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "ORDER BY items.name, eitems.date_added DESC " //, images.name "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
        $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'items', 'fname'=>'permalink', 
                'fields'=>array('id', 'exhibit_item_id', 'name', 'permalink', 'inventory', 'status', 'flags',
                    'unit_amount', 'unit_discount_amount', 'unit_discount_percentage',
                    'image_id'=>'primary_image_id', 'synopsis', 
                    'customer_id', 'subname'=>'display_name')),
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $exhibit['items'] = isset($rc['items']) ? $rc['items'] : array();
/* Moved to processRequest before tradingcards block */
/*        foreach($exhibit['categories'] as $cid => $category) {
            if( isset($category['items']) ) {
                foreach($category['items'] as $iid => $item) {
                    $display_price = 'Not for sale';
                    if( ($item['flags']&0x01) == 0x01 ) {
                        $display_price = '';
                        $final_amount = $item['unit_amount'];
                        if( $item['unit_discount_amount'] > 0 ) {
                            $final_amount = $final_amount - $item['unit_amount'];
                            $display_price = '<strike>$' . number_format($item['unit_amount'], 2) . '</strike>';
                        }
                        if( $item['unit_discount_percentage'] > 0 ) {
                            $percentage = bcdiv($item['unit_discount_percentage'], 100, 4);
                            $final_amount = $final_amount - ($final_amount * $percentage);
                            $display_price = '<strike>$' . number_format($item['unit_amount'], 2) . '</strike>';
                        }
                        $display_price .= ($display_price != '' ? '&nbsp;' : '') 
                            . '$' . number_format($final_amount, 2);
                    }
                    $exhibit['categories'][$cid]['items'][$iid]['display_price'] = $display_price;
                }
            }
        } */
    } else {
        //
        // Load the items and images
        //
        $strsql = "SELECT items.id, "
            . "items.name, "
            . "items.permalink, "
            . "items.status, "
            . "items.flags, "
            . "items.primary_image_id, "
            . "items.synopsis, "
            . "items.description, "
            . "items.last_updated "
            . "FROM ciniki_ags_exhibit_items AS eitems "
            . "INNER JOIN ciniki_ags_items AS items ON ("
                . "eitems.item_id = items.id "
                . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE eitems.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $exhibit['id']) . "' "
            . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "ORDER BY items.name "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'items', 'fname'=>'id', 
                'fields'=>array('id', 'name', 'permalink', 'status', 'flags',
                    'image_id'=>'primary_image_id', 'synopsis', 'description', 'last_updated')),
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $exhibit['items'] = isset($rc['items']) ? $rc['items'] : array();

        foreach($exhibit['items'] as $item) {
            if( isset($item['image_id']) && $item['image_id'] > 0 ) {
                $exhibit['images'][$item['image_id']] = array(
                    'title' => $item['name'],
                    'permalink' => $item['permalink'],
                    'image_sold' => ($item['status'] == 70 ? 'yes' : 'no'),
                    'image_id' => $item['image_id'],
                    'last_updated' => $item['last_updated'],
                    );
            }
        }
    }

    //
    // Get the location for the exhibit
    //
    if( ($exhibit['flags']&0x08) == 0x08 ) {
        $joined_address = $exhibit['address1'] . "<br/>";
        if( isset($exhibit['address2']) && $exhibit['address2'] != '' ) {
            $joined_address .= $exhibit['address2'] . "<br/>";
        }
        $city = '';
        $comma = '';
        if( isset($exhibit['city']) && $exhibit['city'] != '' ) {
            $city = $exhibit['city'];
            $comma = ', ';
        }
        if( isset($exhibit['province']) && $exhibit['province'] != '' ) {
            $city .= $comma . $exhibit['province'];
            $comma = ', ';
        }
        if( isset($exhibit['postal']) && $exhibit['postal'] != '' ) {
            $city .= $comma . ' ' . $exhibit['postal'];
            $comma = ', ';
        }
        if( $city != '' ) {
            $joined_address .= $city . "<br/>";
        }
        $exhibit['location_address'] = $joined_address;
    } else {
        $exhibit['location_address'] = '';
    }

    return array('stat'=>'ok', 'exhibit'=>$exhibit);
}
?>
