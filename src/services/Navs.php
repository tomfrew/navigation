<?php
namespace verbb\navigation\services;

use verbb\navigation\Navigation;
use verbb\navigation\elements\Node;
use verbb\navigation\events\NavEvent;
use verbb\navigation\models\Nav as NavModel;
use verbb\navigation\records\Nav as NavRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\db\Table;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\models\Structure;

use yii\web\UserEvent;

class Navs extends Component
{
    // Constants
    // =========================================================================

    const EVENT_BEFORE_SAVE_NAV = 'beforeSaveNav';
    const EVENT_AFTER_SAVE_NAV = 'afterSaveNav';
    const EVENT_BEFORE_APPLY_NAV_DELETE = 'beforeApplyNavDelete';
    const EVENT_BEFORE_DELETE_NAV = 'beforeDeleteNav';
    const EVENT_AFTER_DELETE_NAV = 'afterDeleteNav';

    const CONFIG_NAV_KEY = 'navigation.navs';


    // Properties
    // =========================================================================

    private $_navs;


    // Public Methods
    // =========================================================================

    public function getAllNavs(): array
    {
        if ($this->_navs !== null) {
            return $this->_navs;
        }

        $this->_navs = [];

        $navRecords = NavRecord::find()
            ->orderBy(['sortOrder' => SORT_ASC])
            ->with('structure')
            ->all();

        foreach ($navRecords as $navRecord) {
            $this->_navs[] = $this->_createNavFromRecord($navRecord);
        }

        return $this->_navs;
    }

    public function getNavByHandle(string $handle)
    {
        return ArrayHelper::firstWhere($this->getAllNavs(), 'handle', $handle, true);
    }

    public function getNavById($id)
    {
        return ArrayHelper::firstWhere($this->getAllNavs(), 'id', $id);
    }

    public function getNavByUid(string $uid)
    {
        return ArrayHelper::firstWhere($this->getAllNavs(), 'uid', $uid, true);
    }

