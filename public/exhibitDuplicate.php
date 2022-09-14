<?php
//
// Description
// -----------
// This method will add a new exhibit for the tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to add the Exhibit to.
//
// Returns
// -------
//
function ciniki_ags_exhibitDuplicate(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'exhibit_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Exhibit'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitDuplicate');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load the tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    //
    // Load the existing exhibit
    //
    $strsql = "SELECT exhibits.id, "
        . "exhibits.name, "
        . "exhibits.permalink, "
        . "exhibits.location_id, "
        . "exhibits.status, "
        . "exhibits.flags, "
        . "exhibits.start_date, "
        . "exhibits.end_date, "
        . "exhibits.reception_info, "
        . "exhibits.primary_image_id, "
        . "exhibits.synopsis, "
        . "exhibits.description "
        . "FROM ciniki_ags_exhibits AS exhibits "
        . "LEFT JOIN ciniki_ags_locations AS locations ON ("
            . "exhibits.location_id = locations.id "
            . "AND locations.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND exhibits.id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'exhibits', 'fname'=>'id', 
            'fields'=>array('name', 'permalink', 'location_id', 'status', 
                'flags', 'start_date', 'end_date', 'reception_info', 'primary_image_id', 'synopsis', 'description',
                ),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.62', 'msg'=>'Exhibit not found', 'err'=>$rc['err']));
    }
    if( !isset($rc['exhibits'][0]) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.63', 'msg'=>'Unable to find Exhibit')); 
    }
    $exhibit = $rc['exhibits'][0];

    //
    // Load the exhibit tags
    //
    $strsql = "SELECT DISTINCT tag_type, tag_name AS lists "
        . "FROM ciniki_ags_exhibit_tags "
        . "WHERE exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "ORDER BY tag_type, tag_name "
        . "";
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'tags', 'fname'=>'tag_type', 'name'=>'tags',
            'fields'=>array('tag_type', 'lists'), 'dlists'=>array('lists'=>'::')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['tags']) ) {
        foreach($rc['tags'] as $tags) {
            if( $tags['tag_type'] == 20 ) {
                $exhibit['types'] = explode('::', $tags['lists']);
            } elseif( $tags['tag_type'] == 40 ) {
                $exhibit['categories'] = explode('::', $tags['lists']);
            }
        }
    }

    //
    // Load the items with any inventory
    //
    $strsql = "SELECT items.item_id, "
        . "items.inventory, "
        . "items.fee_percent "
        . "FROM ciniki_ags_exhibit_items AS items "
        . "WHERE items.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND items.inventory > 0 "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'items', 'fname'=>'item_id', 
            'fields'=>array('item_id', 'inventory', 'fee_percent'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.150', 'msg'=>'Unable to load exhibit inventory', 'err'=>$rc['err']));
    }
    $items = isset($rc['items']) ? $rc['items'] : array();

    //
    // Get the list of participants
    //
    $strsql = "SELECT "
        . "participants.exhibitor_id, "
        . "participants.status, "
        . "participants.flags, "
        . "participants.message, "
        . "participants.notes "
        . "FROM ciniki_ags_participants AS participants "
        . "INNER JOIN ciniki_ags_items AS items ON ("
            . "participants.exhibitor_id = items.exhibitor_id "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "INNER JOIN ciniki_ags_exhibit_items AS eitems ON ("
            . "items.id = eitems.item_id "
            . "AND eitems.inventory > 0 "
            . "AND eitems.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
            . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE participants.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
        . "AND participants.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND participants.status <> 70 "
        . "ORDER BY participants.exhibitor_id "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.ags', array(
        array('container'=>'participants', 'fname'=>'exhibitor_id', 
            'fields'=>array('exhibitor_id', 'status', 'flags', 'message', 'notes'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.11', 'msg'=>'Unable to load participants', 'err'=>$rc['err']));
    }
    $participants = isset($rc['participants']) ? $rc['participants'] : array();

    //
    // Setup the new start date
    //
    $dt = new DateTime('now', new DateTimezone($intl_timezone));
    $exhibit['start_date'] = $dt->format('Y-m-d');

    //
    // Setup permalink
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'makePermalink');
    $permalink_name = $exhibit['name'];
    if( strstr($permalink_name, $dt->format('Y')) === false ) {
        $permalink_name .= '-' . $dt->format('M-d-Y');
    }
    $exhibit['permalink'] = ciniki_core_makePermalink($ciniki, $permalink_name);

    //
    // Make sure the permalink is unique
    //
    $strsql = "SELECT id, name, permalink "
        . "FROM ciniki_ags_exhibits "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND permalink = '" . ciniki_core_dbQuote($ciniki, $exhibit['permalink']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( $rc['num_rows'] > 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.58', 'msg'=>'You already have a exhibit with that name, please choose another.'));
    }

    //
    // Start transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.ags');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Add the exhibit to the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.ags.exhibit', $exhibit, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
        return $rc;
    }
    $exhibit_id = $rc['id'];

    //
    // Update the types
    //
    if( isset($exhibit['types']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'tagsUpdate');
        $rc = ciniki_core_tagsUpdate($ciniki, 'ciniki.ags', 'exhibittag', $args['tnid'],
            'ciniki_ags_exhibit_tags', 'ciniki_ags_history',
            'exhibit_id', $exhibit_id, 20, $exhibit['types']);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return $rc;
        }
    }
    if( isset($exhibit['categories']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'tagsUpdate');
        $rc = ciniki_core_tagsUpdate($ciniki, 'ciniki.ags', 'exhibittag', $args['tnid'],
            'ciniki_ags_exhibit_tags', 'ciniki_ags_history',
            'exhibit_id', $exhibit_id, 40, $exhibit['categories']);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return $rc;
        }
    }
    
    //
    // Add the participants
    //
    foreach($participants as $participant) {
        $participant['exhibit_id'] = $exhibit_id;
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
        $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.ags.participant', $participant, 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return $rc;
        }
    }

    //
    // Add inventory
    //
    foreach($items AS $item) {
        $item['exhibit_id'] = $exhibit_id;
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
        $rc = ciniki_core_objectAdd($ciniki, $args['tnid'], 'ciniki.ags.exhibititem', $item, 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'ciniki.ags');
            return $rc;
        }
    }

    //
    // Stop the old exhibit
    //
    if( $exhibit['end_date'] != '' ) {
        $dt->sub(new DateInterval('P1D'));
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
        $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.ags.exhibit', $args['exhibit_id'], array(
            'end_date' => $dt->format('Y-m-d'),
            'flags' => ($exhibit['flags']&0xFFFE),
            ), 0x04);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.286', 'msg'=>'Unable to update the exhibit', 'err'=>$rc['err']));
        }
    }

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.ags');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $args['tnid'], 'ciniki', 'ags');

    //
    // Update the web index if enabled
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'hookExec');
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'ciniki.ags.exhibit', 'object_id'=>$exhibit_id));

    return array('stat'=>'ok', 'id'=>$exhibit_id);
}
?>
