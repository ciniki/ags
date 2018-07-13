<?php
//
// Description
// -----------
// This function returns the index details for an object
//
// Arguments
// ---------
// ciniki:
// tnid:     The ID of the tenant to get ags for.
//
// Returns
// -------
//
function ciniki_ags_hooks_webIndexObject($ciniki, $tnid, $args) {

    if( !isset($args['object']) || $args['object'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.47', 'msg'=>'No object specified'));
    }

    if( !isset($args['object_id']) || $args['object_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.48', 'msg'=>'No object ID specified'));
    }

    //
    // Setup the base_url for use in index
    //
    if( isset($args['base_url']) ) {
        $base_url = $args['base_url'];
    } else {
        $base_url = '/exhibits';
    }

    if( $args['object'] == 'ciniki.ags.exhibit' ) {
/*        //
        // Get the category for the artist
        //
        if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.ags', 0x10) ) {
            $strsql = "SELECT tag_type, permalink "
                . "FROM ciniki_event_tags "
                . "WHERE event_id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' "
                . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . "AND tag_type = 10 "
                . "LIMIT 1 "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            if( isset($rc['item']['permalink']) ) {
                $category_permalink = $rc['item']['permalink'];
            }
        } */

        $strsql = "SELECT id, name, permalink, flags, "
            . "DATE_FORMAT(start_date, '%a %b %e, %Y') AS start_date, "
            . "DATE_FORMAT(end_date, '%a %b %e, %Y') AS end_date, "
            . "DATE_FORMAT(start_date, '%M') AS start_month, "
            . "DATE_FORMAT(start_date, '%D') AS start_day, "
            . "DATE_FORMAT(start_date, '%Y') AS start_year, "
            . "IF(end_date = '0000-00-00', '', DATE_FORMAT(end_date, '%M')) AS end_month, "
            . "IF(end_date = '0000-00-00', '', DATE_FORMAT(end_date, '%D')) AS end_day, "
            . "IF(end_date = '0000-00-00', '', DATE_FORMAT(end_date, '%Y')) AS end_year, "
            . "primary_image_id, synopsis, description "
            . "FROM ciniki_ags_exhibits "
            . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.49', 'msg'=>'Object not found'));
        }
        if( !isset($rc['item']) ) {
            return array('stat'=>'noexist', 'err'=>array('code'=>'ciniki.ags.50', 'msg'=>'Object not found'));
        }
        $item = $rc['item'];

        //
        // Check if item is visible on website
        //
        if( ($item['flags']&0x01) != 0x01 ) {
            return array('stat'=>'ok');
        }

        //
        // Process date range
        //
        $meta = '';
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'processDateRange');
        $rc = ciniki_core_processDateRange($ciniki, $item);
        if( $rc['stat'] == 'ok' ) {
            $meta = $rc['dates'];
        }
        $object = array(
            'label'=>'Exhibits',
            'title'=>$item['name'],
            'subtitle'=>'',
            'meta'=>$meta,
            'primary_image_id'=>$item['primary_image_id'],
            'synopsis'=>$item['synopsis'],
            'object'=>'ciniki.ags.exhibit',
            'object_id'=>$item['id'],
            'primary_words'=>$item['name'],
            'secondary_words'=>$item['synopsis'],
            'tertiary_words'=>$item['description'],
            'weight'=>20000,
            'url'=>$base_url 
                . '/' . $item['permalink']
            );
        return array('stat'=>'ok', 'object'=>$object);
    }

    return array('stat'=>'ok');
}
?>
