<?php
//
// Description
// -----------
// This function will process api requests for wng.
//
// Arguments
// ---------
// ciniki:
// tnid:     The ID of the tenant to get sapos request for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_ags_wng_api(&$ciniki, $tnid, &$request) {

    //
    // Check to make sure the module is enabled
    //
    if( !isset($ciniki['tenant']['modules']['ciniki.ags']) ) {
        return array('stat'=>'404', 'err'=>array('code'=>'ciniki.ags.322', 'msg'=>"I'm sorry, the page you requested does not exist."));
    }

    //
    // Check to make sure logged in
    //
    if( !isset($request['session']['customer']['id']) || $request['session']['customer']['id'] < 1 ) {
        return array('stat'=>'404', 'err'=>array('code'=>'ciniki.ags.323', 'msg'=>"I'm sorry, the you are not authorized."));
    }

    //
    // itemSave - Save the form to either add or update an item
    //
    if( isset($request['uri_split'][$request['cur_uri_pos']]) 
        && $request['uri_split'][$request['cur_uri_pos']] == 'profileImage' 
        ) {
        $request['cur_uri_pos']++;
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'apiProfileImage');
        return ciniki_ags_wng_apiProfileImage($ciniki, $tnid, $request);
    }
    elseif( isset($request['uri_split'][$request['cur_uri_pos']]) 
        && $request['uri_split'][$request['cur_uri_pos']] == 'profileSave' 
        ) {
        $request['cur_uri_pos']++;
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'apiProfileSave');
        return ciniki_ags_wng_apiProfileSave($ciniki, $tnid, $request);
    }
    elseif( isset($request['uri_split'][$request['cur_uri_pos']]) 
        && $request['uri_split'][$request['cur_uri_pos']] == 'itemSave' 
        ) {
        $request['cur_uri_pos']++;
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'apiItemSave');
        return ciniki_ags_wng_apiItemSave($ciniki, $tnid, $request);
    }
    elseif( isset($request['uri_split'][$request['cur_uri_pos']]) 
        && $request['uri_split'][$request['cur_uri_pos']] == 'itemImage' 
        ) {
        $request['cur_uri_pos']++;
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'apiItemImage');
        return ciniki_ags_wng_apiItemImage($ciniki, $tnid, $request);
    }

    return array('stat'=>'ok');
}
?>
