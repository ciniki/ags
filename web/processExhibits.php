<?php
//
// Description
// -----------
// This function will process a list of events, and format the html.
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure, similar to ciniki variable but only web specific information.
// events:          The array of events as returned by ciniki_events_web_list.
//
// Returns
// -------
//
function ciniki_ags_web_processExhibits($ciniki, $settings, $exhibits, $args) {

    $page_limit = 0;
    if( isset($args['limit']) ) {
        $page_limit = $args['limit'];
    }

    $content = "<table class='cilist'>\n"
        . "";
    $total = count($exhibits);
    $count = 0;
    foreach($exhibits as $eid => $exhibit) {
        if( $page_limit > 0 && $count >= $page_limit ) { $count++; break; }
        // Display the date
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
        if( $exhibit['end_year'] != '' && $exhibit['start_year'] != $exhibit['end_year'] ) {
            $exhibit_date .= "/" . $exhibit['end_year'];
        }
        $content .= "<tr><th><span class='cilist-category'>$exhibit_date</span>";
        if( $exhibit['location_name'] != '' ) {
            $content .= " <span class='cilist-subcategory'>" . $exhibit['location_name'] . "</span>";
        }
        $content .= "</th>"
            . "<td>";
        // Display the brief details
        $content .= "<table class='cilist-categories'><tbody>\n";
        $exhibit_url = $args['base_url'] . "/" . $exhibit['permalink'];

        // Setup the exhibitor image
        $content .= "<tr><td class='cilist-image' rowspan='3'>";
        if( isset($exhibit['image_id']) && $exhibit['image_id'] > 0 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'web', 'private', 'getScaledImageURL');
            $rc = ciniki_web_getScaledImageURL($ciniki, $exhibit['image_id'], 'thumbnail', '150', 0);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            $content .= "<div class='image-cilist-thumbnail'>";
            if( $exhibit_url != '' ) {
                $content .= "<a href='$exhibit_url' title=\"" . htmlspecialchars(strip_tags($exhibit['name'])) . "\"><img title='' alt=\"" . htmlspecialchars(strip_tags($exhibit['name'])) . "\" src='" . $rc['url'] . "' /></a>";
            } else {
                $content .= "<img title='' alt='" . htmlspecialchars(strip_tags($exhibit['name'])) . "' src='" . $rc['url'] . "' />";
            }
            $content .= "</div></aside>";
        }
        $content .= "</td>";

        // Setup the details
        $content .= "<td class='cilist-details'>";
        $content .= "<p class='cilist-title'>";
        if( $exhibit_url != '' ) {
            $content .= "<a href='$exhibit_url' title=\"" . htmlspecialchars(strip_tags($exhibit['name'])) . "\">" . $exhibit['name'] . "</a>";
        } else {
            $content .= $exhibit['name'];
        }
        $content .= "</p>";
        $content .= "</td></tr>";
        $content .= "<tr><td class='cilist-description'>";
        if( isset($exhibit['synopsis']) && $exhibit['synopsis'] != '' ) {
            $content .= "<span class='cilist-description'>" . $exhibit['synopsis'] . "</span>";
        }
        $content .= "</td></tr>";
        if( $exhibit_url != '' ) {
            $content .= "<tr><td class='cilist-more'><a href='$exhibit_url'>... more</a></td></tr>";
        } elseif( ($count+1) == $total || ($page_limit > 0 && ($count+1) >= $page_limit) ) {
            // Display a more for extra padding between lists
            $content .= "<tr><td class='cilist-more'></td></tr>";
        }
        $content .= "</tbody></table>";
        $content .= "</td></tr>";
        $count++;
    }
    $content .= "</table>\n"
        . "";

    //
    // Check to see if we need prev and next buttons
    //
    $nav_content = '';
    if( $page_limit > 0 && isset($args['base_url']) && $args['base_url'] != '' ) {
        $base_url = $args['base_url'];
        if( isset($args['category']) && $args['category'] != '' ) {
            $base_url .= '/category/' . $args['category'];
        }
        $prev = '';
        if( isset($args['page']) && $args['page'] > 1 ) {
            $prev .= "<a href='" . $base_url . "?page=" . ($args['page']-1) . "'>";
            array_push($ciniki['response']['head']['links'], array('rel'=>'prev', 'href'=>$base_url . "?page=" . ($args['page']-1)));
            if( isset($args['prev']) && $args['prev'] != '' ) {
                $prev .= $args['prev'];
            } else {
                $prev .= 'Prev';
            }
            $prev .= "</a>";
        }
        $next = '';
        if( isset($args['page']) && $count > $page_limit ) {
            $next .= "<a href='" . $base_url . "?page=" . ($args['page']+1) . "'>";
            array_push($ciniki['response']['head']['links'], array('rel'=>'next', 'href'=>$base_url . "?page=" . ($args['page']+1)));
            if( isset($args['prev']) && $args['prev'] != '' ) {
                $next .= $args['next'];
            } else {
                $next .= 'Next';
            }
            $next .= "</a>";
        }
        if( $next != '' || $prev != '' ) {
            $nav_content = "<nav class='content-nav'>"
                . "<span class='prev'>$next</span>"
                . "<span class='next'>$prev</span>"
                . "</nav>"
                . "";
        }
    }

    return array('stat'=>'ok', 'content'=>$content, 'nav'=>$nav_content);
}
?>
