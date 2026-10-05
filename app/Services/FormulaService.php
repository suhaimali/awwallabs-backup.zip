<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Tiny arithmetic evaluator for calculated parameters, e.g. "{HCT} / {RBC} * 10".
 * Supports numbers, {CODE} references, + - * /, unary minus and parentheses. No eval().
 * The same grammar is implemented in public/js/result-grid.js.
 */
class FormulaService
{
    /** @return string[] codes referenced by the formula */
    public static function references(string $formula): array
    {
        preg_match_all('/\{([A-Za-z0-9_]+)\}/', $formula, $m);

        return array_values(array_unique(array_map('strtoupper', $m[1])));
    }

    /** Throws InvalidArgumentException with a readable message if the formula is malformed. */
    public static function validate(string $formula): void
    {
        $values = array_fill_keys(self::references($formula), 1.0);
        self::evaluate($formula, $values, true);
    }

    /**
     * @param array<string, float|int|string|null> $values keyed by upper-case code
     */
    public static function evaluate(string $formula, array $values, bool $syntaxOnly = false): ?float
    {
        $tokens = self::tokenize($formula);
        $pos = 0;
        $values = array_change_key_case($values, CASE_UPPER);

        $result = self::parseExpression($tokens, $pos, $values, $syntaxOnly);
        if ($pos < count($tokens)) {
            throw new InvalidArgumentException('Unexpected "' . $tokens[$pos][1] . '" in formula.');
        }

        return $result === null || is_nan($result) || is_infinite($result) ? null : $result;
    }

    private static function tokenize(string $formula): array
    {
        $tokens = [];
        $len = strlen($formula);
        for ($i = 0; $i < $len;) {
            $ch = $formula[$i];
            if (ctype_space($ch)) {
                $i++;
            } elseif (ctype_digit($ch) || $ch === '.') {
                $j = $i;
                while ($j < $len && (ctype_digit($formula[$j]) || $formula[$j] === '.')) {
                    $j++;
                }
                $num = substr($formula, $i, $j - $i);
                if (!is_numeric($num)) {
                    throw new InvalidArgumentException("Invalid number \"{$num}\" in formula.");
                }
                $tokens[] = ['num', (float) $num];
                $i = $j;
            } elseif ($ch === '{') {
                $j = strpos($formula, '}', $i);
                if ($j === false) {
                    throw new InvalidArgumentException('Missing "}" in formula.');
                }
                $code = substr($formula, $i + 1, $j - $i - 1);
                if (!preg_match('/^[A-Za-z0-9_]+$/', $code)) {
                    throw new InvalidArgumentException("Invalid code \"{{$code}}\" in formula.");
                }
                $tokens[] = ['ref', strtoupper($code)];
                $i = $j + 1;
            } elseif (str_contains('+-*/()', $ch)) {
                $tokens[] = ['op', $ch];
                $i++;
            } else {
                throw new InvalidArgumentException("Unexpected \"{$ch}\" in formula. Use numbers, {CODES}, + - * / and brackets.");
            }
        }
        if (!$tokens) {
            throw new InvalidArgumentException('Formula is empty.');
        }

        return $tokens;
    }

    private static function parseExpression(array $t, int &$p, array $v, bool $s): ?float
    {
        $left = self::parseTerm($t, $p, $v, $s);
        while ($p < count($t) && $t[$p][0] === 'op' && in_array($t[$p][1], ['+', '-'], true)) {
            $op = $t[$p++][1];
            $right = self::parseTerm($t, $p, $v, $s);
            $left = ($left === null || $right === null) ? null : ($op === '+' ? $left + $right : $left - $right);
        }

        return $left;
    }

    private static function parseTerm(array $t, int &$p, array $v, bool $s): ?float
    {
        $left = self::parseFactor($t, $p, $v, $s);
        while ($p < count($t) && $t[$p][0] === 'op' && in_array($t[$p][1], ['*', '/'], true)) {
            $op = $t[$p++][1];
            $right = self::parseFactor($t, $p, $v, $s);
            if ($left === null || $right === null) {
                $left = null;
            } elseif ($op === '*') {
                $left *= $right;
            } else {
                $left = $right == 0.0 ? ($s ? 1.0 : null) : $left / $right;
            }
        }

        return $left;
    }

    private static function parseFactor(array $t, int &$p, array $v, bool $s): ?float
    {
        if ($p >= count($t)) {
            throw new InvalidArgumentException('Formula ends unexpectedly.');
        }
        [$type, $val] = $t[$p++];

        if ($type === 'op' && $val === '-') {
            $inner = self::parseFactor($t, $p, $v, $s);
            return $inner === null ? null : -$inner;
        }
        if ($type === 'op' && $val === '(') {
            $inner = self::parseExpression($t, $p, $v, $s);
            if ($p >= count($t) || $t[$p][1] !== ')') {
                throw new InvalidArgumentException('Missing ")" in formula.');
            }
            $p++;
            return $inner;
        }
        if ($type === 'num') {
            return $val;
        }
        if ($type === 'ref') {
            $value = $v[$val] ?? null;
            return is_numeric($value) ? (float) $value : null;
        }

        throw new InvalidArgumentException('Unexpected "' . $val . '" in formula.');
    }
}
