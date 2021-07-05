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
function ciniki_ags_categoryGet(&$ciniki) {
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
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.237', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
    }
    $category = array(
        'image' => 0,
        'description' => '',
        );
    if( isset($rc['rows']) ) {
        foreach($rc['rows'] as $row) {
            if( $row['detail_key'] == "category-{$args['permalink']}-image" ) {
                $category['image'] = $row['detail_value'];
            } elseif( $row['detail_key'] == "category-{$args['permalink']}-description" ) {
                $category['description'] = $row['detail_value'];
            }
        }
    }
    
    return array('stat'=>'ok', 'category'=>$category);
}
?>
