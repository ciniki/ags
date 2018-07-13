<?php
//
// Description
// -----------
// This method searchs for a Locations for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Location for.
// start_needle:       The search string to search for.
// limit:              The maximum number of entries to return.
//
// Returns
// -------
//
function ciniki_ags_locationSearch($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'start_needle'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Search String'),
        'limit'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Limit'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.locationSearch');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

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
        . "AND ("
            . "name LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR name LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
        . ") "
        . "";
    if( isset($args['limit']) && is_numeric($args['limit']) && $args['limit'] > 0 ) {
        $strsql .= "LIMIT " . ciniki_core_dbQuote($ciniki, $args['limit']) . " ";
    } else {
        $strsql .= "LIMIT 25 ";
    }
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'locations', 'fname'=>'id', 
            'fields'=>array('id', 'name', 'category', 'flags', 'address1', 'address2', 'city', 'province', 'postal', 'country', 'latitude', 'longitude', 'primary_image_id', 'synopsis')),
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

    return array('stat'=>'ok', 'locations'=>$locations, 'nplist'=>$location_ids);
}
?>
