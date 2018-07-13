<?php
//
// Description
// -----------
// This function will update exhibitor names and a customer name has been updated.
//
// Arguments
// ---------
// ciniki:
// tnid:                The tenant ID to check the session user against.
// args:                The requested method.
//
// Returns
// -------
// <rsp stat='ok' />
//
function ciniki_ags_hooks_customerNameUpdate($ciniki, $tnid, $args) {
    //
    // Check to see if the customer is an exhibitor
    //
    if( isset($args['customer_id']) && $args['customer_id'] > 0 && isset($args['display_name']) && $args['display_name'] != '' ) {
       
        $strsql = "SELECT id, "
            . "customer_id, "
            . "display_name_override, "
            . "display_name, "
            . "permalink "
            . "FROM ciniki_ags_exhibitors "
            . "WHERE customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.customers', 'item');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['item']['customer_id']) && $rc['item']['customer_id'] == $args['customer_id'] ) {
            $exhibitor = $rc['item']; 
            if( $exhibitor['display_name_override'] == '' ) {
                if( $exhibitor['display_name'] != $args['display_name'] ) {
                    $update_args = array('display_name'=>$args['display_name']);
                    //
                    // Update the permalink
                    //
                    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'makePermalink');
                    $permalink = ciniki_core_makePermalink($ciniki, $args['display_name']);
                    if( $permalink != $exhibitor['permalink'] ) {
                        $update_args['permalink'] = $permalink;
                    }

                    //
                    // Update the exhibitor
                    //
                    $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.exhibitor', $exhibitor['id'], $update_args);
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'fail', 'code'=>'ciniki.ags.41', 'msg'=>'Unable to update exhibitor name', 'err'=>$rc['err']);
                    }
                }
            } 
        }
    }

    return array('stat'=>'ok');
}
?>
