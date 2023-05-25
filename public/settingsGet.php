<?php
//
// Description
// -----------
// This method will turn the ags settings for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:     The ID of the tenant to get the ATDO settings for.
// 
// Returns
// -------
//
function ciniki_ags_settingsGet($ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.settingsGet'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }
    $modules = $rc['modules'];
    
    //
    // Grab the settings for the tenant from the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQuery');
    $rc = ciniki_core_dbDetailsQuery($ciniki, 'ciniki_ags_settings', 'tnid', $args['tnid'], 'ciniki.ags', 'settings', '');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    if( !isset($rc['settings']) ) {
        return array('stat'=>'ok', 'settings'=>array());
    }
    $settings = $rc['settings'];

    //
    // Check if different settings used for each type of exhibit
    //
    if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.ags', 0x0400) ) {
        //
        // Get the types used
        //
        $strsql = "SELECT DISTINCT tag_name AS name, permalink "
            . "FROM ciniki_ags_exhibit_tags "
            . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND tag_type = 20 "
            . "ORDER BY permalink "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'types', 'fname'=>'permalink', 'fields'=>array('name', 'permalink'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.24', 'msg'=>'Unable to load tags', 'err'=>$rc['err']));
        }
        $types = isset($rc['types']) ? $rc['types'] : array();
    
        $settings['typecards'] = array();
        foreach($types as $type) {
            foreach(['image', 'template', 'artist-prefix', 'include-size', 'last-line', 'qr-code-prefix', 'title', 'description'] as $item) {
                if( isset($settings["namecards-{$type['permalink']}-{$item}"]) ) {
                    $type[$item] = $settings["namecards-{$type['permalink']}-{$item}"];
                } else {
                    $type[$item] = '';
                }
            }
            $settings['typecards'][$type['permalink']] = $type;
        }

    }


    return array('stat'=>'ok', 'settings'=>$settings);
}
?>
