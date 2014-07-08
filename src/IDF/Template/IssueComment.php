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

Pluf::loadFunction('Pluf_HTTP_URL_urlForView');
Pluf::loadFunction('IDF_Template_safePregReplace');

/**
 * Make the links to issues and commits.
 */
class IDF_Template_IssueComment extends Pluf_Template_Tag
{
    private $project = null;
    private $request = null;
    private $scm = null;

    function start($text, $request, $echo=true, $wordwrap=true, $esc=true, $autolink=true, $nl2br=false)
    {
        $this->project = $request->project;
        $this->request = $request;
        $this->scm = IDF_Scm::get($request->project);
        if ($esc) $text = Pluf_esc($text);
        if ($autolink) {
            $text = IDF_Template_safePregReplace('#([a-z]+://[^\s\(\)]+)#i',
                                                 '<a href="\1">\1</a>', $text);
        }
        if ($request->rights['hasIssuesAccess']) {
            $text = IDF_Template_safePregReplace('#((?:issue|bug|ticket)(s)?\s+|\s+\#)(\d+)(\#ic\d+)?(?(2)((?:[, \w]+(?:\s+\#)?)?\d+(?:\#ic\d+)?){0,})#im',
                                                 array($this, 'callbackIssues'), $text);
        }
        if ($request->rights['hasReviewAccess']) {
            $text = IDF_Template_safePregReplace('#(reviews?\s+)(\d+(?:(?:\s+and|\s+or|,)\s+\d+)*)\b#i',
                                                 array($this, 'callbackReviews'), $text);
        }
        if ($request->rights['hasSourceAccess']) {
            $verbs = array('added', 'fixed', 'reverted', 'changed', 'removed');
            $nouns = array('commit', 'commits', 'revision', 'revisions', 'rev', 'revs');
            $prefix = implode(' in|', $verbs).' in' . '|'.
                      implode('|', $nouns);
            $text = IDF_Template_safePregReplace('#((?:'.$prefix.')(?:\s+r?))([0-9a-f]{1,40}((?:\s+and|\s+or|,)\s+r?[0-9a-f]{1,40})*)\b#i',
                                                 array($this, 'callbackCommits'), $text);
            $text = IDF_Template_safePregReplace('=(src:)([^\s@#,\(\)\\\\]+(?:(\\\\)[\s@#][^\s@#,\(\)\\\\]+){0,})+(?:\@([^\s#,]+))?(?:#(\d+))?=im',
                                                 array($this, 'callbackSource'), $text);
        }
        if ($wordwrap) $text = Pluf_Text::wrapHtml($text, 69, "\n");
        if ($nl2br) $text = nl2br($text);
        if ($echo) {
            echo $text;
        } else {
            return $text;
        }
    }

    /**
     * General call back for the issues.
     */
    function callbackIssues($m)
    {
        $c = count($m);
        if (4 === $c || 5 === $c) {
            $issue = new IDF_Issue($m[3]);
            if (0 < $issue->id and $issue->project == $this->project->id) {
                $m[1] = trim($m[1]);
                $prefix = '';
                if ('#' === $m[1]) {
                    $title  = $m[1].$m[3];
                    $prefix = mb_substr($m[0], 0, strpos($m[0], $m[1])); // fixes \n matches
                } else {
                    $title = $m[1].' '.$m[3];
                }
                if (4 === $c) {
                    return $prefix.$this->linkIssue($issue, $title);
                } else {
                    return $prefix.$this->linkIssue($issue, $title, $m[4]);
                }
            }
            return $m[0]; // not existing issue.
        }
        return IDF_Template_safePregReplace('#(\#)?(\d+)(\#ic\d+)?#',
                                            array($this, 'callbackIssue'),
                                            $m[0]);
    }

    /**
     * Call back for the case of multiple issues like 'issues 1, 2 and 3'.
     *
     * Called from callbackIssues, it is linking only the number of
     * the issues.
     */
    function callbackIssue($m)
    {
        $issue = new IDF_Issue($m[2]);
        if (0 < $issue->id and $issue->project == $this->project->id) {
            if (4 === count($m)) {
                return $this->linkIssue($issue, $m[1].$m[2], $m[3]);
            }
            return $this->linkIssue($issue, $m[1].$m[2]);
        }
        return $m[0]; // not existing issue.
    }

