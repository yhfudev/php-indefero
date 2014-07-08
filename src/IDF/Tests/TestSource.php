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
 * Test the source class.
 */
class IDF_Tests_TestSource extends UnitTestCase 
{
 
    public function __construct() 
    {
        parent::__construct('Test the source class.');
    }

    public function testRegexCommit()
    {
        $regex = '#^/p/([\-\w]+)/source/tree/([^\/]+)/(.*)$#';
        $tests = array('/p/test_project/source/tree/default/current/sources' =>
                       array('test_project', 'default', 'current/sources'),
                       '/p/test_project/source/tree/3.6/current/sources' =>
                       array('test_project', '3.6', 'current/sources'),
                       );
        foreach ($tests as $test => $res) {
            $m = array();
            $t = preg_match($regex, $test, $m);
            $this->assertEqual($res[0], $m[1]);
            $this->assertEqual($res[1], $m[2]);
            $this->assertEqual($res[2], $m[3]);
        }
    }
}
