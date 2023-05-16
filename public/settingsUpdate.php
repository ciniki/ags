<?php
//
// Description
// -----------
// This method will update one or more settings for the ags module.
//
// Arguments
// ---------
// user_id:         The user making the request
// 
// Returns
// -------
//
function ciniki_ags_settingsUpdate(&$ciniki) {
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.settingsUpdate'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

    //
    // Grab the settings for the tenant from the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQuery');
    $rc = ciniki_core_dbDetailsQuery($ciniki, 'ciniki_ags_settings', 'tnid', $args['tnid'], 'ciniki.ags', 'settings', '');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $settings = $rc['settings'];

    //  
    // Turn off autocommit
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.ags');
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

    //
    // The list of allowed fields for updating
    //
    $changelog_fields = array(
        'defaults-item-fee-percent',
        'defaults-item-taxtype-id', // **note** Not yet implemented
        'sales-customer-name',
        'sales-pdf-customer-name',
        'namecards-image',
        'namecards-template',
        'namecards-artist-prefix',
        'namecards-include-size',
        'namecards-last-line',
        'barcodes-barcode-format',
        'barcodes-label-format',
        'web-updater-profile-form-intro',
        'web-updater-profile-display_name',
        'web-updater-profile-profile_name',
        'web-updater-profile-primary_image_id',
        'web-updater-profile-synopsis',
        'web-updater-profile-fullbio',
        'web-updater-item-form-intro',
        'web-updater-item-name',
        'web-updater-item-exhibitor_code',
        'web-updater-item-unit_amount',
        'web-updater-item-categories',
        'web-updater-item-numcategories',
        'web-updater-item-categories-list',
        'web-updater-item-subcategories',
        'web-updater-item-numsubcategories',
        'web-updater-item-subcategories-list',
        'web-updater-item-primary_image_id',
        'web-updater-item-creation_year',
        'web-updater-item-medium',
        'web-updater-item-medium-list',
        'web-updater-item-size',
        'web-updater-item-framed_size',
        'web-updater-item-current_condition',
        'web-updater-item-synopsis',
        'web-updater-item-description',
        'web-updater-item-notes',
        );
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
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.312', 'msg'=>'Unable to load tags', 'err'=>$rc['err']));
    }
    $types = isset($rc['types']) ? $rc['types'] : array();

    foreach($types as $type) {
        foreach(['image', 'template', 'artist-prefix', 'include-size', 'last-line', 'title', 'description'] as $item) {
            $changelog_fields[] = "namecards-{$type['permalink']}-{$item}";
        }
    }

    //
    // Check each valid setting and see if a new value was passed in the arguments for it.
    // Insert or update the entry in the ciniki_ags_settings table
    //
    foreach($changelog_fields as $field) {
        if( isset($ciniki['request']['args'][$field]) 
            && (!isset($settings[$field]) || $ciniki['request']['args'][$field] != $settings[$field]) ) {
            $strsql = "INSERT INTO ciniki_ags_settings (tnid, detail_key, detail_value, date_added, last_updated) "
                . "VALUES ('" . ciniki_core_dbQuote($ciniki, $ciniki['request']['args']['tnid']) . "'"
                . ", '" . ciniki_core_dbQuote($ciniki, $field) . "'"
                . ", '" . ciniki_core_dbQuote($ciniki, $ciniki['request']['args'][$field]) . "'"
                . ", UTC_TIMESTAMP(), UTC_TIMESTAMP()) "
                . "ON DUPLICATE KEY UPDATE detail_value = '" . ciniki_core_dbQuote($ciniki, $ciniki['request']['args'][$field]) . "' "
                . ", last_updated = UTC_TIMESTAMP() "
                . "";
            $rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.ags');
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                return $rc;
            }
            ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.ags', 'ciniki_ags_history', $args['tnid'], 
                2, 'ciniki_ags_settings', $field, 'detail_value', $ciniki['request']['args'][$field]);
            $ciniki['syncqueue'][] = array('push'=>'ciniki.ags.setting', 
                'args'=>array('id'=>$field));
        }
    }

    //
    // Commit the database changes
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.ags');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $args['tnid'], 'ciniki', 'ags');

    return array('stat'=>'ok');
}
?>
