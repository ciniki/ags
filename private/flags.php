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
//        array('flag'=>array('bit'=>'2', 'name'=>'')),
//        array('flag'=>array('bit'=>'3', 'name'=>'')),
//        array('flag'=>array('bit'=>'4', 'name'=>'')),
        // 0x10
        array('flag'=>array('bit'=>'5', 'name'=>'Barcodes')),
        array('flag'=>array('bit'=>'6', 'name'=>'Location Categories')),
        array('flag'=>array('bit'=>'7', 'name'=>'Participant Message')),    // Sales message auto added to each item
        array('flag'=>array('bit'=>'8', 'name'=>'Participant Bios')),
        // 0x0100
//        array('flag'=>array('bit'=>'9', 'name'=>'')),
//        array('flag'=>array('bit'=>'10', 'name'=>'')),
//        array('flag'=>array('bit'=>'11', 'name'=>'')),
//        array('flag'=>array('bit'=>'12', 'name'=>'')),
        // 0x1000
//        array('flag'=>array('bit'=>'13', 'name'=>'')),
//        array('flag'=>array('bit'=>'14', 'name'=>'')),
//        array('flag'=>array('bit'=>'15', 'name'=>'')),
//        array('flag'=>array('bit'=>'16', 'name'=>'')),
        );
    //
    return array('stat'=>'ok', 'flags'=>$flags);
}
?>
