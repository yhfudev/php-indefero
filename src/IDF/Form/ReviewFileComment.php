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
 * Add comments to files in a review.
 *
 */
class IDF_Form_ReviewFileComment extends Pluf_Form
{
    public $files = null;
    public $patch = null;
    public $user = null;
    public $project = null;

    public function initFields($extra=array())
    {
        $this->files = $extra['files'];
        $this->patch = $extra['patch'];
        $this->user = $extra['user'];
        $this->project = $extra['project'];

        foreach ($this->files as $filename => $def) {
            $this->fields[md5($filename)] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Comment'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'widget_attrs' => array(
                                                       'cols' => 58,
                                                       'rows' => 9,
                                                                    ),
                                            ));
        }
        $this->fields['content'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('General comment'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'widget_attrs' => array(
                                                       'cols' => 58,
                                                       'rows' => 9,
                                                                    ),
                                            ));
        if ($this->user->hasPerm('IDF.project-owner', $this->project)
            or $this->user->hasPerm('IDF.project-member', $this->project)) {
            $this->show_full = true;
        } else {
            $this->show_full = false;
        }
        if ($this->show_full) {
            $this->fields['summary'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Summary'),
                                            'initial' => $this->patch->get_review()->summary,
                                            'widget_attrs' => array(
                                                       'maxlength' => 200,
                                                       'size' => 67,
                                                                    ),
                                            ));

            $this->fields['status'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Status'),
                                            'initial' => $this->patch->get_review()->get_status()->name,
                                            'widget_attrs' => array(
                                                       'maxlength' => 20,
                                                       'size' => 15,
                                                                    ),
                                            ));
        }
    }


    /**
     * Validate the interconnection in the form.
     */
    public function clean()
    {
        $isOk = false;
        
        foreach($this->files as $filename => $def) {
            $this->cleaned_data[md5($filename)] = trim($this->cleaned_data[md5($filename)]);
            if(!empty($this->cleaned_data[md5($filename)])) {
                $isOk = true;
            }
        }
        
        if(!empty($this->cleaned_data['content'])) {
            $isOk = true;
        }
        
        if (!$isOk) {
            throw new Pluf_Form_Invalid(__('You need to provide your general comment about the proposal, or comments on at least one file.'));
        }
        
        return $this->cleaned_data;
    }

    function clean_content()
    {
        $content = trim($this->cleaned_data['content']);
        if(empty($content)) {
            if ($this->fields['status']->initial != $this->fields['status']->value) {
                return __('The status have been updated.');
            }
        } else {
            return $content;
        }
        
        throw new Pluf_Form_Invalid(__('This field is required.'));
    }

    /**
     * Save the model in the database.
     *
     * @param bool Commit in the database or not. If not, the object
     *             is returned but not saved in the database.
     * @return Object Model with data set from the form.
     */
    function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save the model from an invalid form.'));
        }
        // create a base comment
        $bc = new IDF_Review_Comment();
        $bc->patch = $this->patch;
        $bc->submitter = $this->user;
        $bc->content = $this->cleaned_data['content'];
        $review = $this->patch->get_review();
        if ($this->show_full) {
            // Compare between the old and the new data
            // Status, summary 
            $changes = array();
            $status = IDF_Tag::add(trim($this->cleaned_data['status']), $this->project, 'Status');
            if ($status->id != $this->patch->get_review()->status) {
                $changes['st'] = $status->name;
            }
            if (trim($this->patch->get_review()->summary) != trim($this->cleaned_data['summary'])) {
                $changes['su'] = trim($this->cleaned_data['summary']);
            }
            // Update the review
            $review->summary = trim($this->cleaned_data['summary']);
            $review->status = $status;
            $bc->changes = $changes;
        }
        $bc->create();
        foreach ($this->files as $filename => $def) {
            if (!empty($this->cleaned_data[md5($filename)])) {
                // Add a comment.
                $c = new IDF_Review_FileComment();
                $c->comment = $bc;
                $c->cfile = $filename;
                $c->content = $this->cleaned_data[md5($filename)];
                $c->create();
            }
        }
        $review->update(); // reindex and put up in the list.
        return $bc;
    }
}
