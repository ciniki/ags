<?php
//
// Description
// -----------
// This method searchs for a Items for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Item for.
// start_needle:       The search string to search for.
// limit:              The maximum number of entries to return.
//
// Returns
// -------
//
function ciniki_ags_exhibitorItemSearch($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'start_needle'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Search String'),
        'exhibitor_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Exhibitor'),
        'exhibit_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Exhibit'),
        'limit'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Limit'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitorItemSearch');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load maps
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'maps');
    $rc = ciniki_ags_maps($ciniki);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];

    $args['start_needle'] = preg_replace("/ /", '%', $args['start_needle']);
    
    //
    // Get the list of items
    //
    if( isset($args['exhibit_id']) && $args['exhibit_id'] != '' ) {
        $strsql = "SELECT ciniki_ags_items.id, "
            . "ciniki_ags_items.exhibitor_id, "
            . "ciniki_ags_items.exhibitor_code, "
            . "ciniki_ags_items.code, "
            . "ciniki_ags_items.name, "
            . "ciniki_ags_items.tag_info, "
            . "ciniki_ags_items.permalink, "
            . "ciniki_ags_items.status, "
            . "ciniki_ags_items.status AS status_text, "
            . "ciniki_ags_items.flags, "
            . "ciniki_ags_items.flags AS flags_text, "
            . "(ciniki_ags_items.flags&0x06) AS online_flags_text, "
            . "ciniki_ags_items.unit_amount AS unit_amount_display, "
            . "ciniki_ags_items.unit_discount_amount AS unit_discount_amount_display, "
            . "ciniki_ags_items.unit_discount_percentage AS unit_discount_percentage_display, "
            . "ciniki_ags_items.fee_percent AS fee_percent_display, "
            . "ciniki_ags_items.taxtype_id, "
            . "IFNULL(ciniki_ags_exhibit_items.exhibit_id, 0) AS exhibit_id, "
            . "IFNULL(ciniki_ags_exhibit_items.inventory, 0) AS inventory, "
            . "IFNULL(tags.tag_name, '') as categories "
            . "FROM ciniki_ags_items "
            . "LEFT JOIN ciniki_ags_exhibit_items ON ("
                . "ciniki_ags_items.id = ciniki_ags_exhibit_items.item_id "
                . "AND ciniki_ags_exhibit_items.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
                . "AND ciniki_ags_exhibit_items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") " 
            . "LEFT JOIN ciniki_ags_item_tags AS tags ON ("                                
                . "ciniki_ags_exhibit_items.item_id = tags.item_id "                                               
                . "AND tags.tag_type = 20 "                                                
                . "AND tags.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "                                                                     
            . "WHERE ciniki_ags_items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_ags_items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
            . "AND ("
                . "name LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR name LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR name LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR exhibitor_code LIKE '%" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR exhibitor_code LIKE '%0" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR code LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR code LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR code LIKE '%-" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR code LIKE '%-0" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR code LIKE '%00" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . ") "
            . "";
    } else {
        $strsql = "SELECT ciniki_ags_items.id, "
            . "ciniki_ags_items.exhibitor_id, "
            . "ciniki_ags_items.exhibitor_code, "
            . "ciniki_ags_items.code, "
            . "ciniki_ags_items.name, "
            . "ciniki_ags_items.tag_info, "
            . "ciniki_ags_items.permalink, "
            . "ciniki_ags_items.status, "
            . "ciniki_ags_items.status AS status_text, "
            . "ciniki_ags_items.flags, "
            . "ciniki_ags_items.flags AS flags_text, "
            . "(ciniki_ags_items.flags&0x06) AS online_flags_text, "
            . "ciniki_ags_items.unit_amount AS unit_amount_display, "
            . "ciniki_ags_items.unit_discount_amount AS unit_discount_amount_display, "
            . "ciniki_ags_items.unit_discount_percentage AS unit_discount_percentage_display, "
            . "ciniki_ags_items.fee_percent AS fee_percent_display, "
            . "ciniki_ags_items.taxtype_id, "
            . "0 AS exhibit_id, "
            . "0 AS inventory, "
            . "'' AS categories "
            . "FROM ciniki_ags_items "
            . "WHERE ciniki_ags_items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_ags_items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
            . "AND ("
                . "name LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR name LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR name LIKE '%-" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR exhibitor_code LIKE '%" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR exhibitor_code LIKE '%0" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR code LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR code LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR code LIKE '%-" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR code LIKE '%-0" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
                . "OR code LIKE '%00" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . ") "
            . "";
    }
    if( isset($args['limit']) && is_numeric($args['limit']) && $args['limit'] > 0 ) {
        $strsql .= "LIMIT " . ciniki_core_dbQuote($ciniki, $args['limit']) . " ";
    } else {
        $strsql .= "LIMIT 25 ";
    }
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('id', 'exhibitor_id', 'exhibitor_code', 'tag_info', 'code', 'name', 'permalink', 
                'status', 'status_text', 'flags', 'flags_text', 'online_flags_text',
                'unit_amount_display', 'unit_discount_amount_display', 'unit_discount_percentage_display', 
                'fee_percent_display', 'taxtype_id', 'exhibit_id', 'inventory', 'categories'),
            'maps'=>array('status_text'=>$maps['item']['status']),
            'dlists'=>array('categories'=>', '),
            'flags'=>array('flags_text'=>$maps['item']['flags'],
                'online_flags_text'=>$maps['item']['flags'],
                ),
            'naprices'=>array('unit_amount_display', 'unit_discount_amount_display'),
            'percents'=>array('unit_discount_percentage_display', 'fee_percent_display'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['items']) ) {
        $items = $rc['items'];
        $item_ids = array();
        foreach($items as $iid => $item) {
            $item_ids[] = $item['id'];
        }
    } else {
        $items = array();
        $item_ids = array();
    }

    return array('stat'=>'ok', 'items'=>$items, 'nplist'=>$item_ids);
}
?>
