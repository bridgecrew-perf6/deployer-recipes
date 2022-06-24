<?php declare(strict_types=1);

namespace HelloNico\Deployer\Vendors;

use function Deployer\set;
use function Deployer\get;
use function Deployer\warning;
use function Deployer\commandExist;

require dirname(dirname(__DIR__)) . '/vendor/deployer/deployer/recipe/vendors.php';

set('vendor_paths', ['{{release_or_current_path}}']);
set('composer_force_phar', true);

// Self update composer from time to time
// Set `false` to disable or an integer to update every N days
// Default to 30 days
set('composer_self_update', 30);

set('composer_options', get('composer_options') . ' --classmap-authoritative');

// Returns Composer binary path if found. Otherwise try to install latest
// composer version to `.dep/composer.phar`. To use specific composer version
// download desired phar and place it at `.dep/composer.phar`.
set('bin/composer', function () {
    if (test('[ -f {{deploy_path}}/.dep/composer.phar ]')) {
        // If composer.phar is older than `composer_self_update` days, run self update
        if(
            get('composer_self_update', 0)
            && strtotime(sprintf('+%d days', (int) get('composer_self_update')), (int) run('stat -c %Y {{deploy_path}}/.dep/composer.phar')) <= time()
        ) {
            warning("Composer is older than {{composer_self_update}} days, updating composer...");
            run('{{deploy_path}}/.dep/composer.phar self-update');
        }

        return '{{bin/php}} {{deploy_path}}/.dep/composer.phar';
    }

    if (commandExist('composer') && !get('composer_force_phar')) {
        return '{{bin/php}} ' . which('composer');
    }

    warning("Composer binary wasn't found. Installing latest composer to \"{{deploy_path}}/.dep/composer.phar\".");
    run("cd {{deploy_path}} && curl -sS https://getcomposer.org/installer | {{bin/php}}");
    run('mv {{deploy_path}}/composer.phar {{deploy_path}}/.dep/composer.phar');
    return '{{bin/php}} {{deploy_path}}/.dep/composer.phar';
});

desc('Installs vendors');
task('deploy:vendors', function () {
    if (!commandExist('unzip')) {
        warning('To speed up composer installation setup "unzip" command with PHP zip extension.');
    }
    foreach (get('vendor_paths', []) as $path) {
        run("cd $path && {{bin/composer}} {{composer_action}} {{composer_options}} 2>&1");
    }
});