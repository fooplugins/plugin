# FooPlugin
Common Plugin Code

Example usage:

```
use FooPlugins\Shared\Admin\ActivatorRestrictedMenu;

require __DIR__ . '/vendor/autoload.php';

new ActivatorRestrictedMenu(
    pluginFile: __FILE__,
    optionKey:  'fooplugins_hub_menu_access',
    onAllowedCallback: function () {
        // Only runs for activator + whitelisted users.
        add_menu_page(
            'Foo Hub',
            'Foo Hub',
            'manage_options',
            'fooplugins-hub',
            function () {
                echo '<div class="wrap"><h1>Foo Hub</h1></div>';
            }
        );
    }
);
```

