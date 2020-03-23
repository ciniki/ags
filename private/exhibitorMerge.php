<?php
//
// Description
// -----------
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_ags_exhibitorMerge(&$ciniki, $tnid, $primary_exhibitor_id, $secondary_exhibitor_id) {

    $updated = 0;

    //
    // Get the list of items from secondary to move to primary
    //
    $strsql = "SELECT id "
        . "FROM ciniki_ags_items "
        . "WHERE exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $secondary_exhibitor_id) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.192', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
    }
    $items = isset($rc['rows']) ? $rc['rows'] : array();
    foreach($items as $item) {
        //
        // Update each item set to new exhibitor
        //
        $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.item', $item['id'], array('exhibitor_id'=>$primary_exhibitor_id), 0x04);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.193', 'msg'=>'Unable to update exhibitor items.', 'err'=>$rc['err']));
        }
        $updated++;
    }

    //
    // Get the list of participants for the primary_exhibitor_id 
    //
    $strsql = "SELECT exhibit_id "
        . "FROM ciniki_ags_participants "
        . "WHERE exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $primary_exhibitor_id) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbQueryList($ciniki, $strsql, 'ciniki.ags', 'pariticipants', 'exhibit_id');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.192', 'msg'=>'Unable to load participants', 'err'=>$rc['err']));
    }
    $primary_participants = isset($rc['participants']) ? $rc['participants'] : array();

    //
    // Get the list of participants for the secondary_exhibitor_id 
    //
    $strsql = "SELECT id, uuid, exhibit_id "
        . "FROM ciniki_ags_participants "
        . "WHERE exhibitor_id = '" . ciniki_core_dbQuote($ciniki, $secondary_exhibitor_id) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.192', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
    }
    $participants = isset($rc['rows']) ? $rc['rows'] : array();
    foreach($participants as $participant) {
        //
        // Check if already part of exhibit
        //
        if( in_array($participant['exhibit_id'], $primary_participants) ) {
            //
            // Remove the participant
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
            $rc = ciniki_core_objectDelete($ciniki, $tnid, 'ciniki.ags.participant', $participant['id'], $participant['uuid']);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.195', 'msg'=>'Unable to merge exhibitor', 'err'=>$rc['err']));
            }
        } 
       
        //
        // Change exhibitor_id of participant
        //
        else {
            $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.participant', $participant['id'], array('exhibitor_id'=>$primary_exhibitor_id), 0x04);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.193', 'msg'=>'Unable to update exhibitor items.', 'err'=>$rc['err']));
            }
            $updated++;
        }
    }

    //
    // Remove the secondary exhibitor
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
    $rc = ciniki_core_objectDelete($ciniki, $tnid, 'ciniki.ags.exhibitor', $secondary_exhibitor_id, null);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.196', 'msg'=>'Unable to merge exhibitor', 'err'=>$rc['err']));
    }

    return array('stat'=>'ok', 'updated'=>$updated);
}
?>

