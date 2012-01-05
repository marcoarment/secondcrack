<?php

function _match_owners_if_possible($target_filename, $match_from_filename)
{
    try {
        if (false === ($intended_uid = fileowner($match_from_filename)) ) throw new Exception("fileowner failed on source");
        if (false === ($intended_gid = filegroup($match_from_filename)) ) throw new Exception("filegroup failed on source");

        if (false === ($uid = fileowner($target_filename)) ) throw new Exception("fileowner failed on target");
        if (false === ($gid = filegroup($target_filename)) ) throw new Exception("filegroup failed on target");
        
        if ($intended_uid != $uid && ! chown($target_filename, $intended_uid)) throw new Exception("chown failed on target");
        if ($intended_gid != $gid && ! chgrp($target_filename, $intended_gid)) throw new Exception("chgrp failed on target");
    } catch (Exception $e) {
        error_log("Cannot assign ownership of [$target_filename] to owner of [$match_from_filename]: " . $e->getMessage());
    }
}

function file_put_contents_as_dir_owner($filename, $data)
{
    if (false !== ($ret = file_put_contents($filename, $data)) ) {
        _match_owners_if_possible($filename, dirname($filename));
    }    
    return $ret;
}

function mkdir_as_parent_owner($pathname, $mode = 0777, $recursive = false)
{
    if (false !== ($ret = mkdir($pathname, $mode, $recursive)) ) {
        _match_owners_if_possible($filename, dirname($pathname));
    }
    return $ret;
}

function h($text) 
{ 
    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    return htmlentities($text, ENT_QUOTES, 'UTF-8'); 
}

function js_array($in_array)
{
    if (! is_array($in_array)) return '[]';
    return '[' . implode(',', array_map('js_string', $in_array)) . ']';
}

function js_string($input)
{
    if (! strlen($input)) return "''";
    try {
        $wchars = mb_convert_encoding($input, 'UCS-2', 'UTF-8');
        $out = '';
        foreach (str_split($wchars, 2) as $wchar) {
            list($o1, $o2) = array(ord($wchar[0]), ord($wchar[1]));
            if ($o1 == 0 && (
                $o2 <= 0x1F || 
                $wchar[1] == '<' || $wchar[1] == '>' || $wchar[1] == '"' || $wchar[1] == '&'
            )) {
                $out .= '\x' . sprintf('%02x', $o2);
            } else if ($o1 == 0 && $wchar[1] == "\\") {
                $out .= "\\\\";
            } else if ($o1 == 0 && $wchar[1] == "'") {
                $out .= "\'";
            } else if ($o1 == 0 && $o2 <= 0x7F) {
                $out .= $wchar[1];
            } else {
                $out .= '\u' . sprintf('%02x%02x', $o1, $o2);
            }
        }
        return "'$out'";
    } catch (Exception $e) {
        error_log('js_string(): ' . $e->getMessage() . ' [input:[' . $input . ']]');

        $str = str_replace(array('\\', "'"), array("\\\\", "\\'"), $input);
        $str = preg_replace('#([\x00-\x1F])#e', '"\x" . sprintf("%02x", ord("\1"))', $str);
        return "'{$str}'";
    }
}

function starts_with($haystack, $needle)
{
    return (substr($haystack, 0, strlen($needle)) == $needle);
}

function ends_with($haystack, $needle)
{
    return (substr($haystack, 0 - strlen($needle)) == $needle);
}

function contains($haystack, $needle)
{
    return (strpos($haystack, $needle) !== false);
}

function contains_any($haystack, array $needles)
{
    foreach ($needles as $needle) {
        if (strpos($haystack, $needle) !== false) return true;
    }
    return false;
}

function contains_all($haystack, array $needles)
{
    foreach ($needles as $needle) {
        if (strpos($haystack, $needle) === false) return false;
    }
    return true;
}

function substring_before($haystack, $needle, $from_end = false)
{
    if ($from_end) {
        if ( ($p = strrpos($haystack, $needle)) === false) return $haystack;
    } else {
        if ( ($p = strpos($haystack, $needle)) === false) return $haystack;
    }
    return substr($haystack, 0, $p);
}

function substring_after($haystack, $needle, $from_end = false)
{
    if ($from_end) {
        if ( ($p = strrpos($haystack, $needle)) === false) return $haystack;
    } else {
        if ( ($p = strpos($haystack, $needle)) === false) return $haystack;
    }
    return substr($haystack, $p + strlen($needle));
}

function substring_between($haystack, $left_needle, $right_needle, $from_end = false)
{
    return substring_before(
        substring_after($haystack, $left_needle), $right_needle, $from_end
    );
}

function normalize_space($str, $trim_beginning_and_end = true)
{
    if ($trim_beginning_and_end) $str = trim($str);
    $str = str_replace("\xC2\xA0", ' ', $str);
    return preg_replace('/\s\s+/ms', ' ', $str);
}

function pluralize($number, $noun, $nouns = false)
{
    if (! $nouns) $nouns = $noun . 's';
    return number_format($number) . ' ' . ($number == 1 ? $noun : $nouns);
}

function array_to_verbal_list(array $a, $and_or_or = 'and')
{
    $c = count($a);
    if (! $c) return '';
    if ($c == 1) return $a[0];
    if ($c == 2) return $a[0] . ' ' . $and_or_or . ' ' . $a[1];
    $last = array_pop($a);
    return implode(', ', $a) . ', ' . $and_or_or . ' ' . $last;
}

function strip_tags_preserve_space($str, $allowable_tags = false)
{    
    $str = preg_replace('#([^ ])<(p|br|ul|ol|li)/?>#msi', '\\1 <\\2>', $str);
    return (
        $allowable_tags ? strip_tags($str, $allowable_tags) : strip_tags($str)
    );
}

function summarize(
    $text, $length, $ellipsis = '...', $encoding = 'UTF-8', 
    $strict_cut = false, $strict_sentences = false
)
{
    if ($length == 0) return $text;
    
    if ($strict_sentences) {
        $split_char = '.';
        $ellipsis = '.' . $ellipsis;
    } else {
        $split_char = ' ';
    }
        
    if (mb_strlen($text, $encoding) <= $length) return $text;
    $cut_str = mb_substr($text, 0, $length + 1, $encoding);
    $split_pos = (
        $cut_str ? mb_strrpos($cut_str, $split_char, $encoding) : false
    );
    
    if ($split_pos) {
        return mb_substr($text, 0, $split_pos, $encoding) . $ellipsis;
    } else {
        return ($strict_cut ? $ellipsis : $cut_str . $ellipsis);
    }
}

function safe_unlink($file)
{
    try { @unlink($file); } catch (Exception $e) { }
}

