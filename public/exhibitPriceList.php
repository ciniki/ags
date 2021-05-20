<?php
//
// Description
// ===========
// This method will return all the information about an exhibition.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:     The ID of the tenant the seller is attached to.
// exhibit_id:       The ID of the exhibition to get the details for.
// 
// Returns
// -------
//
function ciniki_ags_exhibitPriceList($ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'exhibit_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Exhibit'),
        'participant_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Seller'),
        'output'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Format'),
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];
    
    //  
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitPriceList'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $modules = $rc['modules'];

    //
    // Load the tenant intl settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki);

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
    $strsql = "SELECT name "
        . "FROM ciniki_ags_exhibits "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibition');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['exhibition']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.22', 'msg'=>'Exhibition does not exist'));
    }
    $exhibit_name = $rc['exhibition']['name'];

    //
    // Get the list of exhibition items
    //
    $strsql = "SELECT ciniki_ags_exhibit_items.id, "
        . "items.id AS item_id, "
        . "items.code, "
        . "IFNULL(ciniki_ags_exhibitors.display_name, '') AS display_name, "
        . "items.name, "
        . "items.flags, "
        . "items.unit_amount, "
        . "items.taxtype_id "
        . "FROM ciniki_ags_exhibit_items "
        . "INNER JOIN ciniki_ags_items AS items ON ("
            . "ciniki_ags_exhibit_items.item_id = items.id "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "LEFT JOIN ciniki_ags_exhibitors ON ("
            . "items.exhibitor_id = ciniki_ags_exhibitors.id "
            . "AND ciniki_ags_exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE ciniki_ags_exhibit_items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND ciniki_ags_exhibit_items.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
        . "";
    if( isset($args['customer_id']) && $args['customer_id'] != '' ) {
        $strsql .= "AND ciniki_ags_exhibition_items.customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' ";
    }
    $strsql .= "ORDER BY code, name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'items', 'fname'=>'id',
            'fields'=>array('id', 'display_name', 'code', 'name', 'flags', 'unit_amount', 'taxtype_id')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['items']) ) {
        $items = array();   
    } else {
        $items = $rc['items'];
    }

    $today = new DateTime('now', new DateTimeZone($intl_timezone));

    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'templates', 'pricelist');
    $rc = ciniki_ags_templates_pricelist($ciniki, $args['tnid'], array(
        'title'=>$exhibit_name . ' - Price List',
        'author'=>$tenant_details['name'],
        'footer'=>$today->format('M d, Y'),
        'items'=>$items,
        ));
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }
    $pdf = $rc['pdf'];

    //
    // Output the pdf
    //
    $filename = $exhibit_name . ' - Price List - ' . $today->format('M d, Y');
    $filename = preg_replace('/[^A-Za-z0-9\-]/', '', $filename) . '.pdf';
    $pdf->Output($filename, 'D');

    return array('stat'=>'exit');
}
?>
