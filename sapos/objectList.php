<?php
//
// Description
// ===========
// This method returns the list of objects that can be returned
// as invoice items.
//
// Arguments
// =========
// 
// Returns
// =======
//
function ciniki_ags_sapos_objectList($ciniki, $tnid) {

    $objects = array(
        //
        // this object should only be added to carts
        //
        'ciniki.ags.itemsale' => array(
            'name' => 'Marketplace Sale',
            ),
        'ciniki.ags.exhibititem' => array(
            'name' => 'Marketplace Sale',
            ),
        );

    return array('stat'=>'ok', 'objects'=>$objects);
}
?>