    public function saveNav(NavModel $nav, bool $runValidation = true): bool
    {
        $isNewNav = !$nav->id;

        // Fire a 'beforeSaveNav' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_NAV)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_NAV, new NavEvent([
                'nav' => $nav,
                'isNew' => $isNewNav,
            ]));
        }

        if ($runValidation && !$nav->validate()) {
            Craft::info('Navigation not saved due to validation error.', __METHOD__);
            return false;
        }

        if ($isNewNav) {
            $nav->uid = StringHelper::UUID();
            $structureUid = StringHelper::UUID();

            $nav->sortOrder = (new Query())
                    ->from(['{{%navigation_navs}}'])
                    ->max('[[sortOrder]]') + 1;
        } else {
            $existingNavRecord = NavRecord::find()
                ->where(['id' => $nav->id])
                ->one();

            if (!$existingNavRecord) {
                throw new NavNotFoundException("No nav exists with the ID '{$nav->id}'");
            }

            $nav->uid = $existingNavRecord->uid;
            $structureUid = Db::uidById(Table::STRUCTURES, $existingNavRecord->structureId);
        }

        // If they've set maxLevels to 0 (don't ask why), then pretend like there are none.
        if ((int)$nav->maxLevels === 0) {
            $nav->maxLevels = null;
        }

        $projectConfig = Craft::$app->getProjectConfig();

        $configData = [
            'name' => $nav->name,
            'handle' => $nav->handle,
            'structure' => [
                'uid' => $structureUid,
                'maxLevels' => $nav->maxLevels,
            ],
            'instructions' => $nav->instructions,
            'propagateNodes' => (bool)$nav->propagateNodes,
            'sortOrder' => $nav->sortOrder,
        ];

        $configPath = self::CONFIG_NAV_KEY . '.' . $nav->uid;
        $projectConfig->set($configPath, $configData);

        if ($isNewNav) {
            $nav->id = Db::idByUid('{{%navigation_navs}}', $nav->uid);
        }

        return true;
    }

    public function handleChangedNav(ConfigEvent $event)
    {
        $navUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Basic data
            $navRecord = $this->_getNavRecord($navUid, true);
            $isNewNav = $navRecord->getIsNewRecord();

            $navRecord->name = $data['name'];
            $navRecord->handle = $data['handle'];
            $navRecord->instructions = $data['instructions'];
            $navRecord->propagateNodes = $data['propagateNodes'];
            $navRecord->sortOrder = $data['sortOrder'];
            $navRecord->uid = $navUid;

            // Structure
            $structureData = $data['structure'];
            $structureUid = $structureData['uid'];

            $structuresService = Craft::$app->getStructures();
            $structure = $structuresService->getStructureByUid($structureUid, true) ?? new Structure(['uid' => $structureUid]);
            $structure->maxLevels = $structureData['maxLevels'];
            $structuresService->saveStructure($structure);

            $navRecord->structureId = $structure->id;

            // Save the nav
            if ($wasTrashed = (bool)$navRecord->dateDeleted) {
                $navRecord->restore();
            } else {
                $navRecord->save(false);
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Clear caches
        $this->_navs = null;

        if ($wasTrashed) {
            // Restore the nodes that were deleted with the nav
            $nodes = Node::find()
                ->navId($navRecord->id)
                ->trashed()
                ->andWhere(['nodes.deletedWithNav' => true])
                ->all();

            Craft::$app->getElements()->restoreElements($nodes);
        }

        // Fire an 'afterSaveNav' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_NAV)) {
            $this->trigger(self::EVENT_AFTER_SAVE_NAV, new NavEvent([
                'nav' => $this->getNavById($navRecord->id),
                'isNew' => $isNewNav,
            ]));
        }
    }

    public function deleteNavById(int $navId): bool
    {
        if (!$navId) {
            return false;
        }

        $nav = $this->getNavById($navId);

        if (!$nav) {
            return false;
        }

        return $this->deleteNav($nav);
    }

    public function deleteNav(NavModel $nav): bool
    {
        // Fire a 'beforeDeleteNav' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_NAV)) {
            $this->trigger(self::EVENT_BEFORE_DELETE_NAV, new NavEvent([
                'nav' => $nav
            ]));
        }

        Craft::$app->getProjectConfig()->remove(self::CONFIG_NAV_KEY . '.' . $nav->uid);
        
        return true;
    }

    public function handleDeletedNav(ConfigEvent $event)
    {
        $uid = $event->tokenMatches[0];
        $navRecord = $this->_getNavRecord($uid);

        if (!$navRecord->id) {
            return;
        }

        $nav = $this->getNavById($navRecord->id);

        // Fire a 'beforeApplyNavDelete' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_APPLY_NAV_DELETE)) {
            $this->trigger(self::EVENT_BEFORE_APPLY_NAV_DELETE, new NavEvent([
                'nav' => $nav,
            ]));
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            // Delete the nodes
            $nodes = Node::find()
                ->anyStatus()
                ->navId($navRecord->id)
                ->all();

            $elementsService = Craft::$app->getElements();

            foreach ($nodes as $node) {
                $node->deletedWithNav = true;
                $elementsService->deleteElement($node);
            }

            // Delete the structure
            Craft::$app->getStructures()->deleteStructureById($navRecord->structureId);

            // Delete the navigation
            Craft::$app->getDb()->createCommand()
                ->softDelete('{{%navigation_navs}}', ['id' => $navRecord->id])
                ->execute();

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Clear caches
        $this->_navs = null;

        // Fire an 'afterDeleteNav' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_NAV)) {
            $this->trigger(self::EVENT_AFTER_DELETE_NAV, new NavEvent([
                'nav' => $nav
            ]));
        }
    }

    public function reorderNavs(array $navIds): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        $uidsByIds = Db::uidsByIds('{{%navigation_navs}}', $navIds);

        foreach ($navIds as $navOrder => $navId) {
            if (!empty($uidsByIds[$navId])) {
                $navUid = $uidsByIds[$navId];
                $projectConfig->set(self::CONFIG_NAV_KEY . '.' . $navUid . '.sortOrder', $navOrder + 1);
            }
        }

        return true;
    }

    public function buildNavTree($nodes, &$nodeTree)
    {
        foreach ($nodes as $key => $node) {
            $nodeTree[$key] = $node->toArray();

            if ($node->hasDescendants) {
                $this->buildNavTree($node->children, $nodeTree[$key]['children']);
            }
        }
    }


    // Private Methods
    // =========================================================================

    private function _createNavFromRecord(NavRecord $record = null)
    {
        if (!$record) {
            return null;
        }

        $nav = new NavModel($record->toArray([
            'id',
            'structureId',
            'name',
            'handle',
            'instructions',
            'sortOrder',
            'propagateNodes',
            'uid',
        ]));

        if ($record->structure) {
            $nav->maxLevels = $record->structure->maxLevels;
        }

        return $nav;
    }

    private function _getNavRecord(string $uid, bool $withTrashed = false): NavRecord
    {
        $query = $withTrashed ? NavRecord::findWithTrashed() : NavRecord::find();
        $query->andWhere(['uid' => $uid]);
        return $query->one() ?? new NavRecord();
    }
    
}