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
        $strsql = "SELECT exhibits.id, exhibits.name, exhibits.permalink, exhibits.flags, "
            . "DATE_FORMAT(exhibits.start_date, '%a %b %e, %Y') AS start_date, "
            . "DATE_FORMAT(exhibits.end_date, '%a %b %e, %Y') AS end_date, "
            . "DATE_FORMAT(exhibits.start_date, '%M') AS start_month, "
            . "DATE_FORMAT(exhibits.start_date, '%D') AS start_day, "
            . "DATE_FORMAT(exhibits.start_date, '%Y') AS start_year, "
            . "IF(exhibits.end_date = '0000-00-00', '', DATE_FORMAT(exhibits.end_date, '%M')) AS end_month, "
            . "IF(exhibits.end_date = '0000-00-00', '', DATE_FORMAT(exhibits.end_date, '%D')) AS end_day, "
            . "IF(exhibits.end_date = '0000-00-00', '', DATE_FORMAT(exhibits.end_date, '%Y')) AS end_year, "
            . "exhibits.primary_image_id, exhibits.synopsis, exhibits.description, "
            . "tags.permalink AS type_permalink "
            . "FROM ciniki_ags_exhibits AS exhibits "
            . "LEFT JOIN ciniki_ags_exhibit_tags AS tags ON ("
                . "exhibits.id = tags.exhibit_id "
                . "AND tags.tag_type = 20 "
                . ") "
            . "WHERE exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND exhibits.id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibit');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.49', 'msg'=>'Object not found'));
        }
        if( !isset($rc['exhibit']) ) {
            return array('stat'=>'noexist', 'err'=>array('code'=>'ciniki.ags.50', 'msg'=>'Object not found'));
        }
        $exhibit = $rc['exhibit'];

        //
        // Lookup the base_url for this module
        //
        $exhibit_base_url = '';
        if( $exhibit['type_permalink'] != '' ) {
            $rc = ciniki_web_indexModuleBaseURL($ciniki, $tnid, 'ciniki.ags.' . $exhibit['type_permalink']);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.210', 'msg'=>'Unable to get exhibit base URL', 'err'=>$rc['err']));
            }
            $exhibit_base_url = (isset($rc['base_url']) ? $rc['base_url'] : '');
        }
        if( $exhibit_base_url == '' ) {
            $rc = ciniki_web_indexModuleBaseURL($ciniki, $tnid, 'ciniki.ags.exhibits');
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.209', 'msg'=>'Unable to get exhibit base URL', 'err'=>$rc['err']));
            }
            $exhibit_base_url = (isset($rc['base_url']) ? $rc['base_url'] : '');
        }
        if( $exhibit_base_url != '' ) {
            $base_url = $exhibit_base_url;
        }

        //
        // Check if exhibit is visible on website
        //
        if( ($exhibit['flags']&0x01) != 0x01 ) {
            return array('stat'=>'ok');
        }

        //
        // Process date range
        //
        $meta = '';
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'processDateRange');
        $rc = ciniki_core_processDateRange($ciniki, $exhibit);
        if( $rc['stat'] == 'ok' ) {
            $meta = $rc['dates'];
        }
        $object = array(
            'label'=>'Exhibits',
            'title'=>$exhibit['name'],
            'subtitle'=>'',
            'meta'=>$meta,
            'primary_image_id'=>$exhibit['primary_image_id'],
            'synopsis'=>$exhibit['synopsis'],
            'object'=>'ciniki.ags.exhibit',
            'object_id'=>$exhibit['id'],
            'primary_words'=>$exhibit['name'],
            'secondary_words'=>$exhibit['synopsis'],
            'tertiary_words'=>$exhibit['description'],
            'weight'=>20000,
            'url'=>$base_url 
                . '/' . $exhibit['permalink']
            );
        return array('stat'=>'ok', 'object'=>$object);
    }

    //
    // Lookup item
    //
    if( $args['object'] == 'ciniki.ags.item' ) {
        $strsql = "SELECT items.id, "
            . "items.exhibitor_id, "
            . "items.exhibitor_code, "
            . "items.code, "
            . "items.name, "
            . "items.permalink, "
            . "items.status, "
            . "items.flags, "
            . "items.unit_amount, "
            . "items.unit_discount_amount, "
            . "items.unit_discount_percentage, "
            . "items.fee_percent, "
            . "items.taxtype_id, "
            . "items.primary_image_id, "
            . "items.synopsis, "
            . "items.description, "
            . "items.tag_info, "
            . "items.creation_year, "
            . "items.medium, "
            . "items.size, "
            . "items.current_condition, "
            . "exhibitors.display_name, "
            . "exhibitors.flags AS exhibitor_flags, "
            . "exhibits.name AS exhibit_name, "
            . "exhibits.permalink AS exhibit_permalink, "
            . "tags.tag_name AS exhibit_type_name, "
            . "tags.permalink AS exhibit_type_permalink "
            . "FROM ciniki_ags_items AS items "
            . "LEFT JOIN ciniki_ags_exhibitors AS exhibitors ON ( "
                . "items.exhibitor_id = exhibitors.id "
                . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "LEFT JOIN ciniki_ags_exhibit_items AS eitems ON ( "
                . "items.id = eitems.item_id "
                . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "LEFT JOIN ciniki_ags_exhibits AS exhibits ON ( "
                . "eitems.exhibit_id = exhibits.id "
                . "AND (exhibits.flags&0x01) = 0x01 "
                . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "LEFT JOIN ciniki_ags_exhibit_tags AS tags ON ( "
                . "exhibits.id = tags.exhibit_id "
                . "AND tags.tag_type = 20 "
                . "AND tags.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND items.id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' "
            . "LIMIT 1 "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.214', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
        }
        if( !isset($rc['item']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.215', 'msg'=>'Unable to find requested item'));
        }
        $item = $rc['item'];
        
        //
        // Lookup the base_url for this module
        //
        $exhibit_base_url = '';
        if( $item['exhibit_type_permalink'] != '' ) {
            $rc = ciniki_web_indexModuleBaseURL($ciniki, $tnid, 'ciniki.ags.' . $item['exhibit_type_permalink']);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.210', 'msg'=>'Unable to get exhibit base URL', 'err'=>$rc['err']));
            }
            $exhibit_base_url = (isset($rc['base_url']) ? $rc['base_url'] : '');
        }
        if( $exhibit_base_url == '' ) {
            $rc = ciniki_web_indexModuleBaseURL($ciniki, $tnid, 'ciniki.ags.exhibits');
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.209', 'msg'=>'Unable to get exhibit base URL', 'err'=>$rc['err']));
            }
            $exhibit_base_url = (isset($rc['base_url']) ? $rc['base_url'] : '');
        }
        if( $exhibit_base_url != '' ) {
            $base_url = $exhibit_base_url;
        }

        //
        // Check if item is visible on website
        //
        if( ($item['flags']&0x02) != 0x02 ) {
            return array('stat'=>'ok');
        }

        //
        // Process date range
        //
        $meta = isset($item['display_name']) ? $item['display_name'] : '';
        $object = array(
            'label'=>($item['exhibit_type_name'] != '' ? $item['exhibit_type_name'] : 'Exhibits'),
            'title'=>$item['name'],
            'subtitle'=>'',
            'meta'=>$meta,
            'primary_image_id'=>$item['primary_image_id'],
            'synopsis'=>$item['synopsis'],
            'object'=>'ciniki.ags.item',
            'object_id'=>$item['id'],
            'primary_words'=>$item['name'],
            'secondary_words'=>$item['synopsis'],
            'tertiary_words'=>$item['description'],
            'weight'=>20000,
            'url'=>$base_url 
                . '/' . $item['exhibit_permalink'] . '/item/' . $item['permalink']
            );
        return array('stat'=>'ok', 'object'=>$object);
    }

    return array('stat'=>'ok');
}
?>
