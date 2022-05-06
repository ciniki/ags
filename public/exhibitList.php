<?php
//
// Description
// -----------
// This method will return the list of Exhibits for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Exhibit for.
//
// Returns
// -------
//
function ciniki_ags_exhibitList($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'etype'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Exhibit Type'),
        'open'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Open/Upcoming Exhibits'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitList');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load maps
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'maps');
    $rc = ciniki_ags_maps($ciniki);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];

    //
    // Load the date format strings for the user
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');
    
    if( isset($args['open']) && $args['open'] == 'yes' ) {
        $strsql = "SELECT exhibits.id, "
            . "exhibits.name, "
            . "exhibits.permalink, "
            . "exhibits.location_id, "
            . "IFNULL(locations.name, '') AS location_name, "
            . "exhibits.status, "
            . "exhibits.status AS status_text, "
            . "exhibits.flags, "
            . "exhibits.start_date, "
            . "exhibits.start_date AS start_date_display, "
            . "DATE_FORMAT(exhibits.start_date, '%Y') AS year, "
            . "IF((exhibits.flags&0x01)=0x01, 'Yes', 'No') AS visible, "
            . "exhibits.end_date, "
            . "exhibits.end_date AS end_date_display "
            . "FROM ciniki_ags_exhibits AS exhibits "
            . "LEFT JOIN ciniki_ags_locations AS locations ON ("
                . "exhibits.location_id = locations.id "
                . "AND locations.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND (exhibits.end_date = '0000-00-00' OR exhibits.end_date > NOW()) "
            . "ORDER BY exhibits.name ASC "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        return ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'exhibits', 'fname'=>'id', 
                'fields'=>array('id', 'name', 'permalink', 'location_id', 'location_name', 'status', 'status_text', 'flags', 
                    'start_date', 'start_date_display', 'visible', 'end_date', 'end_date_display'),
                'maps'=>array('status_text'=>$maps['exhibit']['status']),
                'utctotz'=>array('start_date_display'=>array('timezone'=>'UTC', 'format'=>$date_format),
                    'end_date_display'=>array('timezone'=>'UTC', 'format'=>$date_format),
                    ),
                ),
            ));
    }

    //
    // Get the list of exhibits
    //
    $strsql = "SELECT exhibits.id, "
        . "exhibits.name, "
        . "exhibits.permalink, "
        . "exhibits.location_id, "
        . "IFNULL(locations.name, '') AS location_name, "
        . "exhibits.status, "
        . "exhibits.status AS status_text, "
        . "exhibits.flags, "
        . "exhibits.start_date, "
        . "exhibits.start_date AS start_date_display, "
        . "DATE_FORMAT(exhibits.start_date, '%Y') AS year, "
        . "IF((exhibits.flags&0x01)=0x01, 'Yes', 'No') AS visible, "
        . "exhibits.end_date, "
        . "exhibits.end_date AS end_date_display ";
    if( isset($args['etype']) && $args['etype'] != '' ) {
        $strsql .= "FROM ciniki_ags_exhibit_tags AS tags "
            . "LEFT JOIN ciniki_ags_exhibits AS exhibits ON ("
                . "tags.exhibit_id = exhibits.id "
                . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "LEFT JOIN ciniki_ags_locations AS locations ON ("
                . "exhibits.location_id = locations.id "
                . "AND locations.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE tags.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND tags.tag_type = 20 "
            . "AND tags.permalink = '" . ciniki_core_dbQuote($ciniki, $args['etype']) . "' "
            . "ORDER BY year, exhibits.start_date DESC, exhibits.name ASC "
            . "";
    } else {
        $strsql .= "FROM ciniki_ags_exhibits AS exhibits "
            . "LEFT JOIN ciniki_ags_locations AS locations ON ("
                . "exhibits.location_id = locations.id "
                . "AND locations.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "ORDER BY year, exhibits.start_date DESC, exhibits.name ASC "
            . "";
    }
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'years', 'fname'=>'year', 'fields'=>array('year')),
        array('container'=>'exhibits', 'fname'=>'id', 
            'fields'=>array('id', 'name', 'permalink', 'location_id', 'location_name', 'status', 'status_text', 'flags', 
                'start_date', 'start_date_display', 'visible', 'end_date', 'end_date_display'),
            'maps'=>array('status_text'=>$maps['exhibit']['status']),
            'utctotz'=>array('start_date_display'=>array('timezone'=>'UTC', 'format'=>$date_format),
                'end_date_display'=>array('timezone'=>'UTC', 'format'=>$date_format),
                ),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $years = isset($rc['years']) ? $rc['years'] : array();

    return array('stat'=>'ok', 'years'=>$years);
}
?>
