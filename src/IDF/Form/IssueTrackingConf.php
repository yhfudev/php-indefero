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
 * Configuration of the labels etc.
 */
class IDF_Form_IssueTrackingConf extends Pluf_Form
{
    /**
     * Defined as constants to easily access the value in the
     * IssueUpdate/Create form in the case nothing is in the db yet.
     */
    const init_template = 'Steps to reproduce the problem:
1.
2.
3.

Expected result:

Actual result:
';
    const init_open = 'New                 = Issue has not had initial review yet
Accepted            = Problem reproduced / Need acknowledged
Started             = Work on this issue has begun';
    const init_closed = 'Fixed               = Developer made requested changes, QA should verify
Verified            = QA has verified that the fix worked
Invalid             = This was not a valid issue report
Duplicate           = This report duplicates an existing issue
WontFix             = We decided to not take action on this issue';
    const init_predefined = 'Type:Defect          = Report of a software defect
Type:Enhancement     = Request for enhancement
Type:Task            = Work item that doesn\'t change the code or docs
Type:Patch           = Source code patch for review
Type:Other           = Some other kind of issue
Priority:Critical    = Must resolve in the specified milestone
Priority:High        = Strongly want to resolve in the specified milestone
Priority:Medium      = Normal priority
Priority:Low         = Might slip to later milestone
OpSys:All            = Affects all operating systems
OpSys:Windows        = Affects Windows users
OpSys:Linux          = Affects Linux users
OpSys:OSX            = Affects Mac OS X users
Milestone:Release1.0 = All essential functionality working
Component:UI         = Issue relates to program UI
Component:Logic      = Issue relates to application logic
Component:Persistence = Issue relates to data storage components
Component:Scripts    = Utility and installation scripts
Component:Docs       = Issue relates to end-user documentation
Security             = Security risk to users
Performance          = Performance issue
Usability            = Affects program usability
Maintainability      = Hinders future changes';
    const init_one_max = 'Type, Priority, Milestone';
    // ATTENTION: if you change something here, change the values below as well!
    const init_relations = 'is related to
blocks, is blocked by
duplicates, is duplicated by';

    // These are actually all noop's, but we have no other chance to
    // tell IDF's translation mechanism to mark the strings as translatable
    // FIXME: IDF should get a internal translation system for strings like
    // that, that can also be easily expanded by users
    private function noop()
    {
        __('is related to');
        __('blocks');
        __('is blocked by');
        __('duplicates');
        __('is duplicated by');
    }

    public function initFields($extra=array())
    {
        $this->fields['labels_issue_template'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Define an issue template to hint the reporter to provide certain information'),
                                            'initial' => self::init_template,
                                            'widget_attrs' => array('rows' => 7,
                                                                    'cols' => 75),
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            ));

        $this->fields['labels_issue_open'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Open issue status values'),
                                            'initial' => self::init_open,
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'widget_attrs' => array('rows' => 5,
                                                                    'cols' => 75),
                                            ));
        $this->fields['labels_issue_closed'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Closed issue status values'),
                                            'initial' => self::init_closed,
                                            'widget_attrs' => array('rows' => 7,
                                                                    'cols' => 75),
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            ));

        $this->fields['labels_issue_predefined'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Predefined issue labels'),
                                            'initial' => self::init_predefined,
                                            'help_text' => __('The first "Type:" and "Priority:" entries found in this list are automatically chosen as defaults for new issues.'),
                                            'widget_attrs' => array('rows' => 7,
                                                                    'cols' => 75),
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            ));

        $this->fields['labels_issue_one_max'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Each issue may have at most one label with each of these classes.'),
                                            'initial' => self::init_one_max,
                                            'widget_attrs' => array('size' => 60),
                                            ));

        $this->fields['issue_relations'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Issue relations'),
                                            'initial' => self::init_relations,
                                            'help_text' => __('You can define bidirectional relations like "is related to" or "blocks, is blocked by". For standard relations pre-configured translations exist, new relations should however be defined in a language that is understood by all project members.'),
                                            'widget_attrs' => array('rows' => 7,
                                                                    'cols' => 75),
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            ));
    }
}


