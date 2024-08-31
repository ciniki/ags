<?php
//
// Description
// ===========
// This method will return all the information about an location.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the location is attached to.
// location_id:          The ID of the location to get the details for.
//
// Returns
// -------
//
function ciniki_ags_locationGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'location_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Location'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.locationGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    //
    // Return default for new Location
    //
    if( $args['location_id'] == 0 ) {
        $location = array('id'=>0,
            'name'=>'',
            'category'=>'',
            'flags'=>'0',
            'address1'=>'',
            'address2'=>'',
            'city'=>'',
            'province'=>'',
            'postal'=>'',
            'country'=>'',
            'latitude'=>'',
            'longitude'=>'',
            'notes'=>'',
            'primary_image_id'=>'0',
            'synopsis'=>'',
            'description'=>'',
        );
    }

    //
    // Get the details for an existing Location
    //
    else {
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
            . "ciniki_ags_locations.notes, "
            . "ciniki_ags_locations.primary_image_id, "
            . "ciniki_ags_locations.synopsis, "
            . "ciniki_ags_locations.description "
            . "FROM ciniki_ags_locations "
            . "WHERE ciniki_ags_locations.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_ags_locations.id = '" . ciniki_core_dbQuote($ciniki, $args['location_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'locations', 'fname'=>'id', 
                'fields'=>array('name', 'category', 'flags', 'address1', 'address2', 'city', 'province', 'postal', 'country', 'latitude', 'longitude', 'notes', 'primary_image_id', 'synopsis', 'description'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.68', 'msg'=>'Location not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['locations'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.69', 'msg'=>'Unable to find Location'));
        }
        $location = $rc['locations'][0];
    }

    return array('stat'=>'ok', 'location'=>$location);
}
?>
