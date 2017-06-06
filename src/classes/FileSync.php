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
 class FileSync {

     /** * */
     const CMP_OK = 0;

     /** * */
     const CMP_TYPE = 1;

     /** * */
     const CMP_TARGET = 2;

     /** * */
     const CMP_SUM = 3;

     /** * */
     const CMP_MODE = 4;

     /** * */
     private /*string*/ $mFile;

     /** * */
     private /*string*/ $mName;

     /** * */
     private /*string*/ $mPath;

     /** * */
     private /*string*/ $mSum = null;

     /** * */
     private /*string*/ $mAlgo = null;

     /**
      *
      */
     public function __construct(string $file, string $base=null) {
         $this->mFile = $file;
         $this->mName = $file;
         $this->mPath = $file;

         if (($pos = strrpos($file, "#")) !== false && preg_match("/#(?:xz|bz|gz):[0-9a-f]+$/", $file)) {
             $this->mAlgo = substr($file, $pos+1, (($pos2 = strrpos($file, ":"))-($pos+1)));
             $this->mSum = substr($file, $pos2+1);
             $this->mName = substr($file, 0, $pos);
             $this->mPath = $this->mName;
         }

         if ($base != null && strpos($this->mName, $base) === 0) {
             $this->mPath = trim(substr($this->mName, strlen($base)), "/");
         }
     }

     /**
      *
      */
     public function __get(/*string*/ $name) /*mixed*/ {
         switch ($name) {
             case "file":
                 return $this->mFile;

             case "name":
                 return $this->mName;

             case "path":
                 return $this->mPath;

             case "linkTarget":
                 if (is_link($this->mFile)) {
                     return readlink($this->mFile);
                 }

                 return null;

             case "sum":
                 if ($this->mSum === null && file_exists($this->mFile)) {
                     return substr(sha1_file($this->mFile), 0, 7);
                 }

                 return $this->mSum;

             case "hash":
                 if (file_exists($this->mFile)) {
                     if ($this->isCompressed()) {
                         $handler = new FileCompr($this->mFile, $this->algo);
                         $header = $handler->getHeader();

                         if (!empty($header)) {
                             return $header;
                         }

                     } else {
                         return sha1_file($this->mFile, true);
                     }
                 }

                 return null;

             case "type":
                 if (is_link($this->mFile)) {
                     return "l";

                 } else if (is_dir($this->mFile)) {
                     return "d";

                 } else if (is_file($this->mFile)) {
                     return "f";

                 } else {
                     return "v";
                 }

             case "algo":
                 return $this->mAlgo ?? COMPR_ALGO;

             case "size":
                 if (is_file($this->mFile)) {
                     return filesize($this->mFile);
                 }

                 return 0;
         }

         $trace = debug_backtrace();

         throw new RuntimeException(
             'Undefined property via __get(): ' . $name .
             ' in ' . $trace[0]['file'] .
             ' on line ' . $trace[0]['line']
         );
     }

     /**
      *
      */
     public function exists(): bool {
         return file_exists($this->file);
     }

     /**
      *
      */
     public function isDir(): bool {
         return is_dir(($file = $this->file)) && !is_link($file);
     }

     /**
      *
      */
     public function isFile(): bool {
         return is_file(($file = $this->file)) && !is_link($file);
     }

     /**
      *
      */
     public function isLink(): bool {
         return is_link($this->file);
     }

     /**
      *
      */
     public function isCompressed(): bool {
         return preg_match("/#(?:xz|bz|gz):[0-9a-f]+$/", $this->file);
     }

     /**
      *
      */
     public function isReadable(): bool {
         if ($this->exists()) {
             return is_readable($this->file);
         }

         return true;
     }

     /**
      *
      */
     public function isWritable(): bool {
         if ($this->exists() && !is_writable($this->file)) {
             return false;
         }

         if (is_dir(($dir = dirname($this->file)))) {
             return is_writable($dir);
         }

         return true;
     }

     /**
      *
      */
     public function isOwner(): bool {
         if ($this->exists()) {
             $uid = posix_geteuid();

             if ($uid == 0 || $uid == fileowner($this->file)) {
                 return true;
             }
         }

         return true;
     }

     /**
      *
      */
     public function compare(FileSync $fileInfo): int {
         if ($this->type != $fileInfo->type) {
             return static::CMP_TYPE;

         } else if ($this->isLink() && $this->linkTarget !== $fileInfo->linkTarget) {
             return static::CMP_TARGET;

         } else if ($this->isFile() && $this->sum !== $fileInfo->sum) {
             return static::CMP_SUM;

         } else {
             $stat1 = $this->isLink() ? lstat($this->file) : stat($this->file);
             $stat2 = $fileInfo->isLink() ? lstat($fileInfo->file) : stat($fileInfo->file);

             if (strcmp($stat1["mtime"], $stat2["mtime"]) != 0 && $this->isFile()) {
                 if (strcmp($this->hash, $fileInfo->hash) != 0) {
                     return static::CMP_SUM;
                 }
             }

             /*
              * If this script is running as regular user, any copied files and folders
              * will get the running user as owner. The script will not add another user as owner
              * while not running as root. This means of cause that the owner in these circomstances will differ
              * beween 'src' and 'dst'. So to avoid the script trying to fix it every time, which it will not do,
              * we do not report any issues if owner of a file is the running user while not root.
              */
             $uid = posix_geteuid();
             if (strcmp($stat1["mode"], $stat2["mode"]) != 0
                 || (strcmp($stat1["uid"], $stat2["uid"]) != 0 && ($uid == 0 || strcmp($stat1["uid"], $uid) != 0))
                 || strcmp($stat1["gid"], $stat2["gid"]) != 0) {

                 return static::CMP_MODE;
             }
         }

         return static::CMP_OK;
     }

     /**
      *
      */
     public function touch(FileSync $fileInfo, bool $setSum=false, string $algo=null): bool {
         $stat = $fileInfo->isLink() ? lstat($fileInfo->file) : stat($fileInfo->file);
         $file = $this->file;
         $result = $stat !== false;
         $uid = posix_geteuid();

         if ($this->isLink()) {
             if ($result) {
                 lchown($file, $uid == 0 ? $stat["uid"] : $uid);
                 lchgrp($file, $stat["gid"]);
             }

         } else if ($result) {
             chmod($file, $stat["mode"]);
             chown($file, $uid == 0 ? $stat["uid"] : $uid);
             chgrp($file, $stat["gid"]);
             touch($file, $stat["mtime"], $stat["atime"]);
         }

         if ($setSum && $this->isFile()) {
             $sum = $fileInfo->sum;
             $algo = $algo ?? $this->algo;

             if ($this->sum !== $sum || !$this->isCompressed()) {
                 $newFile = "$this->name#$algo:$sum";

                 if (rename($file, $newFile)) {
                     $this->mFile = $newFile;
                     $this->mSum = $sum;

                     if ($sum === null) {
                         $result = false;
                     }

                 } else {
                     $result = false;
                 }
             }

         } else if ($this->isCompressed()) {
             $newFile = $this->name;

             if (rename($file, $newFile)) {
                 $this->mFile = $newFile;
                 $this->mSum = null;

             } else {
                 $result = false;
             }
         }

         return $result;
     }

     /**
      *
      */
     public function remove(): bool {
         if ($this->isDir()) {
             /*
              * Note:
              *      This should never be used, but just in case.
              *      Next sync will try to sort it out, or alert about it.
              */
             if (!@rmdir($this->file)) {
                 return rename($this->file, dirname($this->file)."/.".trim(basename($this->file), ".").".d".random_int(0, 1000000));
             }

         } else if ($this->exists()) {
             return unlink($this->file);
         }

         return true;
     }

     /**
      *
      */
     public function backup(FileSync $fileInfo): bool {
         if ($fileInfo->isLink()) {
             if ($this->exists()) {
                 if (!$this->remove()) {
                     return false;
                 }
             }

             if (symlink($fileInfo->linkTarget, $this->file)) {
                 return $this->touch($fileInfo, false);
             }

         } else if ($fileInfo->isDir()) {
             if ($this->isDir()) {
                 return $this->touch($fileInfo, false);

             } else if ($this->exists()) {
                 if (!$this->remove()) {
                     return false;
                 }
             }

             if (mkdir($this->file)) {
                 return $this->touch($fileInfo, false);
             }

         } else if ($fileInfo->isFile()) {
             /*
              * The file size must exceed 256bytes in order for the compression
              * to be affective. In many cases you actually encrease the size on files
              * below 256bytes.
              */
             if ($fileInfo->isCompressed() || $fileInfo->size < 256 || COMPR_ALGO == "none") {
                 if ($this->exists()) {
                     if (!$this->remove()) {
                         return false;
                     }
                 }

                 if (copy($fileInfo->file, $this->file)) {
                     return $this->touch($fileInfo, $fileInfo->isCompressed());
                 }

             } else {
                 $manager = new FileCompr($this->file, COMPR_ALGO);

                 if ($manager->compress($fileInfo->file, $fileInfo->hash)) {
                     return $this->touch($fileInfo, true, COMPR_ALGO);
                 }
             }
         }

         return false;
     }

     /**
      *
      */
     public function restore(FileSync $fileInfo): bool {
         /*
          * If it's a folder or a link,
          * then there's no difference between the backup and restore
          */
         if (!$fileInfo->isFile()) {
             return $this->backup($fileInfo);
         }

         if (!$fileInfo->isCompressed()) {
             if ($this->exists()) {
                 if (!$this->remove()) {
                     return false;
                 }
             }

             if (copy($fileInfo->file, $this->file)) {
                 return $this->touch($fileInfo, false);
             }

         } else {
             $manager = new FileCompr($fileInfo->file, $fileInfo->algo);

             if ($manager->decompress($this->file)) {
                 return $this->touch($fileInfo, false);
             }
         }

         return false;
     }
 }
