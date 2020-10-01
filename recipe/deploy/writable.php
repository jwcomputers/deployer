<?php
namespace Deployer;

desc('Make writable dirs');
task('deploy:writable', function () {
    $dirs = join(' ', get('writable_dirs'));
    $mode = get('writable_mode');
    $sudo = get('writable_use_sudo') ? 'sudo' : '';
    $httpUser = get('http_user', false);

    if (empty($dirs)) {
        return;
    }

    if ($httpUser === false && !in_array($mode, ['chgrp', 'chmod'], true)) {
        // Attempt to detect http user in process list.
        $httpUserCandidates = explode("\n", run("ps axo comm,user | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | sort | awk '{print \$NF}' | uniq"));
        if (count($httpUserCandidates)) {
            $httpUser = array_shift($httpUserCandidates);
        }

        if (empty($httpUser)) {
            throw new \RuntimeException(
                "Can't detect http user name.\n" .
                "Please setup `http_user` config parameter."
            );
        }
    }

    cd('{{release_path}}');

    // Create directories if they don't exist
    run("mkdir -p $dirs");

    $recursive = get('writable_recursive') ? '-R' : '';

    if ($mode === 'chown') {
        // Change owner.
        // -R   operate on files and directories recursively
        // -L   traverse every symbolic link to a directory encountered
        run("$sudo chown -L $recursive $httpUser $dirs");
    } elseif ($mode === 'chgrp') {
        // Change group ownership.
        // -R   operate on files and directories recursively
        // -L   if a command line argument is a symbolic link to a directory, traverse it
        $httpGroup = get('http_group', false);
        if ($httpGroup === false) {
            throw new \RuntimeException("Please setup `http_group` config parameter.");
        }
        run("$sudo chgrp -H $recursive $httpGroup $dirs");
    } elseif ($mode === 'chmod') {
        // in chmod mode, defined `writable_chmod_recursive` has priority over common `writable_recursive`
        if (is_bool(get('writable_chmod_recursive'))) {
            $recursive = get('writable_chmod_recursive') ? '-R' : '';
        }
        run("$sudo chmod $recursive {{writable_chmod_mode}} $dirs");
    } elseif ($mode === 'acl') {
        if (strpos(run("chmod 2>&1; true"), '+a') !== false) {
            // Try OS-X specific setting of access-rights

            run("$sudo chmod +a \"$httpUser allow delete,write,append,file_inherit,directory_inherit\" $dirs");
            run("$sudo chmod +a \"`whoami` allow delete,write,append,file_inherit,directory_inherit\" $dirs");
        } elseif (commandExist('setfacl')) {
            if (!empty($sudo)) {
                run("$sudo setfacl -L $recursive -m u:\"$httpUser\":rwX -m u:`whoami`:rwX $dirs");
                run("$sudo setfacl -dL $recursive -m u:\"$httpUser\":rwX -m u:`whoami`:rwX $dirs");
            } else {
                // When running without sudo, exception may be thrown
                // if executing setfacl on files created by http user (in directory that has been setfacl before).
                // These directories/files should be skipped.
                // Now, we will check each directory for ACL and only setfacl for which has not been set before.
                $writeableDirs = get('writable_dirs');
                foreach ($writeableDirs as $dir) {
                    // Check if ACL has been set or not
                    $hasfacl = run("getfacl -p $dir | grep \"^user:$httpUser:.*w\" | wc -l");
                    // Set ACL for directory if it has not been set before
                    if (!$hasfacl) {
                        run("setfacl -L $recursive -m u:\"$httpUser\":rwX -m u:`whoami`:rwX $dir");
                        run("setfacl -dL $recursive -m u:\"$httpUser\":rwX -m u:`whoami`:rwX $dir");
                    }
                }
            }
        } else {
            throw new \RuntimeException("Can't set writable dirs with ACL.\nConnect to host with \"dep ssh\",\nand run: \"sudo apt-get install acl\"");
        }
    } else {
        throw new \RuntimeException("Unknown writable_mode `$mode`.");
    }
});
