<?php
//
// Description
// -----------
// This function will return the list of permission groups for this module.
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function ciniki_ags_hooks_modPerms(&$ciniki, $tnid, $args) {

    $modperms = array(
        'label' => 'Gallery',
        'perms' => array(
            'ciniki.ags' => 'Full Access',
            ),
        );

    return array('stat'=>'ok', 'modperms'=>$modperms);
}
?>
