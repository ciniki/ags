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
        return array('stat'=>'404', 'err'=>array('code'=>'ciniki.ags.203', 'msg'=>"I'm sorry, the page you requested does not exist."));
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
    // The initial limit is how many to show on the exhibits page after current and upcoming.  
    // This allows a shorter list in the initial page, and longer lists for the archive
    //
    $page_past_initial_limit = 2;
    $page_past_limit = 10;
    $page_submenu = '';
    $members_link = 'no';
    $exhibitor_label = 'Created By';
    $settings_prefix = 'page-ags';
    if( $exhibit_type != '' ) {
        $settings_prefix .= '-' . $exhibit_type;
    }
    if( isset($settings[$settings_prefix . '-initial-number']) 
        && $settings[$settings_prefix . '-initial-number'] != ''
        && is_numeric($settings[$settings_prefix . '-initial-number'])
        && $settings[$settings_prefix . '-initial-number'] > 0 ) {
        $page_past_initial_limit = intval($settings[$settings_prefix . '-initial-number']);
    }
    if( isset($settings[$settings_prefix . '-archive-number']) 
        && $settings[$settings_prefix . '-archive-number'] != ''
        && is_numeric($settings[$settings_prefix . '-archive-number'])
        && $settings[$settings_prefix . '-archive-number'] > 0 ) {
        $page_past_limit = intval($settings[$settings_prefix . '-archive-number']);
    }
    if( isset($settings["{$settings_prefix}-submenu-categories"]) 
        && $settings["{$settings_prefix}-submenu-categories"] == 'yes'
        ) {
        $page_submenu = 'categories';
    }
    if( isset($settings["{$settings_prefix}-exhibitor-label"]) 
        && $settings["{$settings_prefix}-exhibitor-label"] != ''
        ) {
        $exhibitor_label = $settings["{$settings_prefix}-exhibitor-label"];
    }
    if( isset($settings["{$settings_prefix}-members-link"]) 
        && $settings["{$settings_prefix}-members-link"] != ''
        ) {
        $members_link = $settings["{$settings_prefix}-members-link"];
    }
    $image_quality = 'regular';
    if( isset($settings["{$settings_prefix}-image-quality"]) 
        && $settings["{$settings_prefix}-image-quality"] == 'high'
        ) {
        $image_quality = 'high';
    }

    if( isset($ciniki['request']['args']['page']) && $ciniki['request']['args']['page'] != '' && is_numeric($ciniki['request']['args']['page']) ) {
        $page_past_cur = intval($ciniki['request']['args']['page']);
    } else {
        $page_past_cur = 1;
    }

    //
    // Check for image formats
    //
    $thumbnail_format = 'square-cropped';
    $thumbnail_padding_color = '#ffffff';
    if( isset($settings["{$settings_prefix}-thumbnail-format"]) && $settings["{$settings_prefix}-thumbnail-format"] == 'square-padded' ) {
        $thumbnail_format = $settings["{$settings_prefix}-thumbnail-format"];
        if( isset($settings["{$settings_prefix}-thumbnail-padding-color"]) && $settings["{$settings_prefix}-thumbnail-padding-color"] != '' ) {
            $thumbnail_padding_color = $settings["{$settings_prefix}-thumbnail-padding-color"];
        } 
    }

    //
    // Check for categories
    //
    $categories = array();
    if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.ags', 0x20) ) {
        if( $exhibit_type != '' ) {
            $strsql = "SELECT DISTINCT locations.category "
                . "FROM ciniki_ags_exhibit_tags as tags, ciniki_ags_exhibits AS exhibits, ciniki_ags_locations AS locations "
                . "WHERE tags.permalink = '" . ciniki_core_dbQuote($ciniki, $exhibit_type) . "' "
                . "AND tags.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . "AND tags.exhibit_id = exhibits.id "
                . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . "AND exhibits.status = 50 "
                . "AND (exhibits.flags&0x01) = 0x01 "
                . "AND exhibits.location_id = locations.id "
                . "AND locations.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . "ORDER BY locations.category "
                . "";
        } else {
            $strsql = "SELECT DISTINCT locations.category "
                . "FROM ciniki_ags_exhibits AS exhibits, ciniki_ags_locations AS locations "
                . "WHERE exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . "AND exhibits.status = 50 "
                . "AND (exhibits.flags&0x01) = 0x01 "
                . "AND exhibits.location_id = locations.id "
                . "AND locations.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . "ORDER BY locations.category "
                . "";
        }
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQueryList');
        $rc = ciniki_core_dbQueryList($ciniki, $strsql, 'ciniki.ags', 'categories', 'category');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.127', 'msg'=>'', 'err'=>$rc['err']));
        }
        if( isset($rc['categories']) ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'makePermalink');
            foreach($rc['categories'] as $category) {
                $permalink = ciniki_core_makePermalink($ciniki, $category);
                if( $page_submenu == 'categories' ) {
                    $page['submenu'][$permalink] = array(
                        'name'=>$category,
                        'url'=>$args['base_url'] . '/category/' . $permalink,
                        );
                }
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
        $category_permalink = $args['uri_split'][1];
        if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.ags', 0x20) && isset($categories[$category_permalink]) ) {
            $category = $categories[$category_permalink]['name'];
        }
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
    $ciniki['response']['head']['og']['url'] = $args['domain_base_url']; // $ciniki['request']['domain_base_url'] . '/exhibits';

    if( count($page['breadcrumbs']) == 0 ) {
        $page['breadcrumbs'][] = array('name'=>$page_title, 'url'=>$args['base_url']);
    }
    
    if( isset($category_permalink) && $category != '' ) {
        $page['breadcrumbs'][] = array('name'=>$category, 'url'=>$args['base_url'] . '/category/' . $category_permalink);
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
    else*/
    $display = 'list';
    if( isset($args['uri_split'][0]) && $args['uri_split'][0] != '' 
        && $args['uri_split'][0] != 'category'
        ) {
        $display = 'exhibit';
        $exhibit_permalink = $args['uri_split'][0];
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
            // Fetch the list of upcoming, incase there are none then display current
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'web', 'exhibitList');
            $rc = ciniki_ags_web_exhibitList($ciniki, $settings, $tnid, 
                array('type'=>'upcoming', 'limit'=>0, 'category'=>$category, 'exhibit_type'=>$exhibit_type));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            $upcoming = $rc['exhibits'];

            //
            // Display current exhibits first
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'web', 'exhibitList');
            $rc = ciniki_ags_web_exhibitList($ciniki, $settings, $tnid, 
                array('type'=>'current', 'limit'=>0, 'category'=>$category, 'exhibit_type'=>$exhibit_type));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            //
            // If there is only 1 exhibit, and no past exhibits, then jump straight to exhibit
            //
            if( count($rc['exhibits']) == 1 && count($upcoming) == 0 
                && (($exhibit_type != '' 
                        && (!isset($settings["page-ags-{$exhibit_type}-past"]) 
                        || $settings["page-ags-{$exhibit_type}-past"] != 'yes'))
                    || (isset($settings['page-ags-past']) && $settings['page-ags-past'] == 'yes')
                    )
                ) {
                //
                // Display the exhibit
                //
                $display = 'exhibit';
                $exhibit_permalink = $rc['exhibits'][0]['permalink'];
            } elseif( count($rc['exhibits']) > 0 ) {
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
            if( count($upcoming) > 0 ) {
                $rc = ciniki_ags_web_processExhibits($ciniki, $settings, $upcoming, array(
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
            } elseif( $display == 'list' ) {
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

    if( $display == 'exhibit' ) {
        $base_url = $args['base_url'] . '/' . $exhibit_permalink;
        $gallery_url = $base_url . "/gallery";
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

        if( $exhibit['name'] != $args['page_title'] ) {
            $page['breadcrumbs'][] = array('name'=>$exhibit['name'], 'url'=>$args['base_url'] . '/' . $exhibit['permalink']);
        }
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
        if( $exhibit['end_month'] != '' ) {
            $page['article_meta'] = array($exhibit_date);
        }
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
        // Display the items in a category
        //
        elseif( isset($args['uri_split'][1]) && $args['uri_split'][1] == 'category' && isset($args['uri_split'][2]) && $args['uri_split'][2] != '' ) {
            $category_permalink = $args['uri_split'][2];
            $base_url .= '/category/' . $category_permalink;
            $ciniki['response']['head']['og']['url'] .= '/category/' . $category_permalink;

            if( !isset($exhibit['categories'][$category_permalink]['items']) ) {
                return array('stat'=>'404', 'err'=>array('code'=>'ciniki.ags.204', 'msg'=>"I'm sorry, the page you requested does not exist."));
            } 

            $category = $exhibit['categories'][$category_permalink];
            $page['breadcrumbs'][] = array('name'=>$exhibit['categories'][$category_permalink]['name'], 'url'=>$base_url);

            //
            // Check if item specified
            //
            if( isset($args['uri_split'][3]) && $args['uri_split'][3] == 'item' && isset($args['uri_split'][4]) && $args['uri_split'][4] != '' ) {
                $item_permalink = $args['uri_split'][4];

                if( !isset($category['items'][$item_permalink]) ) {
                    return array('stat'=>'404', 'err'=>array('code'=>'ciniki.ags.205', 'msg'=>"I'm sorry, the page you requested does not exist."));
                } 
                $item_list = $category['items'];
                $item = $category['items'][$item_permalink];
                $base_url .= '/item/' . $item_permalink;
                $ciniki['response']['head']['og']['url'] .= '/item/' . $item_permalink;
                $ciniki['response']['head']['links'][] = array('rel'=>'canonical', 'href'=>$args['base_url'] . '/item/' . $item_permalink);
                $page['breadcrumbs'][] = array('name'=>$item['name'], 'url'=>$base_url);
                
                //
                // Display item.
                //
                $display = 'item'; 

                //
                // Check if gallery image
                //
                if( isset($args['uri_split'][5]) && $args['uri_split'][5] == 'gallery' 
                    && isset($args['uri_split'][6]) && $args['uri_split'][6] != '' 
                    ) {
                    $image_permalink = $args['uri_split'][6];
                }
            } 
           
            //
            // Display item thumbnails
            //
            else {
                //
                // Check for category details in settings
                //
                $strsql = "SELECT detail_key, detail_value "
                    . "FROM ciniki_ags_settings "
                    . "WHERE detail_key LIKE 'category-" . ciniki_core_dbQuote($ciniki, $category_permalink) . "-%' "
                    . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                    . "";
                $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.222', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
                }
                if( isset($rc['rows']) ) {
                    foreach($rc['rows'] as $row) {
                        if( $row['detail_key'] == "category-{$category_permalink}-image" ) {
                            $category_image_id = $row['detail_value'];
                        } elseif( $row['detail_key'] == "category-{$category_permalink}-description" ) {
                            $category_description = $row['detail_value'];
                        }
                    }
                }
                if( isset($category_image_id) && $category_image_id > 0 && $category_image_id != '' 
                    && isset($category_description) && $category_description != '' 
                    ) {
                    $page['blocks'][] = array(
                        'id' => 'aside-image',
                        'type' => 'asideimage', 
                        'section' => 'primary-image', 
                        'primary' => 'yes',
                        'quality' => $image_quality,
                        'image_id' => $category_image_id, 
                        'title' => $exhibit['categories'][$category_permalink]['name'], 
                        'caption' => '',
                        );
                    $page['blocks'][] = array('type'=>'content', 'section'=>'content', 'title'=>'', 'content'=>$category_description);
                }
                
                //
                // Format prices
                //
                foreach($category['items'] as $iid => $item) {
                    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'web', 'formatPrice');
                    $rc = ciniki_ags_web_formatPrice($ciniki, $tnid, $item);
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.199', 'msg'=>'Unable to format price', 'err'=>$rc['err']));
                    }
                    $category['items'][$iid]['display_price'] = $rc['display_price'];
                }

                $page['blocks'][] = array('type'=>'tradingcards', 
                    'thumbnail_format'=>$thumbnail_format,
                    'thumbnail_padding_color' => $thumbnail_padding_color,
                    'section'=>'exhibit-items',
                    'base_url'=>$base_url . '/item', 
                    'anchors'=>'permalink', 
                    'cards'=>$category['items']);
            }

        }
        //
        // Display the item
        //
        elseif( isset($args['uri_split'][1]) && $args['uri_split'][1] == 'item' && isset($args['uri_split'][2]) && $args['uri_split'][2] != '' ) {
            $item_permalink = $args['uri_split'][2];
            
            //
            // If category buttons or thumbnails are enabled
            //
            if( ($exhibit['flags']&0x12) > 0 ) {
                foreach($exhibit['categories'] as $category) {
                    if( isset($category['items'][$item_permalink]) ) {
                        $item_list = $category['items'];
                        $item = $category['items'][$item_permalink];
                        $base_url .= '/item/' . $item_permalink;
                        $ciniki['response']['head']['og']['url'] .= '/item/' . $item_permalink;
                        $page['breadcrumbs'][] = array('name'=>$item['name'], 'url'=>$base_url);

                        $display = 'item';
                        break;
                    }
                }
            }
            elseif( isset($exhibit['items'][$item_permalink]) ) {
                $item_list = $exhibit['items'];
                $item = $exhibit['items'][$item_permalink];
                $base_url .= '/item/' . $item_permalink;
                $ciniki['response']['head']['og']['url'] .= '/item/' . $item_permalink;
                $page['breadcrumbs'][] = array('name'=>$item['name'], 'url'=>$base_url);
                $display = 'item';
            }

            //
            // Check if gallery image
            //
            if( isset($args['uri_split'][3]) && $args['uri_split'][3] == 'gallery' 
                && isset($args['uri_split'][4]) && $args['uri_split'][4] != '' 
                ) {
                $image_permalink = $args['uri_split'][4];
            }

            if( $display == 'exhibit' ) {
                $page['blocks'][] = array('type'=>'formmessage', 'level'=>'error', 'message'=>'Sorry, the item you requested is no longer available.');
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
                    'quality' => $image_quality,
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
                    . '</script>';
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
/*            if( (!isset($settings['page-exhibits-share-buttons']) || $settings['page-exhibits-share-buttons'] == 'yes') 
                && $page_submenu != 'categories'
                ) {
                $tags = array($etype_label);
                $tags = array();
                $page['blocks'][] = array('type'=>'sharebuttons', 'section'=>'share', 'pagetitle'=>$page['title'], 'tags'=>$tags);
            } */
          
            //
            // Note: The exhibit categories will be shown as button list when the
            // flag is set on the exhibit. This has nothing to do with the page
            // submenu.
            //

            //
            // Add images if they exist
            //
            if( isset($exhibit['images']) && count($exhibit['images']) > 0 && ($exhibit['flags']&0x02) == 0 ) {
                $page['blocks'][] = array('type'=>'gallery', 'section'=>'gallery', 'title'=>'Additional Images',
                    'base_url'=>$args['base_url'] . "/" . $exhibit_permalink . "/gallery",
                    'images'=>$exhibit['images']);
            }
            elseif( isset($exhibit['categories']) && count($exhibit['categories']) > 0 && ($exhibit['flags']&0x02) == 0x02 ) {
                $page['blocks'][] = array('type'=>'buttonlist', 
                    'section'=>'exhibit-categories', 
                    'title'=>'Categories', 
                    'base_url'=>$base_url . '/category', 
                    'tags'=>$exhibit['categories'],
                    );
            }
            elseif( isset($exhibit['categories']) && count($exhibit['categories']) > 0 && ($exhibit['flags']&0x10) == 0x10 ) {
                $page['blocks'][] = array('type'=>'thumbnaillist', 
                    'section'=>'exhibit-categories', 
                    'title'=>'Categories', 
                    'base_url'=>$base_url . '/category', 
                    'list'=>$exhibit['categories'],
                    );
            }
            elseif( isset($exhibit['items']) && count($exhibit['items']) > 0 && ($exhibit['flags']&0x12) == 0 ) {
                foreach($exhibit['items'] as $iid => $item) {
                    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'web', 'formatPrice');
                    $rc = ciniki_ags_web_formatPrice($ciniki, $tnid, $item);
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.199', 'msg'=>'Unable to format price', 'err'=>$rc['err']));
                    }
                    $exhibit['items'][$iid]['display_price'] = $rc['display_price'];
                }

                $page['blocks'][] = array('type'=>'tradingcards', 
                    'section'=>'exhibit-items',
                    'title'=>'',
                    'thumbnail_format'=>$thumbnail_format,
                    'thumbnail_padding_color' => $thumbnail_padding_color,
                    'base_url'=>$base_url . '/item', 
                    'anchors'=>'permalink', 
                    'cards'=>$exhibit['items']);
                
            }
        }
    }

    //
    // Display the page for the item
    //
    if( $display == 'item' && isset($exhibit) && isset($item) ) {

        ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'web', 'itemDetails');
        $rc = ciniki_ags_web_itemDetails($ciniki, $settings, $tnid, $exhibit['id'], $item['id']);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'404', 'err'=>array('code'=>'ciniki.ags.124', 'msg'=>"I'm sorry, the page you requested does not exist."));
        }
        $eitem = $item;
        $item = $rc['item'];
        $item['inventory'] = $eitem['inventory'];

        //
        // Check if gallery image requested
        //
        if( isset($image_permalink) && $image_permalink != '' ) {
            if( !isset($item['images']) || count($item['images']) < 1 ) {
                return array('stat'=>'404', 'err'=>array('code'=>'ciniki.ags.197', 'msg'=>"I'm sorry, but we can't seem to find the image your requested."));
            }

            ciniki_core_loadMethod($ciniki, 'ciniki', 'web', 'private', 'galleryFindNextPrev');
            $rc = ciniki_web_galleryFindNextPrev($ciniki, $item['images'], $image_permalink);
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
                $page_title = $item['name'] . ' - ' . $rc['img']['title'];
                $page['breadcrumbs'][] = array('name'=>$rc['img']['title'], 'url'=>$base_url . '/gallery/' . $image_permalink);
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
                        'url'=>$base_url . '/gallery/' . $rc['prev']['permalink'], 
                        'image_id'=>$rc['prev']['image_id'],
                        );
                }
                if( $rc['next'] != null ) {
                    $block['next'] = array(
                        'url'=>$base_url . '/gallery/' . $rc['next']['permalink'], 
                        'image_id'=>$rc['next']['image_id']);
                }
                $page['blocks'][] = $block;
            }

        } else {
            if( isset($item['primary_image_id']) && $item['primary_image_id'] > 0 ) {
                $page['blocks'][] = array(
                    'id' => 'aside-image',
                    'type' => 'asideimage', 
                    'section' => 'primary-image', 
                    'primary' => 'yes',
                    'quality' => $image_quality,
                    'image_id' => $item['primary_image_id'], 
                    'title' => $item['name'], 
                    'caption' => '',
                    );
            }
            //
            // Check if member link should be shown
            //
            if( $members_link == 'yes' ) {
                // Get the base url of the customers module
                ciniki_core_loadMethod($ciniki, 'ciniki', 'web', 'private', 'indexModuleBaseURL');
                $rc = ciniki_web_indexModuleBaseURL($ciniki, $tnid, 'ciniki.customers.members');
                if( $rc['stat'] != 'ok' ) {
                    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.190', 'msg'=>'Unable to get members base URL', 'err'=>$rc['err']));
                }
                $members_base_url = $ciniki['request']['domain_base_url'] . (isset($rc['base_url']) ? $rc['base_url'] : '');
                $content = $exhibitor_label . ': '
                    . "<a href='{$members_base_url}/{$item['customer_permalink']}'>"
                    . $item['display_name'] 
                    . '</a>';
                if( $item['medium'] != '' ) {
                    $content .= '<br/>Medium: ' . $item['medium'];
                }
                if( $item['size'] != '' ) {
                    $content .= '<br/>Size: ' . $item['size'];
                }
                $page['blocks'][] = array('type'=>'content', 'section'=>'artist-link', 'content'=>$content);
            }

            if( isset($item['description']) && $item['description'] != '' ) {
                $page['blocks'][] = array('type'=>'content', 'section'=>'content', 'title'=>'', 'content'=>$item['description']);
            } else {
                $page['blocks'][] = array('type'=>'content', 'section'=>'content', 'title'=>'', 'content'=>$item['synopsis']);
            }

            //
            // Check if there is a standard message to display for this participant
            //
            if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.ags', 0x40) 
                && isset($item['message']) && $item['message'] != '' 
                ) {
                $page['blocks'][] = array('type'=>'content', 'section'=>'message', 'title'=>'', 'content'=>$item['message']);
            }

            //
            // Check if price should be shown
            //
            if( $item['inventory'] <= 0 ) {
                $page['blocks'][] = array('type'=>'content', 'section'=>'sold-out', 'title'=>'', 'content'=>'<b>Sold Out</b>');
            }
            elseif( ($item['flags']&0x09) == 0x01 ) {
                $price = $item; 
                $price['name'] = 'Price';
                $price['limited_units'] = 'yes';
                if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.sapos', 0x30000040) && ($item['flags']&0x04) == 0x04 ) {
                    $price['cart'] = 'yes';
                    $price['object'] = 'ciniki.ags.exhibititem';
                    $price['object_id'] = $eitem['exhibit_item_id'];

                    // Check inventory
                    if( $item['inventory'] <= 0 ) {
                        $price['limited_units'] = 'yes';
                        $price['units_available'] = 0;
                    } else {
                        $price['limited_units'] = 'yes';
                        $price['units_available'] = $eitem['inventory'];
                    }
                }
                $page['blocks'][] = array('type'=>'prices', 'section'=>'price-list', 'prices'=>array($price));
            }
            elseif( ($item['flags']&0x08) == 0 ) {
                $page['blocks'][] = array('type'=>'content', 'section'=>'content', 'title'=>'', 'content'=>'<b>Not for sale</b>');
            }


            //
            // Check if share buttons should be shown
            //
            if( (!isset($settings['page-exhibits-share-buttons']) || $settings['page-exhibits-share-buttons'] == 'yes') ) {
                $tags = array();
                $page['blocks'][] = array('type'=>'sharebuttons', 'section'=>'share', 'pagetitle'=>$item['name'], 'tags'=>$tags);
            }
                
            //
            // Add images if they exist
            //
            if( isset($item['images']) && count($item['images']) > 0 ) {
                $page['blocks'][] = array('type'=>'gallery', 'section'=>'gallery', 'title'=>'Additional Images',
                    'base_url'=>$base_url . "/gallery",
                    'images'=>$item['images']);
            }
        }
    }

    return array('stat'=>'ok', 'page'=>$page);
}
?>
