<?php

namespace App\Vars;

class GridLayout {
    
    const MAX_WIDTH = 2048;
    const MAX_HEIGHT = 1536;

    const one = [
        'header' => [
            'order' => '1',
            'coords' => '1.4',
            'mainAxis' => 1,
            'crossAxis' => 4,
            'data_requied' => false,
        ],
        'leftbar' => [
            'order' => '2',
            'coords' => '5.1',
            'mainAxis' => 5,
            'crossAxis' => 1,
            'data_requied' => true,
        ],
        'one' => [
            'order' => '3',
            'coords' => '2.2',
            'mainAxis' => 2,
            'crossAxis' => 2,
            'data_requied' => true,
        ],
        'two' => [
            'order' => '4',
            'coords' => '2.1',
            'mainAxis' => 2,
            'crossAxis' => 1,
            'data_requied' => true,
        ],
        'three' => [
            'order' => '5',
            'coords' => '3.3',
            'mainAxis' => 3,
            'crossAxis' => 3,
            'data_requied' => true,
        ],
    ];

    const two = [
        'header' => [
            'order' => '1',
            'coords' => '1.4',
            'mainAxis' => 1,
            'crossAxis' => 4,
            'data_requied' => false
        ],
        'leftbar' => [
            'order' => '2',
            'coords' => '5.1',
            'mainAxis' => 5,
            'crossAxis' => 1,
            'data_requied' => true
        ],
        'one' => [
            'order' => '3',
            'coords' => '2.3',
            'mainAxis' => 2,
            'crossAxis' => 3,
            'data_requied' => true
        ],
        'two' => [
            'order' => '4',
            'coords' => '3.2',
            'mainAxis' => 3,
            'crossAxis' => 2,
            'data_requied' => true
        ],
        'three' => [
            'order' => '5',
            'coords' => '3.1',
            'mainAxis' => 3,
            'crossAxis' => 1,
            'data_requied' => true
        ]
    ];

    const three = [
        'header' => [
            'order' => '1',
            'coords' => '1.4',
            'mainAxis' => 1,
            'crossAxis' => 4,
            'data_requied' => false
        ],
        'one' => [
            'order' => '2',
            'coords' => '2.1',
            'mainAxis' => 2,
            'crossAxis' => 1,
            'data_requied' => true
        ],
        'two' => [
            'order' => '3',
            'coords' => '2.2',
            'mainAxis' => 2,
            'crossAxis' => 2,
            'data_requied' => true
        ],
        'three' => [
            'order' => '4',
            'coords' => '2.1',
            'mainAxis' => 2,
            'crossAxis' => 1,
            'data_requied' => true
        ],
        'four' => [
            'order' => '5',
            'coords' => '2.2',
            'mainAxis' => 2,
            'crossAxis' => 2,
            'data_requied' => true
        ],
        'five' => [
            'order' => '6',
            'coords' => '2.2',
            'mainAxis' => 2,
            'crossAxis' => 2,
            'data_requied' => true
        ],
        'six' => [
            'order' => '7',
            'coords' => '1.2',
            'mainAxis' => 1,
            'crossAxis' => 2,
            'data_requied' => true
        ],
        'seven' => [
            'order' => '8',
            'coords' => '1.2',
            'mainAxis' => 1,
            'crossAxis' => 2,
            'data_requied' => true
        ],
    ];
}