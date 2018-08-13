<?php
//
// Description
// -----------
// This function returns the int to text mappings for the module.
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_ags_maps(&$ciniki) {
    //
    // Build the maps object
    //
    $maps = array();
    $maps['item'] = array(
        'status'=>array(
            '50'=>'Active',
            '70'=>'Sold',
            '90'=>'Archived',
        ),
        'flags'=>array(
            0x01=>'For Sale',
            0x02=>'Online',
            0x10=>'Tagged',
        ),
    );
    $maps['participant'] = array('status'=>array(
        '0'=>'Unknown',
        '30'=>'Applied',
        '50'=>'Accepted',
        '90'=>'Rejected',
    ));
    $maps['exhibit'] = array('status'=>array(
        '50'=>'Active',
        '90'=>'Archived',
    ));


    //
    return array('stat'=>'ok', 'maps'=>$maps);
}
?>
