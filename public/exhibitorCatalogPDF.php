<?php
//
// Description
// -----------
// This method will return the inventory for an exhibitor
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
function ciniki_ags_exhibitorCatalogPDF($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'exhibitor_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Exhibitor'),
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
        $report_title = 'Catalog';
    }
    if( isset($args['exhibitor_id']) && $args['exhibitor_id'] > 0 ) {
        $strsql = "SELECT exhibitors.display_name "
            . "FROM ciniki_ags_exhibitors AS exhibitors "
            . "WHERE exhibitors.id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
            . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibitor');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.145', 'msg'=>'Unable to load exhibitor', 'err'=>$rc['err']));
        }
        if( !isset($rc['exhibitor']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.146', 'msg'=>'Unable to find requested exhibitor'));
        }
        $exhibitor = $rc['exhibitor'];
        $report_title = $exhibitor['display_name'] . ' - Catalog';
    }

    $strsql = "SELECT items.id, "
        . "exhibitors.display_name, "
        . "items.exhibitor_id, "
        . "items.code, "
        . "items.name, "
        . "items.creation_year, "
        . "items.medium, "
        . "items.size, "
        . "items.current_condition, "
        . "items.tag_info, "
        . "items.flags, "
        . "items.flags AS flags_text, "
        . "items.exhibitor_code, "
        . "items.unit_amount, "
        . "items.taxtype_id "
        . "FROM ciniki_ags_items AS items "
        . "INNER JOIN ciniki_ags_exhibitors AS exhibitors ON ("
            . "items.exhibitor_id = exhibitors.id "
            . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND items.exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibitor_id']) . "' "
        . "ORDER by items.code, items.name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibitors', 'fname'=>'exhibitor_id', 'fields'=>array('display_name')),
        array('container'=>'items', 'fname'=>'id', 
            'fields'=>array('code', 'name', 'exhibitor_code', 'creation_year', 'medium', 'size', 'current_condition', 
                'tag_info', 'flags', 'flags_text', 'unit_amount', 'taxtype_id')),
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
            ));
        if( $rc['stat'] != 'ok' ) { 
            return $rc;
        }
        $pdf = $rc['pdf'];
    } else {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'templates', 'catalogReport');
        $rc = ciniki_ags_templates_catalogReport($ciniki, $args['tnid'], array(
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
