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
 * Manage differents SCM systems.
 *
 * This is the base class with the different required methods to be
 * implemented by the SCMs. Each SCM backend need to extend this
 * class. We are not using an interface because this is not really
 * needed.
 *
 * The philosophy behind the interface is not to provide a wrapper
 * around the different SCMs but to provide methods to retrieve in the
 * most efficient way the informations to be displayed/needed in the
 * web interface. This means that each SCM can use the best options,
 * including caching to retrieve the informations.
 *
 * Note on caching: You must not cache ephemeral information like the
 * changelog, but you can cache the commit info (except with
 * subversion where you can change commit info...). It is ok to do
 * some caching for the lifetime of the IDF_Scm object, for example
 * not to retrieve several times the list of branches, etc.
 *
 * All the output of the methods must be serializable. This means that
 * if you are parsing XML you need to correctly cast the results as
 * string when needed.
 */
class IDF_Scm
{
    /**
     * String template for consistent error messages.
     */
    public $error_tpl = 'Error command "%s" returns code %d and output: %s';

    /**
     * Path to the repository.
     */
    public $repo = '';

    /**
     * Corresponding project object.
     */
    public $project = null;

    /**
     * Cache storage.
     *
     * It must only be used to store data for the lifetime of the
     * object. For example if you need to get the list of branches in
     * several functions, better to try to get from the cache first.
     */
    protected $cache = array();


    /**
     * Returns an instance of the correct scm backend object.
     *
     * @param IDF_Project
     * @return Object
     */
    public static function get($project)
    {
        // Get scm type from project conf ; defaults to git
        // We will need to cache the factory
        $scm = $project->getConf()->getVal('scm', 'git');
        $scms = Pluf::f('allowed_scm');
        return call_user_func(array($scms[$scm], 'factory'), $project);
    }

    /**
     * Runs the given command and log some information.
     *
     * A previous version used plain exec(), but this should not be used
     * for various reasons, one being that this command does not preserve
     * trailing whitespace, which is essential for proper diff parsing.
     *
     * @param $caller Calling method
     * @param $cmd Command to run
     * @param &$out Array of output
     * @param &$return Return value
     */
    public static function exec($caller, $cmd, &$out=null, &$return=null)
    {
        $return = -1;
        Pluf_Log::stime('timer');
        $fp = popen($cmd, 'r');
        $buf = '';
        if ($fp !== false) {
            while (!feof($fp)) {
                $buf .= fread($fp, 1024);
            }
            $return = pclose($fp);
        }
        $out = preg_split('/\r\n|\r|\n/', $buf);
        $elem = count($out);
        if ($elem > 0 && $out[$elem-1] === '')
            unset($out[$elem-1]);
        Pluf_Log::perf(array($caller, $cmd, Pluf_Log::etime('timer', 'total_exec')));
        Pluf_Log::debug(array($caller, $cmd, $out));
        Pluf_Log::inc('exec_calls');
    }

    /**
     * Run shell_exec and log some information.
     *
     * @param $caller Calling method
     * @param $cmd Command to run
     * @return string The output
     */
    public static function shell_exec($caller, $cmd)
    {
        Pluf_Log::stime('timer');
        $ret = shell_exec($cmd);
        Pluf_Log::perf(array($caller, $cmd, Pluf_Log::etime('timer', 'total_exec')));
        Pluf_Log::debug(array($caller, $cmd, $ret));
        Pluf_Log::inc('exec_calls');
        return $ret;
    }

    /**
     * Return the size of the repository in bytes.
     *
     * @return int Size in byte, -1 if the size cannot be evaluated.
     */
    public function getRepositorySize()
    {
        return -1;
    }

    /**
     * Returns the URL of the git daemon.
     *
     * @param IDF_Project
     * @return string URL
     */
    public static function getAnonymousAccessUrl($project)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Returns the URL for SSH access
     *
     * @param IDF_Project
     * @param Pluf_User
     * @return string URL
     */
    public static function getAuthAccessUrl($project, $user)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Check if the backend is available for display.
     *
     * @return bool Available
     */
    public function isAvailable()
    {
        throw new Pluf_Exception_NotImplemented();
    }

    const REVISION_VALID = 0;
    const REVISION_INVALID = 1;
    const REVISION_AMBIGUOUS = 2;

