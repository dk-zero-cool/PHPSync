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

define("PHPSYNC_VERSION", "1.0.1");

define("FLAG_YES", 0b000001);
define("FLAG_DELETE", 0b000010);
define("FLAG_TEST", 0b000100);
define("FLAG_QUIET", 0b001000);
define("FLAG_EXTRACT", 0b010000);
define("FLAG_HELP", 0b100000);

require "classes/Log.php";
require "classes/FileCompr.php";
require "classes/FileSync.php";

$cfg = new stdClass;
$cfg->flags = 0;
$cfg->log = new Log();
$cfg->src = null;
$cfg->dst = null;
$cfg->algo = "gz";

for ($i=1; $i < count($argv); $i++) {
    switch ($argv[$i]) {
        case "--version":
            print PHPSYNC_VERSION."\n"; exit(0);

        case "--help":
            $cfg->flags |= FLAG_HELP; break 2;

        case "--algo":
            $cfg->algo = $argv[++$i];

            if (!in_array($cfg->algo, ["xz", "bz", "gz", "none"])) {
                $cfg->log->write("Compression algo '%s' is not valid", $cfg->algo, true); exit(1);
            }

            break;

        case "--log":
            $path = $argv[++$i];

            if (!file_exists($path) && !file_exists(dirname($path))) {
                $cfg->log->write("The defined log path does not exist", $path, true); exit(1);

            } else if ((file_exists($path) && !is_writable($path)) || (!file_exists($path) && !is_writable(dirname($path)))) {
                $cfg->log->write("The log path '%s' must be writable", $path, true); exit(1);

            } else if (is_dir($path)) {
                $path = rtrim($path, "/")."/phpsync.log";
            }

            $cfg->log->setPath($path);

            break;

        default:
            if ($argv[$i][0] == "-") {
                for ($x=1; $x < strlen($argv[$i]); $x++) {
                    switch ($argv[$i][$x]) {
                        case "v":
                            print PHPSYNC_VERSION."\n"; exit(0);

                        case "h":
                            $cfg->flags |= FLAG_HELP; break 2;

                        case "y":
                            $cfg->flags |= FLAG_YES; break;

                        case "d":
                            $cfg->flags |= FLAG_DELETE; break;

                        case "t":
                            $cfg->flags |= FLAG_TEST; break;

                        case "q":
                            $cfg->log->setQuiet(true);
                            $cfg->flags |= FLAG_QUIET;

                            break;

                        case "x":
                            $cfg->flags |= FLAG_EXTRACT; break;

                        default:
                            $cfg->log->write("Unknown argument '%s'", $argv[$i][$x], true); exit(1);
                    }
                }

            } else {
                if (empty($cfg->src)) {
                    if (!is_dir($argv[$i])) {
                        $cfg->log->write("Source must be a directory", true); exit(1);

                    } else if (!is_readable($argv[$i])) {
                        $cfg->log->write("Source must be readable", true); exit(1);
                    }

                    $cfg->src = realpath($argv[$i]);

                } else if (empty($cfg->dst)) {
                    if (!is_dir($argv[$i])) {
                        $cfg->log->write("Destination must be a directory", true); exit(1);

                    } else if (!is_writable($argv[$i])) {
                        $cfg->log->write("Destination must be writable", true); exit(1);
                    }

                    $cfg->dst = realpath($argv[$i]);

                } else {
                    $cfg->log->write("Unknown argument '%s'", $argv[$i], true); exit(1);
                }
            }
    }
}

if (($cfg->flags & FLAG_HELP) != 0 || $cfg->src === null || $cfg->dst === null) {
    print "PHPSync version ".PHPSYNC_VERSION."\n\n";
    print "Syntax: php " . basename($argv[0]) . " -dy --algo gz --log <path> /src /dst\n\n";
    print "Sync files from src to dst\n\n";
    print "\t-d: Delete files that does not exist in src\n";
    print "\t-y: Don't ask to continue\n";
    print "\t-q: Quiet, only print to log file, except errors\n";
    print "\t-x: Extract files from src into dst\n";
    print "\t-t: Test run, just print without performing any actual actions\n\n";
    print "\t--log <path>: Path to the log file/dir\n";
    print "\t--algo <xz|bz|gz|none>: Change default compression algo\n";
    print "\t--help, -h: Print this screen\n\n";
    print "\t--version, -v: Print the current PHPSync version\n\n";

    exit(0);

} else if (($cfg->flags & FLAG_YES) == 0) {
    if (posix_geteuid() != 0) {
        print "\nWARNING: To avoid permission issues, consider running this as root!!!\n";
    }

    print "\nFrom: ".$cfg->src."\nTo: ".$cfg->dst."\n\n";
    print "Continue? [Y/N]: ";

    $line = fgets(STDIN);

    if (strtolower(trim($line)) == "n") {
        exit(0);
    }
}

$cfg->log->setActive(true);
