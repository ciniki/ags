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
/*        . "images.image_id, "
        . "images.name AS image_name, "
        . "images.permalink AS image_permalink, "
        . "IF((images.flags&0x01)=1, 'yes', 'no') AS image_sold, "
        . "images.description AS image_description, "
        . "images.url AS image_url, "
        . "UNIX_TIMESTAMP(images.last_updated) AS image_last_updated " */
        . "FROM ciniki_ags_exhibits AS exhibits "
        . "LEFT JOIN ciniki_ags_locations AS locations ON ("
            . "exhibits.location_id = locations.id "
            . "AND locations.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
/*        . "LEFT JOIN ciniki_ags_exhibit_images AS images ON ("
            . "ciniki_ags_exhibits.id = ciniki_ags_exhibit_images.exhibit_id "
            . "AND (ciniki_ags_exhibit_images.webflags&0x01) = 0 "
            . ") " */
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
   
    //
    // Load the items and images
    //
    $strsql = "SELECT items.id, "
        . "items.name, "
        . "items.permalink, "
        . "items.status, "
        . "items.primary_image_id, "
        . "items.synopsis, "
        . "items.description, "
        . "items.last_updated, "
        . "images.image_id, "
        . "images.name AS image_name, "
        . "images.permalink AS image_permalink, "
        . "images.description AS image_description, "
        . "images.last_updated AS image_last_updated, "
        . "IF((images.flags&0x02)=0x02, 'yes', 'no') AS image_sold "
        . "FROM ciniki_ags_exhibit_items AS eitems "
        . "INNER JOIN ciniki_ags_items AS items ON ("
            . "eitems.item_id = items.id "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "LEFT JOIN ciniki_ags_item_images AS images ON ("
            . "items.id = images.item_id "
            . "AND (images.flags&0x01) = 0x01 "
            . "AND images.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE eitems.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $exhibit['id']) . "' "
        . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "ORDER BY items.name, images.name "
        . "";
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'name', 'permalink', 'status',
                'image_id'=>'primary_image_id', 'synopsis', 'description', 'last_updated')),
        array('container'=>'images', 'fname'=>'image_id', 
            'fields'=>array('image_id', 'title'=>'image_name', 'permalink'=>'image_permalink',
                'description'=>'image_description', 'sold'=>'image_sold', 'url'=>'image_url',
                'last_updated'=>'image_last_updated')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $exhibit['items'] = isset($rc['items']) ? $rc['items'] : array();

    $exhibit['images'] = array();
    foreach($exhibit['items'] as $item) {
        if( isset($item['image_id']) && $item['image_id'] > 0 ) {
            $exhibit['images'][] = array(
                'title' => $item['name'],
                'permalink' => $item['permalink'],
                'image_sold' => ($item['status'] == 70 ? 'yes' : 'no'),
                'image_id' => $item['image_id'],
                'last_updated' => $item['last_updated'],
                );
        }
        if( isset($item['images']) ) {
            foreach($item['images'] as $image) {
                if( isset($item['image_id']) && $item['image_id'] > 0 ) {
                    $exhibit['images'][] = $image;
                }
            }
        }
    }

    //
    // Get the location for the exhibit
    //
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

    return array('stat'=>'ok', 'exhibit'=>$exhibit);
}
?>
