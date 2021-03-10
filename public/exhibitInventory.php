<?php
//
// Description
// ===========
// This method will return all the information about an exhibit.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:             The ID of the tenant the seller is attached to.
// exhibit_id:       The ID of the market to get the details for.
// 
// Returns
// -------
//
function ciniki_ags_exhibitInventory($ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'exhibit_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Exhibit'),
        'output'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Format'),
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];
    
    //  
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'checkAccess');
    $rc = ciniki_ags_checkAccess($ciniki, $args['tnid'], 'ciniki.ags.exhibitInventory'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $modules = $rc['modules'];

    //
    // Load the tenant intl settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki);

    //
    // Load exhibit maps
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'private', 'maps');
    $rc = ciniki_ags_maps($ciniki, $modules);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];

    //
    // Get the exhibit name
    //
    $strsql = "SELECT name, permalink "
        . "FROM ciniki_ags_exhibits "
        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'exhibit');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['exhibit']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.13', 'msg'=>'Exhibit does not exist'));
    }
    $exhibit_name = $rc['exhibit']['name'];
    $exhibit_permalink = $rc['exhibit']['permalink'];

    //
    // Get the working url
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'web', 'private', 'indexObjectBaseURL');
    $rc = ciniki_web_indexObjectBaseURL($ciniki, $args['tnid'], 'ciniki.ags.exhibit');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.226', 'msg'=>'Unable to find exhibit URL', 'err'=>$rc['err']));
    }
    if( isset($rc['base_url']) ) {
        $base_url = $rc['base_url'];
    } else {
        //
        // Base URL not found, check for the exhibit type base url
        //
        $strsql = "SELECT permalink "
            . "FROM ciniki_ags_exhibit_tags "
            . "WHERE exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
            . "AND tag_type = 20 "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'tag');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.227', 'msg'=>'Unable to load tag', 'err'=>$rc['err']));
        }
        $tags = isset($rc['rows']) ? $rc['rows'] : array();
        foreach($tags as $tag) {
            $rc = ciniki_web_indexObjectBaseURL($ciniki, $args['tnid'], 'ciniki.ags.' . $tag['permalink']);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.226', 'msg'=>'Unable to find exhibit URL', 'err'=>$rc['err']));
            }
            if( isset($rc['base_url']) ) {
                $base_url = $rc['base_url'];
                break;
            }
        }
    }
    if( isset($base_url) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'web', 'private', 'lookupTenantURL');
        $rc = ciniki_web_lookupTenantURL($ciniki, $args['tnid']);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.228', 'msg'=>'Unable to get tenant URL', 'err'=>$rc['err']));
        }
        $base_url = $rc['secure_url'] . $base_url . '/' . $exhibit_permalink;
    }

    //
    // Get the list of exhibits items 
    //
    $strsql = "SELECT items.id, "
        . "exhibitors.display_name, "
        . "items.permalink, "
        . "items.code, "
        . "items.name, "
        . "items.medium, "
        . "items.size, "
        . "items.unit_amount, "
        . "items.fee_percent, "
        . "IFNULL(sales.sell_date, '') AS sell_date, "
        . "IFNULL(sales.total_amount, '') AS total_amount, "
        . "IFNULL(sales.tenant_amount, '') AS tenant_amount, "
        . "IFNULL(sales.exhibitor_amount, '') AS exhibitor_amount, "
        . "items.notes "
        . "FROM ciniki_ags_exhibit_items AS eitems "
        . "INNER JOIN ciniki_ags_items AS items ON ("
            . "eitems.item_id = items.id "
            . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "LEFT JOIN ciniki_ags_item_sales AS sales ON ("
            . "items.id = sales.id "
            . "AND sales.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "LEFT JOIN ciniki_ags_exhibitors AS exhibitors ON ("
            . "items.exhibitor_id = exhibitors.id "
            . "AND exhibitors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND eitems.exhibit_id = '" . ciniki_core_dbQuote($ciniki, $args['exhibit_id']) . "' "
        . "ORDER BY items.code, items.name "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.marketplaces', array(
        array('container'=>'items', 'fname'=>'id',
            'fields'=>array('id', 'display_name', 'code', 'name', 'medium', 'size', 'unit_amount', 'fee_percent',
                'sell_date', 'total_amount', 'tenant_amount', 'exhibitor_amount', 'notes', 'permalink')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['items']) ) {
        $items = array();   
    } else {
        $items = $rc['items'];
    }

    //
    // Start an excel file
    //
    ini_set('memory_limit', '4192M');
    require($ciniki['config']['core']['lib_dir'] . '/PHPExcel/PHPExcel.php');
    $objPHPExcel = new PHPExcel();
    $sheet = $objPHPExcel->setActiveSheetIndex(0);
    $sheet->setTitle(substr(str_replace(":", '-', $exhibit_name), 0, 31));

    //
    // Headers
    //
    $i = 0;
    $sheet->setCellValueByColumnAndRow($i++, 1, 'Code', false);
    $sheet->setCellValueByColumnAndRow($i++, 1, 'Name', false);
    $sheet->setCellValueByColumnAndRow($i++, 1, 'Medium', false);
    $sheet->setCellValueByColumnAndRow($i++, 1, 'Size', false);
    $sheet->setCellValueByColumnAndRow($i++, 1, 'Exhibitor', false);
    $sheet->setCellValueByColumnAndRow($i++, 1, 'Price', false);
    $sheet->setCellValueByColumnAndRow($i++, 1, 'Fee %', false);
    $sheet->setCellValueByColumnAndRow($i++, 1, 'Sell Date', false);
    $sheet->setCellValueByColumnAndRow($i++, 1, 'Sell Price', false);
    $sheet->setCellValueByColumnAndRow($i++, 1, 'Fees', false);
    $sheet->setCellValueByColumnAndRow($i++, 1, 'Amount', false);
    $sheet->setCellValueByColumnAndRow($i++, 1, 'Notes', false);
    if( isset($base_url) ) {
        $sheet->setCellValueByColumnAndRow($i++, 1, 'URL', false);
    }

    $sheet->getStyle('A1')->getFont()->setBold(true);
    $sheet->getStyle('B1')->getFont()->setBold(true);
    $sheet->getStyle('C1')->getFont()->setBold(true);
    $sheet->getStyle('D1')->getFont()->setBold(true);
    $sheet->getStyle('E1')->getFont()->setBold(true);
    $sheet->getStyle('F1')->getFont()->setBold(true);
    $sheet->getStyle('G1')->getFont()->setBold(true);
    $sheet->getStyle('H1')->getFont()->setBold(true);
    $sheet->getStyle('I1')->getFont()->setBold(true);
    $sheet->getStyle('J1')->getFont()->setBold(true);
    $sheet->getStyle('K1')->getFont()->setBold(true);
    $sheet->getStyle('L1')->getFont()->setBold(true);
    $sheet->getStyle('M1')->getFont()->setBold(true);
    if( isset($base_url) ) {
        $sheet->getStyle('N1')->getFont()->setBold(true);
    }

    $row = 2;
    foreach($items as $item) {
        $i = 0;
        $sheet->setCellValueByColumnAndRow($i++, $row, $item['code']);
        $sheet->setCellValueByColumnAndRow($i++, $row, $item['name']);
        $sheet->setCellValueByColumnAndRow($i++, $row, $item['medium']);
        $sheet->setCellValueByColumnAndRow($i++, $row, $item['size']);
        $sheet->setCellValueByColumnAndRow($i++, $row, $item['display_name']);
        $sheet->setCellValueByColumnAndRow($i++, $row, $item['unit_amount']);
        if( $item['fee_percent'] > 0 ) {
            $sheet->setCellValueByColumnAndRow($i++, $row, ($item['fee_percent']/100));
        } else {
            $sheet->setCellValueByColumnAndRow($i++, $row, $item['fee_percent']);
        }
        if( $item['sell_date'] != '' && $item['sell_date'] != '0' ) {
            $sheet->setCellValueByColumnAndRow($i++, $row, $item['sell_date']);
        } else {
            $sheet->setCellValueByColumnAndRow($i++, $row, '');
        }
        if( $item['total_amount'] != '' && $item['total_amount'] != 0 ) {
            $sheet->setCellValueByColumnAndRow($i++, $row, $item['total_amount']);
            $sheet->setCellValueByColumnAndRow($i++, $row, $item['tenant_amount']);
            $sheet->setCellValueByColumnAndRow($i++, $row, $item['exhibitor_amount']);
        } else {
            $sheet->setCellValueByColumnAndRow($i++, $row, '');
            $sheet->setCellValueByColumnAndRow($i++, $row, '');
            $sheet->setCellValueByColumnAndRow($i++, $row, '');
        }
        $sheet->setCellValueByColumnAndRow($i++, $row, $item['notes']);
        if( isset($base_url) ) {
            $sheet->setCellValueByColumnAndRow($i++, $row, $base_url . '/item/' . $item['permalink']);
        }

        $row++;
    }
    $sheet->getStyle('D2:D' . ($row-1))->getNumberFormat()->setFormatCode("$#,##0.00");
    $sheet->getStyle('E2:E' . ($row-1))->getNumberFormat()->setFormatCode("0%");
    $sheet->getStyle('G2:G' . ($row-1))->getNumberFormat()->setFormatCode("$#,##0.00");
    $sheet->getStyle('H2:H' . ($row-1))->getNumberFormat()->setFormatCode("$#,##0.00");
    $sheet->getStyle('I2:I' . ($row-1))->getNumberFormat()->setFormatCode("$#,##0.00");

    PHPExcel_Shared_Font::setAutoSizeMethod(PHPExcel_Shared_Font::AUTOSIZE_METHOD_EXACT);
    $sheet->getColumnDimension('A')->setAutoSize(true);
    $sheet->getColumnDimension('B')->setAutoSize(true);
    $sheet->getColumnDimension('C')->setAutoSize(true);
    $sheet->getColumnDimension('D')->setAutoSize(true);
    $sheet->getColumnDimension('E')->setAutoSize(true);
    $sheet->getColumnDimension('F')->setAutoSize(true);
    $sheet->getColumnDimension('G')->setAutoSize(true);
    $sheet->getColumnDimension('H')->setAutoSize(true);
    $sheet->getColumnDimension('I')->setAutoSize(true);
    $sheet->getColumnDimension('J')->setAutoSize(true);
    $sheet->getColumnDimension('K')->setAutoSize(true);
    $sheet->getColumnDimension('L')->setAutoSize(true);

    //
    // Output the excel
    //
    header('Content-Type: application/vnd.ms-excel');
    $filename = preg_replace('/[^a-zA-Z0-9\-]/', '', $exhibit_name);
    header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');

    return array('stat'=>'exit');
}
?>
