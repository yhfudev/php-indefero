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
 * This class is a plugin which allows to synchronise access riths
 * between InDefero and a common restricted SSH account for git
 * access.
 *
 * As the authentication is directly performed by accessing the
 * InDefero database, we only need to synchronize the SSH keys. This
 * synchronization process can only be performed by a process running
 * under the git user as we need to write in
 * /home/git/.ssh/authorized_keys
 *
 * So, here, we are just creating a file informing that a sync needs
 * to be done. We connect this plugin to the IDF_Key::postSave signal.
 */
class IDF_Plugin_SyncGit
{
    /**
     * Entry point of the plugin.
     */
    static public function entry($signal, &$params)
    {
        // First check for the single mandatory config variable.
        if (!Pluf::f('idf_plugin_syncgit_sync_file', false)) {
            Pluf_Log::debug('IDF_Plugin_SyncGit plugin not configured.');
            return;
        }
        $plug = new IDF_Plugin_SyncGit();
        switch ($signal) {
        case 'IDF_Project::created':
            $plug->processCreate($params['project']);
            break;
        case 'IDF_Key::postSave':
            break;
        case 'IDF_Key::preDelete':
            $plug->processDelete($params['project']);
            break;
        case 'IDF_Project::membershipsUpdated':
            //$plug->processSyncAuthz($params['project']);
            break;
        case 'gitpostupdate.php::run':
            self::postUpdate($signal, $params);
            break;
        default:
            Pluf_Log::event($P, 'create', 
                            Pluf::f('idf_plugin_syncgit_sync_file'));
            @touch(Pluf::f('idf_plugin_syncgit_sync_file'));
            @chmod(Pluf::f('idf_plugin_syncgit_sync_file'), 0777);
            break;
        }
    }

    /**
     * Entry point for the post-update signal.
     *
     * It tries to find the name of the project, when found it runs an
     * update of the timeline.
     */
    static public function postUpdate($signal, &$params)
    {
        // Chop the ".git" and get what is left
        $pname = basename($params['git_dir'], '.git');
        try {
            $project = IDF_Project::getOr404($pname);
        } catch (Pluf_HTTP_Error404 $e) {
            Pluf_Log::event(array('IDF_Plugin_SyncGit::postUpdate', 'Project not found.', array($pname, $params)));
            return false; // Project not found
        }
        // Now we have the project and can update the timeline
        Pluf_Log::debug(array('IDF_Plugin_SyncGit::postUpdate', 'Project found', $pname, $project->id));
        IDF_Scm::syncTimeline($project, true);
        Pluf_Log::event(array('IDF_Plugin_SyncGit::postUpdate', 'sync', array($pname, $project->id)));
    }

    /**
     * Run git command to create the corresponding Git
     * repository.
     *
     * @param IDF_Project 
     * @return bool Success
     */
    function processCreate($project)
    {
        if ($project->getConf()->getVal('scm') != 'git') {
            Pluf_Log::event(array('IDF_Plugin_SyncGit::processCreate', 'Git exec not installed.', array($project)));
            return false;
        }
        $shortname = $project->shortname;
        if (false===($git_reporoot_path=Pluf::f('idf_plugin_syncgit_base_repositories',false))) {
            throw new Pluf_Exception_SettingError("'idf_plugin_syncgit_base_repositories' must be defined in your configuration file.");
        }
        if (file_exists($git_reporoot_path.'/'.$shortname)) {
            throw new Exception(sprintf(__('The repository %s already exists.'),
                                        $git_reporoot_path.'/'.$shortname));
        }
        $return = 0;
        $output = array();
        $path = escapeshellarg($git_reporoot_path.'/'.$shortname.'.git');
        $exec = Pluf::f('git_path', 'git');

        # or git --bare --git-dir projxxx init
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$exec.' --bare init '.$path;
        $ll = exec($cmd, $output, $return);
        if ($return != 0) {
            Pluf_Log::error(array('IDF_Plugin_SyncGit::processCreate', 
                                  'Error', 
                                  array('path' => $git_reporoot_path.'/'.$shortname,
                                        'output' => $output)));
            return;
        }

        $cmd = Pluf::f('idf_exec_cmd_prefix', '').' cp -p '.$path.'/hooks/post-update.sample '.$path.'/hooks/post-update； cd '.$path.'/hooks/; ./post-update';
        $ll = exec($cmd, $output, $ret2);
        if ($ret2 != 0) {
            Pluf_Log::warn(array('IDF_Plugin_SyncGit::processCreate', 
                                  'post-update hook creation error', 
                                  array('path' => $path.'/hooks/post-update',
                                        'output' => $output)));
            return;
        }

        return ($return == 0);
    }

    /**
     * Remove the project from the drive and update the access rights.
     *
     * @param IDF_Project 
     * @return bool Success
     */
    function processDelete($project)
    {
        if (!Pluf::f('idf_plugin_syncgit_remove_orphans', false)) {
            //Pluf_Log::event(array('IDF_Plugin_SyncGit::processDelete', 'idf_plugin_syncgit_remove_orphans set to false.', array($project)));
            return;
        }
        if ($project->getConf()->getVal('scm') != 'git') {
            Pluf_Log::event(array('IDF_Plugin_SyncGit::processDelete', 'Git exec not installed.', array($project)));
            return false;
        }
        $this->SyncAccess($project); // exclude $project
        $shortname = $project->shortname;
        if (false===($git_reporoot_path=Pluf::f('idf_plugin_syncgit_base_repositories',false))) {
            throw new Pluf_Exception_SettingError("'idf_plugin_syncgit_base_repositories' must be defined in your configuration file.");
        }
        if (file_exists($git_reporoot_path.'/'.$shortname)) {
            $cmd = Pluf::f('idf_exec_cmd_prefix', '').'rm -rf '.$git_reporoot_path.'/'.$shortname;
            exec($cmd);
        }
    }

}
