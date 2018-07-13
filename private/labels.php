<?php
//
// Description
// -----------
// The definitions for different label sheets
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_ags_labels($ciniki, $tnid, $format='all') {

    //
    // Define the start of each row/col
    //
    $labels = array();
    $labels['avery5167'] = array(
        'name'=>((isset($names['labels-avery5167-name']) && $names['labels-avery5167-name'] != '') ? $names['labels-avery5167-name'] . ' - ' : '') . '1/2" x 1 3/4" - Avery Template 5167',
        'rows'=>array(
            '1'=>array('y'=>11.5),
            '2'=>array('y'=>24.2),
            '3'=>array('y'=>36.9),
            '4'=>array('y'=>49.6),
            '5'=>array('y'=>62.3),
            '6'=>array('y'=>75.0),
            '7'=>array('y'=>87.7),
            '8'=>array('y'=>100.4),
            '9'=>array('y'=>113.1),
            '10'=>array('y'=>125.8),
            '11'=>array('y'=>138.5),
            '12'=>array('y'=>151.2),
            '13'=>array('y'=>163.9),
            '14'=>array('y'=>176.6),
            '15'=>array('y'=>189.3),
            '16'=>array('y'=>202.0),
            '17'=>array('y'=>214.7),
            '18'=>array('y'=>227.4),
            '19'=>array('y'=>240.1),
            '20'=>array('y'=>252.8),
            ),
        'cols'=>array(
            '1'=>array('x'=>7),
            '2'=>array('x'=>59),
            '3'=>array('x'=>111),
            '4'=>array('x'=>163),
            ),
        'cell'=>array(
            'width'=>44,
            'height'=>12.7,
            ),
        );

    return array('stat'=>'ok', 'labels'=>$labels);
}
?>
