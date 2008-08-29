<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008 Céondo Ltd and contributors.
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
 * Manage differents SCM systems
 */
class IDF_ScmFactory
{

    /**
     * Returns an instance of the correct scm backend object.
     *
     * @return Object
     */
    public static function getScm($request=null)
    {
        // Get scm type from project conf ; defaults to git
        switch ($request->conf->getVal('scm', 'git')) {
        case 'svn':
            return new IDF_Svn($request->conf->getVal('svn_repository'),
                               $request->conf->getVal('svn_username'),
                               $request->conf->getVal('svn_password'));
        case 'git':
        default:
            return new IDF_Git($request->project->getGitRepository());
        }
    }
}

