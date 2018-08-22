<?php

namespace verbb\navigation\craftql\types;

use markhuot\CraftQL\Builders\Schema;

class NavigationNode extends Schema {

    protected $interfaces = [
        NavigationNodeInterface::class,
        \markhuot\CraftQL\Types\ElementInterface::class,
    ];

    // function boot() {
    // }

    // function getName(): string {
    //     return 'NavigationNode';
    // }
}
