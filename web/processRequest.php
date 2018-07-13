<?php
//
// Description
// -----------
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure, similar to ciniki variable but only web specific information.
//
// Returns
// -------
//
function ciniki_ags_web_processRequest(&$ciniki, $settings, $tnid, $args) {

    //
    // Check to make sure the module is enabled
    //
    if( !isset($ciniki['tenant']['modules']['ciniki.ags']) ) {
        return array('stat'=>'404', 'err'=>array('code'=>'ciniki.ags.124', 'msg'=>"I'm sorry, the page you requested does not exist."));
    }
    $page = array(
        'title'=>$args['page_title'],
        'breadcrumbs'=>$args['breadcrumbs'],
        'blocks'=>array(),
        'submenu'=>array(),
        );

    $exhibit_type = '';
    //
    // Setup the labels
    $etype_label = 'Exhibits'; 
    if( preg_match('/ciniki.ags.(.*)/', $args['module_page'], $m) ) {
        $exhibit_type = $m[1];
        $strsql = "SELECT tag_name "
            . "FROM ciniki_ags_exhibit_tags "
            . "WHERE permalink = '" . ciniki_core_dbQuote($ciniki, $exhibit_type) . "' " 
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "LIMIT 1 "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.126', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
        }
        if( isset($rc['item']['tag_name']) ) {
            $etype_label = $rc['item']['tag_name'];
        }
    }

    //
    // Check for categories
    //
    $categories = array();
    if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.ags', 0x20) ) {
        $strsql = "SELECT DISTINCT locations.category "
            . "FROM ciniki_ags_exhibits AS exhibits, ciniki_ags_locations AS locations "
            . "WHERE exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND exhibits.status = 50 "
            . "AND (exhibits.flags&0x01) = 0x01 "
            . "AND exhibits.location_id = locations.id "
            . "AND locations.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "ORDER BY locations.category "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQueryList');
        $rc = ciniki_core_dbQueryList($ciniki, $strsql, 'ciniki.ags', 'categories', 'category');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.127', 'msg'=>'', 'err'=>$rc['err']));
        }
        if( isset($rc['categories']) ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'makePermalink');
            foreach($rc['categories'] as $category) {
                $permalink = ciniki_core_makePermalink($ciniki, $category);
                $page['submenu'][$permalink] = array(
                    'name'=>$category,
                    'url'=>$args['base_url'] . '/category/' . $permalink,
                    );
                $categories[$permalink] = array(
                    'name' => $category,
                    'permalink' => $permalink,
                    );
            }
        }

    }

    //
    // Check for selected category
    //
    $category = '';
    if( isset($args['uri_split'][0]) && $args['uri_split'][0] == 'category' 
        && isset($args['uri_split'][1]) && $args['uri_split'][1] != '' 
        ) {
        $category = $args['uri_split'][1];
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'web', 'private', 'processContent');
    //
    // Check if a file was specified to be downloaded
    //
