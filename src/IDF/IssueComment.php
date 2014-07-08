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
 * A comment to an issue.
 *
 * The first description of an issue is also stored as a comment.
 *
 * A comment is also tracking the changes in the main issue.
 */
class IDF_IssueComment extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_issuecomments';
        $this->_a['model'] = __CLASS__;
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  'blank' => true,
                                  ),
                            'issue' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'IDF_Issue',
                                  'blank' => false,
                                  'verbose' => __('issue'),
                                  'relate_name' => 'comments',
                                  ),
                            'content' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Text',
                                  'blank' => false,
                                  'verbose' => __('comment'),
                                  ),
                            'submitter' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'Pluf_User',
                                  'blank' => false,
                                  'verbose' => __('submitter'),
                                  'relate_name' => 'commented_issue',
                                  ),
                            'changes' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Serialized',
                                  'blank' => true,
                                  'verbose' => __('changes'),
                                  'help_text' => __('Serialized array of the changes in the issue.'),
                                  ),
                            'creation_dtime' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Datetime',
                                  'blank' => true,
                                  'verbose' => __('creation date'),
                                  ),
                            );
        $this->_a['idx'] = array(
                            'creation_dtime_idx' =>
                            array(
                                  'col' => 'creation_dtime',
                                  'type' => 'normal',
                                  ),
                            );
    }

    function changedIssue()
    {
        return (is_array($this->changes) and count($this->changes) > 0);
    }

    function _toIndex()
    {
        return $this->content;
    }

    function preDelete()
    {
        IDF_Timeline::remove($this);
    }

    function preSave($create=false)
    {
        if ($this->id == '') {
            $this->creation_dtime = gmdate('Y-m-d H:i:s');
        }
    }

    function postSave($create=false)
    {
        if ($create) {
            // Check if more than one comment for this issue. We do
            // not want to insert the first comment in the timeline as
            // the issue itself is inserted.
            $sql = new Pluf_SQL('issue=%s', array($this->issue));
            $co = Pluf::factory('IDF_IssueComment')->getList(array('filter'=>$sql->gen()));
            if ($co->count() > 1) {
                IDF_Timeline::insert($this, $this->get_issue()->get_project(),
                                     $this->get_submitter());
            }
        }
        IDF_Search::index($this->get_issue());
    }

    public function timelineFragment($request)
    {
        $issue = $this->get_issue();
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view',
                                        array($request->project->shortname,
                                              $issue->id));
        $url .= '#ic'.$this->id;
        $out = "\n".'<tr class="log"><td><a href="'.$url.'">'.
            Pluf_esc(Pluf_Template_dateAgo($this->creation_dtime, 'without')).
            '</a></td><td>';
        $stag = new IDF_Template_ShowUser();
        $user = $stag->start($this->get_submitter(), $request, '', false);

        $ic = (in_array($issue->status, $request->project->getTagIdsByStatus('closed'))) ? 'issue-c' : 'issue-o';
        $out .= sprintf(__('<a href="%1$s" class="%2$s" title="View issue">Issue %3$d</a>, %4$s'), $url, $ic, $issue->id, Pluf_esc($issue->summary));

        if ($this->changedIssue()) {
            $out .= '<div class="issue-changes-timeline">';
            foreach ($this->changes as $w => $v) {
                $out .= '<strong>';
                switch ($w) {
                case 'su':
                    $out .= __('Summary:'); break;
                case 'st':
                    $out .= __('Status:'); break;
                case 'ow':
                    $out .= __('Owner:'); break;
                case 'lb':
                    $out .= __('Labels:'); break;
                case 'rel':
                    $out .= __('Relations:'); break;
                }
                $out .= '</strong>&nbsp;';
                if ($w == 'lb' || $w == 'rel') {
                    foreach ($v as $t => $ls) {
                        foreach ($ls as $l) {
                            if ($t == 'rem') $out .= '<s>';
                            $out .= Pluf_esc($l);
                            if ($t == 'rem') $out .= '</s>';
                            $out .= ' ';
                        }
                    }
                } else {
                    $out .= Pluf_esc($v);
                }
                $out .= ' ';
            }
            $out .= '</div>';
        }
        $out .= '</td></tr>';
        $out .= "\n".'<tr class="extra"><td colspan="2">
<div class="helptext right">'.sprintf(__('Comment on <a href="%1$s" class="%2$s">issue %3$d</a>, by %4$s'), $url, $ic, $issue->id, $user).'</div></td></tr>';
        return Pluf_Template::markSafe($out);
    }

    public function feedFragment($request)
    {
        $issue = $this->get_issue();
        $url = Pluf::f('url_base')
            .Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view',
                                      array($request->project->shortname,
                                            $issue->id));
        $title = sprintf(__('%1$s: Comment on issue %2$d - %3$s'),
                         Pluf_esc($request->project->name),
                         $issue->id, Pluf_esc($issue->summary));
        $url .= '#ic'.$this->id;
        $date = Pluf_Date::gmDateToGmString($this->creation_dtime);
        $context = new Pluf_Template_Context_Request(
                       $request,
                       array('url' => $url,
                             'author' => $issue->get_submitter(),
                             'title' => $title,
                             'c' => $this,
                             'issue' => $issue,
                             'date' => $date)
                                                     );
        $tmpl = new Pluf_Template('idf/issues/feedfragment.xml');
        return $tmpl->render($context);
    }

    public function get_submitter_data()
    {
        return IDF_UserData::factory($this->get_submitter());
    }
}
