<?php

// commentsubmit.php -- Receive comment, sanitize comment, submit comment
//
// Copyright (C) 2011 Matt Palmer <mpalmer@hezmatt.org>
// Copyright (C) 2015 Nat! Mulle kybernetiK
//
//  This program is free software; you can redistribute it and/or modify it
//  under the terms of the GNU General Public License version 3, as
//  published by the Free Software Foundation.
//
//  This program is distributed in the hope that it will be useful, but
//  WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
//  General Public License for more details.
//
//  You should have received a copy of the GNU General Public License along
//  with this program; if not, see <http://www.gnu.org/licences/>

require_once __DIR__ . '/' . 'mulle-jekyll-comment-submitter.php';

/* test it with  

    curl -v -d 'post_id=123456&info=223232&comment=harhar' \
    http://127.0.0.1/~nat/mulle-jekyll-static-comments/php/mullecommentsubmit.php
*/
$committer = new MulleJekyllCommentSubmitter();
$commiter->submit_script = __DIR__ . '/submit-mail.sh';
$committer->submit( $_SERVER, $_POST);

?>
