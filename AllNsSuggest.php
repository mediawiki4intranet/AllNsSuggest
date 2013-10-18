<?php

/* AllNsSuggest extension
 * Copyright (c) 2011, Vitaliy Filippov <vitalif[d.o.g]mail.ru>
 * License: GPLv3.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

# This extension suggests pages from all namespaces instead of
# just Main, in case there is no explicit one specified.

$wgExtensionCredits['other'][] = array(
    'name'           => 'AllNsSuggest',
    'version'        => '2011-05-13',
    'author'         => 'Vitaliy Filippov',
    'url'            => 'http://wiki.4intra.net/AllNsSuggest',
    'description'    => 'Allows search suggest within all namespaces by default',
);

$wgHooks['PrefixSearchBackend'][] = 'AllNsSuggestPrefixSearch';

# $namespaces and $search is really ignored
# We suggest from either from all namespaces or from one specified in $search as prefix
# $search is taken directly from request

function AllNsSuggestPrefixSearch($namespaces, $search, $limit, &$titles)
{
    global $wgRequest;
    $dbr = wfGetDB(DB_SLAVE);
    $search = $wgRequest->getVal('search');
    $nstest = Title::newFromText($search.'A');
    $where = array();
    if ($nstest && $nstest->getNamespace() != NS_MAIN)
    {
        $where = array('page_title LIKE '.$dbr->addQuotes(substr($nstest->getDBkey(), 0, -1).'%'));
        $ns = array($nstest->getNamespace());
    }
    else
    {
        $where = array('page_title LIKE '.$dbr->addQuotes(str_replace(' ', '_', $search).'%'));
        $res = $dbr->select('page', 'page_namespace', $where, __METHOD__, array('GROUP BY' => 'page_namespace'));
        $ns = array();
        foreach ($res as $row)
        {
            $ns[] = $row->page_namespace;
        }
    }
    $sql = array();
    foreach ($ns as $k)
    {
        $sql[] = $dbr->selectSQLText(
            'page', '*', $where + array('page_namespace' => $k),
            __METHOD__, array('ORDER BY' => 'page_title', 'LIMIT' => $limit)
        );
    }
    $sql = count($sql) > 1 ? '('.implode(') UNION (', $sql).')' : $sql[0];
    $res = $dbr->query($sql, __METHOD__);
    $titles = array();
    foreach ($res as $row)
    {
        $t = Title::newFromRow($row);
        if ($t->userCanRead())
        {
            $titles[] = $t->getPrefixedText();
        }
    }
    return false;
}
