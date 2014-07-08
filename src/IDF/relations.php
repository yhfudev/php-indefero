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

$m = array();
$m['IDF_ProjectActivity'] = array('relate_to' => array('IDF_Project'));
$m['IDF_Tag'] = array('relate_to' => array('IDF_Project'),
                      'relate_to_many' => array('IDF_Project'));
$m['IDF_Issue'] = array('relate_to' => array('IDF_Project', 'Pluf_User', 'IDF_Tag'),
                        'relate_to_many' => array('IDF_Tag', 'Pluf_User'));
$m['IDF_IssueComment'] = array('relate_to' => array('IDF_Issue', 'Pluf_User'));
$m['IDF_IssueFile'] = array('relate_to' => array('IDF_IssueComment', 'Pluf_User'));
$m['IDF_Upload'] = array('relate_to' => array('IDF_Project', 'Pluf_User'),
                         'relate_to_many' => array('IDF_Tag'));
$m['IDF_Search_Occ'] = array('relate_to' => array('IDF_Project'),);
$m['IDF_Wiki_Page'] = array('relate_to' => array('IDF_Project', 'Pluf_User'),
                            'relate_to_many' => array('IDF_Tag', 'Pluf_User'));
$m['IDF_Wiki_PageRevision'] = array('relate_to' => array('IDF_Wiki_Page', 'Pluf_User'));
$m['IDF_Wiki_Resource'] = array('relate_to' => array('IDF_Project', 'Pluf_User'));
$m['IDF_Wiki_ResourceRevision'] = array('relate_to' => array('IDF_Wiki_Resource', 'Pluf_User'),
                                        'relate_to_many' => array('IDF_PageRevision', 'Pluf_User'));
$m['IDF_Review'] = array('relate_to' => array('IDF_Project', 'Pluf_User', 'IDF_Tag'),
                        'relate_to_many' => array('IDF_Tag', 'Pluf_User'));
$m['IDF_Review_Patch'] = array('relate_to' => array('IDF_Review', 'Pluf_User'));
$m['IDF_Review_Comment'] = array('relate_to' => array('IDF_Review_Patch', 'Pluf_User'));
$m['IDF_Review_FileComment'] = array('relate_to' => array('IDF_Review_Comment', 'Pluf_User'));
$m['IDF_Key'] = array('relate_to' => array('Pluf_User'));
$m['IDF_Conf'] = array('relate_to' => array('IDF_Project'));
$m['IDF_Commit'] = array('relate_to' => array('IDF_Project', 'Pluf_User'));
$m['IDF_Scm_Cache_Git'] = array('relate_to' => array('IDF_Project'));

$m['IDF_UserData'] = array('relate_to' => array('Pluf_User'));
$m['IDF_EmailAddress'] = array('relate_to' => array('Pluf_User'));
$m['IDF_IssueRelation'] = array('relate_to' => array('IDF_Issue', 'Pluf_User'));

Pluf_Signal::connect('Pluf_Template_Compiler::construct_template_tags_modifiers',
                     array('IDF_Middleware', 'updateTemplateTagsModifiers'));

# -- Standard plugins, they will run only if configured --
#
# Subversion synchronization
Pluf_Signal::connect('IDF_Project::membershipsUpdated',
                     array('IDF_Plugin_SyncSvn', 'entry'));
Pluf_Signal::connect('IDF_Project::created',
                     array('IDF_Plugin_SyncSvn', 'entry'));
Pluf_Signal::connect('Pluf_User::passwordUpdated',
                     array('IDF_Plugin_SyncSvn', 'entry'));
Pluf_Signal::connect('IDF_Project::preDelete',
                     array('IDF_Plugin_SyncSvn', 'entry'));
Pluf_Signal::connect('svnpostcommit.php::run',
                     array('IDF_Plugin_SyncSvn', 'entry'));

#
# Mercurial synchronization
Pluf_Signal::connect('IDF_Project::membershipsUpdated',
                     array('IDF_Plugin_SyncMercurial', 'entry'));
Pluf_Signal::connect('IDF_Project::created',
                     array('IDF_Plugin_SyncMercurial', 'entry'));
Pluf_Signal::connect('Pluf_User::passwordUpdated',
                     array('IDF_Plugin_SyncMercurial', 'entry'));
Pluf_Signal::connect('hgchangegroup.php::run',
                     array('IDF_Plugin_SyncMercurial', 'entry'));

#
# Git synchronization
Pluf_Signal::connect('IDF_Project::membershipsUpdated',
                     array('IDF_Plugin_SyncGit', 'entry'));
Pluf_Signal::connect('IDF_Key::postSave',
                     array('IDF_Plugin_SyncGit', 'entry'));
Pluf_Signal::connect('IDF_Project::created',
                     array('IDF_Plugin_SyncGit', 'entry'));
Pluf_Signal::connect('IDF_Key::preDelete',
                     array('IDF_Plugin_SyncGit', 'entry'));
Pluf_Signal::connect('gitpostupdate.php::run',
                     array('IDF_Plugin_SyncGit', 'entry'));

#
# monotone synchronization
Pluf_Signal::connect('IDF_Project::created',
                     array('IDF_Plugin_SyncMonotone', 'entry'));
Pluf_Signal::connect('IDF_Project::membershipsUpdated',
                     array('IDF_Plugin_SyncMonotone', 'entry'));
Pluf_Signal::connect('IDF_Project::preDelete',
                     array('IDF_Plugin_SyncMonotone', 'entry'));
Pluf_Signal::connect('IDF_Key::postSave',
                     array('IDF_Plugin_SyncMonotone', 'entry'));
Pluf_Signal::connect('IDF_Key::preDelete',
                     array('IDF_Plugin_SyncMonotone', 'entry'));
Pluf_Signal::connect('mtnpostpush.php::run',
                     array('IDF_Plugin_SyncMonotone', 'entry'));

#
# -- Processing of the webhook queue --
Pluf_Signal::connect('queuecron.php::run',
                     array('IDF_Queue', 'process'));

#
# Processing of a given webhook, the hook can be configured
# directly in the configuration file if a different solution
# is required.
Pluf_Signal::connect('IDF_Queue::processItem',
                     Pluf::f('idf_hook_process_item',
                             array('IDF_Webhook', 'process')));
return $m;
