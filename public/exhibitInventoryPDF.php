<?php
//
// Description
// -----------
// This method will return the list of marketplaces for a tenant.  It is restricted
// to tenant owners and sysadmins.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:     The ID of the tenant to get marketplaces for.
//
// Returns
// -------
//
function ciniki_ags_exhibitInventoryPDF($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'exhibit_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Exhibit'),
        'exhibitor_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Exhibit'),
        'codes'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'list', 'name'=>'Codes'),
        'start_col'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Start Column'),
        'start_row'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Start Row'),
        'template'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Template'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];
    
    //  
    // Check access to tnid as owner, or sys admin. 
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitInventoryPDF');
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

    //
    // Load the tenant intl settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');
    $mysql_date_format = ciniki_users_dateFormat($ciniki, 'mysql');

    if( (!isset($args['exhibit_id']) || $args['exhibit_id'] == 0 || $args['exhibit_id'] == '')
        && (!isset($args['exhibitor_id']) || $args['exhibitor_id'] == 0 || $args['exhibitor_id'] == '') 
        ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.144', 'msg'=>'An exhibit or exhibitor must be specified'));
    }

    //
    // Load tenant details
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'tenantDetails');
    $rc = ciniki_tenants_tenantDetails($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['details']) && is_array($rc['details']) ) {
        $tenant_details = $rc['details'];
    } else {
        $tenant_details = array();
    }

    //
    // Get the exhibit name
    //
    if( isset($args['template']) && $args['template'] == 'riskmanagement' ) {
        $report_title = 'Risk Management';
    } elseif( isset($args['template']) && $args['template'] == 'namecards' ) {
        $report_title = 'Name Cards';
    } else {
        $report_title = 'Inventory';
    }
    if( isset($args['exhibit_id']) && $args['exhibit_id'] > 0 ) {
        $strsql = "SELECT exhibits.name, "
            . "DATE_FORMAT(exhibits.start_date, '%b %e, %Y') AS start_date, "
            . "DATE_FORMAT(exhibits.end_date, '%b %e, %Y') AS end_date, "
            . "locations.name AS location_name "
            . "FROM ciniki_ags_exhibits AS exhibits "
            . "LEFT JOIN ciniki_ags_locations AS locations ON ("
                . "exhibits.location_id = locations.id "
                . "AND locations.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE exhibits.id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
            . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibit');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.145', 'msg'=>'Unable to load exhibit', 'err'=>$rc['err']));
        }
        if( !isset($rc['exhibit']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.146', 'msg'=>'Unable to find requested exhibit'));
        }
        $exhibit = $rc['exhibit'];
        $report_title = $exhibit['name'] . ' - Inventory';
    }

    $strsql = "SELECT eitems.id AS exhibit_item_id, "
        . "exhibitors.display_name, "
        . "items.exhibitor_id, "
        . "items.code, "
        . "items.name, "
        . "items.creation_year, "
        . "items.medium, "
        . "items.size, "
        . "items.framed_size, "
        . "items.current_condition, "
        . "items.tag_info, "
        . "items.flags, "
        . "items.flags AS flags_text, "
        . "items.exhibitor_code, "
        . "items.unit_amount, "
        . "items.taxtype_id, "
        . "eitems.inventory "
        . "FROM ciniki_ags_exhibit_items AS eitems "
        . "INNER JOIN ciniki_ags_items AS items ON ("
            . "eitems.item_id = items.id "
            . (isset($args['exhibitor_id']) && $args['exhibitor_id'] > 0 ? "AND items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' " : '')
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "INNER JOIN ciniki_ags_exhibitors AS exhibitors ON ("
            . "items.exhibitor_id = exhibitors.id "
            . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    if( isset($args['exhibit_id']) && $args['exhibit_id'] > 0 ) {
        $strsql .= "AND eitems.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' ";
    }
    if( isset($args['exhibitor_id']) && $args['exhibitor_id'] > 0 ) {
        $strsql .= "AND items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' ";
    }
    if( isset($args['codes']) && count($args['codes']) > 0 && $args['codes'][0] != "" ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteList');
        $strsql .= "AND items.code IN (" . ciniki_core_dbQuoteList($ciniki, $args['codes']) . ") ";
    }
    $strsql .= "ORDER by exhibitors.display_name, items.code, items.name ";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibitors', 'fname'=>'exhibitor_id', 
            'fields'=>array('display_name')),
        array('container'=>'items', 'fname'=>'exhibit_item_id', 
            'fields'=>array('code', 'name', 'exhibitor_code', 'creation_year', 'medium', 'size', 'framed_size', 'current_condition', 
                'tag_info', 'flags', 'flags_text', 'inventory', 'unit_amount', 'taxtype_id')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.147', 'msg'=>'Unable to load exhibitors', 'err'=>$rc['err']));
    }
    if( isset($rc['exhibitors']) ) {
        $exhibitors = $rc['exhibitors'];
    } else {
        $exhibitors = array();
    }
    
    $today = new DateTime('now', new DateTimezone($intl_timezone));

    if( isset($args['template']) && $args['template'] == 'riskmanagement' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'templates', 'riskManagementReport');
        $rc = ciniki_ags_templates_riskManagementReport($ciniki, $args['tnid'], array(
            'title' => $report_title,
            'start_date' => $exhibit['start_date'],
            'end_date' => $exhibit['end_date'],
            'author' => $tenant_details['name'],
            'location' => $exhibit['location_name'],
            'footer' => $today->format('M d, Y'),
            'exhibitors' => $exhibitors,
            ));
        if( $rc['stat'] != 'ok' ) { 
            return $rc;
        }
        $pdf = $rc['pdf'];
    } elseif( isset($args['template']) && $args['template'] == 'namecards' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'templates', 'nameCards');
        $rc = ciniki_ags_templates_nameCards($ciniki, $args['tnid'], array(
            'title' => $report_title,
            'start_date' => $exhibit['start_date'],
            'end_date' => $exhibit['end_date'],
            'author' => $tenant_details['name'],
            'location' => $exhibit['location_name'],
            'footer' => $today->format('M d, Y'),
            'exhibitors' => $exhibitors,
            'start_col' => (isset($args['start_col']) && $args['start_col'] > 0 ? $args['start_col'] : 1),
            'start_row' => (isset($args['start_row']) && $args['start_row'] > 0 ? $args['start_row'] : 1),
            ));
        if( $rc['stat'] != 'ok' ) { 
            return $rc;
        }
        $pdf = $rc['pdf'];
    } else {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'templates', 'inventoryReport');
        $rc = ciniki_ags_templates_inventoryReport($ciniki, $args['tnid'], array(
            'title' => $report_title,
            'author' => $tenant_details['name'],
            'footer' => $today->format('M d, Y'),
            'exhibitors' => $exhibitors,
            ));
        if( $rc['stat'] != 'ok' ) { 
            return $rc;
        }
        $pdf = $rc['pdf'];
    }

    //
    // Output the pdf
    //
    $filename = $report_title . ' - ' . $today->format('M d, Y');
    $filename = preg_replace('/[^A-Za-z0-9\-]/', '', $filename) . '.pdf';
    $pdf->Output($filename, 'D');

    return array('stat'=>'exit');
}
?>
