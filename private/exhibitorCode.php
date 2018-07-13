<?php
//
// Description
// -----------
// This function will lookup the customer details in ciniki customers module 
// and attempt to make a unique code for them.
//
// This is used by the importer and when adding a new exhibitor
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to add the Exhibitor to.
//
// Returns
// -------
//
function ciniki_ags_exhibitorCode(&$ciniki, $tnid, $customer_id) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'exhibitorCodeCheck');

    //
    // Lookup the customer
    //
    $strsql = "SELECT type, first, middle, last, company, display_name "
        . "FROM ciniki_customers "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $customer_id) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'customer');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.7', 'msg'=>'Unable to load customer', 'err'=>$rc['err']));
    }
    if( !isset($rc['customer']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.8', 'msg'=>'Unable to find requested customer'));
    }
    $customer = $rc['customer'];
    $code = '';

    //
    // Remove special characters that shouldn't be in the code
    //
    $customer['first'] = preg_replace("/[^A-Za-z0-9]/", '', $customer['first']);
    $customer['middle'] = preg_replace("/[^A-Za-z0-9]/", '', $customer['middle']);
    $customer['last'] = preg_replace("/[^A-Za-z0-9]/", '', $customer['last']);
    $customer['company'] = preg_replace("/[^A-Za-z0-9]/", '', $customer['company']);

    //
    // The order to try building a code
    // 
    // use company name, if specified as a company
    // use first/last 
    // use first
    // use last
    // use company
    // use display_name
    //
    if( $customer['type'] == 2 && $customer['company'] != '' ) {
        // Get the company initials
        $code = preg_replace("#\s*([a-z])[a-z]*#i", "$1", $customer['company']);
        $code = strtoupper(substr($code, 0, 4));
        // Too short, use the first letters of company
        if( strlen($code) < 3 ) {
            $code = strtoupper(substr($customer['company'], 0, 3));
            // Code exists, try first 4 letters
            $rc = ciniki_ags_exhibitorCodeCheck($ciniki, $tnid, $code);
            if( $rc['stat'] == 'exists' ) {
                $code = strtoupper(substr($customer['company'], 0, 4));
            }
        } else {
            // Code exists, try next 4 initials
            $rc = ciniki_ags_exhibitorCodeCheck($ciniki, $tnid, $code);
            if( $rc['stat'] == 'exists' ) {
                $code = preg_replace("#\s*([a-z])[a-z]*#i", "$1", $customer['company']);
                $code = strtoupper(substr($code, 1, 4));
                // Code exists, try first 2 initials
                $rc = ciniki_ags_exhibitorCodeCheck($ciniki, $tnid, $code);
                if( $rc['stat'] == 'exists' ) {
                    $code = preg_replace("#\s*([a-z])[a-z]*#i", "$1", $customer['company']);
                    $code = strtoupper(substr($code, 0, 2));
                }
            }
        }
    } elseif( $customer['first'] != '' && $customer['last'] != '' ) {
        $code = strtoupper(substr($customer['first'], 0, 1));
        if( isset($customer['middle']) && $customer['middle'] != '' ) {
            $code .= strtoupper(substr($customer['middle'], 0, 1));
        }
        if( isset($customer['last']) && $customer['last'] != '' ) {
            $code .= strtoupper(substr($customer['last'], 0, 1));
        }
        $rc = ciniki_ags_exhibitorCodeCheck($ciniki, $tnid, $code);
        if( $rc['stat'] == 'exists' ) {
            $code .= strtoupper(substr($customer['last'], 1, 1));
        }
    } elseif( $customer['last'] != '' ) {
        $code = strtoupper(substr($customer['last'], 0, 3));
        $rc = ciniki_ags_exhibitorCodeCheck($ciniki, $tnid, $code);
        if( $rc['stat'] == 'exists' ) {
            $code = strtoupper(substr($customer['last'], 0, 4));
        }
    } elseif( $customer['first'] != '' ) {
        $code = strtoupper(substr($customer['first'], 0, 3));
        $rc = ciniki_ags_exhibitorCodeCheck($ciniki, $tnid, $code);
        if( $rc['stat'] == 'exists' ) {
            $code = strtoupper(substr($customer['first'], 0, 4));
        }
    } elseif( $customer['company'] != '' ) {
        // Get the company initials
        $code = preg_replace("#\s*([a-z])[a-z]*#i", "$1", $customer['company']);
        $code = strtoupper(substr($code, 0, 4));
        // Too short, use the first letters of company
        if( strlen($code) < 3 ) {
            $code = strtoupper(substr($customer['company'], 0, 3));
            // Code exists, try first 4 letters
            $rc = ciniki_ags_exhibitorCodeCheck($ciniki, $tnid, $code);
            if( $rc['stat'] == 'exists' ) {
                $code = strtoupper(substr($customer['company'], 0, 4));
            }
        } else {
            // Code exists, try next 4 initials
            $rc = ciniki_ags_exhibitorCodeCheck($ciniki, $tnid, $code);
            if( $rc['stat'] == 'exists' ) {
                $code = preg_replace("#\s*([a-z])[a-z]*#i", "$1", $customer['company']);
                $code = strtoupper(substr($code, 1, 4));
                // Code exists, try first 2 initials
                $rc = ciniki_ags_exhibitorCodeCheck($ciniki, $tnid, $code);
                if( $rc['stat'] == 'exists' ) {
                    $code = preg_replace("#\s*([a-z])[a-z]*#i", "$1", $customer['company']);
                    $code = strtoupper(substr($code, 0, 2));
                }
            }
        }
    } elseif( $customer['display_name'] != '' ) {
        $code = preg_replace("#\s*([a-z])[a-z]*#i", "$1", $customer['display_name']);
        $code = strtoupper(substr($code, 0, 3));
        // Too short, use first 3 letters
        if( strlen($code) < 2 ) {
            $code = strtoupper(substr($customer['display_name'], 0, 3));
            // Code exists, try first 4 letters
            $rc = ciniki_ags_exhibitorCodeCheck($ciniki, $tnid, $code);
            if( $rc['stat'] == 'exists' ) {
                $code = strtoupper(substr($customer['company'], 0, 4));
            }
        } else {
            // Code exists, try first 3 letters
            $rc = ciniki_ags_exhibitorCodeCheck($ciniki, $tnid, $code);
            if( $rc['stat'] == 'exists' ) {
                $code = strtoupper(substr($customer['display_name'], 0, 3));
                // Code exists, try first 4 letters
                $rc = ciniki_ags_exhibitorCodeCheck($ciniki, $tnid, $code);
                if( $rc['stat'] == 'exists' ) {
                    $code = strtoupper(substr($customer['display_name'], 0, 4));
                }
            }
        }
    }

    return array('stat'=>'ok', 'code'=>$code);
}
?>
