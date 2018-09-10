<?php

namespace Dallgoot\Yaml;

use Dallgoot\Yaml\{Yaml as Y, Regex as R};

/**
 * TODO
 *
 * @author  Stéphane Rebai <stephane.rebai@gmail.com>
 * @license Apache 2.0
 * @link    TODO : url to specific online doc
 */
final class Node2PHP
{

    /**
     * Returns the correct PHP datatype for the value of the current Node
     *
     * @return mixed  The value as PHP type : scalar, array or Compact, DateTime
     */
    public static function get(Node $n)
    {
        if (is_null($n->value)) return null;
        if ($n->type & (Y::REF_CALL | Y::SCALAR)) return self::getScalar($n->value);
        if ($n->type & (Y::COMPACT_MAPPING | Y::COMPACT_SEQUENCE))
            return self::getCompact(substr($n->value, 1, -1), $n->type);
        $expected = [Y::JSON   => json_decode($n->value, false, 512, JSON_PARTIAL_OUTPUT_ON_ERROR),
                     Y::QUOTED => trim($n->value, "\"'"),
                     Y::RAW    => strval($n->value)];
        return $expected[$n->type] ?? null;
    }

    /**
     * Returns the correct PHP type according to the string value
     *
     * @param string $v a string value
     *
     * @return mixed The value with appropriate PHP type
     */
    private static function getScalar(string $v)
    {
        if (R::isDate($v))   return date_create($v);
        if (R::isNumber($v)) return self::getNumber($v);
        $types = ['yes'   => true,
                    'no'    => false,
                    'true'  => true,
                    'false' => false,
                    'null'  => null,
                    '.inf'  => INF,
                    '-.inf' => -INF,
                    '.nan'  => NAN
        ];
        return array_key_exists(strtolower($v), $types) ? $types[strtolower($v)] : strval($v);
    }

    /**
     * Returns the correct PHP type according to the string value
     *
     * @param string $v a string value
     *
     * @return int|float   The scalar value with appropriate PHP type
     */
    private static function getNumber(string $v)
    {
        if (preg_match("/^(0o\d+)$/i", $v))      return intval(base_convert($v, 8, 10));
        if (preg_match("/^(0x[\da-f]+)$/i", $v)) return intval(base_convert($v, 16, 10));
        return is_bool(strpos($v, '.')) ? intval($v) : floatval($v);
    }

    /**
     * Returns a Compact object representing the inline object/array provided as string
     *
     * @param string  $mappingOrSeqString The mapping or sequence string
     * @param integer $type               The type
     *
     * @return Compact The compact object equivalent to $mappingOrString
     */
    private static function getCompact(string $mappingOrSeqString, int $type):object
    {
        //TODO : this should handle references present inside the string
        $out = new Compact();
        if ($type === Y::COMPACT_SEQUENCE) {
            $f = function ($e) { return self::getScalar(trim($e));};
            //TODO : that's not robust enough, improve it
            foreach (array_map($f, explode(",", $mappingOrSeqString)) as $key => $value) {
                $out[$key] = $value;
            }
        }
        if ($type === Y::COMPACT_MAPPING) {
            //TODO : that's not robust enough, improve it
            foreach (explode(',', $mappingOrSeqString) as $value) {
                list($keyName, $keyValue) = explode(':', $value);
                $out->{trim($keyName, '\'" ')} = self::getScalar(trim($keyValue));
            }
        }
        return $out;
    }
}