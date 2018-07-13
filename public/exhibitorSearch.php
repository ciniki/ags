<?php
//
// Description
// -----------
// This method searchs for a Exhibitors for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Exhibitor for.
// start_needle:       The search string to search for.
// limit:              The maximum number of entries to return.
//
// Returns
// -------
//
function ciniki_ags_exhibitorSearch($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'start_needle'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Search String'),
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
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitorSearch');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Get the list of exhibitors
    //
    $strsql = "SELECT ciniki_ags_exhibitors.id, "
        . "ciniki_ags_exhibitors.customer_id, "
        . "ciniki_ags_exhibitors.display_name_override, "
        . "ciniki_ags_exhibitors.display_name, "
        . "ciniki_ags_exhibitors.permalink, "
        . "ciniki_ags_exhibitors.code, "
        . "ciniki_ags_exhibitors.status, "
        . "ciniki_ags_exhibitors.flags "
        . "FROM ciniki_ags_exhibitors "
        . "WHERE ciniki_ags_exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND ("
            . "display_name LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR display_name LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR code LIKE '" . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
            . "OR code LIKE '% " . ciniki_core_dbQuote($ciniki, $args['start_needle']) . "%' "
        . ") "
        . "";
    if( isset($args['limit']) && is_numeric($args['limit']) && $args['limit'] > 0 ) {
        $strsql .= "LIMIT " . ciniki_core_dbQuote($ciniki, $args['limit']) . " ";
    } else {
        $strsql .= "LIMIT 25 ";
    }
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibitors', 'fname'=>'id', 
            'fields'=>array('id', 'customer_id', 'display_name_override', 'display_name', 'permalink', 'code', 'status', 'flags')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['exhibitors']) ) {
        $exhibitors = $rc['exhibitors'];
        $exhibitor_ids = array();
        foreach($exhibitors as $iid => $exhibitor) {
            $exhibitor_ids[] = $exhibitor['id'];
        }
    } else {
        $exhibitors = array();
        $exhibitor_ids = array();
    }

    return array('stat'=>'ok', 'exhibitors'=>$exhibitors, 'nplist'=>$exhibitor_ids);
}
?>