     /**
      * General call back to convert commits to HTML links.
      *
      * @param array $m Single regex match.
      * @return string Content with converted commits.
      */
    function callbackCommits($m)
    {
        $keyword = rtrim($m[1]);
        if (empty($m[3])) {
            // Single commit like 'commit 6e030e6'.
            return $m[1].call_user_func(array($this, 'callbackCommit'), array($m[2]));
        }
        // Multiple commits like 'commits 6e030e6, a25bfc1 and 3c094f8'.
        return $m[1].IDF_Template_safePregReplace('#\b[0-9a-f]{1,40}\b#i', array($this, 'callbackCommit'), $m[2]);
    }

    /**
     * Convert plaintext commit to HTML link. Called from callbackCommits.
     *
     * Regex callback for {@link IDF_Template_IssueComment::callbackCommits()}.
     *
     * @param array Single regex match.
     * @return string HTML A element with commit.
     */
    function callbackCommit($m)
    {
        $co = $this->scm->getCommit($m[0]);
        if (!$co) {
            return $m[0]; // not a commit.
        }
        return '<a href="'
            .Pluf_HTTP_URL_urlForView('IDF_Views_Source::commit', array($this->project->shortname, $co->commit))
            .'">'.$m[0].'</a>';
    }

     /**
      * General call back to convert reviews to HTML links.
      *
      * @param array $m Single regex match.
      * @return string Content with converted reviews.
      */
    function callbackReviews($m)
    {
        $keyword = rtrim($m[1]);
        if ('reviews' === $keyword) {
            return $m[1].IDF_Template_safePregReplace('#\b(\d+)\b#i', array($this, 'callbackReview'), $m[2]);
        } else if ('review' === $keyword) {
            return $m[1].call_user_func(array($this, 'callbackReview'), array('', $m[2]));
        }
        return $m[0];
    }

    /**
     * Convert plaintext commit to HTML link. Called from callbackReviews.
     *
     * Regex callback for {@link IDF_Template_IssueComment::callbackReviews()}.
     *
     * @param array Single regex match.
     * @return string HTML A element with review.
     */
    function callbackReview($m)
    {
        $review = new IDF_Review($m[1]);
        if ($review->id > 0 and $review->project == $this->project->id) {
            return $this->linkReview($review, $m[1]);
        } else {
            return $m[0]; // not existing issue.
        }
    }

    function callbackSource($m)
    {
        if (!$this->scm->isAvailable())
            return $m[0];
        $commit = null;
        if (!empty($m[4])) {
            if (!$this->scm->getCommit($m[4])) {
                return $m[0];
            }
            $commit = $m[4];
        }
        $file = $m[2];
        if (!empty($m[3]))
            $file = str_replace($m[3], '', $file);
        $linktext = $file;
        if (!empty($commit))
            $linktext .= '@'.$commit;
        $request_file_info = $this->scm->getPathInfo($file, $commit);
        if (!$request_file_info) {
            return $m[0];
        }
        if ($request_file_info->type == 'tree') {
            return $m[0];
        }
        $link = Pluf_HTTP_URL_urlForView('IDF_Views_Source::tree', array(
            $this->project->shortname,
            $commit == null ? $this->scm->getMainBranch() : $commit,
            $file
        ));
        if (!empty($m[5])) {
            $link .= '#L'.$m[5];
            $linktext .= '#'.$m[5];
        }
        return $m[1].'<a href="'.$link.'">'.$linktext.'</a>';
    }

    /**
     * Generate the link to an issue.
     *
     * @param IDF_Issue Issue.
     * @param string Name of the link.
     * @return string Linked issue.
     */
    public function linkIssue($issue, $title, $anchor='')
    {
        $ic = (in_array($issue->status, $this->project->getTagIdsByStatus('closed'))) ? 'issue-c' : 'issue-o';
        return '<a href="'.Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view',
                                                    array($this->project->shortname, $issue->id)).$anchor.'" class="'.$ic.'" title="'.Pluf_esc($issue->summary).'">'.Pluf_esc($title).'</a>';
    }

    /**
     * Generate the link to a review.
     *
     * @param IDF_Review Review.
     * @param string Name of the link.
     * @return string Linked review.
     */
    public function linkReview($review, $title, $anchor='')
    {
        $ic = (in_array($review->status, $this->project->getTagIdsByStatus('closed'))) ? 'issue-c' : 'issue-o';
        return '<a href="'.Pluf_HTTP_URL_urlForView('IDF_Views_Review::view',
                                                    array($this->project->shortname, $review->id)).$anchor.'" class="'.$ic.'" title="'.Pluf_esc($review->summary).'">'.Pluf_esc($title).'</a>';
    }
}
