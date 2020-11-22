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
function ciniki_ags_sapos_cartItemPaymentReceived($ciniki, $tnid, $customer, $args) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'ags', 'sapos', 'itemPaymentReceived');
    return ciniki_ags_sapos_itemPaymentReceived($ciniki, $tnid, $args);
}
?>
