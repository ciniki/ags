<?php
//
// Description
// -----------
// Save the profile 
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function ciniki_ags_wng_apiProfileSave(&$ciniki, $tnid, $request) {
    
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');

    //
    // Make sure customer is logged in
    //
    if( !isset($request['session']['customer']['id']) || $request['session']['customer']['id'] <= 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.350', 'msg'=>'Not signed in'));
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
  
    //
    // Load the exhibitor
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountExhibitorLoad');
    $rc = ciniki_ags_wng_accountExhibitorLoad($ciniki, $tnid, $request);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.351', 'msg'=>'Unable to load your account.'));
    }
    $exhibitor = $rc['exhibitor'];
    if( !isset($exhibitor['requested_changes']) || !is_array($exhibitor['requested_changes']) ) {
        $exhibitor['requested_changes'] = array();
    }

    //
    // Load the customer
    //
    $strsql = "SELECT display_name "
        . "FROM ciniki_customers "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $request['session']['customer']['id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'customer');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.103', 'msg'=>'Unable to load customer', 'err'=>$rc['err']));
    }
    if( !isset($rc['customer']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.104', 'msg'=>'Unable to find requested customer'));
    }
    $customer = $rc['customer'];
    
    //
    // Setup default exhibitor name
    //
    if( $exhibitor['display_name'] == '' ) {
        $exhibitor['display_name'] = $customer['display_name'];
    }

    //
    // Make sure the action is either add or update
    //
    if( !isset($_POST['f-action']) || !in_array($_POST['f-action'], ['add', 'update']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.352', 'msg'=>'Form Error: No action specified.'));
    }

    $update_args = array();
    $fields = array(
        'primary_image_id' => 'Profile Image', 
        'display_name' => 'Name', 
        'Profile Name' => 'Long Name', 
        'synopsis' => 'Synopsis', 
        'fullbio' => 'Biography',
        );
    $form_errors = '';
    foreach($fields as $field => $label) {
        if( isset($settings["web-updater-profile-{$field}"]) 
            && $settings["web-updater-profile-{$field}"] != 'hidden' 
            && $field != 'primary_image_id'
            && isset($_POST["f-{$field}"])
            ) {
            $_POST["f-{$field}"] = preg_replace("/\r/", '', $_POST["f-{$field}"]);
            if( $exhibitor[$field] != $_POST["f-{$field}"] ) {
                if( isset($_POST['f-action']) && $_POST['f-action'] == 'add' ) {
                    $exhibitor[$field] = $_POST["f-{$field}"];
                } else {
                    $exhibitor['requested_changes'][$field] = $_POST["f-{$field}"];
                }
            } elseif( isset($exhibitor['requested_changes'][$field]) ) {
                unset($exhibitor['requested_changes'][$field]);
            }
        }
        //
        // Check for required fields, based on if this is an add or update
        //
        if( isset($settings["web-updater-profile-{$field}"]) 
            && $settings["web-updater-profile-{$field}"] == 'required' 
            && $field == 'primary_image_id'
            ) {
            if( isset($_POST['f-action']) && $_POST['f-action'] == 'add'
                && (!isset($_FILES["f-{$field}"]) || $_FILES["f-{$field}"]['tmp_name'] == '')
                ) {
                $form_errors .= ($form_errors != '' ? "\n" : '') . "You must upload an {$label}.";
            }
            elseif( isset($_POST['f-action']) && $_POST['f-action'] == 'update'
                && $exhibitor['primary_image_id'] == 0 
                && (!isset($_FILES["f-{$field}"]) || $_FILES["f-{$field}"]['tmp_name'] == '')
                && $exhibitor[$field] == 0
                ) {
                $form_errors .= ($form_errors != '' ? "\n" : '') . "You need to upload an {$label}.";
            }
        }
        elseif( isset($settings["web-updater-profile-{$field}"]) 
            && $settings["web-updater-profile-{$field}"] == 'required' 
            && isset($_POST['f-action']) && $_POST['f-action'] == 'add'
            && $exhibitor[$field] == '' 
            ) {
            $form_errors .= ($form_errors != '' ? "\n" : '') . "You must specifiy the {$label}.";
        }
        elseif( isset($settings["web-updater-profile-{$field}"]) 
            && $settings["web-updater-profile-{$field}"] == 'required' 
            && isset($_POST['f-action']) && $_POST['f-action'] == 'update'
            && isset($exhibitor['requested_changes'][$field])
            && $exhibitor['requested_changes'][$field] == ''
            ) {
            $form_errors .= ($form_errors != '' ? "\n" : '') . "You must specifiy the {$label}.";
        }
    }
    if( $form_errors != '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.338', 'msg'=>"{$form_errors}"));
    }

    //
    // Check if adding a new exhibitor
    //
    if( isset($_POST['f-action']) && $_POST['f-action'] == 'add' ) {
        $exhibitor['customer_id'] = $request['session']['customer']['id'];
        //
        // Create an exhibitor code
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'exhibitorCode');
        $rc = ciniki_ags_exhibitorCode($ciniki, $tnid, $exhibitor['customer_id']);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.338', 'msg'=>"Unable to create exhibitor code, please contact us to setup your profile."));
        }
        $exhibitor['code'] = $rc['code'];

        //
        // Check if display name is different than customer name
        //
        if( $exhibitor['display_name'] != $customer['display_name'] ) {
            $exhibitor['display_name_override'] = $exhibitor['display_name'];
        }

        //
        // Setup the permalink
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'makePermalink');
        $exhibitor['permalink'] = ciniki_core_makePermalink($ciniki, $exhibitor['display_name']);

        //
        // Make sure the permalink is unique
        //
        $strsql = "SELECT id, display_name, permalink "
            . "FROM ciniki_ags_exhibitors "
            . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND permalink = '" . ciniki_core_dbQuote($ciniki, $exhibitor['permalink']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibitor');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( $rc['num_rows'] > 0 ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.353', 'msg'=>'We had problems with your profile, please contact us to get your profile setup.'));
        }

        $exhibitor['barcode_message'] = '';
        $exhibitor['status'] = 10;

        //
        // Start a transaction
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
        // Add the exhibitor
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
        $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.ags.exhibitor', $exhibitor, 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.354', 'msg'=>'Unable to setup your profile, please try again or contact us for help.', 'err'=>$rc['err']));
        }
        $exhibitor['id'] = $rc['id'];

        //
        // Check for an image
        //
        if( isset($_FILES['f-primary_image_id']['tmp_name']) && $_FILES['f-primary_image_id']['tmp_name'] != '' ) {
            //
            // Save the image
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'insertFromUpload');
            $rc = ciniki_images_insertFromUpload($ciniki, $tnid, -3, $_FILES['f-primary_image_id'], 1, '', '', 'no');
            // Duplicates allowed, reuse image id
            if( $rc['stat'] != 'ok' && $rc['err']['code'] != 'ciniki.images.66' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.355', 'msg'=>'Unable to save image', 'err'=>$rc['err']));
            }
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.exhibitor', $exhibitor['id'], array(
                'primary_image_id' => $rc['id'],
                ), 0x04);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.356', 'msg'=>'Unable to save image', 'err'=>$rc['err']));
            }
        }

        //
        // Add exhibitor to the exhibit
        //
        if( isset($_POST['f-exhibit_id']) && $_POST['f-exhibit_id'] > 0 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
            $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.ags.participant', array(
                'exhibit_id' => $_POST['f-exhibit_id'],
                'exhibitor_id' => $exhibitor['id'],
                'status' => 30,
                'flags' => 0,
                'message' => '',
                'notes' => '',
                ), 0x04);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.357', 'msg'=>'Unable to add the participant', 'err'=>$rc['err']));
            }
        }
        
        //
        // Commit the transaction
        //
        $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.ags');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }

        return array('stat'=>'ok', 'exhibitor_id'=>$exhibitor['id']);
    }
    //
    // Check if updating an existing exhibitor
    //
    elseif( isset($_POST['f-action']) && $_POST['f-action'] == 'update' ) {
        //
        // Start a transaction
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
        // Check for an image
        //
        if( isset($_FILES['f-primary_image_id']['tmp_name']) && $_FILES['f-primary_image_id']['tmp_name'] != '' ) {
            //
            // Save the image
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'insertFromUpload');
            $rc = ciniki_images_insertFromUpload($ciniki, $tnid, -3, $_FILES['f-primary_image_id'], 1, '', '', 'no');
            if( $rc['stat'] != 'ok' && $rc['err']['code'] != 'ciniki.images.66' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.342', 'msg'=>'Unable to save image', 'err'=>$rc['err']));
            }
            $exhibitor['requested_changes']['primary_image_id'] = $rc['id'];
        }
        
        if( is_array($exhibitor['requested_changes']) && count($exhibitor['requested_changes']) > 0 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            if( $exhibitor['status'] == 30 ) {
                $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.exhibitor', $exhibitor['id'], $exhibitor['requested_changes'], 0x04);
            } else {
                $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.exhibitor', $exhibitor['id'], array(
                    'requested_changes' => serialize($exhibitor['requested_changes']),
                    ), 0x04);
            }
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.341', 'msg'=>'Unable to update the exhibitor', 'err'=>$rc['err']));
            }
        } elseif( is_array($item['requested_changes']) && count($item['requested_changes']) == 0 ) {
            $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.exhibitor', $exhibitor['id'], array(
                'requested_changes' => '',
                ), 0x04);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.341', 'msg'=>'Unable to update the exhibitor', 'err'=>$rc['err']));
            }
        }

        //
        // Check if exhibit specified and if exhibitor is applied or accepted
        //
        if( isset($_POST['f-exhibit_id']) && $_POST['f-exhibit_id'] > 0 ) {
            //
            // Get the current status of participant
            //
            $strsql = "SELECT id, status "
                . "FROM ciniki_ags_participants "
                . "WHERE exhibit_id = '" . ciniki_core_dbQuote($ciniki, $_POST['f-exhibit_id']) . "' "
                . "AND exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $exhibitor['id']) . "' "
                . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'participant');
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.358', 'msg'=>'Unable to load participant', 'err'=>$rc['err']));
            }
            if( !isset($rc['participant']) ) {
                ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
                $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.ags.participant', array(
                    'exhibit_id' => $_POST['f-exhibit_id'],
                    'exhibitor_id' => $exhibitor['id'],
                    'status' => 30,
                    'flags' => 0,
                    'message' => '',
                    'notes' => '',
                    ), 0x04);
                if( $rc['stat'] != 'ok' ) {
                    ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.357', 'msg'=>'Unable to add the participant', 'err'=>$rc['err']));
                }
            }
        }

        //
        // Commit the transaction
        //
        $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.ags');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }

        return array('stat'=>'ok');
    }

    //
    // Should never reach this point
    //
    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.337', 'msg'=>'Unknown error.'));
}
?>
