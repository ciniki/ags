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
function ciniki_ags_feesUpdate($ciniki) {
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.feesUpdate');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'public', 'feesGet');
    $rc = ciniki_ags_feesGet($ciniki);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.26', 'msg'=>'', 'err'=>$rc['err']));
    }
    $fees = $rc['fees'];

    foreach($fees as $fee) {
        if( isset($ciniki['request']['args'][$fee['id']]) ) {
            $value = preg_replace("/[^0-9\.]/", '', $ciniki['request']['args'][$fee['id']]);
            $value = $value/100;

            if( $value != $fee['value'] ) {
                //
                // Update default item percents
                //
                $strsql = "SELECT id, fee_percent "
                    . "FROM ciniki_ags_items "
                    . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                    . "AND fee_percent = '" . ciniki_core_dbQuote($ciniki, $fee['value']) . "' "
                    . "";
                $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.294', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
                }
                $items = isset($rc['rows']) ? $rc['rows'] : array();
                foreach($items as $item) {
                    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
                    $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.ags.item', $item['id'], array('fee_percent'=>$value), 0x04);
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.110', 'msg'=>'Unable to update the item', 'err'=>$rc['err']));
                    }
                }
                //
                // Update exhibit fee percents
                //
                $strsql = "SELECT id, fee_percent "
                    . "FROM ciniki_ags_exhibit_items "
                    . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                    . "AND fee_percent = '" . ciniki_core_dbQuote($ciniki, $fee['value']) . "' "
                    . "";
                $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.295', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
                }
                $items = isset($rc['rows']) ? $rc['rows'] : array();
                foreach($items as $item) {
                    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
                    $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.ags.exhibititem', $item['id'], array('fee_percent'=>$value), 0x04);
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.177', 'msg'=>'Unable to update the item', 'err'=>$rc['err']));
                    }
                }
            }
        }
    }

    return array('stat'=>'ok', 'fees'=>$fees);
}
?>
