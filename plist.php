<?php
/*
 * The MIT License
 *
 * Copyright (c) 2011 Shuhei Tanuma
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */


/**
 * Plist
 * NextStep Plist format parser.
 *
 * you should use CFPropertyList instead of this.
 * currentry, this libarary is focusing to parse NextStep style Plist file.
 * 
 * you might also use plutil.
 */
class Plist{
	const CF_TYPE_OBJECT = 0x01;
	const CF_TYPE_ARRAY = 0x02;
	
	const OP_SPACE = 0x00;
	const OP_DICT_KEY = 0x01;
	const OP_EQUAL = 0x02;
	const OP_SPACE2 = 0x03;
	const OP_VALUE = 0x04;
	const OP_SEMI_COLON = 0x05;

	protected static $line = 0;

	public static function parse($data, &$offset=0, $length = 0) {
		$result = array();

		if($length == 0) {
			self::$line = 0;
			$length = strlen($data);
		}

		$flag = $mark = $sub = 0x0;
		$arr_nest  = $obj_nest = 0;
		$token_offset = 0;
		$DQ = false;

		for (;$offset<$length;$offset++) {
			$do_process = true;

			$previous = ($offset > 0) ? $data[$offset-1] : null;
			$current  = $data[$offset];
			$next     = ($offset+1 < $length) ? $data[$offset+1] : null;
			
			/** ignore comments */
			if ($DQ == false && $current == "/" && $next == "/") {
				for(;$offset<$length && $data[$offset] != "\n";$offset++){}
				self::$line++;
				continue;
			} else if ($DQ == false && $current == "/" && $next == "*") {
				for($offset; $offset<$length && ($data[$offset] == "*" && $data[$offset+1] == "/") == false;$offset++){}
				$offset +=1;
				continue;
			}
			
			/* skip spaces */
			if ($flag == self::CF_TYPE_OBJECT) {
				if ($DQ == false && ($sub == self::OP_SPACE || $sub == self::OP_EQUAL || $sub == self::OP_SPACE2 || $sub == self::OP_SEMI_COLON)) {
					if ($current == " " || $current == "\t" || $current == "\r" || $current == "\n") {
						$do_process = false;
					}
				}
			}
			
			if ($do_process) {
				/* detect object type */
				if ($flag == self::OP_SPACE) {
					switch ($current) {
						case '{':
							$flag = self::CF_TYPE_OBJECT;
							break;
						case '(':
							$flag = self::CF_TYPE_ARRAY;
							break;
						default:
							throw new Exception("Unexpected token {$current} found.");
							break;
					}
					$token_offset = 0;
					$obj_nest++;

				} else if ($flag == self::CF_TYPE_OBJECT) {
					if ($sub == self::OP_SPACE) {
						if ($current == "}") {
							/* Todo: empty / omitted object */
							for(; $offset<$length && ($data[$offset] != ";");$offset++){}
							return $result;
						} else if ($token_offset == 0 && $current == '"') {
							$DQ = true;
						}
						$sub = self::OP_DICT_KEY;
						$mark = $offset;
						$token_offset = 0;

					} else if ($sub == self::OP_DICT_KEY) {
						// finding key
						if ($DQ == false && ($current == ";" || $current == " " || $current == "\t" || $current == "\r" || $current == "\n")) {
							$key = substr($data,$mark,$offset-$mark);
							$result[$key] = null;
							$mark =  0;
							$sub = self::OP_EQUAL;
						} else if ($DQ == true && ($current == '"' && $previous != "\\")) {
							$DQ = false;
							$key = substr($data,$mark,$offset-$mark);
							$result[$key] = null;
							$mark =  0;
							$sub = self::OP_EQUAL;
						} else {
						}
					} else if ($sub == self::OP_EQUAL) {
						if ($current == '='){
							$sub = self::OP_SPACE2;
						} else {
							throw new Exception("parse error: unexpected token `{$current}` found.");
						}
					} else if ($sub == self::OP_SPACE2) {
						if ($current == "{") {
							$result[$key] = self::parse($data,$offset,$length);
							$sub = self::OP_SPACE;
						} else if ($current == "(") {
							$result[$key] = self::parse($data,$offset,$length);
							$sub = self::OP_SPACE;
						} else if ($current == '"') {
							$DQ = true;
							$sub = self::OP_VALUE;
							$mark = $offset;
						} else {
							$sub = self::OP_VALUE;
							$mark = $offset;
						}
					} else if ($sub == self::OP_VALUE) {
						if ($DQ == false && ($current == ";" || $current == " " || $current == "\t" || $current == "\r" || $current == "\n")) {
							$value = substr($data,$mark,$offset-$mark);
							$mark =  0;
							if ($current == ";"){
								$sub = self::OP_SPACE;
							} else {
								$sub = self::OP_SEMI_COLON;
							}
							$result[$key] = $value;
						} else if ($DQ == true && ($current == '"' && $previous != "\\")) {
							$value = substr($data,$mark,$offset-$mark);

							$DQ = false;
							$mark =  0;
							if ($current == ";"){
								$sub = self::OP_SPACE;
							} else {
								$sub = self::OP_SEMI_COLON;
							}
							$result[$key] = $value;
						} else {
							
						}
					} else if ($sub == self::OP_SEMI_COLON) {
						if ($current == ";") {
							$sub = self::OP_SPACE;
						} else {
							throw new Exception("parse error: unexpected token `{$current}` found. line:{self::$line}");
						}
					}

				} else if ($flag == self::CF_TYPE_ARRAY) {
					if ($sub == 0x0) {
						// skip spaces;
						if ($current == " " || $current == "\t" || $current == "\r" || $current == "\n") {
						} else if ($current == ")") {
							/* Todo */
							for($offset; $offset<$length && ($data[$offset] != ";");$offset++){}
							return $result;
						} else if ($current == '"') {
							$DQ = true;
							$sub = 0x1;
							$mark = $offset;
						} else {
							$sub = 0x1;
							$mark = $offset;
						}
					} else if ($sub == 0x1) {
						// finding values
						if ($DQ == false && ($current == "," || $current == " " || $current == "\t" || $current == "\r" || $current == "\n")) {
							$value = substr($data,$mark,$offset-$mark);
							$result[] = $value;
							$mark =  0;
							if($current == ","){
								$sub = 0x00;
							} else {
								$sub = 0x02;
							}
						} else if ($DQ == true && ($current == '"' && $previous != "\\")) {
							$value = substr($data,$mark,$offset-$mark);
							$result[] = $value;
							$mark =  0;
							$sub = 0x02;
						} else if ($current == ")"){
							$value = substr($data,$mark,$offset-$mark);
							$result[] = $value;
							for($j=$offset; $j<$length && ($data[$j] != ";");$j++){}
							$offset = $j;

							return $result;
						} else {
						}
					} else if ($sub == 0x2) {
						if ($current == "," || $current == " " || $current == "\t" || $current == "\r" || $current == "\n") {
							$mark =  0;
							$sub = 0x00;
						} else {
							throw new Exception("2parse error: unexpected token `{$current}` found.");
						}
					}
				}
			}

			if ($current == "\n") {
				self::$line++;
			}
		}

		return $result;
	}
}