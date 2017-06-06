<?php
/*
 * This file is part of the PHPSync Project: https://github.com/dk-zero-cool/phpsync
 *
 * Copyright (c) 2017 Daniel Bergløv, License: MIT
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software
 * and associated documentation files (the "Software"), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO
 * THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR
 * THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

require "header.php";

/**
 * =====================================================================
 * ---------------------------------------------------------------------
 *  SYNC PROCESS
 *
 *      When we backup, any compressed file will get the name [name]#sync:[md5].
 *      The hash value makes the files non-unique, meaning that there
 *      could easily be multiple files with the same real name. It also makes it more
 *      dificult to check if a file exist, unless you have the complete name incl. the hash.
 *
 *      Because of this, the first round should WALWAYS be on the directory containing
 *      the files with hash valued names. That means 'dst' during backup and 'src' during restore.
 *      That way we can handle exisiting hash valued file names first and use round 2 'second'
 *      to handle files not currently synced.
 *
 *      It's important that 'src' runs 'SELF_FIRST' and 'dst' runs 'CHILD_FIRST'.
 *      We always sync from 'src' to 'dst' meaning that 'src' will be creating a lot of
 *      directories while 'dst' will be deleting some. When deleting a directory, we need to empty
 *      it first and creating them is best to do one at a time.
 */

$cfg->log->write("PHPSync version %s", PHPSYNC_VERSION);
$cfg->log->write("Sync started at %s", date("Y-m-d H:i"));
$cfg->log->write("Sync from '%s' to '%s'", $cfg->src, $cfg->dst);

$FILES = [];
$ITERATORS = [
    "first" => [
        "path" => ($cfg->flags & FLAG_EXTRACT) == 0 ? $cfg->dst : $cfg->src,
        "flags" => ($cfg->flags & FLAG_EXTRACT) == 0 ? RecursiveIteratorIterator::CHILD_FIRST : RecursiveIteratorIterator::SELF_FIRST
    ],

    "second" => [
        "path" => ($cfg->flags & FLAG_EXTRACT) == 0 ? $cfg->src : $cfg->dst,
        "flags" => ($cfg->flags & FLAG_EXTRACT) == 0 ? RecursiveIteratorIterator::SELF_FIRST : RecursiveIteratorIterator::CHILD_FIRST
    ],
];

foreach ($ITERATORS as $round => $iterators) {
    $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($iterators["path"], FilesystemIterator::SKIP_DOTS|FilesystemIterator::KEY_AS_PATHNAME|FilesystemIterator::CURRENT_AS_FILEINFO),
                        $iterators["flags"]);

    foreach($iterator as $name => $object) {
        /*
         * Create FileSync instances for 'src' and 'dst'
         */
        if ((($cfg->flags & FLAG_EXTRACT) == 0 && $round == "first")
                || (($cfg->flags & FLAG_EXTRACT) != 0) && $round == "second") {

            $dst = new FileSync($name, $cfg->dst);
            $src = new FileSync($cfg->src."/".$dst->path, $cfg->src);
            $cur = $dst;
            $diff = $dst->compare($src);

        } else {
            $src = new FileSync($name, $cfg->src);
            $dst = new FileSync($cfg->dst."/".$src->path, $cfg->dst);
            $cur = $src;
            $diff = $dst->compare($src);
        }

        /*
         * Check to see if this file was handed by the first round.
         */
        if ($round == "second" && isset($FILES[($dir = dirname($cur->path))]) && in_array(basename($cur->path), $FILES[$dir])) {
            continue;
        }

        /*
         * If file modes are the only difference,
         * simply fix it and continue to the next file
         */
        if ($diff == FileSync::CMP_MODE) {
            $cfg->log->write("Changing permissions on '%s'", $cur->path);

            if (!$dst->isOwner()) {
                $cfg->log->write("You don't have permission to make changes to '%s'", $cur->path, true);

            } else if (($cfg->flags & FLAG_TEST) == 0) {
                if(!$dst->touch($src, $dst->isCompressed())) {
                    $cfg->log->write("Failed to change permissions on '%s'", $cur->path, true);
                }
            }

        } else if ($diff != FileSync::CMP_OK && $src->exists()) {
            if (($cfg->flags & FLAG_EXTRACT) == 0) {
                $cfg->log->write("Backing up file '%s'", $cur->path);

            } else {
                $cfg->log->write("Restoring file '%s'", $cur->path);
            }

            if (!$src->isReadable()) {
                $cfg->log->write("You don't have permission to access the source of '%s'", $cur->path, true);

            } else if (!$dst->isWritable()) {
                $cfg->log->write("You don't have permission to modify the destination of '%s'", $cur->path, true);

            } else if (($cfg->flags & FLAG_TEST) == 0) {
                if (($cfg->flags & FLAG_EXTRACT) == 0 && !$dst->backup($src)) {
                    $cfg->log->write("Failed to backup file '%s'", $cur->path, true);

                } else if (($cfg->flags & FLAG_EXTRACT) != 0 && !$dst->restore($src)) {
                    $cfg->log->write("Failed to restore file '%s'", $cur->path, true);
                }
            }

        } else if (!$src->exists()) {
            /*
             * This is never used when we loop against 'src', only on 'dst'.
             * We don't need to check which one is currently being used since looping on 'src'
             * will only provide files that exists, hence it will never parse the condition above.
             */
            if (($cfg->flags & FLAG_DELETE) != 0) {
                $cfg->log->write("Deleting file '%s'", $cur->path);

                if (!$dst->isWritable()) {
                    $cfg->log->write("You don't have permission to delete the destination of '%s'", $cur->path, true);

                } else if (($cfg->flags & FLAG_TEST) == 0) {
                    if (!$dst->remove()) {
                        $cfg->log->write("Failed to delete file '%s'", $cur->path, true);
                    }
                }

            } else {
                $cfg->log->write("Not deleting file '%s'", $cur->path);
            }
        }

        /*
         * Cache this file so that we don't waste time
         * on it during second round.
         */
        if ($round == "first" && ($cur->exists() || ($cfg->flags & FLAG_TEST) != 0)) {
            $dir = dirname($cur->path);

            if (!isset($FILES[$dir])) {
                $FILES[$dir] = [];
            }

            $FILES[$dir][] = basename($cur->path);
        }
    }
}

$cfg->log->write("Sync finished with %d error(s)", $cfg->log->errors);

exit($cfg->log->errors);
