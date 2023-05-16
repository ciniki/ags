<?php
//
// Description
// -----------
// Save the item
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function ciniki_ags_wng_apiProfileImage(&$ciniki, $tnid, $request) {
    
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');

    //
    // Make sure customer is logged in
    //
    if( !isset($request['session']['customer']['id']) || $request['session']['customer']['id'] <= 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.326', 'msg'=>'Not signed in'));
    }

    //
    // Make sure customer is logged in
    //
    if( !isset($request['session']['customer']['id']) || $request['session']['customer']['id'] <= 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.326', 'msg'=>'Not signed in'));
    }

    //
    // Load settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQuery');
    $rc = ciniki_core_dbDetailsQuery($ciniki, 'ciniki_ags_settings', 'tnid', $tnid, 'ciniki.ags', 'settings', '');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $settings = isset($rc['settings']) ? $rc['settings'] : array();

    if( !isset($request['uri_split'][($request['cur_uri_pos'])]) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.326', 'msg'=>'No image specified'));
    }
    $image_id = $request['uri_split'][($request['cur_uri_pos'])];
  
    //
    // Load the exhibitor
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountExhibitorLoad');
    $rc = ciniki_ags_wng_accountExhibitorLoad($ciniki, $tnid, $request);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.330', 'msg'=>'Unable to load your account.'));
    }
    $exhibitor = $rc['exhibitor'];

    if( $exhibitor['primary_image_id'] == $image_id 
        || (isset($exhibitor['requested_changes']['primary_image_id']) && $exhibitor['requested_changes']['primary_image_id'] == $image_id) 
        ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'hooks', 'loadOriginal');
        $rc = ciniki_images_hooks_loadOriginal($ciniki, $tnid, array('image_id'=>$image_id));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.340', 'msg'=>'Unable to load image', 'err'=>$rc['err']));
        }

        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $rc['last_updated']) . ' GMT', true, 200);
        if( isset($ciniki['request']['args']['attachment']) && $ciniki['request']['args']['attachment'] == 'yes' ) {
            header('Content-Disposition: attachment; filename="' . $rc['original_filename'] . '"');
        }
        if( isset($rc['type']) && $rc['type'] == 6 ) {
            header("Content-type: image/svg+xml"); 
        } else {
            header("Content-type: image/jpeg"); 
        }
        echo $rc['image'];
    }

    return array('stat'=>'exit');
}
?>
