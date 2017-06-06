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

 /**
  *
  */
class Log {

    /** * */
    private /*stream*/ $mStream = null;

    /** * */
    private /*int*/ $mErrors = 0;

    /** * */
    private /*bool*/ $mQuiet = false;

    /** * */
    private /*bool*/ $mActive = false;

    /**
     *
     */
    public function __construct(bool $quiet = false) {
        $this->mQuiet = $quiet;
    }

    /**
     *
     */
    public function __destruct() {
        if (is_resource($this->mStream)) {
            fclose($this->mStream);
        }
    }

    /**
     *
     */
    public function setPath(string $file): void {
        if (is_File($file)) {
            $this->mStream = fopen($file, "w");
        }
    }

    /**
     *
     */
    public function setQuiet(bool $flag): void {
        $this->mQuiet = $flag;
    }

    /**
     *
     */
    public function setActive(bool $flag): void {
        $this->mActive = $flag;
    }

    /**
     *
     */
    public function write(string $msg, /*mixed*/ ...$args): void {
        $err = false;

        if (count($args) > 0) {
            if (is_bool($args[count($args)-1])) {
                $err = array_pop($args);

                if ($err) {
                    $this->mErrors++;
                }
            }

            $msg = sprintf($msg, ...$args);
        }

        if (!$this->mQuiet || $err) {
            if ($err) {
                fwrite(STDERR, "\tE: $msg\n");

            } else {
                fwrite(STDIN, $msg."\n");
            }
        }

        if ($this->mActive && is_resource($this->mStream)) {
            fwrite($this->mStream, ($err ? "\tE: " : "").$msg."\n");
        }
    }

    /**
     *
     */
    public function __get($name) {
        if ($name == "errors") {
            return $this->mErrors;
        }
    }
}
