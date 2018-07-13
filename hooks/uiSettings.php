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
    $rsp = array('stat'=>'ok', 'menu_items'=>array(), 'settings_menu_items'=>array());

    //
    // Check permissions for what menu items should be available
    //
    if( isset($ciniki['tenant']['modules']['ciniki.ags'])
        && (isset($args['permissions']['owners'])
            || isset($args['permissions']['employees'])
            || isset($args['permissions']['resellers'])
            || ($ciniki['session']['user']['perms']&0x01) == 0x01
            )
        ) {
        $menu_item = array(
            'priority'=>5000,
            'label'=>'Exhibitions & Marketplaces',
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
        $rsp['settings_menu_items'][] = array('priority'=>5000, 'label'=>'Art Gallery Sales', 'edit'=>array('app'=>'ciniki.ags.settings'));
    }

    return $rsp;
}
?>
