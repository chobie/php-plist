<?php


$data = <<<EOF
// !$*UTF8*$!
{
	archiveVersion = "\"$(SRCROOT)\"";
	"super classes" = {};
	objectVersion = 46;
	ARCHS = "$(ARCHS_STANDARD_32_BIT)";
	data = {attr = (raw,);};
	objects = {
		Hello = Moe;
		Ana = {
			Monu = Wana;
		};
	};
	array = (
		abc,
		junior
		);
};
EOF;


define("CF_TYPE_OBJECT",0x01);
define("CF_TYPE_ARRAY",0x02);
var_dump(parse($data));

function parse($data,&$offset=0){
static $line = 0;
$result = array();

$length = strlen($data);
$flag = 0x0;
$sub = 0x0;
$mark = 0;
$obj_nest = 0;
$arr_nest = 0;
$offsetsFirst = true;
$DQ = false;

for ($offset=$offset;$offset<$length;$offset++) {
	$previous = ($offset > 0) ? $data[$offset-1] : null;
	$current = $data[$offset];
	$next = ($offset+1 < $length) ? $data[$offset+1] : null;
	
	// ignore comments
	if ($current == "/" && $next == "/" && $offsetsFirst) {
		// skip until line fead
		for($j=$offset;$j<$length && $data[$j] != "\n";$j++){}
		$offset = $j;
		continue;
		//var_dump(substr($data,$offset,$j));
	} else if ($DQ == false && $current == "/" && $next == "*") {
		for($j=$offset+2; $j<$length && ($data[$j] == "*" && $data[$j+1] == "/") == false;$j++){}
		$offset = $j+1;
		continue;
		//var_dump(substr($data,$offset,$j-$offset+2));
	}
	
	/* process block */
	if ($flag == 0x0) {
		if ($current == "{") {
			$flag = CF_TYPE_OBJECT;
			$obj_nest++;
		} else if ($current == "(") {
			$flag = CF_TYPE_ARRAY;
			$obj_nest++;
		}
	} else if ($flag == CF_TYPE_OBJECT) {
		if ($sub == 0x0) {
			// skip spaces;
			if ($current == " " || $current == "\t" || $current == "\r" || $current == "\n") {
			} else if ($current == "}") {
				for($j=$offset; $j<$length && ($data[$j] != ";");$j++){}
				$offset = $j;
				return $result;
			} else if ($current == '"' && $previous != "\\") {
				$DQ = true;
				$sub = 0x1;
				$mark = $offset;
			} else {
				$sub = 0x1;
				$mark = $offset;
			}
		} else if ($sub == 0x1) {
			// finding key
			if ($DQ == false && ($current == ";" || $current == " " || $current == "\t" || $current == "\r" || $current == "\n")) {
				$key = substr($data,$mark,$offset-$mark);
				$result[$key] = null;
				$mark =  0;
				$sub = 0x02;
			} else if ($DQ == true && ($current == '"' && $previous != "\\")) {
				$DQ = false;
				$key = substr($data,$mark,$offset-$mark);
				$result[$key] = null;
				$mark =  0;
				$sub = 0x02;

			} else {
			}
		} else if ($sub == 0x02) {
			// expected  = 
			if ($current == " " || $current == "\t" || $current == "\r" || $current == "\n") {
			} else if ($current == "="){
				$sub = 0x3;
			} else {
				var_dump(substr($data,$offset));
				throw new Exception("parse error: unexpected token `{$current}` found.");
			}
		} else if ($sub == 0x03) {
			// skip spaces
			if ($current == " " || $current == "\t" || $current == "\r" || $current == "\n") {
			} else if ($current == "{") {
				// nested
				$result[$key] = parse($data,$offset);
				//var_dump(substr($data,$offset));
				//printf("%s : %s\n",$key,json_encode($value));
				$sub = 0x0;
			} else if ($current == "(") {
				// nested array
				$result[$key] = parse($data,$offset);
				//var_dump(substr($data,$offset));
				//printf("%s : %s\n",$key,json_encode($value));
				$sub = 0x0;
			} else if ($current == '"') {
				$DQ = true;
				$sub = 0x4;
				$mark = $offset;
			} else {
				$sub = 0x4;
				$mark = $offset;
			}
		} else if ($sub == 0x04) {
			if ($DQ == false && ($current == ";" || $current == " " || $current == "\t" || $current == "\r" || $current == "\n")) {
				$value = substr($data,$mark,$offset-$mark);
				$mark =  0;
				if ($current == ";"){
					$sub = 0x00;
				} else {
					$sub = 0x05;
				}
				//printf("%s : %s\n",$key,$value);
				$result[$key] = $value;
			} else if ($DQ == true && ($current == '"' && $previous != "\\")) {
				$value = substr($data,$mark,$offset-$mark);

				$DQ = false;
				$mark =  0;
				if ($current == ";"){
					$sub = 0x00;
				} else {
					$sub = 0x05;
				}
				//printf("%s : %s\n",$key,$value);
				$result[$key] = $value;
			} else {
				
			}
		} else if ($sub == 0x05) {
			if ($current == " " || $current == "\t" || $current == "\r" || $current == "\n") {
			} else if ($current == ";") {
				$sub = 0x00;
			} else {
				var_dump(substr($data,$offset));
				printf(
					"
flag :%d
sub :%d
mark :%d
DQ :%d
					",
					$flag,$sub,$mark,$DQ
					);
				throw new Exception("2parse error: unexpected token `{$current}` found. line:{$line}");
			}
		}

	} else if ($flag == CF_TYPE_ARRAY) {
		/*kopipe*/
		
		if ($sub == 0x0) {
			// skip spaces;
			if ($current == " " || $current == "\t" || $current == "\r" || $current == "\n") {
			} else if ($current == ")") {
				for($j=$offset; $j<$length && ($data[$j] != ";");$j++){}
				$offset = $j;
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
				var_dump(substr($data,$offset));
				throw new Exception("2parse error: unexpected token `{$current}` found.");
			}
		}

		/*kopipe*/
	}

	// for next
	if ($current == "\n") {
		$offsetsFirst = true;
		$line++;
	} else {
		$offsetsFirst = false;
	}
}

return $result;
}