/*    $download_err = '';
    if( isset($args['uri_split'][0]) && $args['uri_split'][0] != ''
        && isset($args['uri_split'][1]) && $args['uri_split'][1] == 'download'
        && isset($args['uri_split'][2]) && $args['uri_split'][2] != '' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'info', 'web', 'fileDownload');
        $rc = ciniki_info_web_fileDownload($ciniki, $tnid, $args['uri_split'][0], '', $args['uri_split'][2]);
        if( $rc['stat'] == 'ok' ) {
            return array('stat'=>'ok', 'download'=>$rc['file']);
        }
        
        //
        // If there was an error locating the files, display generic error
        //
        return array('stat'=>'404', 'err'=>array('code'=>'ciniki.ags.125', 'msg'=>'The file you requested does not exist.'));
    } */

    //
    // Store the content created by the page
    // Make sure everything gets generated ok before returning the content
    //
    $content = '';
    $page_content = '';
    $page['title'] = 'Exhibitors';
    $ciniki['response']['head']['og']['url'] = $args['base_url']; // $ciniki['request']['domain_base_url'] . '/exhibits';

    if( count($page['breadcrumbs']) == 0 ) {
        $page['breadcrumbs'][] = array('name'=>$page_title, 'url'=>$args['base_url']);
    }
    
    if( $category != '' && isset($categories[$category]) ) {
        $page['breadcrumbs'][] = array('name'=>$categories[$category]['name'], 'url'=>$args['base_url'] . '/category/' . $category);
    }

    //
    // The initial limit is how many to show on the exhibits page after current and upcoming.  
    // This allows a shorter list in the initial page, and longer lists for the archive
    //
    $page_past_initial_limit = 2;
    $page_past_limit = 10;
    if( $exhibit_type != '' ) {
        if( isset($settings['page-ags-' . $exhibit_type . '-initial-number']) 
            && $settings['page-ags-' . $exhibit_type . '-initial-number'] != ''
            && is_numeric($settings['page-ags-' . $exhibit_type . '-initial-number'])
            && $settings['page-ags-' . $exhibit_type . '-initial-number'] > 0 ) {
            $page_past_initial_limit = intval($settings['page-ags-' . $exhibit_type . '-initial-number']);
        }
        if( isset($settings['page-ags-' . $exhibit_type . '-archive-number']) 
            && $settings['page-ags-' . $exhibit_type . '-archive-number'] != ''
            && is_numeric($settings['page-ags-' . $exhibit_type . '-archive-number'])
            && $settings['page-ags-' . $exhibit_type . '-archive-number'] > 0 ) {
            $page_past_limit = intval($settings['page-ags-' . $exhibit_type . '-archive-number']);
        }
    } else {
        if( isset($settings['page-ags-initial-number']) 
            && $settings['page-ags-initial-number'] != ''
            && is_numeric($settings['page-ags-initial-number'])
            && $settings['page-ags-initial-number'] > 0 ) {
            $page_past_initial_limit = intval($settings['page-ags-initial-number']);
        }
        if( isset($settings['page-ags-archive-number']) 
            && $settings['page-ags-archive-number'] != ''
            && is_numeric($settings['page-ags-archive-number'])
            && $settings['page-ags-archive-number'] > 0 ) {
            $page_past_limit = intval($settings['page-ags-archive-number']);
        }
    }
    if( isset($ciniki['request']['args']['page']) && $ciniki['request']['args']['page'] != '' && is_numeric($ciniki['request']['args']['page']) ) {
        $page_past_cur = intval($ciniki['request']['args']['page']);
    } else {
        $page_past_cur = 1;
    }

    //
    // Check if we are to display the application
    //
