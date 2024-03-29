<?php
//
// Description
// -----------
// This method will add a new participant for the tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to add the Participant to.
//
// Returns
// -------
//
function ciniki_ags_participantAdd(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'exhibit_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Exhibit'),
        'exhibitor_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Exhibitor'),
        'status'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Status'),
        'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
        'message'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Message'),
        'notes'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Notes'),
        'synopsis'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Synopsis'),
        // Fields for adding exhibitor
        'customer_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Customer'),
        'display_name_override'=>array('required'=>'no', 'blank'=>'yes', 'trim'=>'yes', 'name'=>'Override Name'),
        'display_name'=>array('required'=>'no', 'blank'=>'yes', 'trim'=>'yes', 'name'=>'Name'),
        'profile_name'=>array('required'=>'no', 'blank'=>'yes', 'trim'=>'yes', 'name'=>'Profile Name'),
        'code'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Code'),
        // If form submission is specified for import
        'submission_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Form Submission'),
        'fee_percent'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'percent', 'name'=>'Fee Percent'),
        'item_flags3'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Sell Items Online'),
        'item_flags5'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Items Tagged'),
        'item_synopsis'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Synopsis Append'),
        'item_description'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Description Append'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.participantAdd');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Check to make sure the exhibit_id exists
    //
    $strsql = "SELECT id, name "
        . "FROM ciniki_ags_exhibits "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibit');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.119', 'msg'=>'Unable to load exhibit', 'err'=>$rc['err']));
    }
    if( !isset($rc['exhibit']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.120', 'msg'=>'Unable to find requested exhibit'));
    }
    $exhibit = $rc['exhibit'];

    //
    // Start transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.ags');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Check if the exhibitor is 0, then add exhibitor first
    //
    if( !isset($args['exhibitor_id']) || $args['exhibitor_id'] == '' || $args['exhibitor_id'] == 0 ) {
        //
        // Add the customer
        //
        if( !isset($args['customer_id']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.114', 'msg'=>'No customer specified'));
        }

        //
        // Load the customer
        //
        $strsql = "SELECT display_name "
            . "FROM ciniki_customers "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'customer');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.115', 'msg'=>'Unable to load customer', 'err'=>$rc['err']));
        }
        if( !isset($rc['customer']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.116', 'msg'=>'Unable to find requested customer'));
        }
        $customer = $rc['customer'];
        
        //
        // Check if the display_name_override was passed with a value
        //
        if( isset($args['display_name_override']) && $args['display_name_override'] != '' && $args['display_name_override'] != $customer['display_name'] ) {
            $args['display_name'] = $args['display_name_override'];
        } else {
            $args['display_name_override'] = '';
            $args['display_name'] = $customer['display_name'];
        }

        //
        // Check the code does not already exist
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'exhibitorCodeCheck');
        $rc = ciniki_ags_exhibitorCodeCheck($ciniki, $args['tnid'], $args['code']);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'warn', 'err'=>array('code'=>'ciniki.ags.117', 'msg'=>'Code already exists, please choose another.'));
        }
        
        //
        // Setup permalink
        //
        if( !isset($args['permalink']) || $args['permalink'] == '' ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'makePermalink');
            $args['permalink'] = ciniki_core_makePermalink($ciniki, $args['display_name']);
        }

        //
        // Make sure the permalink is unique
        //
        $strsql = "SELECT id, display_name, permalink "
            . "FROM ciniki_ags_exhibitors "
            . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND permalink = '" . ciniki_core_dbQuote($ciniki, $args['permalink']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( $rc['num_rows'] > 0 ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.118', 'msg'=>'You already have a exhibitor with that name, please choose another.'));
        }

        //
        // Add the exhibitor
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
        $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.ags.exhibitor', $args, 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return $rc;
        }
        $args['exhibitor_id'] = $rc['id'];
    } 
    //
    // If exhibitor_id is specified, check to make sure they exist
    //
    else {
        $strsql = "SELECT id, display_name, profile_name, code, synopsis "
            . "FROM ciniki_ags_exhibitors "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibitor');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.121', 'msg'=>'Unable to load exhibitor', 'err'=>$rc['err']));
        }
        if( !isset($rc['exhibitor']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.122', 'msg'=>'Unable to find requested exhibitor'));
        }
        $exhibitor = $rc['exhibitor'];

        $update_args = array();
        if( isset($args['synopsis']) && $args['synopsis'] != $exhibitor['synopsis'] ) {
            $update_args['synopsis'] = $args['synopsis'];
        }
        if( isset($args['profile_name']) && $args['profile_name'] != $exhibitor['profile_name'] ) {
            $update_args['profile_name'] = $args['profile_name'];
        }
        if( count($update_args) > 0 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.ags.exhibitor', $exhibitor['id'], $update_args, 0x04);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.285', 'msg'=>'Unable to update the exhibitor', 'err'=>$rc['err']));
            }
        }
    }

    //
    // Check to make sure the exhibitor does not already exist
    //
    $strsql = "SELECT id, status "  
        . "FROM ciniki_ags_participants AS participants "
        . "WHERE participants.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
        . "AND participants.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
        . "AND participants.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'participant');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.364', 'msg'=>'Unable to load participant', 'err'=>$rc['err']));
    }
    if( isset($rc['participant']) ) {
        return array('stat'=>'warn', 'err'=>array('code'=>'ciniki.ags.364', 'msg'=>'This exhibitor has already be added to the exhibit.'));
    }
    

    //
    // Add the participant to the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.ags.participant', $args, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
        return $rc;
    }
    $participant_id = $rc['id'];

    //
    // Check if a form submission has be specified to import
    //
    if( ciniki_core_checkModuleActive($ciniki, 'ciniki.forms') 
        && isset($args['submission_id']) 
        && $args['submission_id'] > 0 
        ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'exhibitorImportFormSubmission');
        $rc = ciniki_ags_exhibitorImportFormSubmission($ciniki, $args['tnid'], $args);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.249', 'msg'=>'Unable to import form', 'err'=>$rc['err']));
        }
    }

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.ags');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $args['tnid'], 'ciniki', 'ags');

    //
    // Update the web index if enabled
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'hookExec');
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'ciniki.ags.participant', 'object_id'=>$participant_id));
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'wng', 'indexObject', array('object'=>'ciniki.ags.participant', 'object_id'=>$participant_id));

    return array('stat'=>'ok', 'id'=>$participant_id);
}
?>
