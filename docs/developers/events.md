# Events

Events can be used to extend the functionality of Navigation.

## Nav related events

### The `beforeSaveNav` event

Plugins can get notified before an navigation is saved

```php
use verbb\navigation\events\NavEvent;
use verbb\navigation\services\Navs;
use yii\base\Event;

Event::on(Navs::class, Navs::EVENT_BEFORE_SAVE_NAV, function(NavEvent $e) {
    // Do something
});
```

### The `afterSaveNav` event

Plugins can get notified after a navigation has been saved

```php
use verbb\navigation\events\NavEvent;
use verbb\navigation\services\Navs;
use yii\base\Event;

Event::on(Navs::class, Navs::EVENT_AFTER_SAVE_NAV, function(NavEvent $e) {
    // Do something
});
```

### The `beforeDeleteNav` event

Plugins can get notified before an navigation is deleted

```php
use verbb\navigation\events\NavEvent;
use verbb\navigation\services\Navs;
use yii\base\Event;

Event::on(Navs::class, Navs::EVENT_BEFORE_DELETE_NAV, function(NavEvent $e) {
    // Do something
});
```

### The `afterDeleteNav` event

Plugins can get notified after a navigation has been deleted

```php
use verbb\navigation\events\NavEvent;
use verbb\navigation\services\Navs;
use yii\base\Event;

Event::on(Navs::class, Navs::EVENT_AFTER_DELETE_NAV, function(NavEvent $e) {
    // Do something
});
```


## Node related events

### The `beforeSaveNode` event

Plugins can get notified before a node is saved. Event handlers can prevent the node from getting sent by setting `$event->isValid` to false.

```php
use verbb\navigation\elements\Node;
use yii\base\Event;

Event::on(Node::class, Node::EVENT_BEFORE_SAVE, function(Event $e) {
    $node = $event->sender;
    $event->isValid = false;
});
```

### The `afterSaveNode` event

Plugins can get notified after a node has been saved

```php
use verbb\navigation\elements\Node;
use yii\base\Event;

Event::on(Node::class, Node::EVENT_AFTER_SAVE, function(Event $e) {
    $node = $event->sender;
});
```
