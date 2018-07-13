<?php
//
// Description
// -----------
// This function will return the list of options for the module that can be set for the website.
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// tnid:            The ID of the tenant to get ags for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_ags_hooks_webOptions(&$ciniki, $tnid, $args) {

    //
    // Check to make sure the module is enabled
    //
    if( !isset($ciniki['tenant']['modules']['ciniki.ags']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.38', 'msg'=>"I'm sorry, the page you requested does not exist."));
    }

    //
    // Get the settings from the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
    $rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_web_settings', 'tnid', $tnid, 'ciniki.web', 'settings', 'page-ags');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['settings']) ) {
        $settings = array();
    } else {
        $settings = $rc['settings'];
    }

    //
    // Get the list of Types
    //
    $options[] = array(
        'label'=>'Include Past Exhibits',
        'setting'=>'page-ags-past', 
        'type'=>'toggle',
        'value'=>(isset($settings['page-ags-past'])?$settings['page-ags-past']:'no'),
        'toggles'=>array(
            array('value'=>'no', 'label'=>'No'),
            array('value'=>'yes', 'label'=>'Yes'),
            ),
        );
    $options[] = array(
        'label'=>'Initial Exhibits/page',
        'setting'=>'page-ags-initial-number', 
        'type'=>'text',
        'value'=>(isset($settings['page-ags-initial-number'])?$settings['page-ags-initial-number']:'10'),
        );
    $options[] = array(
        'label'=>'Archive Exhibits/page',
        'setting'=>'page-ags-archive-number', 
        'type'=>'text',
        'value'=>(isset($settings['page-ags-archive-number'])?$settings['page-ags-archive-number']:'10'),
        );

    $pages['ciniki.ags'] = array('name'=>'Exhibits', 'options'=>$options);

    //
    // For specific pages, no options are required currently
    //
    $options = array();
    if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.ags', 0x01) ) {
        $strsql = "SELECT types.tag_name, "
            . "types.permalink "
            . "FROM ciniki_ags_exhibit_tags AS types "
            . "INNER JOIN ciniki_ags_exhibits AS exhibits ON ("
                . "types.exhibit_id = exhibits.id "
                . "AND exhibits.status = 50 "
                . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE types.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND types.tag_type = 20 "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.39', 'msg'=>'Unable to load types', 'err'=>$rc['err']));
        }
        foreach($rc['rows'] as $row) {
            $options = array();
            $options[] = array(
                'label'=>'Include Past Exhibits',
                'setting'=>'page-ags-' . $row['permalink'] . '-past', 
                'type'=>'toggle',
                'value'=>(isset($settings['page-ags-' . $row['permalink'] . '-past'])?$settings['page-ags-' . $row['permalink'] . '-past']:'no'),
                'toggles'=>array(
                    array('value'=>'no', 'label'=>'No'),
                    array('value'=>'yes', 'label'=>'Yes'),
                    ),
                );
            $options[] = array(
                'label'=>'Initial Exhibits/page',
                'setting'=>'page-ags-' . $row['permalink'] . '-initial-number', 
                'type'=>'text',
                'value'=>(isset($settings['page-ags-' . $row['permalink'] . '-initial-number'])?$settings['page-ags-' . $row['permalink'] . '-initial-number']:'10'),
                );
            $options[] = array(
                'label'=>'Archive Exhibits/page',
                'setting'=>'page-ags-' . $row['permalink'] . '-archive-number', 
                'type'=>'text',
                'value'=>(isset($settings['page-ags-' . $row['permalink'] . '-archive-number'])?$settings['page-ags-' . $row['permalink'] . '-archive-number']:'10'),
                );
            $pages['ciniki.ags.' . $row['permalink']] = array('name'=>'Exhibits - ' . $row['tag_name'], 'options'=>$options);
        } 
    }
    
    return array('stat'=>'ok', 'pages'=>$pages);
}
?>
