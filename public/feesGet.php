<?php
//
// Description
// ===========
// This method will return a list of distinct fees being charged in the module.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the exhibit is attached to.
// exhibit_id:          The ID of the exhibit to get the details for.
//
// Returns
// -------
//
function ciniki_ags_feesGet($ciniki) {
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.feesGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    $strsql = "SELECT DISTINCT fee_percent "
        . "FROM ciniki_ags_items "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "ORDER BY fee_percent "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'fees', 'fname'=>'fee_percent', 
            'fields'=>array('fee_percent'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.186', 'msg'=>'Unable to load fees', 'err'=>$rc['err']));
    }
    $fees = array();
    if( isset($rc['fees']) ) {
        foreach($rc['fees'] as $fee) {
            $fees['f_' . $fee['fee_percent']] = array(
                'id' => 'f_' . $fee['fee_percent'],
                'label' => number_format(($fee['fee_percent']*100), 2) . '%',
                'fee' => number_format(($fee['fee_percent']*100), 2) . '%',
                'value' => $fee['fee_percent'],
                );
        }
    }
    $strsql = "SELECT DISTINCT fee_percent "
        . "FROM ciniki_ags_exhibit_items "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "ORDER BY fee_percent "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'fees', 'fname'=>'fee_percent', 
            'fields'=>array('fee_percent'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.187', 'msg'=>'Unable to load fees', 'err'=>$rc['err']));
    }
    if( isset($rc['fees']) ) {
        foreach($rc['fees'] as $fee) {
            if( !isset($fees['f_' . $fee['fee_percent']]) ) {
                $fees['f_' . $fee['fee_percent']] = array(
                    'id' => 'f_' . $fee['fee_percent'],
                    'label' => number_format(($fee['fee_percent']*100), 2) . '%',
                    'fee' => number_format(($fee['fee_percent']*100), 2) . '%',
                    'value' => $fee['fee_percent'],
                    );
            }
        }
    }

    return array('stat'=>'ok', 'fees'=>$fees);
}
?>
