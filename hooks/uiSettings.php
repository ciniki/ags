<?php
//
// Description
// -----------
// This function returns the settings for the module and the main menu items and settings menu items
//
// Arguments
// ---------
// ciniki:
// tnid:
// args: The arguments for the hook
//
// Returns
// -------
//
function ciniki_ags_hooks_uiSettings(&$ciniki, $tnid, $args) {
    //
    // Setup the default response
    //
    $rsp = array('stat'=>'ok', 'settings'=>array(), 'menu_items'=>array(), 'settings_menu_items'=>array());

    //
    // Check if all tags should be returned
    //
    if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.ags', 0x01) ) {
        //
        // Get the available tags
        //
        $strsql = "SELECT DISTINCT permalink, tag_name "
            . "FROM ciniki_ags_exhibit_tags "
            . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND tag_type = 20 "
            . "ORDER BY permalink "
            . "";
        $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.ags', array(
            array('container'=>'tags', 'fname'=>'permalink', 'fields'=>array('name'=>'tag_name')),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.160', 'msg'=>'Unable to load tags', 'err'=>$rc['err']));
        }
        if( isset($rc['tags']) ) {
            $rsp['settings']['etypes'] = $rc['tags'];
        }
    }

    //
    // Check permissions for what menu items should be available
    //
    if( isset($ciniki['tenant']['modules']['ciniki.ags'])
        && (isset($args['permissions']['owners'])
            || isset($args['permissions']['ciniki.ags'])
            || isset($args['permissions']['resellers'])
            || ($ciniki['session']['user']['perms']&0x01) == 0x01
            )
        ) {
        $menu_item = array(
            'priority'=>5000,
            'label'=>'Gallery',
            'edit'=>array('app'=>'ciniki.ags.main'),
            );
        $rsp['menu_items'][] = $menu_item;
    }

    //
    // Check for owner, resellers or sysadmin for settings
    //
    if( isset($ciniki['tenant']['modules']['ciniki.ags']) && isset($ciniki['tenant']['modules']['ciniki.mail']) 
        && (isset($args['permissions']['owners'])
            || isset($args['permissions']['resellers'])
            || ($ciniki['session']['user']['perms']&0x01) == 0x01
            )
        ) {
        $rsp['settings_menu_items'][] = array('priority'=>5000, 'label'=>'Gallery Sales', 'edit'=>array('app'=>'ciniki.ags.settings'));
    }

    return $rsp;
}
?>
