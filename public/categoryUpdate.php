<?php
//
// Description
// -----------
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function ciniki_ags_categoryUpdate(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'permalink'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Category Permalink'),
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.categoryGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    $strsql = "SELECT detail_key, detail_value "
        . "FROM ciniki_ags_settings "
        . "WHERE detail_key LIKE 'category-" . ciniki_core_dbQuote($ciniki, $args['permalink']) . "-%' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.236', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
    }
    $category = array(
        'image_id' => 0,
        'description' => '',
        );
    if( isset($rc['rows']) ) {
        foreach($rc['rows'] as $row) {
            if( $row['detail_key'] == "category-{$args['permalink']}-image" ) {
                $category['image_id'] = $row['detail_value'];
            } elseif( $row['detail_key'] == "category-{$args['permalink']}-description" ) {
                $category['description'] = $row['detail_value'];
            }
        }
    }
    $fields = array(
        'image',
        'description',
        );
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    foreach($fields as $field) {
        if( isset($ciniki['request']['args'][$field]) ) {
            $strsql = "INSERT INTO ciniki_ags_settings (tnid, detail_key, detail_value, date_added, last_updated) "
                . "VALUES ('" . ciniki_core_dbQuote($ciniki, $ciniki['request']['args']['tnid']) . "'"
                . ", '" . ciniki_core_dbQuote($ciniki, "category-{$args['permalink']}-{$field}") . "'"
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
                2, 'ciniki_ags_settings', "category-{$args['permalink']}-{$field}", 'detail_value', $ciniki['request']['args'][$field]);
            $ciniki['syncqueue'][] = array('push'=>'ciniki.ags.setting', 
                'args'=>array('id'=>"category-{$args['permalink']}-{$field}"));
        }
    }
    
    return array('stat'=>'ok');
}
?>
