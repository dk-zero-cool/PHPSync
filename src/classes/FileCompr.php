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
 class FileCompr {

     /** * */
     private /*string*/ $mFile;

     /** * */
     private /*string*/ $mAlgo;

     /**
      *
      */
     public function __construct(string $file, string $algo="gz") {
         $this->mFile = $file;
         $this->mAlgo = $algo;
     }

     /**
      *
      */
     private function readLine(/*resource*/ $stream): ?string {
         $hash = "";

         switch ($this->mAlgo) {
             case "bz":
                 while(!feof($stream)) {
                     $buffer = bzread($stream, 16);

                     if ($buffer === false || bzerrno($stream) !== 0) {
                         return null;

                     } else if (($pos = strpos($buffer, "\n")) !== false) {
                         /*
                          * Seek the pointer to right after the LF character
                          */
                         fseek($stream, -($pos-1), SEEK_CUR);
                         $hash .= substr($buffer, 0, $pos); break;

                     } else {
                         $hash .= $buffer;
                     }
                 }

                 break;

             case "xz":
                 $hash = fgets($stream); break;

             default:
                 $hash = gzgets($stream);
         }

         /*
          * Note: Trim is important as fgets and gzgets returns the LF character
          */
         return $hash !== false && !empty($hash) ? trim($hash) : null;
     }

     /**
      *
      */
     public function getHeader(): ?string {
         switch ($this->mAlgo) {
             case "bz":
                 $stream = bzopen($this->mFile, "r");
                 if (is_resource($stream)) {
                     $hash = $this->readLine($stream);
                     bzclose($stream);

                     return $hash;
                 }

             case "xz":
                 $stream = fopen($this->mFile, "rb");
                 if (is_resource($stream)) {
                     $hash = $this->readLine($stream);
                     fclose($stream);

                     return $hash;
                 }

             default:
                 $stream = gzopen($this->mFile, "r");
                 if (is_resource($stream)) {
                     $hash = $this->readLine($stream);
                     gzclose($stream);

                     return $hash;
                 }
         }

         return null;
     }

     /**
      *
      */
     public function compress(string $src, string $header): bool {
         switch ($this->mAlgo) {
             case "xz":
                 /*
                  * Temp. convert the binary hash into hex.
                  * The binary data may contain characters that can cause problems when sending
                  * commands as string to the shell. We use 'xxd' to convert it back via pipe,
                  * before adding it to the file.
                  */
                 passthru("echo -n '".bin2hex($header)."' | xxd -r -p | sed 's/$/\\n/' > '".$this->mFile."' && cat '".$src."' | xz >> '".$this->mFile."'", $result);

                 return $result === 0;

             default:
                 $result = false;
                 $input = fopen($src, "r");

                 if (is_resource($input)) {
                     $output = $this->mAlgo == "bz" ?
                         bzopen($this->mFile, "w") : gzopen($this->mFile, "w");

                     if (is_resource($output)) {
                         $result = $this->mAlgo == "bz" ?
                             bzwrite($output, $header."\n") : gzwrite($output, $header."\n");

                         while ($result !== false && !feof($input)) {
                             $buffer = fread($input, 8192);

                             if ($buffer !== false) {
                                 $result = $this->mAlgo == "bz" ?
                                     bzwrite($output, $buffer) : gzwrite($output, $buffer);

                             } else {
                                 $result = false; break;
                             }
                         }

                         if ($this->mAlgo == "bz") {
                             bzclose($output);
                         } else {
                             gzclose($output);
                         }
                     }

                     fclose($input);
                 }

                 return $result !== false;
         }
     }

     /**
      *
      */
     public function decompress(string $dst): bool {
         switch ($this->mAlgo) {
             case "xz":
                 /*
                  * Temp. convert the binary hash into hex.
                  * The binary data may contain characters that can cause problems when sending
                  * commands as string to the shell. We use 'xxd' to convert it back via pipe,
                  * before adding it to the file.
                  */
                 passthru("cat '".$this->mFile."' | sed '1d' | xz -d > '".$dst."'", $result);

                 return $result === 0;

             default:
                 $result = false;
                 $input = $this->mAlgo == "bz" ?
                     bzopen($this->mFile, "r") : gzopen($this->mFile, "r");

                 if (is_resource($input)) {
                     $output = fopen($dst, "w");

                     if (is_resource($output)) {
                         // Remove hash header
                         $this->readLine($input);

                         $result = true;
                         while ($result !== false && !feof($input)) {
                             $buffer = $this->mAlgo == "bz" ?
                                 bzread($input, 8192) : gzread($input, 8192);

                             if ($buffer !== false) {
                                 $result = fwrite($output, $buffer);

                             } else {
                                 $result = false; break;
                             }

                             if ($this->mAlgo == "bz" && bzerrno($input) !== 0) {
                                 $result = false; break;
                             }
                         }

                         if ($this->mAlgo == "bz") {
                             bzclose($output);
                         } else {
                             gzclose($output);
                         }
                     }

                     fclose($input);
                 }

                 return $result !== false;
         }
     }
 }
