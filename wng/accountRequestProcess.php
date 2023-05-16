<?php
//
// Description
// -----------
// This function will process the account request for ags module
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_ags_wng_accountRequestProcess(&$ciniki, $tnid, &$request, $item) {

    if( !isset($item['ref']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.298', 'msg'=>'No reference specified'));
    }

    if( !isset($request['session']['customer']['id']) || $request['session']['customer']['id'] <= 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.299', 'msg'=>'Must be logged in'));
    }

    if( $item['ref'] == 'ciniki.ags.main' ) {
        $request['cur_uri_pos']+=2;
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountOverviewProcess');
        return ciniki_ags_wng_accountOverviewProcess($ciniki, $tnid, $request, $item);
    } elseif( $item['ref'] == 'ciniki.ags.profile' ) {
        $request['cur_uri_pos']+=3;
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountProfileProcess');
        return ciniki_ags_wng_accountProfileProcess($ciniki, $tnid, $request, $item);
    } elseif( $item['ref'] == 'ciniki.ags.exhibit' ) {
        $request['cur_uri_pos']+=3;
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountExhibitProcess');
        return ciniki_ags_wng_accountExhibitProcess($ciniki, $tnid, $request, $item);
    } elseif( $item['ref'] == 'ciniki.ags.catalog' ) {
        $request['cur_uri_pos']+=3;
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountCatalogProcess');
        return ciniki_ags_wng_accountCatalogProcess($ciniki, $tnid, $request, $item);
    } elseif( $item['ref'] == 'ciniki.ags.pendingpayouts' ) {
        $request['cur_uri_pos']+=3;
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountPendingPayoutsProcess');
        return ciniki_ags_wng_accountPendingPayoutsProcess($ciniki, $tnid, $request, $item);
    } elseif( $item['ref'] == 'ciniki.ags.paidsales' ) {
        $request['cur_uri_pos']+=3;
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountPaidSalesProcess');
        return ciniki_ags_wng_accountPaidSalesProcess($ciniki, $tnid, $request, $item);
    }

    return array('stat'=>'404', 'err'=>array('code'=>'ciniki.ags.300', 'msg'=>'Account page not found'));
}
?>
