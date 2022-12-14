<?php
//
// Description
// ===========
// This function executes when a payment is received for an invoice or POS.
//
// Arguments
// =========
// 
// Returns
// =======
//
function ciniki_ags_sapos_itemPaymentReceived($ciniki, $tnid, $args) {

error_log(print_r($args,true));
    if( !isset($args['object']) || $args['object'] == '' 
        || !isset($args['object_id']) || $args['object_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.132', 'msg'=>'No item specified.'));
    }

    if( !isset($args['invoice_id']) || $args['invoice_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.133', 'msg'=>'No invoice specified.'));
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');

    //
    // Get the tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $dt = new DateTime('now', new DateTimeZone($intl_timezone));

    //
    // Load the tax rates
    //
    if( ciniki_core_checkModuleActive($ciniki, 'ciniki.taxes') ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'taxes', 'hooks', 'taxTypesRates');
        $rc = ciniki_taxes_hooks_taxTypesRates($ciniki, $tnid, array());
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.220', 'msg'=>'Unable to load taxes', 'err'=>$rc['err']));
        }
        $tax_types = isset($rc['types']) ? $rc['types'] : array();
    }

    if( $args['object'] == 'ciniki.ags.exhibititem' ) {
        //
        // Get the exhibit item
        //
        $strsql = "SELECT "
            . "eitems.id AS object_id, "
            . "eitems.exhibit_id, "
            . "items.id AS item_id, "
            . "items.code, "
            . "items.name AS description, "
            . "items.unit_amount, "
            . "items.unit_discount_amount, "
            . "items.unit_discount_percentage, "
            . "items.taxtype_id, "
            . "items.fee_percent, "
            . "items.flags, "
            . "eitems.inventory AS quantity, "
            . "exhibits.name AS exhibit_name "
            . "FROM ciniki_ags_exhibit_items AS eitems "
            . "INNER JOIN ciniki_ags_items AS items ON ("
                . "eitems.item_id = items.id "
                . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "INNER JOIN ciniki_ags_exhibits AS exhibits ON ("
                . "eitems.exhibit_id = exhibits.id "
                . "AND exhibits.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE eitems.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND eitems.id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' "
            . "ORDER BY items.code, items.name, items.notes "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.ags', 'item');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( !isset($rc['item']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.134', 'msg'=>'Unable to find item'));
        }
        $item = $rc['item'];

        //
        // Check if a donation receipt number should be generated
        //
        if( ciniki_core_checkModuleFlags($ciniki, 'ciniki.ags', 0x0100) && ($item['flags']&0x20) == 0x20 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'sapos', 'hooks', 'donationReceiptNumber');
            $rc = ciniki_sapos_hooks_donationReceiptNumber($ciniki, $tnid, array());
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.243', 'msg'=>'Unable to get receipt number', 'err'=>$rc['err']));
            }
            $receipt_number = $rc['receipt_number'];
        }

        //
        // Create the sales entry
        //
        if( $item['fee_percent'] != 0 ) {
            $tenant_amount = round(bcmul($args['total_amount'], $item['fee_percent'], 8), 2);
        } else {
            $tenant_amount = 0;
        }
        $tenant_tax_amount = 0;
        $exhibitor_tax_amount = 0;
        if( ciniki_core_checkModuleActive($ciniki, 'ciniki.taxes') 
            && $item['taxtype_id'] > 0 
            && isset($tax_types[$item['taxtype_id']]['rates']) 
            ) {
            //
            // Calculate taxes on this item
            //
            $tax_amount = 0;
            foreach($tax_types[$item['taxtype_id']]['rates'] as $rate) {
                if( $rate['item_percentage'] > 0 ) {
                    $tax_amount += ($args['total_amount'] * ($rate['item_percentage']/100));
                }
            }
            if( $tax_amount > 0 ) {
                $exhibitor_tax_amount = round($tax_amount, 2);
            }
            $args['total_amount'] += $exhibitor_tax_amount;
        }
        $sales_item = array(
            'item_id' => $item['item_id'],
            'exhibit_id' => $item['exhibit_id'],
            'invoice_id' => $args['invoice_id'],
            'flags' => 0x01,
            'sell_date' => $dt->format('Y-m-d'),
            'quantity' => $args['quantity'],
            'tenant_amount' => $tenant_amount,
            'tenant_tax_amount' => $tenant_tax_amount,
            'exhibitor_amount' => bcsub($args['total_amount'], $tenant_amount, 6),
            'exhibitor_tax_amount' => $exhibitor_tax_amount,
            'total_amount' => $args['total_amount'],
            'receipt_number' => isset($receipt_number) ? $receipt_number : '',
            );
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
        $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.ags.itemsale', $sales_item, 0x04);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.135', 'msg'=>'Unable to add sales item', 'err'=>$rc['err']));
        }
        $sale_id = $rc['id'];

        //
        // Update inventory
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
        $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'ciniki.ags.exhibititem', $args['object_id'], array(
            'inventory' => ($item['quantity'] - $args['quantity']),
            ), 0x04);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.136', 'msg'=>'Unable to update inventory', 'err'=>$rc['err']));
        }

        if( isset($args['quantity']) ) {
            //
            // Add Log entry
            //
            $dt = new DateTime('now', new DateTimezone('UTC'));
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
            $rc = ciniki_core_objectAdd($ciniki, $tnid, 'ciniki.ags.itemlog', array(
                'item_id' => $item['item_id'],
                'action' => 60,
                'actioned_id' => $item['exhibit_id'],
                'quantity' => -($args['quantity']),
                'log_date' => $dt->format('Y-m-d H:i:s'),
                'user_id' => $ciniki['session']['user']['id'],
                'notes' => '',
                ), 0x04);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.ags.180', 'msg'=>'Unable to add log', 'err'=>$rc['err']));
            }
        }
        //
        // Return new object, object_id
        //
        return array('stat'=>'ok', 'object'=>'ciniki.ags.itemsale', 'object_id'=>$sale_id);
    }

    return array('stat'=>'ok');
}
?>
