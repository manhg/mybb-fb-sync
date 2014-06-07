<?php
mb_internal_encoding('UTF-8');

function dd() {
    array_map(function($x) { print_r($x); }, func_get_args()); echo "\n"; die;
}

function e($string) {
	return htmlentities($string, ENT_COMPAT, 'UTF-8');
}

function array_only($array, $keys) {
    return array_intersect_key($array, array_flip((array) $keys));
}

/**
 * Convert atom datetime to localdatetime
 */
function datetime_from_atom($atom) {
    static $tz = null;
    if (empty($tz)) {
        $tz = new DateTimeZone('Asia/Tokyo');
    }
    $dt = DateTime::createFromFormat(DateTime::ATOM, $atom);
    $dt->setTimezone($tz);
    return $dt->getTimestamp();
}

/**
 * Based on http://stackoverflow.com/questions/1401317/remove-non-utf8-characters-from-string
 */
function utf8_filter($text) {
    $text = iconv("utf-8", "utf-8//ignore", $text);
    $text = preg_replace('/[\xF0-\xF7].../s', '', $text);
    return $text;
}

/*
 * Run each funciton on a value of a key in an assoc array
 */
function apply_filters($array, $filters) {
    foreach ($filters as $key => $func) {
        if (isset($array[$key])) {
            $array[$key] = $func($array[$key]);
        }
    }
    return $array;
}

function string_excerpt($s, $length = 50) {
	if (mb_strlen($s) > $length) {
		$s = array_shift(explode("\n", $s));
		if (mb_strlen($s) > $length)
			$s = mb_substr($s, 0, $length) . '...';
	}
	return $s;
}
/*
$dt = DateTime::createFromFormat(DateTime::ATOM, '2013-08-07T02:07:15+0000');
echo $dt->getTimestamp();
echo strtotime('2013-05-20 22:03:11');
*/
