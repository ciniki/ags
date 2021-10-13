<?php
//
// Description
// -----------
// Return the list of available field refs for ciniki.forms module.
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function ciniki_ags_hooks_formFieldRefs(&$ciniki, $tnid, $args) {
    
    $module = 'Art Gallery';
    $refs = array(
        'ciniki.ags.exhibit.name' => array('module'=>$module, 'type'=>'text', 'name'=>'Exhibit Name'),
        'ciniki.ags.exhibit.synopsis' => array('module'=>$module, 'type'=>'textarea', 'name'=>'Exhibit Synopsis'),
        'ciniki.ags.exhibit.description' => array('module'=>$module, 'type'=>'textarea', 'name'=>'Exhibit Description'),
        'ciniki.ags.item.primary_image_id' => array('module'=>$module, 'type'=>'image', 'name'=>'Item Image'),
        'ciniki.ags.item.name' => array('module'=>$module, 'type'=>'text', 'name'=>'Item Name'),
        'ciniki.ags.item.flags.1' => array('module'=>$module, 'type'=>'checkbox', 'name'=>'For Sale'),
        'ciniki.ags.item.unit_amount' => array('module'=>$module, 'type'=>'price', 'name'=>'Item Price'),
        'ciniki.ags.item.description' => array('module'=>$module, 'type'=>'textarea', 'name'=>'Item Description'),
        'ciniki.ags.item.creation_year' => array('module'=>$module, 'type'=>'text', 'name'=>'Item Year Created'),
        'ciniki.ags.item.medium' => array('module'=>$module, 'type'=>'text', 'name'=>'Item Medium'),
        'ciniki.ags.item.size' => array('module'=>$module, 'type'=>'text', 'name'=>'Item Size'),
        'ciniki.ags.item.current_condition' => array('module'=>$module, 'type'=>'text', 'name'=>'Item Current Condition'),
        'ciniki.ags.item.notes' => array('module'=>$module, 'type'=>'text', 'name'=>'Item Notes'),
        );

    return array('stat'=>'ok', 'refs'=>$refs);
}
?>
