<?php
//
// Description
// ===========
// This method will return all the information about an item image.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the item image is attached to.
// itemimage_id:          The ID of the item image to get the details for.
//
// Returns
// -------
//
function ciniki_ags_itemImageGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'itemimage_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Item Image'),
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.itemImageGet');
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
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    //
    // Return default for new Item Image
    //
    if( $args['itemimage_id'] == 0 ) {
        $itemimage = array('id'=>0,
            'item_id'=>'',
            'name'=>'',
            'permalink'=>'',
            'flags'=>0x01,
            'sequence'=>'1',
            'image_id'=>'',
            'description'=>'',
        );
    }

    //
    // Get the details for an existing Item Image
    //
    else {
        $strsql = "SELECT ciniki_ags_item_images.id, "
            . "ciniki_ags_item_images.item_id, "
            . "ciniki_ags_item_images.name, "
            . "ciniki_ags_item_images.permalink, "
            . "ciniki_ags_item_images.flags, "
            . "ciniki_ags_item_images.sequence, "
            . "ciniki_ags_item_images.image_id, "
            . "ciniki_ags_item_images.description "
            . "FROM ciniki_ags_item_images "
            . "WHERE ciniki_ags_item_images.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_ags_item_images.id = '" . ciniki_core_dbQuote($ciniki, $args['itemimage_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'itemimages', 'fname'=>'id', 
                'fields'=>array('item_id', 'name', 'permalink', 'flags', 'sequence', 'image_id', 'description'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.87', 'msg'=>'Item Image not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['itemimages'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.88', 'msg'=>'Unable to find Item Image'));
        }
        $itemimage = $rc['itemimages'][0];
    }

    return array('stat'=>'ok', 'itemimage'=>$itemimage);
}
?>
