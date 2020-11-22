<?php
//
// Description
// -----------
// This function returns the list of objects for the module.
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_ags_objects(&$ciniki) {
    //
    // Build the objects
    //
    $objects = array();
    $objects['location'] = array(
        'name' => 'Location',
        'sync' => 'yes',
        'o_name' => 'location',
        'o_container' => 'locations',
        'table' => 'ciniki_ags_locations',
        'fields' => array(
            'name' => array('name'=>'Name'),
            'permalink' => array('name'=>'Permalink', 'default'=>''),
            'category' => array('name'=>'Category', 'default'=>''),
            'flags' => array('name'=>'Options', 'default'=>'0'),
            'address1' => array('name'=>'Address Line 1', 'default'=>''),
            'address2' => array('name'=>'Address Line 2', 'default'=>''),
            'city' => array('name'=>'City', 'default'=>''),
            'province' => array('name'=>'Province', 'default'=>''),
            'postal' => array('name'=>'Postal', 'default'=>''),
            'country' => array('name'=>'Country', 'default'=>''),
            'latitude' => array('name'=>'Latitude', 'default'=>''),
            'longitude' => array('name'=>'Longitude', 'default'=>''),
            'notes' => array('name'=>'Notes', 'default'=>''),
            'primary_image_id' => array('name'=>'Primary Image', 'default'=>'0'),
            'synopsis' => array('name'=>'Synopsis', 'default'=>''),
            'description' => array('name'=>'Description', 'default'=>''),
            ),
        'history_table' => 'ciniki_ags_history',
        );
    $objects['exhibit'] = array(
        'name' => 'Exhibit',
        'sync' => 'yes',
        'o_name' => 'exhibit',
        'o_container' => 'exhibits',
        'table' => 'ciniki_ags_exhibits',
        'fields' => array(
            'name' => array('name'=>'Name'),
            'permalink' => array('name'=>'Permalink'),
            'location_id' => array('name'=>'Location', 'ref'=>'ciniki.ags.locations', 'default'=>'0'),
            'status' => array('name'=>'Status', 'default'=>'50'),
            'flags' => array('name'=>'Options', 'default'=>'0'),
            'start_date' => array('name'=>'', 'default'=>''),
            'end_date' => array('name'=>'', 'default'=>''),
            'primary_image_id' => array('name'=>'', 'ref'=>'ciniki.images.image', 'default'=>'0'),
            'synopsis' => array('name'=>'', 'default'=>''),
            'description' => array('name'=>'', 'default'=>''),
            ),
        'history_table' => 'ciniki_ags_history',
        );
    $objects['exhibittag'] = array(
        'name' => 'Exhibit Tag',
        'sync' => 'yes',
        'o_name' => 'exhibittag',
        'o_container' => 'exhibittags',
        'table' => 'ciniki_ags_exhibit_tags',
        'fields' => array(
            'exhibit_id' => array('name'=>'Exhibit', 'ref'=>'ciniki.ags.exhibit'),
            'tag_type' => array('name'=>'Type'),
            'tag_name' => array('name'=>'Name'),
            'permalink' => array('name'=>'Permalink'),
            ),
        'history_table' => 'ciniki_ags_history',
        );
    $objects['exhibititem'] = array(
        'name' => 'Exhibit Item',
        'sync' => 'yes',
        'o_name' => 'exhibititem',
        'o_container' => 'exhibititems',
        'table' => 'ciniki_ags_exhibit_items',
        'fields' => array(
            'exhibit_id' => array('name'=>'Exhibit', 'ref'=>'ciniki.ags.exhibit'),
            'item_id' => array('name'=>'Item', 'ref'=>'ciniki.ags.item'),
            'inventory' => array('name'=>'Inventory'),
            'fee_percent' => array('name'=>'Fee', 'default'=>'0'),
            ),
        'history_table' => 'ciniki_ags_history',
        );
    $objects['exhibitor'] = array(
        'name' => 'Exhibitor',
        'sync' => 'yes',
        'o_name' => 'exhibitor',
        'o_container' => 'exhibitors',
        'table' => 'ciniki_ags_exhibitors',
        'fields' => array(
            'customer_id' => array('name'=>'Customer', 'ref'=>'ciniki.customers.customer_id'),
            'display_name_override' => array('name'=>'Override Name', 'default'=>''),
            'display_name' => array('name'=>'Name', 'default'=>''),
            'permalink' => array('name'=>'Permalink'),
            'code' => array('name'=>'Code'),
            'status' => array('name'=>'Status', 'default'=>'30'),
            'flags' => array('name'=>'Options', 'default'=>'0'),
            ),
        'history_table' => 'ciniki_ags_history',
        );
    $objects['item'] = array(
        'name' => 'Item',
        'sync' => 'yes',
        'o_name' => 'item',
        'o_container' => 'items',
        'table' => 'ciniki_ags_items',
        'fields' => array(
            'exhibitor_id' => array('name'=>'Exhibitor', 'ref'=>'ciniki.ags.exhibitor'),
            'exhibitor_code' => array('name'=>'Code', 'default'=>''),
            'code' => array('name'=>'Code'),
            'name' => array('name'=>'Name'),
            'permalink' => array('name'=>'Permalink'),
            'status' => array('name'=>'Status', 'default'=>'30'),
            'flags' => array('name'=>'Options', 'default'=>'0'),
            'unit_amount' => array('name'=>'Price', 'default'=>'0'),
            'unit_discount_amount' => array('name'=>'Discount Amount', 'default'=>'0'),
            'unit_discount_percentage' => array('name'=>'Discount Percent', 'default'=>'0'),
            'fee_percent' => array('name'=>'Fee Percent', 'default'=>'0'),
            'taxtype_id' => array('name'=>'Tax', 'ref'=>'ciniki.taxes.type', 'default'=>'0'),
            'shipping_profile_id' => array('name'=>'Shipping Profile', 'ref'=>'ciniki.sapos.shippingprofile', 'default'=>'0'),
            'primary_image_id' => array('name'=>'Image', 'ref'=>'ciniki.images.image', 'default'=>'0'),
            'synopsis' => array('name'=>'Synopsis', 'default'=>''),
            'description' => array('name'=>'Description', 'default'=>''),
            'creation_year' => array('name'=>'Creation Year', 'default'=>''),
            'medium' => array('name'=>'Medium', 'default'=>''),
            'size' => array('name'=>'Size', 'default'=>''),
            'current_condition' => array('name'=>'Condition', 'default'=>''),
            'tag_info' => array('name'=>'Tag Info', 'default'=>''),
            'notes' => array('name'=>'Notes', 'default'=>''),
            ),
        'history_table' => 'ciniki_ags_history',
        );
    $objects['itemsale'] = array(
        'name' => 'Item Sale',
        'sync' => 'yes',
        'o_name' => 'sale',
        'o_container' => 'sales',
        'table' => 'ciniki_ags_item_sales',
        'fields' => array(
            'item_id' => array('name'=>'Item', 'ref'=>'ciniki.ags.item'),
            'exhibit_id' => array('name'=>'Exhibit', 'ref'=>'ciniki.ags.exhibit'),
            'invoice_id' => array('name'=>'Invoice', 'ref'=>'ciniki.sapos.invoice'),
            'flags' => array('name'=>'Options', 'default'=>'0'),
            'sell_date' => array('name'=>'Sell Date', 'default'=>''),
            'quantity' => array('name'=>'Quantity', 'default'=>'1'),
            'tenant_amount' => array('name'=>'Business Amount', 'default'=>'0'),
            'exhibitor_amount' => array('name'=>'Exhibitor Amount', 'default'=>'0'),
            'total_amount' => array('name'=>'Total Sale Amount', 'default'=>'0'),
            ),
        'history_table' => 'ciniki_ags_history',
        );
    $objects['itemtag'] = array(
        'name' => 'Item Tag',
        'sync' => 'yes',
        'o_name' => 'itemtag',
        'o_container' => 'itemtags',
        'table' => 'ciniki_ags_item_tags',
        'fields' => array(
            'item_id' => array('name'=>'Item', 'ref'=>'ciniki.ags.item'),
            'tag_type' => array('name'=>'Type'),
            'tag_name' => array('name'=>'Name'),
            'permalink' => array('name'=>'Permalink'),
            ),
        'history_table' => 'ciniki_ags_history',
        );
    $objects['itemimage'] = array(
        'name' => 'Item Image',
        'sync' => 'yes',
        'o_name' => 'itemimage',
        'o_container' => 'itemimages',
        'table' => 'ciniki_ags_item_images',
        'fields' => array(
            'item_id' => array('name'=>'Item', 'ref'=>'ciniki.ags.item'),
            'name' => array('name'=>'Name'),
            'permalink' => array('name'=>'Permalink'),
            'flags' => array('name'=>'Options', 'default'=>'0'),
            'sequence' => array('name'=>'Order', 'default'=>'1'),
            'image_id' => array('name'=>'Image', 'ref'=>'ciniki.images.image'),
            'description' => array('name'=>'Description', 'default'=>''),
            ),
        'history_table' => 'ciniki_ags_history',
        );
    $objects['itemlog'] = array(
        'name' => 'Item Log',
        'sync' => 'yes',
        'o_name' => 'itemlog',
        'o_container' => 'itemlogs',
        'table' => 'ciniki_ags_item_logs',
        'fields' => array(
            'item_id' => array('name'=>'Item', 'ref'=>'ciniki.ags.item'),
            'action' => array('name'=>'Action', 'default'=>0),
            'actioned_id' => array('name'=>'Actioned ID', 'default'=>0),
            'quantity' => array('name'=>'Quantity', 'default'=>0),
            'log_date' => array('name'=>'Log Date', 'default'=>''),
            'user_id' => array('name'=>'User', 'default'=>0),
            'notes' => array('name'=>'Notes', 'default'=>''),
            ),
        'history_table' => 'ciniki_ags_history',
        );
    $objects['participant'] = array(
        'name' => 'Participant',
        'sync' => 'yes',
        'o_name' => 'participant',
        'o_container' => 'participants',
        'table' => 'ciniki_ags_participants',
        'fields' => array(
            'exhibit_id' => array('name'=>'Exhibit', 'ref'=>'ciniki.ags.exhibit'),
            'exhibitor_id' => array('name'=>'Exhibitor', 'ref'=>'ciniki.ags.exhibitor'),
            'status' => array('name'=>'Status', 'default'=>'10'),
            'flags' => array('name'=>'Options', 'default'=>'0'),
            'notes' => array('name'=>'Notes', 'default'=>''),
            ),
        'history_table' => 'ciniki_ags_history',
        );
    //
    return array('stat'=>'ok', 'objects'=>$objects);
}
?>
