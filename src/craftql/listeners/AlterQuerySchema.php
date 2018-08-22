<?php

namespace verbb\navigation\craftql\listeners;

use Craft;

use verbb\navigation\Navigation;
use verbb\navigation\craftql\types\NavigationNode;
use verbb\navigation\records\Nav as NavRecord;

class AlterQuerySchema
{
    /**
     * Handle the request for the schema
     *
     * @param \markhuot\CraftQL\Events\AlterQuerySchema $event
     * @return void
     */
    function handle(\markhuot\CraftQL\Events\AlterQuerySchema $event) {
        $event->handled = true;

        $field = $event->query->addField('navigation')
            ->lists()
            ->type(NavigationNode::class)

            // $context, $info…should I be using these?
            ->resolve(function ($root, $args, $context, $info){
                $siteId = $args['siteId'] ?? null;
                $navId = $args['id'] ?? null;
                $siteHandle = $args['site'] ?? null;
                $navHandle = $args['handle'] ?? null;

                if ($siteHandle) {
                    $siteId = Craft::$app->getSites()->getSiteByHandle($siteHandle)->id;
                }

                if ($navHandle) {

                    // TODO: No getNavByHandle method
                    // https://github.com/verbb/navigation/issues/24
                    $navId = $record = NavRecord::find()
                        ->where(['handle' => $navHandle])
                        ->one()
                        ->id;
                }

                return Navigation::getInstance()->nodes->getNodesForNav($navId, $siteId);
            });

        // TODO: how do I make nav OR navId required
        // TODO: Split these into a behavior and `->use(new NavigationNodeQueryArguments)`
        $field->addStringArgument('handle');
        $field->addStringArgument('site');
        $field->addIntArgument('id');
        $field->addIntArgument('siteId');

    }
}
