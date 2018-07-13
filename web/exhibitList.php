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
function ciniki_ags_web_exhibitList($ciniki, $settings, $tnid, $args, $format='') {

    $strsql = "SELECT exhibits.id, "
        . "exhibits.name, "
        . "exhibits.permalink, "
        . "exhibits.primary_image_id, "
        . "exhibits.synopsis, "
        . "DATE_FORMAT(exhibits.start_date, '%M') AS start_month, "
        . "DATE_FORMAT(exhibits.start_date, '%D') AS start_day, "
        . "DATE_FORMAT(exhibits.start_date, '%Y') AS start_year, "
        . "IF(exhibits.end_date = '0000-00-00', '', DATE_FORMAT(end_date, '%M')) AS end_month, "
        . "IF(exhibits.end_date = '0000-00-00', '', DATE_FORMAT(end_date, '%D')) AS end_day, "
        . "IF(exhibits.end_date = '0000-00-00', '', DATE_FORMAT(end_date, '%Y')) AS end_year, "
        . "DATE_FORMAT(exhibits.start_date, '%b %e, %Y') AS start_date, "
        . "DATE_FORMAT(exhibits.end_date, '%b %e, %Y') AS end_date, "
        . "locations.name AS location_name, "
        . "locations.category "
        . "";
    //
    // Load the exhibition based on type
    //
    if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.ags', 0x01)
        && isset($args['exhibit_type']) && $args['exhibit_type'] != '' 
        ) {
        $strsql .= "FROM ciniki_ags_exhibit_tags AS tags "
            . "INNER JOIN ciniki_ags_exhibits AS exhibits ON ("
                . "tags.exhibit_id = exhibits.id "
                . "AND exhibits.status = 50 "
                . "AND (exhibits.flags&0x01) = 0x01 "
                . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "INNER JOIN ciniki_ags_locations AS locations ON ("
                . "exhibits.location_id = locations.id "
                . "AND locations.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE tags.permalink = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_type']) . "' "
            . "AND tags.tag_type = 20 "
            . "AND tags.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
    } 
    //
    // Load all exhibitions
    //
    else {
        $strsql .= "FROM JOIN ciniki_ags_exhibits AS exhibits "
            . "INNER JOIN ciniki_ags_locations AS locations ON ("
                . "exhibits.location_id = locations.id "
                . "AND locations.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE exhibits.status = 50 "
            . "AND (exhibits.flags&0x01) = 0x01 "
            . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
    }
    if( isset($args['category']) && $args['category'] != '' ) {
        $strsql .= "AND locations.category = '" . ciniki_core_dbQuote($ciniki, $args['category']) . "' ";
    }

    if( isset($args['type']) && $args['type'] == 'past' ) {
        $strsql .= "AND ((exhibits.end_date > exhibits.start_date AND exhibits.end_date < DATE(NOW())) "
                . "OR (exhibits.end_date < exhibits.start_date AND exhibits.start_date <= DATE(NOW())) "
                . ") "
            . "GROUP BY exhibits.id "
            . "ORDER BY exhibits.start_date DESC, exhibits.name "
            . "";
    } elseif( isset($args['type']) && $args['type'] == 'current' ) {
        $strsql .= "AND (exhibits.end_date >= DATE(NOW()) AND exhibits.start_date <= DATE(NOW())) "
            . "GROUP BY exhibits.id "
            . "ORDER BY exhibits.start_date ASC, exhibits.name "
            . "";
    } elseif( isset($args['type']) && $args['type'] == 'upcoming' ) {
        $strsql .= "AND (exhibits.start_date > DATE(NOW())) "
            . "GROUP BY exhibits.id "
            . "ORDER BY exhibits.start_date ASC, exhibits.name "
            . "";
    } else {
        $strsql .= "AND (exhibits.end_date >= DATE(NOW()) OR exhibits.start_date >= DATE(NOW())) "
            . "GROUP BY exhibits.id "
            . "ORDER BY exhibits.start_date ASC, exhibits.name "
            . "";
    }
    if( isset($args['offset']) && $args['offset'] > 0
        && isset($args['limit']) && $args['limit'] > 0 ) {
        $strsql .= "LIMIT " . $args['offset'] . ', ' . $args['limit'];
    } elseif( $args['limit'] != '' && $args['limit'] > 0 && is_int($args['limit']) ) {
        $strsql .= "LIMIT " . $args['limit'] . " ";
    }
    
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibits', 'fname'=>'id', 
            'fields'=>array('id', 'name', 'permalink', 'location_name', 
                'image_id'=>'primary_image_id', 
                'start_date', 'start_month', 'start_day', 'start_year', 
                'end_date', 'end_month', 'end_day', 'end_year', 
                'synopsis')),
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
