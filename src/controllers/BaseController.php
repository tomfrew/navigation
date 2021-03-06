<?php
namespace verbb\navigation\controllers;

use verbb\navigation\Navigation;
use verbb\navigation\migrations\AmNavPlugin;

use Craft;
use craft\web\Controller;

class BaseController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionMigrate()
    {
        // Backup!
        Craft::$app->getDb()->backup();

        $settings = Navigation::$plugin->getSettings();

        $migration = new AmNavPlugin();

        ob_start();
        $migration->up();
        $output = ob_get_contents();
        ob_end_clean();

        $output = nl2br($output);

        Craft::$app->getSession()->setNotice(Craft::t('navigation', 'A&M Nav migrated.'));

        return $this->renderTemplate('navigation/settings', [
            'output' => $output,
            'settings' => $settings,
        ]);
    }

    public function actionSettings()
    {
        $settings = Navigation::$plugin->getSettings();

        $this->renderTemplate('navigation/settings', [
            'settings' => $settings,
        ]);
    }

}