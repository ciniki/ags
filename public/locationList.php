<?php
//
// Description
// -----------
// This method will return the list of Locations for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Location for.
//
// Returns
// -------
//
function ciniki_ags_locationList($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'location_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Location'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.locationList');
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

    //
    // Get the list of locations
    //
    $strsql = "SELECT ciniki_ags_locations.id, "
        . "ciniki_ags_locations.name, "
        . "ciniki_ags_locations.category, "
        . "ciniki_ags_locations.flags, "
        . "ciniki_ags_locations.address1, "
        . "ciniki_ags_locations.address2, "
        . "ciniki_ags_locations.city, "
        . "ciniki_ags_locations.province, "
        . "ciniki_ags_locations.postal, "
        . "ciniki_ags_locations.country, "
        . "ciniki_ags_locations.latitude, "
        . "ciniki_ags_locations.longitude, "
        . "ciniki_ags_locations.primary_image_id, "
        . "ciniki_ags_locations.synopsis "
        . "FROM ciniki_ags_locations "
        . "WHERE ciniki_ags_locations.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "ORDER BY category, name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'locations', 'fname'=>'id', 
            'fields'=>array('id', 'name', 'category', 'flags', 
                'address1', 'address2', 'city', 'province', 'postal', 'country', 'latitude', 'longitude', 
                'primary_image_id', 'synopsis')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['locations']) ) {
        $locations = $rc['locations'];
        $location_ids = array();
        foreach($locations as $iid => $location) {
            $location_ids[] = $location['id'];
        }
    } else {
        $locations = array();
        $location_ids = array();
    }

    $rsp = array('stat'=>'ok', 'locations'=>$locations, 'nplist'=>$location_ids);

    //
    // Get the details about a location
    //
    if( isset($args['location_id']) && $args['location_id'] != '' && $args['location_id'] > 0 ) {
        $strsql = "SELECT notes "
            . "FROM ciniki_ags_locations "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['location_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'location');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.5', 'msg'=>'Unable to load location', 'err'=>$rc['err']));
        }
        if( !isset($rc['location']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.6', 'msg'=>'Unable to find location'));
        }
        $rsp['notes'] = $rc['location']['notes'];

        //
        // Load the list of exhibits for this location organized by year
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
            . "exhibits.end_date, "
            . "exhibits.end_date AS end_date_display "
            . "FROM ciniki_ags_exhibits AS exhibits "
            . "LEFT JOIN ciniki_ags_locations AS locations ON ("
                . "exhibits.location_id = locations.id "
                . "AND locations.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND exhibits.location_id = '" . ciniki_core_dbQuote($ciniki, $args['location_id']) . "' "
            . "ORDER BY year, exhibits.start_date DESC, exhibits.name ASC "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'years', 'fname'=>'year', 'fields'=>array('year')),
            array('container'=>'exhibits', 'fname'=>'id', 
                'fields'=>array('id', 'name', 'permalink', 'location_id', 'location_name', 'status', 'status_text', 'flags', 
                    'start_date', 'start_date_display', 'end_date', 'end_date_display'),
                'maps'=>array('status_text'=>$maps['exhibit']['status']),
                'utctotz'=>array('start_date_display'=>array('timezone'=>'UTC', 'format'=>$date_format),
                    'end_date_display'=>array('timezone'=>'UTC', 'format'=>$date_format),
                    ),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.4', 'msg'=>'Unable to load exhibits', 'err'=>$rc['err']));
        }
        $rsp['years'] = isset($rc['years']) ? $rc['years'] : array();
    }

    return $rsp;
}
?>
