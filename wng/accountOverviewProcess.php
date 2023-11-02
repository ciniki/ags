<?php
//
// Description
// -----------
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_ags_wng_accountOverviewProcess(&$ciniki, $tnid, &$request, $item) {

    $blocks = array();

    if( !isset($item['ref']) ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "Request error, please contact us for help..",
            )));
    }

    if( !isset($request['session']['customer']['id']) || $request['session']['customer']['id'] <= 0 ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "You must be logged in."
            )));
    }

    //
    // Load the tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    
/*    //
    // Load the exhibitor
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountExhibitorLoad');
    $rc = ciniki_ags_wng_accountExhibitorLoad($ciniki, $tnid, $request);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $exhibitor = $rc['exhibitor']; */

    //
    // Load the menu
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountMenuItems');
    $rc = ciniki_ags_wng_accountMenuItems($ciniki, $tnid, $request, array(
        'base_url' => $request['ssl_domain_base_url'] . '/account',
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "You must be logged in."
            )));
    }

    if( isset($rc['items'][0]['items']) ) {
        $blocks[] = array(
            'type' => 'buttons', 
            'class' => 'aligncenter',
            'list' => $rc['items'][0]['items'],
            );
    }

    return array('stat'=>'ok', 'blocks'=>$blocks);
}
?>
