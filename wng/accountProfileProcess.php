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
function ciniki_ags_wng_accountProfileProcess(&$ciniki, $tnid, &$request, $item) {

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
    // Build base url
    //
    $base_url = '';
    for($i = 0; $i <= $request['cur_uri_pos'];$i++) {
        if( isset($request['uri_split'][$i]) && $request['uri_split'][$i] != '' ) {
            if( $request['uri_split'][$i] != 'edit' ) {
                $base_url .= '/' . $request['uri_split'][$i];
            }
        }
    }

    //
    // Load the tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "Internal error, please try again or contact us for help."
            )));
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    
    //
    // Load settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQuery');
    $rc = ciniki_core_dbDetailsQuery($ciniki, 'ciniki_ags_settings', 'tnid', $tnid, 'ciniki.ags', 'settings', '');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "Internal error, please try again or contact us for help."
            )));
    }
    $settings = isset($rc['settings']) ? $rc['settings'] : array();
  
    //
    // Load the exhibitor
    //
    if( isset($item['apply']) && $item['apply'] == 'yes' ) {
        $exhibitor = array(
            'id' => 0,
            'customer_id' => $request['session']['customer']['id'],
            'display_name' => '',
            'code' => '',
            'status' => 10,
            'primary_image_id' => 0,
            'synopsis' => '',
            'fullbio' => '',
            );
        $editable = 'yes';
        $blocks[] = array(
            'type' => 'title', 
            'class' => 'limit-width limit-width-60',
            'title' => 'Exhibitor Application',
            );
        if( isset($settings['web-updater-profile-form-application']) && $settings['web-updater-profile-form-application'] != '' ) {
            $blocks[] = array(
                'type' => 'text', 
                'class' => 'limit-width limit-width-60',
                'content' => $settings['web-updater-profile-form-application'],
                );
        } else {
            $blocks[] = array(
                'type' => 'text', 
                'class' => 'limit-width limit-width-60',
                'content' => 'Please fill out the form below to apply to be an exhibitor. Once your profile is completed you will then be able to add items.',
                );
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
        $exhibitor['display_name'] = $customer['display_name'];
    } else {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountExhibitorLoad');
        $rc = ciniki_ags_wng_accountExhibitorLoad($ciniki, $tnid, $request);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'ok', 'blocks'=>array(array(
                'type' => 'msg', 
                'level' => 'error',
                'content' => "Internal Error, please try again or contact us for help."
                )));
        }
        $exhibitor = $rc['exhibitor'];

        //
        // Load the exhibitor details (photo, bio)
        //
        $editable = 'no';
        if( isset($request['uri_split'][($request['cur_uri_pos'])])
            && $request['uri_split'][($request['cur_uri_pos'])] == 'edit' 
            ) {
            $editable = 'yes';
        }

        $blocks[] = array(
            'type' => 'title', 
            'class' => 'limit-width limit-width-60',
            'title' => 'Profile',
            );
    }

    //
    // Build the form fields
    //
    $fields = array();
    $formDataAdd = '';
    $fields['action'] = array(
        'id' => 'action',
        'ftype' => 'hidden',
        'value' => isset($exhibitor['id']) && $exhibitor['id'] > 0 ? 'update' : 'add',
        );
    if( isset($settings['web-updater-profile-display_name']) && $settings['web-updater-profile-display_name'] != 'hidden' ) {
        $fields['display_name'] = array(
            'id' => 'display_name',
            'label' => 'Name',
            'ftype' => 'text',
            'size' => 'large',
            'editable' => $editable,
            'required' => $editable == 'yes' && $settings['web-updater-profile-display_name'] == 'required' ? 'yes' : 'no',
            'value' => isset($exhibitor['requested_changes']['display_name']) ? $exhibitor['requested_changes']['display_name'] : $exhibitor['display_name'],
            );
    }
    if( isset($settings['web-updater-profile-profile_name']) && $settings['web-updater-profile-profile_name'] != 'hidden' ) {
        $fields['profile_name'] = array(
            'id' => 'profile_name',
            'label' => 'Full Name',
            'ftype' => 'text',
            'size' => 'large',
            'editable' => $editable,
            'required' => $editable == 'yes' && $settings['web-updater-profile-profile_name'] == 'required' ? 'yes' : 'no',
            'value' => isset($exhibitor['requested_changes']['profile_name']) ? $exhibitor['requested_changes']['profile_name'] : $exhibitor['profile_name'],
            );
    }
    if( isset($settings['web-updater-profile-primary_image_id']) && $settings['web-updater-profile-primary_image_id'] != 'hidden' ) {
        $fields['primary_image_id'] = array(
            'id' => 'primary_image_id',
            'label' => 'Profile Image',
            'ftype' => 'image',
            'size' => 'large',
            'editable' => $editable,
            'required' => $editable == 'yes' && $settings['web-updater-profile-primary_image_id'] == 'required' ? 'yes' : 'no',
            'value' => isset($exhibitor['requested_changes']['primary_image_id']) ? $exhibitor['requested_changes']['primary_image_id'] : $exhibitor['primary_image_id'],
            'src' => '',
            );
        if( isset($exhibitor['requested_changes']['primary_image_id']) && $exhibitor['requested_changes']['primary_image_id'] > 0 ) {
            $fields['primary_image_id']['src'] = "{$request['api_url']}/ciniki/ags/profileImage/{$exhibitor['requested_changes']['primary_image_id']}";
        } elseif( isset($exhibitor['primary_image_id']) && $exhibitor['primary_image_id'] > 0 ) {
            $fields['primary_image_id']['src'] = "{$request['api_url']}/ciniki/ags/profileImage/{$exhibitor['primary_image_id']}";
        }
    }
    if( isset($settings['web-updater-profile-synopsis']) && $settings['web-updater-profile-synopsis'] != 'hidden' ) {
        $fields['synopsis'] = array(
            'id' => 'synopsis',
            'label' => 'Synopsis',
            'ftype' => 'textarea',
            'size' => 'small',
            'editable' => $editable,
            'required' => $editable == 'yes' && $settings['web-updater-profile-synopsis'] == 'required' ? 'yes' : 'no',
            'value' => isset($exhibitor['requested_changes']['synopsis']) ? $exhibitor['requested_changes']['synopsis'] : $exhibitor['synopsis'],
            );
    }
    if( isset($settings['web-updater-profile-fullbio']) && $settings['web-updater-profile-fullbio'] != 'hidden' ) {
        $fields['fullbio'] = array(
            'id' => 'fullbio',
            'label' => 'Biography',
            'ftype' => 'textarea',
            'size' => 'medium',
            'editable' => $editable,
            'required' => $editable == 'yes' && $settings['web-updater-profile-fullbio'] == 'required' ? 'yes' : 'no',
            'value' => isset($exhibitor['requested_changes']['fullbio']) ? $exhibitor['requested_changes']['fullbio'] : $exhibitor['fullbio'],
            );
    }
    
    //
    // Display the form to view/edit the profile
    //
    if( $editable == 'no' ) {
        $blocks[] = array(
            'type' => 'form',
            'guidelines' => '',
            'title' => '',
            'class' => 'limit-width limit-width-60 viewonly',
            'problem-list' => '',
            'submit-hide' => 'yes',
            'fields' => $fields,
            );
        $blocks[] = array(
            'type' => 'buttons',
            'class' => 'limit-width limit-width-60 aligncenter',
            'list' => array(
                array('url'=>"{$base_url}/edit", 'text'=>'Edit Profile'),
                ),
            );

    } else {
        $blocks[] = array(
            'type' => 'form',
            'form-id' => 'profileedit',
            'guidelines' => isset($settings['web-updater-profile-form-intro']) ? $settings['web-updater-profile-form-intro'] : '',
            'title' => $exhibitor['id'] > 0 ? 'Update Profile' : 'Add Profile',
            'class' => 'limit-width limit-width-60',
            'problem-list' => '',
            'cancel-label' => 'Cancel',
            'js-cancel' => 'profileCancel();',
            'submit-label' => 'Save',
            'js-submit' => 'profileSave();',
            'fields' => $fields,
            'js' => ''
                . "function profileCancel() {"
                    . "window.location.href = '{$base_url}';"
                . "};"
                . "function profileSave() {"
                    . "var f=C.gE('profileedit');"
                    . "var fdata = new FormData(f);"
                    . "C.postFDBg('{$request['api_url']}/ciniki/ags/profileSave',null,fdata,profileSaved);"
                . "};"
                . "function profileSaved(rsp) {"
                    . "var rc=eval(rsp);"
                    . "if(rc.stat!=null&&rc.stat=='ok'){"
                        . "window.location.href = '{$base_url}';"
                    . "}else if(rc.stat!=null&&rc.stat!='ok'&&rc.err!=null&&rc.err.msg!=null){"
                       . "C.gE('form-errors-msg').innerHTML = '<p>'+rc.err.msg.replace(/\\n/g,'<br/>')+'</p>';"
                       . "C.aC(C.gE('form-errors'), 'error');"
                       . "C.rC(C.gE('form-errors'), 'hidden');"
                       . "window.scrollTo(0,0);"
                    . "}else{"
                       . "C.gE('form-errors-msg').innerHTML = '<p>Error saving profile, please try again or contact us for help.</p>';" 
                       . "C.aC(C.gE('form-errors'), 'error');"
                       . "C.rC(C.gE('form-errors'), 'hidden');"
                       . "window.scrollTo(0,0);"
                    . "}"
                . "};"
                . '',
            );
    }


    return array('stat'=>'ok', 'blocks'=>$blocks);
}
?>