/*    if( isset($args['uri_split'][0]) && $args['uri_split'][0] == 'exhibitapplication' 
        && isset($settings['page-ags-application-details']) && $settings['page-ags-application-details'] == 'yes'
        ) {
        $page['breadcrumbs'][] = array('url'=>$args['base_url'] . '/exhibitapplication', 'name'=>'Application');
        ciniki_core_loadMethod($ciniki, 'ciniki', 'web', 'private', 'processContent');
        ciniki_core_loadMethod($ciniki, 'ciniki', 'info', 'web', 'pageDetails');
//      $rc = ciniki_ags_web_exhibitApplicationDetails($ciniki, $settings, $tnid);
        $rc = ciniki_info_web_pageDetails($ciniki, $settings, $tnid, array('content_type'=>10));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'404', 'err'=>array('code'=>'ciniki.web.44', 'msg'=>"I'm sorry, but we can't find any information about the requestion application.", 'err'=>$rc['err']));;
        }
        $page['blocks'][] = array('type'=>'content', 'content'=>$rc['content']['content']);
        if( isset($rc['content']['files']) && count($rc['content']['files']) > 0 ) {
            $page['blocks'][] = array('type'=>'files', 'base_url'=>$args['base_url'] . '/exhibitapplication/download', 'files'=>$rc['content']['files']);
        }
        $page['blocks'][] = array('type'=>'content', 'html'=>$page_content);
    }
    //
    // Check if we are to display an image, from the gallery, or latest images
    //
    else*/if( isset($args['uri_split'][0]) && $args['uri_split'][0] != '' 
        && $args['uri_split'][0] != 'category'
        ) {
        $exhibit_permalink = $args['uri_split'][0];
        $gallery_url = $args['base_url'] . "/" . $exhibit_permalink . "/gallery";
        $ciniki['response']['head']['og']['url'] .= '/' . $exhibit_permalink;

        //
        // Load the exhibit to get all the details, and the list of images.
        // It's one query, and we can find the requested image, and figure out next
        // and prev from the list of images returned
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'web', 'exhibitDetails');
        $rc = ciniki_ags_web_exhibitDetails($ciniki, $settings, $tnid, $exhibit_permalink);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'404', 'err'=>array('code'=>'ciniki.web.45', 'msg'=>"I'm sorry, but we can't seem to find the image your requested.", $rc['err']));
        }
        $exhibit = $rc['exhibit'];

        $page['breadcrumbs'][] = array('name'=>$exhibit['name'], 'url'=>$args['base_url'] . '/' . $exhibit['permalink']);
        if( isset($exhibit['synopsis']) && $exhibit['synopsis'] != '' ) {
            $ciniki['response']['head']['og']['description'] = strip_tags($exhibit['synopsis']);
        } elseif( isset($exhibit['description']) && $exhibit['description'] != '' ) {
            $ciniki['response']['head']['og']['description'] = strip_tags($exhibit['description']);
        }

        $exhibit_date = $exhibit['start_month'];
        $exhibit_date .= " " . $exhibit['start_day'];
        if( $exhibit['end_day'] != '' && ($exhibit['start_day'] != $exhibit['end_day'] || $exhibit['start_month'] != $exhibit['end_month']) ) {
            if( $exhibit['end_month'] != '' && $exhibit['end_month'] == $exhibit['start_month'] ) {
                $exhibit_date .= " - " . $exhibit['end_day'];
            } else {
                $exhibit_date .= " - " . $exhibit['end_month'] . " " . $exhibit['end_day'];
            }
        }
        $exhibit_date .= ", " . $exhibit['start_year'];
        $page_title = $exhibit['name'];
        $page['title'] = $exhibit['name'];
        $page['meta_data'] = $exhibit_date;
