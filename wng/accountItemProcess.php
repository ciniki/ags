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
function ciniki_ags_wng_accountItemProcess(&$ciniki, $tnid, &$request, $item) {

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
    // Make sure exhibitor and exhibit_id is passed in 
    //
    if( !isset($item['exhibit_id']) || !isset($item['exhibitor']) ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "Invalid request."
            )));
    }
    $exhibitor = $item['exhibitor'];
    $exhibit_id = $item['exhibit_id'];

    //
    // Check if cancel submitted
    //
    if( isset($_POST['cancel']) && $_POST['cancel'] == 'Cancel' ) {
        header("Location: {$request['return-url']}");
        return array('stat'=>'exit');
    }

    //
    // Get the permalink
    //
    if( !isset($request['uri_split'][($request['cur_uri_pos'])])
        && $request['uri_split'][($request['cur_uri_pos'])] == ''
        ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "No item specified."
            )));
    }
    $item_permalink = $request['uri_split'][($request['cur_uri_pos'])];

    //
    // Load the tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "Internal."
            )));
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

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
    // Load the item
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'wng', 'accountItemLoad');
    $rc = ciniki_ags_wng_accountItemLoad($ciniki, $tnid, $request, array(
        'item_permalink' => $item_permalink,
        'exhibit_id' => $exhibit_id,
        'exhibitor_id' => $exhibitor['id'],
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'ok', 'blocks'=>array(array(
            'type' => 'msg', 
            'level' => 'error',
            'content' => "Unable to load item."
            )));
    }
    $item = $rc['item'];

    //
    // Build the form fields
    //
    $fields = array();
    $formDataAdd = '';
    $fields['action'] = array(
        'id' => 'action',
        'ftype' => 'hidden',
        'value' => isset($item['permalink']) && $item['permalink'] != '' ? 'update' : 'add',
        );
    $fields['item_permalink'] = array(
        'id' => 'item_permalink',
        'ftype' => 'hidden',
        'value' => $item['permalink'],
        );
    $fields['exhibit_id'] = array(
        'id' => 'exhibit_id',
        'ftype' => 'hidden',
        'value' => $exhibit_id,
        );
    if( isset($settings['web-updater-item-primary_image_id']) && $settings['web-updater-item-primary_image_id'] != 'hidden' ) {
        $fields['primary_image_id'] = array(
            'id' => 'primary_image_id',
            'label' => 'Item Image',
            'ftype' => 'image',
            'size' => 'large',
            'required' => $settings['web-updater-item-primary_image_id'] == 'required' ? 'yes' : 'no',
            'value' => isset($item['requested_changes']['primary_image_id']) ? $item['requested_changes']['primary_image_id'] : $item['primary_image_id'],
            'src' => '',
            );
        if( isset($item['requested_changes']['primary_image_id']) && $item['requested_changes']['primary_image_id'] > 0 ) {
            $fields['primary_image_id']['src'] = "{$request['api_url']}/ciniki/ags/itemImage/{$item['permalink']}/{$item['requested_changes']['primary_image_id']}";
        } elseif( isset($item['primary_image_id']) && $item['primary_image_id'] > 0 ) {
            $fields['primary_image_id']['src'] = "{$request['api_url']}/ciniki/ags/itemImage/{$item['permalink']}/{$item['primary_image_id']}";
        }
    }
    if( isset($settings['web-updater-item-name']) && $settings['web-updater-item-name'] != 'hidden' ) {
        $fields['name'] = array(
            'id' => 'name',
            'label' => 'Item Name',
            'ftype' => 'text',
            'size' => 'large',
            'required' => $settings['web-updater-item-name'] == 'required' ? 'yes' : 'no',
            'value' => isset($item['requested_changes']['name']) ? $item['requested_changes']['name'] : $item['name'],
            );
    }
    if( isset($settings['web-updater-item-exhibitor_code']) && $settings['web-updater-item-exhibitor_code'] != 'hidden' ) {
        $fields['exhibitor_code'] = array(
            'id' => 'exhibitor_code',
            'label' => 'Your Code',
            'ftype' => 'text',
            'size' => 'small',
            'required' => $settings['web-updater-item-exhibitor_code'] == 'required' ? 'yes' : 'no',
            'value' => isset($item['requested_changes']['exhibitor_code']) ? $item['requested_changes']['exhibitor_code'] : $item['exhibitor_code'],
            );
    }
    if( isset($settings['web-updater-item-unit_amount']) && $settings['web-updater-item-unit_amount'] != 'hidden' ) {
        $fields['unit_amount'] = array(
            'id' => 'unit_amount',
            'label' => 'Price',
            'ftype' => 'text',
            'size' => 'small',
            'required' => $settings['web-updater-item-unit_amount'] == 'required' ? 'yes' : 'no',
            'value' => (isset($item['requested_changes']['unit_amount']) ? $item['requested_changes']['unit_amount'] : $item['unit_amount']),
            );
        if( $fields['unit_amount']['value'] != '' ) {
            $fields['unit_amount']['value'] = '$' . $fields['unit_amount']['value'];
        }
    }
    if( isset($settings['web-updater-item-categories']) && $settings['web-updater-item-categories'] != 'hidden' ) {
        if( isset($settings['web-updater-item-categories-list']) && $settings['web-updater-item-categories-list'] != '' ) {
            $cats = array();
            $pieces = str_getcsv($settings['web-updater-item-categories-list']);
            foreach($pieces as $p) {
                if( trim($p) != '' ) {
                    $cats[] = array('id'=>trim($p), 'name'=>trim($p));
                }
            }
            $fields['categories'] = array(
                'id' => 'categories',
                'label' => 'Category',
                'ftype' => 'select',
                'size' => 'medium',
                'options' => $cats,
                'required' => $settings['web-updater-item-categories'] == 'required' ? 'yes' : 'no',
                'value' => isset($item['requested_changes']['categories']) ? $item['requested_changes']['categories'] : $item['categories'],
                );
        } else {
            // FIXME: Add select to get distinct categories from items in ciniki.ags
        }
    }
    if( isset($settings['web-updater-item-subcategories']) && $settings['web-updater-item-subcategories'] != 'hidden' ) {
        if( isset($settings['web-updater-item-subcategories-list']) && $settings['web-updater-item-subcategories-list'] != '' ) {
            $subcats = array();
            $pieces = str_getcsv($settings['web-updater-item-subcategories-list']);
            foreach($pieces as $p) {
                if( trim($p) != '' ) {
                    $subcats[] = array('id'=>trim($p), 'name'=>trim($p));
                }
            }
            $fields['subcategories'] = array(
                'id' => 'subcategories',
                'label' => 'Subcategory',
                'ftype' => 'select',
                'size' => 'medium',
                'options' => $subcats,
                'required' => $settings['web-updater-item-subcategories'] == 'required' ? 'yes' : 'no',
                'value' => isset($item['requested_changes']['subcategories']) ? $item['requested_changes']['subcategories'] : $item['subcategories'],
                );
        } else {
            // FIXME: Add select to get distinct sub categories from items in ciniki.ags
        }
    }
    if( isset($settings['web-updater-item-medium']) && $settings['web-updater-item-medium'] != 'hidden' ) {
        if( isset($settings['web-updater-item-medium-list']) && $settings['web-updater-item-medium-list'] != '' ) {
            $mediums = array();
            $pieces = str_getcsv($settings['web-updater-item-medium-list']);
            foreach($pieces as $p) {
                if( trim($p) != '' ) {
                    $mediums[] = array('id'=>trim($p), 'name'=>trim($p));
                }
            }
            $fields['medium'] = array(
                'id' => 'medium',
                'label' => 'Medium',
                'ftype' => 'select',
                'size' => 'medium',
                'options' => $mediums,
                'required' => $settings['web-updater-item-medium'] == 'required' ? 'yes' : 'no',
                'value' => isset($item['requested_changes']['medium']) ? $item['requested_changes']['medium'] : $item['medium'],
                );
        } else {
            $fields['medium'] = array(
                'id' => 'medium',
                'label' => 'Medium',
                'ftype' => 'text',
                'size' => 'medium',
                'required' => $settings['web-updater-item-medium'] == 'required' ? 'yes' : 'no',
                'value' => isset($item['requested_changes']['medium']) ? $item['requested_changes']['medium'] : $item['medium'],
                );
        }
    }
    if( isset($settings['web-updater-item-creation_year']) && $settings['web-updater-item-creation_year'] != 'hidden' ) {
        $fields['creation_year'] = array(
            'id' => 'creation_year',
            'label' => 'Year Created',
            'ftype' => 'text',
            'size' => 'small',
            'required' => $settings['web-updater-item-creation_year'] == 'required' ? 'yes' : 'no',
            'value' => isset($item['requested_changes']['creation_year']) ? $item['requested_changes']['creation_year'] : $item['creation_year'],
            );
    }
    if( isset($settings['web-updater-item-size']) && $settings['web-updater-item-size'] != 'hidden' ) {
        $fields['size'] = array(
            'id' => 'size',
            'label' => 'Size',
            'ftype' => 'text',
            'size' => 'small',
            'required' => $settings['web-updater-item-size'] == 'required' ? 'yes' : 'no',
            'value' => isset($item['requested_changes']['size']) ? $item['requested_changes']['size'] : $item['size'],
            );
    }
    if( isset($settings['web-updater-item-framed_size']) && $settings['web-updater-item-framed_size'] != 'hidden' ) {
        $fields['framed_size'] = array(
            'id' => 'framed_size',
            'label' => 'Framed Size',
            'ftype' => 'text',
            'size' => 'small',
            'required' => $settings['web-updater-item-framed_size'] == 'required' ? 'yes' : 'no',
            'value' => isset($item['requested_changes']['framed_size']) ? $item['requested_changes']['framed_size'] : $item['framed_size'],
            );
    }
    if( isset($settings['web-updater-item-current_condition']) && $settings['web-updater-item-current_condition'] != 'hidden' ) {
        $fields['current_condition'] = array(
            'id' => 'current_condition',
            'label' => 'Current Condition',
            'ftype' => 'text',
            'size' => 'medium',
            'required' => $settings['web-updater-item-current_condition'] == 'required' ? 'yes' : 'no',
            'value' => isset($item['requested_changes']['current_condition']) ? $item['requested_changes']['current_condition'] : $item['current_condition'],
            );
    }
    if( isset($exhibit_id) && $exhibit_id > 0 ) {
        $item['new_inventory'] = $item['inventory'];
        if( isset($item['pending_inventory']) ) {
            $item['new_inventory'] = $item['inventory'] + $item['pending_inventory'];
        }
        $fields['new_inventory'] = array(
            'id' => 'new_inventory',
            'label' => 'Inventory',
            'ftype' => 'text',
            'size' => 'small',
            'required' => 'yes', //$settings['web-updater-item-current_condition'] == 'required' ? 'yes' : 'no',
            'value' => $item['new_inventory'],
            );
    }
    if( isset($settings['web-updater-item-synopsis']) && $settings['web-updater-item-synopsis'] != 'hidden' ) {
        $fields['synopsis'] = array(
            'id' => 'synopsis',
            'label' => 'Short 2 Sentence Description',
            'ftype' => 'textarea',
            'size' => 'small',
            'required' => $settings['web-updater-item-synopsis'] == 'required' ? 'yes' : 'no',
            'value' => isset($item['requested_changes']['synopsis']) ? $item['requested_changes']['synopsis'] : $item['synopsis'],
            );
    }
    if( isset($settings['web-updater-item-description']) && $settings['web-updater-item-description'] != 'hidden' ) {
        $fields['description'] = array(
            'id' => 'description',
            'label' => 'Full Description',
            'ftype' => 'textarea',
            'size' => 'medium',
            'required' => $settings['web-updater-item-description'] == 'required' ? 'yes' : 'no',
            'value' => isset($item['requested_changes']['description']) ? $item['requested_changes']['description'] : $item['description'],
            );
    }

    //
    // Display the form to add/edit the form
    //
    $blocks[] = array(
        'type' => 'form',
        'form-id' => 'itemedit',
        'guidelines' => isset($settings['web-updater-item-form-intro']) ? $settings['web-updater-item-form-intro'] : '',
        'title' => $item['id'] > 0 ? 'Update Item' : 'Add Item',
        'class' => 'limit-width limit-width-60',
        'problem-list' => '',
        'cancel-label' => 'Cancel',
        'js-cancel' => 'itemCancel();',
        'submit-label' => 'Save',
        'js-submit' => 'itemSave();',
        'fields' => $fields,
        'js' => ''
            . "function itemCancel() {"
                . "window.location.href = '{$request['return-url']}';"
            . "};"
            . "function itemSave() {"
                . "var f=C.gE('itemedit');"
                . "var fdata = new FormData(f);"
                . "C.postFDBg('{$request['api_url']}/ciniki/ags/itemSave',null,fdata,itemSaved);"
            . "};"
            . "function itemSaved(rsp) {"
                . "var rc=eval(rsp);"
                . "if(rc.stat!=null&&rc.stat=='ok'){"
                    . "window.location.href = '{$request['return-url']}';"
                . "}else if(rc.stat!=null&&rc.stat!='ok'&&rc.err!=null&&rc.err.msg!=null){"
                   . "C.gE('form-errors-msg').innerHTML = '<p>'+rc.err.msg.replace(/\\n/g,'<br/>')+'</p>';"
                   . "C.aC(C.gE('form-errors'), 'error');"
                   . "C.rC(C.gE('form-errors'), 'hidden');"
                   . "window.scrollTo(0,0);"
                . "}else{"
                   . "C.gE('form-errors-msg').innerHTML = '<p>Error saving item, please try again or contact us for help.</p>';" 
                   . "C.aC(C.gE('form-errors'), 'error');"
                   . "C.rC(C.gE('form-errors'), 'hidden');"
                   . "window.scrollTo(0,0);"
                . "}"
            . "};"
            . '',
        );

    return array('stat'=>'ok', 'blocks'=>$blocks);
}
?>