    /**
     * Check if a revision or commit is valid, invalid or ambiguous.
     *
     * @param string Revision or commit
     * @return int One of REVISION_VALID, REVISION_INVALID or REVISION_AMBIGIOUS
     */
    public function validateRevision($rev)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Returns an array of single commit objects for ambiguous commit identifiers
     *
     * @param string Ambiguous commit identifier
     * @return array of objects
     */
    public function disambiguateRevision($commit)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Returns in which branches a commit/path is.
     *
     * A commit can be in several branches and some of the SCMs are
     * managing branches using subfolders (like Subversion).
     *
     * This means that to know in which branch we are at the moment,
     * one needs to have both the path and the commit.
     *
     * @param string Commit
     * @param string Path
     * @return array Branches
     */
    public function inBranches($commit, $path)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Returns the list of branches.
     *
     * The return value must be a branch indexed array with the
     * optional path to access the branch as value. For example with
     * git you would get (note that some people are using / in the
     * name of their git branches):
     *
     * <pre>
     * array('master' => '',
     *       'foo-branch' => '',
     *       'design/feature1' => '')
     * </pre>
     *
     * But with Subversion, as the branches are managed as subfolder
     * with a special folder for trunk, you would get something like:
     *
     * <pre>
     * array('trunk' => 'trunk',
     *       'foo-branch' => 'branches/foo-branch',)
     * </pre>
     *
     * @return array Branches
     */
    public function getBranches()
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Returns the list of tags.
     *
     * The format is the same as for the branches.
     *
     * @see self::getBranches()
     *
     * @return array Tags
     */
    public function getTags()
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Returns in which tags a commit/path is.
     *
     * A commit can be in several tags and some of the SCMs are
     * managing tags using subfolders (like Subversion).
     *
     * This means that to know in which tag we are at the moment,
     * one needs to have both the path and the commit.
     *
     * @param string Commit
     * @param string Path
     * @return array Tags
     */
    public function inTags($commit, $path)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Returns the main branch.
     *
     * The main branch is the one displayed by default. For example
     * master, trunk or tip.
     *
     * @return string
     */
    public function getMainBranch()
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Returns the list of files in a given folder.
     *
     * The list is an array of standard class objects with attributes
     * for each file/directory/external element.
     *
     * This is the most important method of the SCM backend as this is
     * the one conveying the speed feeling of the application. All the
     * dirty optimization tricks are allowed there.
     *
     * @param string Revision or commit
     * @param string Folder ('/')
     * @param string Branch (null)
     * @return array
     */
    public function getTree($rev, $folder='/', $branch=null)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Get commit details.
     *
     * @param string Commit or revision number
     * @param bool Get commit diff (false)
     * @return stdClass
     */
    public function getCommit($commit, $getdiff=false)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Returns all recorded changes which lead to the particular commit
     * or revision.
     *
     * Example output:
     *
     * stdClass object {
     *  'additions' => array('path/to/file', 'path/to/directory', ...),
     *  'deletions' => array('path/to/file', 'path/to/directory', ...),
     *  'renames' => array('old/path/to/file' => 'new/path/to/file', ...),
     *  'copies' => array('path/to/source' => 'path/to/target', ...),
     *  'patches' => array('path/to/file', ...),
     *  'properties' => array('path/to/file' => array(
     *              'propname' => 'propvalue', 'deletedprop' => null, ...)
     *              ),
     *      ...)
     * }
     *
     * Each member of the returned object is mandatory, but may contain
     * an empty array if no changes were recorded.
     *
     * @param string    A commit identifier
     * @return object with arrays of individual changes
     */
    public function getChanges($commit)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Get latest changes.
     *
     * It default to the main branch. If possible you should code in a
     * way to avoid repetitive calls to getCommit. Try to be
     * efficient.
     *
     * @param string Branch (null)
     * @param int Number of changes (25)
     * @return array List of commits
     */
    public function getChangeLog($branch=null, $n=10)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Given the string describing the author from the log find the
     * author in the database.
     *
     * If the input is an array, it will return an array of results.
     *
     * @param mixed string/array Author
     * @return mixed Pluf_User or null or array
     */
    public function findAuthor($author)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Given a revision and a file path, retrieve the file content.
     *
     * The $cmd_only parameter is to only request the command that is
     * used to get the file content. This is used when downloading a
     * file at a given revision as it can be passed to a
     * Pluf_HTTP_Response_CommandPassThru reponse. This allows to
     * stream a large response without buffering it in memory.
     *
     * The file definition is coming from getPathInfo().
     *
     * @see self::getPathInfo()
     *
     * @param stdClass File definition
     * @param bool Returns command only (false)
     * @return string File content
     */
    public function getFile($def, $cmd_only=false)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Get information about a file or a path.
     *
     * @param string File or path
     * @param string Revision (null)
     * @return mixed False or stdClass with info
     */
    public function getPathInfo($file, $rev=null)
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Given a revision and possible path returns additional properties.
     *
     * @param string Revision
     * @param string Path ('')
     * @return mixed null or array of properties
     */
    public function getProperties($rev, $path='')
    {
        return null;
    }

    /**
     * Given a changelog parsed node, returns extra data.
     *
     * For example, if the node is a commit object from git, it will a
     * stdClass object with the parents array. The extra value could
     * then be the parent(s) commit(s).
     *
     * @param stdClass Commit object/Parse object
     * @return array Extra properties
     */
    public function getExtraProperties($obj)
    {
        return array();
    }

    /**
     * Generate a zip archive at a given commit, wrapped in a HTTP response, suitable for pushing to client.
     *
     * @param string Commit
     * @param string Prefix ('repository/')
     * @return Pluf_HTTP_Response The HTTP Response containing the zip archive
     */
    public function getArchiveStream($commit, $prefix='repository/')
    {
        throw new Pluf_Exception_NotImplemented();
    }

    /**
     * Sync the changes in the repository with the timeline.
     *
     */
    public static function syncTimeline($project, $force=false)
    {
        $cache = Pluf_Cache::factory();
        $key = 'IDF_Scm:'.$project->shortname.':lastsync';
        if ($force or null === ($res=$cache->get($key))) {
            $scm = IDF_Scm::get($project);
            if ($scm->isAvailable()) {
                foreach ($scm->getChangeLog($scm->getMainBranch(), 25) as $change) {
                    IDF_Commit::getOrAdd($change, $project);
                }
                $cache->set($key, true, (int)(Pluf::f('cache_timeout', 300)/2));
            }
        }
    }

    /**
     * Given a path, encode everything but the /
     */
    public static function smartEncode($path)
    {
        return str_replace('%2F', '/', rawurlencode($path));
    }

    /**
     * Returns the number of slashes and preceeding path components
     * that should be stripped from paths in the SCM's diff output
     */
    public function getDiffPathStripLevel()
    {
        return 0;
    }

    public function repository($request, $match)
    {
        throw new Exception('This repository does not support web based repository access');
    }
}