//        $page['blocks'][] = array('type'=>'content', 'html'=>'<pre>' . print_r($exhibit, true) . '</pre>');

        //
        // Display the exhibit image
        //
        if( isset($args['uri_split'][1]) && $args['uri_split'][1] == 'gallery' && isset($args['uri_split'][2]) && $args['uri_split'][2] != '' ) {
            if( !isset($exhibit['images']) || count($exhibit['images']) < 1 ) {
                return array('stat'=>'404', 'err'=>array('code'=>'ciniki.web.46', 'msg'=>"I'm sorry, but we can't seem to find the image your requested."));
            }

            $image_permalink = $args['uri_split'][2];
            ciniki_core_loadMethod($ciniki, 'ciniki', 'web', 'private', 'galleryFindNextPrev');
            $rc = ciniki_web_galleryFindNextPrev($ciniki, $exhibit['images'], $image_permalink);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            if( $rc['img'] == NULL ) {
                $page['blocks'][] = array(
                    'type'=>'message', 
                    'section'=>'exhibit-image', 
                    'content'=>"I'm sorry, but we can't seem to find the image you requested.",
                    );
            } else {
                $page_title = $exhibit['name'] . ' - ' . $rc['img']['title'];
                $page['breadcrumbs'][] = array('name'=>$rc['img']['title'], 'url'=>$args['base_url'] . '/' . $exhibit_permalink . '/gallery/' . $image_permalink);
                if( $rc['img']['title'] != '' ) {
                    $page['title'] .= ' - ' . $rc['img']['title'];
                }
                $block = array(
                    'type'=>'galleryimage', 
                    'section'=>'exhibit-image', 
                    'primary'=>'yes', 
                    'image'=>$rc['img'],
                    );
                if( $rc['prev'] != null ) {
                    $block['prev'] = array(
                        'url'=>$args['base_url'] . '/' . $exhibit_permalink . '/gallery/' . $rc['prev']['permalink'], 
                        'image_id'=>$rc['prev']['image_id'],
                        );
                }
                if( $rc['next'] != null ) {
                    $block['next'] = array(
                        'url'=>$args['base_url'] . '/' . $exhibit_permalink . '/gallery/' . $rc['next']['permalink'], 
                        'image_id'=>$rc['next']['image_id']);
                }
                $page['blocks'][] = $block;
            }
        } 
        //
        // Display the exhibit details
        //
        else {
            if( isset($exhibit['primary_image_id']) && $exhibit['primary_image_id'] > 0 ) {
                $page['blocks'][] = array(
                    'id' => 'aside-image',
                    'type' => 'asideimage', 
                    'section' => 'primary-image', 
                    'primary' => 'yes',
                    'image_id' => $exhibit['primary_image_id'], 
                    'title' => $exhibit['name'], 
                    'caption' => '',
                    );
            }
            //
            // display a map to the location
            //
            if( isset($exhibit['latitude']) && $exhibit['latitude'] != 0
                && isset($exhibit['longitude']) && $exhibit['longitude'] != 0
                ) {
                if( !isset($ciniki['request']['inline_javascript']) ) {
                    $ciniki['request']['inline_javascript'] = '';
                }
                $page['blocks'][] = array(
                    'type' => 'map',
                    'aside' => 'yes',
                    'display' => 'none',
                    'latitude' => $exhibit['latitude'],
                    'longitude' => $exhibit['longitude'],
                    );

                $ciniki['request']['inline_javascript'] .= ''
                    . '<script type="text/javascript">'
/*                    . 'var gmap_loaded=0;'
                    . 'function gmap_initialize() {'
                        . 'var myLatlng = new google.maps.LatLng(' . $exhibit['latitude'] . ',' . $exhibit['longitude'] . ');'
                        . 'var mapOptions = {'
                            . 'zoom: 13,'
                            . 'center: myLatlng,'
                            . 'panControl: false,'
                            . 'zoomControl: true,'
                            . 'scaleControl: true,'
                            . 'mapTypeId: google.maps.MapTypeId.ROADMAP'
                        . '};'
                        . 'var map = new google.maps.Map(document.getElementById("googlemap"), mapOptions);'
                        . 'var marker = new google.maps.Marker({'
                            . 'position: myLatlng,'
                            . 'map: map,'
                            . 'title:"",'
                            . '});'
                    . '};'
                    . 'function loadMap() {'
                        . 'if(gmap_loaded==1) {return;}'
                        . 'var script = document.createElement("script");'
                        . 'script.type = "text/javascript";'
                        . 'script.src = "' . ($ciniki['request']['ssl']=='yes'?'https':'http') . '://maps.googleapis.com/maps/api/js?key=' . $ciniki['config']['ciniki.web']['google.maps.api.key'] . '&sensor=false&callback=gmap_initialize";'
                        . 'document.body.appendChild(script);'
                        . 'gmap_loaded=1;'
                    . '};' */
                    . 'function toggleMap(e) {'
                        . "var i = document.getElementById('aside-image');"
                        . "var m = document.getElementById('googlemap');"
                        . "if(i!=null){"
                            . "if(i.style.display!='none') {"
                                . "i.style.display='none';"
                                . "m.style.display='block';"
                                . "loadMap();"
                                . "document.getElementById('map-toggle').innerHTML='picture';"
                            . "} else {"
                                . "i.style.display='block';"
                                . "m.style.display='none'; "
                                . "document.getElementById('map-toggle').innerHTML='map';"
                            . "}"
                        . "}"
                    . '};'
//                    . ((!isset($exhibit['image_id']) || $exhibit['image_id'] == 0)?'window.onload=loadMap;':'')
                    . '</script>';
//                $page['blocks'] .= "<aside id='aside-map' style='display:${aside_display};'><div class='googlemap' id='googlemap'></div></aside>"; */
            }
            $content = '';
            if( isset($exhibit['description']) && $exhibit['description'] != '' ) {
                $content = $exhibit['description'];
            } else {
                $content = $exhibit['synopsis'];
            }
            $page['blocks'][] = array('type'=>'content', 'section'=>'content', 'title'=>'', 'content'=>$content);

            //
            // Check if map is address is supplied
            //
            if( isset($exhibit['location_address']) && $exhibit['location_address'] != '' ) {
                $toggle_map = '';
                if( isset($exhibit['primary_image_id']) && $exhibit['primary_image_id'] > 0 
                    && isset($exhibit['latitude']) && $exhibit['latitude'] != 0
                    && isset($exhibit['longitude']) && $exhibit['longitude'] != 0
                    ) {
                    $toggle_map = "<a id='map-toggle' href='javascript: toggleMap(event);'>map</a>";
                }
                $page['blocks'][] = array(
                    'type' => 'content',
                    'title' => 'Location',
                    'content' => $exhibit['location_name'] . "\n" . $exhibit['location_address']
                        . ($toggle_map!=''?" (" . $toggle_map . ")":''),
                    );
            }

            //
            // Check if share buttons should be shown
            //
            if( !isset($settings['page-exhibits-share-buttons']) || $settings['page-exhibits-share-buttons'] == 'yes' ) {
                $tags = array($etype_label);
                if( !isset($settings['page-events-share-buttons']) || $settings['page-events-share-buttons'] == 'yes' ) {
                    $tags = array();
                    $page['blocks'][] = array('type'=>'sharebuttons', 'section'=>'share', 'pagetitle'=>$page['title'], 'tags'=>$tags);
                }
            }
            
            //
            // Add images if they exist
            //
            if( isset($exhibit['images']) && count($exhibit['images']) > 0 ) {
                $page['blocks'][] = array('type'=>'gallery', 'section'=>'gallery', 'title'=>'Additional Images',
                    'base_url'=>$args['base_url'] . "/" . $exhibit_permalink . "/gallery",
                    'images'=>$exhibit['images']);
            }
        }
    }

    //
    // Display the list of exhibitors if a specific one isn't selected
    //
    else {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'web', 'processExhibits');
        //
        // Check to see if there is an introduction message to display
        //
        /*
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDetailsQueryDash');
        $rc = ciniki_core_dbDetailsQueryDash($ciniki, 'ciniki_web_content', 'tnid', $tnid, 'ciniki.web', 'content', 'page-ags');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $ciniki['response']['head']['og']['description'] = strip_tags('Upcoming ' . $etype_label);
        if( $page_past_cur == 1 && isset($rc['content']['page-ags-content']) && $rc['content']['page-ags-content'] != '' ) {
            $page_content = '';
            if( isset($settings['page-ags-image']) 
                && $settings['page-ags-image'] != '' 
                && $settings['page-ags-image'] > 0 
                ) {
                $page['blocks'][] = array('type'=>'asideimage', 'section'=>'primary-image', 'primary'=>'yes',
                    'image_id'=>$settings['page-ags-image'],
                    'captions'=>(isset($settings['page-ags-image-caption']) ? $settings['page-ags-image-caption'] : ''),
                    );
            }
            $content = $rc['content']['page-ags-content'];

            //
            // Check if there is an application
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'info', 'web', 'pageDetails');
            $rc = ciniki_info_web_pageDetails($ciniki, $settings, $tnid, array('content_type'=>10));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            $application = $rc['content'];
            if( $application['content'] != '' ) {
                $content .= "\n\n<a href='" . $args['base_url'] . "/exhibitapplication'>Apply to be an exhibitor</a>";
            }

            $page['blocks'][] = array('type'=>'content', 'section'=>'content', 'content'=>$content);
        } 
        */

        //
        // Display list of upcoming exhibits
        //
        $num_current = 0;
        if( $page_past_cur == 1 ) {
            //
            // Display current exhibits first
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'web', 'exhibitList');
            $rc = ciniki_ags_web_exhibitList($ciniki, $settings, $tnid, 
                array('type'=>'current', 'limit'=>0, 'category'=>$category, 'exhibit_type'=>$exhibit_type));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            if( count($rc['exhibits']) > 0 ) {
                $exhibits = $rc['exhibits'];
                $num_current = count($exhibits);
                if( $num_current > 0 ) {
                    $rc = ciniki_ags_web_processExhibits($ciniki, $settings, $exhibits, array(
                        'base_url' => $args['base_url'],
                        ));
                    if( $rc['stat'] != 'ok' ) {
                        return $rc;
                    }
                    $page['blocks'][] = array(
                        'type'=>'content', 
                        'section'=>'current-exhibits',
                        'title' => 'Current ' . $etype_label,
                        'html' => $rc['content'],
                        );
                }
            }

            //
            // Display upcoming exhibits second
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'web', 'exhibitList');
            $rc = ciniki_ags_web_exhibitList($ciniki, $settings, $tnid, 
                array('type'=>'upcoming', 'limit'=>0, 'category'=>$category, 'exhibit_type'=>$exhibit_type));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            $exhibits = $rc['exhibits'];
            if( count($exhibits) > 0 ) {
                $rc = ciniki_ags_web_processExhibits($ciniki, $settings, $exhibits, array(
                    'base_url' => $args['base_url'],
                    ));
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
                $page['blocks'][] = array(
                    'type'=>'content', 
                    'section'=>'exhibit-list', 
                    'title'=>'Upcoming ' . $etype_label,
                    'html'=>$rc['content'],
                    ); 
            } else {
                $page['blocks'][] = array(
                    'type'=>'content', 
                    'title'=>'', 
                    'content'=>'No upcoming exhibits',
                    );
            }
        }

        //
        // Include past exhibits if the user wants
        //
