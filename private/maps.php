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
            '30'=>'Pending',
            '50'=>'Active',
            '70'=>'Sold',
            '90'=>'Archived',
        ),
        'flags'=>array(
            0x01=>'For Sale',
            0x02=>'Visible',
            0x04=>'Sold Online',
            0x10=>'Tagged',
            0x20=>'Donated',
        ),
    );
    $maps['participant'] = array(
        'status'=>array(
            '0'=>'Unknown',
            '30'=>'Applied',
            '50'=>'Accepted',
            '70'=>'Inactive',
            '90'=>'Rejected',
        ),
    );
    $maps['exhibit'] = array(
        'status'=>array(
            '50'=>'Active',
            '90'=>'Archived',
        ),
    );
    $maps['exhibititem'] = array(
        'status'=>array(
            '30'=>'Pending',
            '50'=>'Active',
        ),
    );
    $maps['itemlog'] = array(
        'action'=>array(
            '10'=>'Add',
            '50'=>'Update',
            '90'=>'Update',
        ),
    );


    //
    return array('stat'=>'ok', 'maps'=>$maps);
}
?>
