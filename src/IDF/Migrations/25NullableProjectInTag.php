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

function IDF_Migrations_25NullableProjectInTag_up($params=null)
{
    $engine = Pluf::f('db_engine');
    $db = Pluf::db();

    if ($engine === 'PostgreSQL') {
        $db->execute('ALTER TABLE '.$db->pfx.'idf_tags ALTER COLUMN project DROP NOT NULL');
    } else if ($engine === 'MySQL') {
        $db->execute('ALTER TABLE '.$db->pfx.'idf_tags MODIFY project MEDIUMINT NULL');
        // this is only needed for non-transactional setups where MySQL set 0 as default value
        $db->execute('UPDATE '.$db->pfx.'idf_tags SET project=NULL WHERE project=0');
    }
}

function IDF_Migrations_25NullableProjectInTag_down($params=null)
{
    $engine = Pluf::f('db_engine');
    $db = Pluf::db();
    if ($engine === 'PostgreSQL') {
        $db->execute('ALTER TABLE '.$db->pfx.'idf_tags ALTER COLUMN project SET NOT NULL');
    } else if ($engine === 'MySQL') {
        $db->execute('ALTER TABLE '.$db->pfx.'idf_tags MODIFY project MEDIUMINT NOT NULL');
    }
}
