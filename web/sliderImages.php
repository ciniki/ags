<?php
//
// Description
// -----------
// This funciton will return a list of the random added items in the art catalog. 
// These are used on the homepage of the tenant website.
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// tnid:     The ID of the tenant to get images for.
// limit:           The maximum number of images to return.
//
// Returns
// -------
// <images>
//      [title="Slow River" permalink="slow-river" image_id="431" 
//          caption="Based on a photograph taken near Slow River, Ontario, Pastel, size: 8x10" sold="yes"
//          last_updated="1342653769"],
//      [title="Open Field" permalink="open-field" image_id="217" 
//          caption="An open field in Ontario, Oil, size: 8x10" sold="yes"
//          last_updated="1342653769"],
//      ...
// </images>
//
function ciniki_ags_web_sliderImages($ciniki, $settings, $tnid, $list, $limit) {


    if( $list == 'random' ) {
        $strsql = "SELECT items.id, "
            . "items.primary_image_id AS image_id, "
            . "items.name AS title, "
            . "exhibitors.display_name, "
            . "items.permalink AS item_permalink, "
            . "exhibits.permalink AS exhibit_permalink, "
            . "IF(images.last_updated > eitems.last_updated, UNIX_TIMESTAMP(images.last_updated), UNIX_TIMESTAMP(eitems.last_updated)) AS last_updated, "
            . "IFNULL(tags.permalink, '') AS tag_permalink "
            . "FROM ciniki_ags_exhibits AS exhibits "
            . "INNER JOIN ciniki_ags_exhibit_items AS eitems ON ("
                . "exhibits.id = eitems.exhibit_id "
                . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "INNER JOIN ciniki_ags_items AS items ON ("
                . "eitems.item_id = items.id "
                . "AND (items.flags&0x02) = 0x02 "  // Visible online
                . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "INNER JOIN ciniki_images AS images ON ("
                . "items.primary_image_id = images.id "
                . "AND images.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "INNER JOIN ciniki_ags_exhibitors AS exhibitors ON ("
                . "items.exhibitor_id = exhibitors.id "
                . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "LEFT JOIN ciniki_ags_exhibit_tags AS tags ON ( "
                . "exhibits.id = tags.exhibit_id "
                . "AND tags.tag_type = 20 "
                . "AND tags.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE exhibits.status = 50 "
            . "AND (exhibits.flags&0x05) = 0x05 "   // Visible on website AND Show Items
            . "AND (exhibits.end_date = '0000-00-00' OR exhibits.end_date > NOW()) "
            . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
        if( $limit != '' && $limit > 0 && is_int($limit) ) {
            $strsql .= "ORDER BY RAND() "
                . "LIMIT " . intval($limit) . " ";
        } else {
            $strsql .= "ORDER BY RAND() "
                . "LIMIT 15 ";
        }
    } else {
        $strsql = "SELECT items.id, "
            . "items.primary_image_id AS image_id, "
            . "items.name AS title, "
            . "exhibitors.display_name, "
            . "items.permalink AS item_permalink, "
            . "exhibits.permalink AS exhibit_permalink, "
            . "IF(images.last_updated > eitems.last_updated, UNIX_TIMESTAMP(images.last_updated), UNIX_TIMESTAMP(eitems.last_updated)) AS last_updated, "
            . "IFNULL(tags.permalink, '') AS tag_permalink "
            . "FROM ciniki_ags_exhibits AS exhibits "
            . "INNER JOIN ciniki_ags_exhibit_items AS eitems ON ("
                . "exhibits.id = eitems.exhibit_id "
                . "AND eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "INNER JOIN ciniki_ags_items AS items ON ("
                . "eitems.item_id = items.id "
                . "AND (items.flags&0x02) = 0x02 "  // Visible online
                . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "INNER JOIN ciniki_images AS images ON ("
                . "items.primary_image_id = images.id "
                . "AND images.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "INNER JOIN ciniki_ags_exhibitors AS exhibitors ON ("
                . "items.exhibitor_id = exhibitors.id "
                . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "LEFT JOIN ciniki_ags_exhibit_tags AS tags ON ( "
                . "exhibits.id = tags.exhibit_id "
                . "AND tags.tag_type = 20 "
                . "AND tags.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE exhibits.status = 50 "
            . "AND (exhibits.flags&0x05) = 0x05 "   // Visible on website AND Show Items
            . "AND (exhibits.end_date = '0000-00-00' OR exhibits.end_date > NOW()) "
            . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "ORDER BY eitems.date_added DESC "
            . "";
        if( $limit != '' && $limit > 0 && is_int($limit) ) {
            $strsql .= "LIMIT " . intval($limit) . " ";
        } else {
            $strsql .= "LIMIT 15";
        }
    }
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.customers', array(
        array('container'=>'images', 'fname'=>'id',
            'fields'=>array('id', 'image_id', 'title', 'display_name', 'item_permalink', 'exhibit_permalink', 'last_updated')),
        array('container'=>'tags', 'fname'=>'id',
            'fields'=>array('tag_permalink')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $images = isset($rc['images']) ? $rc['images'] : array();
    ciniki_core_loadMethod($ciniki, 'ciniki', 'web', 'private', 'indexObjectBaseURL');
    $base_urls = array();
    foreach($images as $iid => $image) {
        $base_url = '';
        if( isset($image['tags']) ) {
            foreach($image['tags'] as $tag) {
                if( isset($base_urls['ciniki.ags.' . $tag['tag_permalink']]) ) {
                    $base_url = $base_urls['ciniki.ags.' . $tag['tag_permalink']];
                    break;
                } else {
                    $rc = ciniki_web_indexObjectBaseURL($ciniki, $tnid, 'ciniki.ags.' . $tag['tag_permalink']);
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.223', 'msg'=>'Unable to find base url', 'err'=>$rc['err']));
                    }
                    if( isset($rc['base_url']) ) {
                        $base_url = $rc['base_url'];
                        $base_urls['ciniki.ags.' . $tag['tag_permalink']] = $base_url;
                        break;
                    }
                }
            }
        }
        if( $base_url == '' ) {
            if( isset($base_urls['ciniki.ags']) ) {

            } else {
                $rc = ciniki_web_indexObjectBaseURL($ciniki, $tnid, 'ciniki.ags');
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.224', 'msg'=>'Unable to find base url', 'err'=>$rc['err']));
                }
                if( isset($rc['base_url']) ) {
                    $base_url = $rc['base_url'];
                    $base_urls['ciniki.ags'] = $base_url;
                }
            }
        }
        $images[$iid]['url'] = $ciniki['request']['base_url'] . $base_url . '/' . $image['exhibit_permalink'] . '/item/' . $image['item_permalink'];
    }

    return array('stat'=>'ok', 'images'=>$images);
}
?>
