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
        'label'=>'Include Upcoming',
        'setting'=>'page-ags-upcoming', 
        'type'=>'toggle',
        'value'=>(isset($settings['page-ags-upcoming'])?$settings['page-ags-upcoming']:'yes'),
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
                'value'=>(isset($settings["page-ags-{$row['permalink']}-past"])?$settings["page-ags-{$row['permalink']}-past"]:'no'),
                'toggles'=>array(
                    array('value'=>'no', 'label'=>'No'),
                    array('value'=>'yes', 'label'=>'Yes'),
                    ),
                );
            $options[] = array(
                'label'=>'Include Upcoming Exhibits',
                'setting'=>'page-ags-' . $row['permalink'] . '-upcoming', 
                'type'=>'toggle',
                'value'=>(isset($settings["page-ags-{$row['permalink']}-upcoming"])?$settings["page-ags-{$row['permalink']}-upcoming"]:'yes'),
                'toggles'=>array(
                    array('value'=>'no', 'label'=>'No'),
                    array('value'=>'yes', 'label'=>'Yes'),
                    ),
                );
            // This is the category submenu for the page when multiple exhibitions shown
            $options[] = array(
                'label'=>'Categories Submenu',
                'setting'=>'page-ags-' . $row['permalink'] . '-submenu-categories', 
                'type'=>'toggle',
                'value'=>(isset($settings["page-ags-{$row['permalink']}-submenu-categories"])?$settings["page-ags-{$row['permalink']}-submenu-categories"]:'no'),
                'toggles'=>array(
                    array('value'=>'no', 'label'=>'No'),
                    array('value'=>'yes', 'label'=>'Yes'),
                    ),
                );
            $options[] = array(
                'label'=>'Show Intro Photo',
                'setting'=>'page-ags-' . $row['permalink'] . '-intro-photo', 
                'type'=>'toggle',
                'value'=>(isset($settings["page-ags-{$row['permalink']}-intro-photo"])?$settings["page-ags-{$row['permalink']}-intro-photo"]:'yes'),
                'toggles'=>array(
                    array('value'=>'no', 'label'=>'No'),
                    array('value'=>'yes', 'label'=>'Yes'),
                    ),
                );
            $options[] = array(
                'label'=>'Link Member Profiles',
                'setting'=>'page-ags-' . $row['permalink'] . '-members-link', 
                'type'=>'toggle',
                'value'=>(isset($settings["page-ags-{$row['permalink']}-members-link"])?$settings["page-ags-{$row['permalink']}-members-link"]:'no'),
                'toggles'=>array(
                    array('value'=>'no', 'label'=>'No'),
                    array('value'=>'yes', 'label'=>'Yes'),
                    ),
                );
            $options[] = array(
                'label'=>'Exhibitor Label',
                'setting'=>'page-ags-' . $row['permalink'] . '-exhibitor-label', 
                'type'=>'text',
                'value'=>(isset($settings['page-ags-' . $row['permalink'] . '-exhibitor-label'])?$settings['page-ags-' . $row['permalink'] . '-exhibitor-label']:'Artist'),
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
                'value'=>(isset($settings["page-ags-{$row['permalink']}-archive-number"])?$settings["page-ags-{$row['permalink']}-archive-number"]:'10'),
                );
            $options[] = array(
                'label'=>'Thumbnail Format',
                'setting'=>'page-ags-' . $row['permalink'] . '-thumbnail-format', 
                'type'=>'toggle',
                'value'=>(isset($settings['page-ags-' . $row['permalink'] . '-thumbnail-format']) ? $settings['page-ags-' . $row['permalink'] . '-thumbnail-format'] : 'square-cropped'),
                'toggles'=>array(
                    array('value'=>'square-cropped', 'label'=>'Cropped'),
                    array('value'=>'square-padded', 'label'=>'Padded'),
                    ),
                ); 
            $options[] = array(
                'label'=>'Thumbnail Padding Color',
                'setting'=>'page-ags-' . $row['permalink'] . '-thumbnail-padding-color',
                'type'=>'colour',
                'value'=>(isset($settings['page-ags-' . $row['permalink'] . '-thumbnail-padding-color'])?$settings['page-ags-' . $row['permalink'] . '-thumbnail-padding-color']:'#ffffff'),
                );
            $options[] = array(
                'label'=>'Image Quality',
                'setting'=>'page-ags-' . $row['permalink'] . '-image-quality',
                'type'=>'toggle',
                'value'=>(isset($settings['page-ags-' . $row['permalink'] . '-image-quality'])?$settings['page-ags-' . $row['permalink'] . '-image-quality']:'low'),
                'toggles'=>array(
                    array('value'=>'low', 'label'=>'Low'),
                    array('value'=>'high', 'label'=>'High'),
                    ),
                );

            $pages["ciniki.ags.{$row['permalink']}"] = array('name'=>"Exhibits - {$row['tag_name']}", 'options'=>$options);
        } 
    }
    
    return array('stat'=>'ok', 'pages'=>$pages);
}
?>
