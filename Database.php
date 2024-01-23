<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * @param string $query
     * @param array $args
     * @return string
     * @throws Exception
     */
    public function buildQuery(string $query, array $args = []): string
    {
        if (count($args) == 0) {
            return $query;
        }

        $skip = false;

        foreach ($args as $arg) {
            if ($arg === $this->skip()) {
                $skip = true;
            }
        }
        if ($skip === false && strpos($query, '{') && strpos($query, '}')) {
            $query = str_replace(array('{', '}'), '', $query);
        } elseif ($skip === true) {
            $query = preg_replace('/\{.*?\}/', '', $query);
        }

        foreach ($args as $param) {
            $placeholder = '?';

            if (is_array($param)) {
                if (!empty($param) && array_keys($param) !== range(0, count($param) - 1) || is_int($param[0])) {
                    $placeholder = '?a';
                    $formattedParam = $this->formatArrayParam($param, $placeholder);
                } elseif (array_keys($param) === range(0, count($param) - 1)) {
                    $placeholder = '?#';
                    $formattedParam = $this->formatArrayParam($param, $placeholder);
                }
            } elseif (is_int($param) || is_bool($param)) {
                $placeholder = '?d';
                $formattedParam = $this->formatParam($param, $placeholder);
            } elseif (is_float($param)) {
                $placeholder = '?f';
                $formattedParam = $this->formatParam($param, $placeholder);
            } elseif (is_string($param)) {
                if (count($args) > 1) {
                    $placeholder = '?#';
                    $formattedParam = "`$param`";
                } else {
                    $formattedParam = $this->formatParam($param, $placeholder);
                }
            } else {
                $formattedParam = $this->formatParam($param, $placeholder);
            }

            $query = preg_replace('/' . preg_quote($placeholder, '/') . '/', $formattedParam, $query, 1);
        }

        return $query;
    }

    /**
     * @param $param
     * @param $placeholder
     * @return float|int|string
     * @throws Exception
     */
    private function formatParam($param, $placeholder): float|int|string
    {
        if ($param === null) {
            return 'NULL';
        }

        switch (gettype($param)) {
            case 'string':
                if ($placeholder == '?#') {
                    return "`" . mysqli_real_escape_string($this->mysqli, $param) . "`";
                } else {
                    return "'" . mysqli_real_escape_string($this->mysqli, $param) . "'";
                }
            case 'integer':
                return (int)$param;
            case 'double':
                return (float)$param;
            case 'boolean':
                return $param ? 1 : 0;
            default:
                throw new Exception('Invalid parameter type');
        }
    }

    /**
     * @param array $param
     * @param $placeholder
     * @return string
     * @throws Exception
     */
    private function formatArrayParam(array $param, $placeholder): string
    {
        $formattedParam = [];

        foreach ($param as $key => $value) {
            if ($value === null) {
                $formattedParam[] = "`$key` = NULL";
            } else {
                $formattedParam[] = is_int($key) ? $this->formatParam($value, $placeholder) : "`$key` = " . $this->formatParam($value, $placeholder);
            }
        }

        return implode(', ', $formattedParam);
    }

    /**
     * @return string
     */
    public function skip(): string
    {
        return '__SKIP_BLOCK__';
    }
}