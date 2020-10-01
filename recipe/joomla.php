<?php
namespace Deployer;

require_once __DIR__ . '/common.php';

set('shared_files', ['configuration.php']);
set('shared_dirs', ['images']);
set('writable_dirs', ['images']);

task('deploy', [
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
    'deploy:symlink',
    'deploy:unlock',
    'deploy:cleanup',
])->desc('Deploy your project');

after('deploy', 'success');