//        $page['blocks'][] = array('type'=>'content', 'html'=>'<pre>' . print_r($settings, true) . "</pre>");
        if( ($exhibit_type != '' 
                && isset($settings['page-ags-' . $exhibit_type . '-past']) 
                && $settings['page-ags-' . $exhibit_type . '-past'] == 'yes')
            || (isset($settings['page-ags-past']) && $settings['page-ags-past'] == 'yes') 
            ) {
            //
            // Generate the content of the page
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'web', 'exhibitList');
            if( $page_past_cur == 1 ) {
                $offset = 0;
            } else {
                $offset = $page_past_initial_limit + ($page_past_cur-2)*$page_past_limit;
            }
            $rc = ciniki_ags_web_exhibitList($ciniki, $settings, $tnid, 
                array('type'=>'past', 
                    'exhibit_type'=>$exhibit_type,
                    'category'=>$category,
                    'offset'=>$offset, 
                    'limit'=>($page_past_cur==1?($page_past_initial_limit+1):($page_past_limit+1))));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            $exhibits = $rc['exhibits'];
            if( count($exhibits) > 0 ) {
                $rc = ciniki_ags_web_processExhibits($ciniki, $settings, $exhibits, 
                    array('page' => $page_past_cur,
                        'limit' => ($page_past_cur==1?$page_past_initial_limit:$page_past_limit), 
                        'prev' => "Newer $etype_label &rarr;",
                        'next' => "&larr; Older $etype_label",
                        'base_url' => $args['base_url'],
                        'category' => $category,
                        ));
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
                $page['blocks'][] = array('type'=>'content', 'title'=>'Past ' . $etype_label, 'html'=>$rc['content']);
                $page['blocks'][] = array('type'=>'content', 'html'=>$rc['nav']);
            } else {
                $page['blocks'][] = array('type'=>'content', 'title'=>'Past ' . $etype_label, 'content'=>'No past ' . $etype_label);
            }
        }

/*        //
        // Check if the exhibit application should be displayed
        //
        if( isset($settings['page-ags-application-details']) 
            && $settings['page-ags-application-details'] == 'yes' 
            && $page_past_cur == 1
            ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'info', 'web', 'pageDetails');
            $rc = ciniki_info_web_pageDetails($ciniki, $settings, $tnid,
                array('content_type'=>10));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            $application = $rc['content'];
            if( $application['content'] != '' ) {
                $page['blocks'][] = array('type'=>'content', 'html'=>"<p class='exhibitors-application'>"
                    . "<a href='" . $args['base_url'] . "/exhibitapplication'>Apply to be an exhibitor</a></p>",
                    );
            }
        } */
    }

/*    if( ($ciniki['tenant']['modules']['ciniki.ags']['flags']&0x04) > 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'web', 'categories');
        $rc = ciniki_ags_web_categories($ciniki, $settings, $tnid, array());
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['categories']) ) {
            foreach($rc['categories'] as $category) {
                $page['submenu'][$category['permalink']] = array('name'=>$category['name'],
                    'url'=>$args['base_url'] . '/category/' . $category['permalink']);
            }
        }
    } */

    return array('stat'=>'ok', 'page'=>$page);
}
?>
