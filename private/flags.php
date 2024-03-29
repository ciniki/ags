<?php
//
// Description
// -----------
// The module flags
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_ags_flags(&$ciniki) {
    //
    // The flags for the object
    //
    $flags = array(
        // 0x01
        array('flag'=>array('bit'=>'1', 'name'=>'Exhibit Types')),
        array('flag'=>array('bit'=>'2', 'name'=>'Item Types')),
        array('flag'=>array('bit'=>'3', 'name'=>'Item Condition')),
        array('flag'=>array('bit'=>'4', 'name'=>'Web Updater')),
        // 0x10
        array('flag'=>array('bit'=>'5', 'name'=>'Barcodes')),
        array('flag'=>array('bit'=>'6', 'name'=>'Location Categories')),
        array('flag'=>array('bit'=>'7', 'name'=>'Participant Message')),    // Sales message auto added to each item
        array('flag'=>array('bit'=>'8', 'name'=>'Exhibitor Bios')),
        // 0x0100
        array('flag'=>array('bit'=>'9', 'name'=>'Donation Receipts')),   // Personal and in kind sponsor donations
        array('flag'=>array('bit'=>'10', 'name'=>'Exhibitor Profile Names')),
        array('flag'=>array('bit'=>'11', 'name'=>'Name Card Type Images')), // Have different images for each type of exhibit
//        array('flag'=>array('bit'=>'12', 'name'=>'')),
        // 0x1000
//        array('flag'=>array('bit'=>'13', 'name'=>'Item Categories')),
        array('flag'=>array('bit'=>'14', 'name'=>'Item Subcategories')),
        array('flag'=>array('bit'=>'15', 'name'=>'Item Tags')),
//        array('flag'=>array('bit'=>'16', 'name'=>'')),
        );
    //
    return array('stat'=>'ok', 'flags'=>$flags);
}
?>
