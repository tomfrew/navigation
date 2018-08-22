<?php

namespace verbb\navigation\craftql\types;

use markhuot\CraftQL\Builders\InterfaceBuilder;
use markhuot\CraftQL\Types\ElementInterface;

class NavigationNodeInterface extends InterfaceBuilder {

    function boot() {
        $this->addIntField('id')->nonNull();
        $this->addStringField('url');
        $this->addStringField('title');
        $this->addBooleanField('enabled')->nonNull();
        $this->addStringField('status')->nonNull();
        $this->addBooleanField('newWindow');
        $this->addIntField('level');
        $this->addIntField('elementId');

        // TODO: add possible sub-types (asset, element, category)
        $this->addField('element')->type(ElementInterface::class);

        $this->addField('parent')->type(NavigationNode::class);
        $this->addField('ancestors')->lists()->type(NavigationNode::class);
        $this->addField('children')->lists()->type(NavigationNode::class);
        $this->addField('siblings')->lists()->type(NavigationNode::class);
    }

    function getResolveType() {
        return function ($element) {
            return $element;
        };
    }

}
