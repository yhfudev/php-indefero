<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008-2011 Céondo Ltd and contributors.
#
# InDefero is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# InDefero is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

/**
 * Synchronize the SSH keys with InDefero.
 */
class IDF_Plugin_SyncGit_Cron
{
    /**
     * Template for the SSH key.
     */
    public $template = 'command="python %s %s",no-port-forwarding,no-X11-forwarding,no-agent-forwarding,no-pty %s';

    /**
     * Synchronize.
     */
    public static function sync()
    {
        $template = Pluf::factory(__CLASS__)->template;
        $cmd = Pluf::f('idf_plugin_syncgit_path_gitserve', '/dev/null');
        $authorized_keys = Pluf::f('idf_plugin_syncgit_path_authorized_keys', false);
        if (false == $authorized_keys) {
            throw new Pluf_Exception_SettingError('Setting idf_plugin_syncgit_path_authorized_keys not set.');
        }
        if (!is_writable($authorized_keys)) {
            throw new Exception('Cannot create file: '.$authorized_keys);
        }
        $out = '';
        $keys = Pluf::factory('IDF_Key')->getList(array('view'=>'join_user'));
        foreach ($keys as $key) {
            try {
                $key_type = $key->getType();
            } catch (Exception $e) {
                // The key is a bad key, skip it
                continue;
            }
            if ($key_type == 'ssh' and preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]*(@[a-zA-Z][a-zA-Z0-9.-]*)?$/', $key->login)) {
                $content = trim(str_replace(array("\n", "\r"), '', $key->content));
                $out .= sprintf($template, $cmd, $key->login, $content)."\n";
            }
        }
        $out = "# indefero start" . PHP_EOL . $out . "# indefero end" . PHP_EOL;
        
        // We update only the part of the file between IDF_START / IDF_END comment
        $original_keys = file_get_contents($authorized_keys);
        if (strstr($original_keys, "# indefero start") && strstr($original_keys, "# indefero end")) {
            $out = preg_replace('%(#\sindefero\sstart).+(#\sindefero\send\s\s?)%isU', 
                                $out, $original_keys);
        } else {
             $out .= $original_keys;   
        }
        file_put_contents($authorized_keys, $out, LOCK_EX);
    }

    /**
     * Mark export of git repositories for the daemon.
     */
    public static function markExport()
    {
        foreach (Pluf::factory('IDF_Project')->getList() as $project) {
            $rep = sprintf(Pluf::f('git_repositories'), $project->shortname);
            $serve = new IDF_Plugin_SyncGit_Serve();
            $serve->setGitExport($project->shortname, $rep);
        }
    }

    /**
     * Remove orphan repositories.
     */
    public static function removeOrphanRepositories()
    {
        $path = Pluf::f('idf_plugin_syncgit_base_repositories', '/home/git/repositories');
        if (!is_dir($path) || is_link($path)) {
            throw new Pluf_Exception_SettingError(sprintf(
                'Directory %s does not exist! Setting "idf_plugin_syncgit_base_repositories not set.',
                $path));
        }
        if (!is_writable($path)) {
            throw new Exception(sprintf('Repository %s is not writable.', $path));
        }
        $projects = array();
        foreach (Pluf::factory('IDF_Project')->getList() as $project) {
            $projects[] = $project->shortname;
        }
        unset($project);
        $it = new DirectoryIterator($path);
        $orphans = array();
        while ($it->valid()) {
            if (!$it->isDot() && $it->isDir() && !in_array(basename($it->getFileName(), '.git'), $projects)) {
                $orphans[] = $it->getPathName();
            }
            $it->next();
        }
        if (count($orphans)) {
            $cmd = Pluf::f('idf_exec_cmd_prefix', '').'rm -rf '.implode(' ', $orphans);
            exec($cmd);
            clearstatcache();
            while (list(, $project) = each($orphans)) {
                if (is_dir($project)) {
                    throw new Exception(sprintf('Cannot remove %s directory.', $project));
                }
            }
        }
    }

    /**
     * Check if a sync is needed.
     *
     */
    public static function main()
    {
        if (file_exists(Pluf::f('idf_plugin_syncgit_sync_file'))) {
            @unlink(Pluf::f('idf_plugin_syncgit_sync_file'));
            self::sync();
            self::markExport();
            if (Pluf::f('idf_plugin_syncgit_remove_orphans', false)) {
                self::removeOrphanRepositories();
            }
        }
    }
}
