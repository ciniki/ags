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
function ciniki_ags_web_webCollectionList($ciniki, $settings, $tnid, $args) {

    $strsql = "SELECT ciniki_ags_exhibits.id, "
        . "ciniki_ags_exhibits.name, "
        . "ciniki_ags_locations.name AS location_name, "
        . "DATE_FORMAT(start_date, '%M') AS start_month, "
        . "DATE_FORMAT(start_date, '%D') AS start_day, "
        . "DATE_FORMAT(start_date, '%Y') AS start_year, "
        . "IF(end_date = '0000-00-00', '', DATE_FORMAT(end_date, '%M')) AS end_month, "
        . "IF(end_date = '0000-00-00', '', DATE_FORMAT(end_date, '%D')) AS end_day, "
        . "IF(end_date = '0000-00-00', '', DATE_FORMAT(end_date, '%Y')) AS end_year, "
        . "DATE_FORMAT(start_date, '%b %e, %Y') AS start_date, "
        . "DATE_FORMAT(end_date, '%b %e, %Y') AS end_date, "
        . "ciniki_ags_exhibits.permalink, "
        . "ciniki_ags_exhibits.synopsis, "
        . "ciniki_ags_exhibits.description, "
        . "ciniki_ags_exhibits.primary_image_id "
//        . "COUNT(ciniki_ags_exhibition_images.id) AS num_images "
        . "FROM ciniki_web_collection_objrefs "
        . "INNER JOIN ciniki_ags_exhibits ON ("
            . "ciniki_web_collection_objrefs.object_id = ciniki_ags_exhibits.id "
            . "AND ciniki_ags_exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND ciniki_ags_exhibits.status = 50 "
            . "AND (ciniki_ags_exhibits.flags&0x01) = 0x01 "
            . "";
        if( isset($args['type']) && $args['type'] == 'past' ) {
            $strsql .= "AND ((ciniki_ags_exhibits.end_date > ciniki_ags_exhibits.start_date AND ciniki_ags_exhibits.end_date < DATE(NOW())) "
                    . "OR (ciniki_ags_exhibits.end_date <= ciniki_ags_exhibits.start_date AND ciniki_ags_exhibits.start_date < DATE(NOW())) "
                    . ") ";
        } else {
            $strsql .= "AND (ciniki_ags_exhibits.end_date >= DATE(NOW()) OR ciniki_ags_exhibits.start_date >= DATE(NOW())) ";
        }
    $strsql .= ") "
        . "LEFT JOIN ciniki_ags_locations ON ("
            . "ciniki_ags_exhibits.location_id = ciniki_ags_locations.id "
            . "AND ciniki_ags_locations.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
//        . "LEFT JOIN ciniki_ags_exhibit_images ON (ciniki_ags_exhibits.id = ciniki_ags_exhibition_images.exhibition_id "
//            . "AND ciniki_ags_exhibit_images.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
//            . ") "
        . "WHERE ciniki_web_collection_objrefs.collection_id = '" . ciniki_core_dbQuote($ciniki, $args['collection_id']) . "' "
        . "AND ciniki_web_collection_objrefs.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND ciniki_web_collection_objrefs.object = 'ciniki.ags.exhibit' "
        . "";
    if( isset($args['type']) && $args['type'] == 'past' ) {
        $strsql .= "GROUP BY ciniki_ags_exhibits.id ";
        $strsql .= "ORDER BY ciniki_ags_exhibits.start_date DESC ";
    } else {
        $strsql .= "GROUP BY ciniki_ags_exhibits.id ";
        $strsql .= "ORDER BY ciniki_ags_exhibits.start_date ASC ";
    }
    if( isset($args['limit']) && $args['limit'] != '' && $args['limit'] > 0 && is_int($args['limit']) ) {
        $strsql .= "LIMIT " . intval($args['limit']) . " ";
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibits', 'fname'=>'id', 'name'=>'exhibit',
            'fields'=>array('id', 'name', 'location_name', 'image_id'=>'primary_image_id', 
                'start_date', 'start_month', 'start_day', 'start_year', 
                'end_date', 'end_month', 'end_day', 'end_year', 
                'permalink', 'description'=>'synopsis', 'description')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['exhibits']) ) {
        return array('stat'=>'ok', 'exhibits'=>array());
    }
    return array('stat'=>'ok', 'exhibits'=>$rc['exhibits']);
}
?>
