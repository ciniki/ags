<?php
//
// Description
// -----------
// This function will return the list of available blocks to the ciniki.reporting module.
//
// Arguments
// ---------
// ciniki:
// tnid:     
// args:            The possible arguments for.
//
//
// Returns
// -------
//
function ciniki_ags_reporting_blocks(&$ciniki, $tnid, $args) {

    //
    // Check to make sure the module is enabled
    //
    if( !isset($ciniki['tenant']['modules']['ciniki.ags']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.360', 'msg'=>"I'm sorry, the block you requested does not exist."));
    }

    $blocks = array();

    //
    // Return the list of blocks for the tenant
    //
    $blocks['ciniki.ags.requestedupdates'] = array(
        'name'=>'Requested Updates',
        'module' => 'Gallery',
        'options'=>array(
            ),
        );

    return array('stat'=>'ok', 'blocks'=>$blocks);
}
?>
