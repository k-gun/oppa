<?php
/**
 * Copyright (c) 2015 Kerem Güneş
 *
 * MIT License <https://opensource.org/licenses/mit>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
declare(strict_types=1);

namespace Oppa\Query;

use Oppa\Util;
use Oppa\Link\Link;
use Oppa\Agent\AgentInterface;
use Oppa\Query\Result\ResultInterface;

/**
 * @package Oppa
 * @object  Oppa\Query\Builder
 * @author  Kerem Güneş <k-gun@mail.com>
 */
final class Builder
{
    /**
     * Builder trait.
     * @object Oppa\Query\BuilderTrait
     */
    use BuilderTrait;

    /**
     * And/or operators.
     * @const string
     */
    public const OP_OR       = 'OR',
                 OP_AND      = 'AND';

    /**
     * Asc/desc operators.
     * @const string
     */
    public const OP_ASC      = 'ASC',
                 OP_DESC     = 'DESC';

    /**
     * Link.
     * @var Oppa\Link\Link
     */
    private $link;

    /**
     * Agent.
     * @var Oppa\Agent\AgentI
     */
    private $agent;

    /**
     * Table.
     * @var string
     */
    private $table;

    /**
     * Query.
     * @var array
     */
    private $query = [];

    /**
     * Is sub.
     * @var bool
     */
    private $isSub = false;

    /**
     * Constructor.
     * @param Oppa\Link\Link|null $link
     * @param string|null         $table
     * @param bool                $isSub
     */
    public function __construct(Link $link = null, string $table = null, bool $isSub = false)
    {
        $link && $this->setLink($link);
        $table && $this->setTable($table);
        $this->isSub = $isSub;
    }

    /**
     * Stringer.
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * Set link.
     * @param  Oppa\Link\Link $link
     * @return self
     */
    public function setLink(Link $link): self
    {
        $this->link = $link;
        $this->agent = $link->getAgent();

        return $this;
    }

    /**
     * Get link.
     * @return ?Oppa\Link\Link
     */
    public function getLink(): ?Link
    {
        return $this->link;
    }

    /**
     * Get agent.
     * @return ?Oppa\Agent\AgentInterface
     */
    public function getAgent(): ?AgentInterface
    {
        return $this->agent;
    }

    /**
     * Set table.
     * @param  string $table
     * @return self
     */
    public function setTable(string $table): self
    {
        $this->table = $this->agent->escapeIdentifier($table);

        return $this;
    }

    /**
     * Get table.
     * @return ?string
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * Reset.
     * @return self
     */
    public function reset(): self
    {
        $this->query = [];

        return $this;
    }

    /**
     * Has.
     * @param  string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->query[$key]);
    }

    /**
     * Select.
     * @param  string|array|Builder $field
     * @param  string               $as
     * @param  bool                 $reset
     * @return self
     * @throws Oppa\Query\BuilderException
     */
    public function select($field, string $as = null, bool $reset = true): self
    {
        $reset && $this->reset();

        // handle other query object
        if ($field instanceof Builder || $field instanceof Sql) {
            if ($as == null) {
                throw new BuilderException('Alias required!');
            }

            return $this->push('select', '('. $field->toString() .') AS '. $this->agent->quoteField($as));
        }

        // handle trivial selects
        if ($field === '1' || $field === 1) {
            return $this->push('select', '1');
        } elseif ($field === true) {
            return $this->push('select', 'TRUE');
        }

        $field = $this->prepareField($field);
        if ($as != null) {
            $field = $field .' AS '. $this->agent->quoteField($as);
        }

        return $this->push('select', $field);
    }

    /**
     * Select more.
     * @param  string|Builder $field
     * @param  string|null    $as
     * @return self
     */
    public function selectMore($field, string $as = null): self
    {
        return $this->select($field, $as, false);
    }

    /**
     * Select json.
     * @param  string|array $field
     * @param  string       $as
     * @param  string       $type
     * @param  bool         $escapeField
     * @param  bool         $reset
     * @return self
     * @throws Oppa\Query\BuilderException
     */
    public function selectJson($field, string $as, string $type = 'object', bool $escapeField = true, bool $reset = true): self
    {
        if (!in_array($type, ['object', 'array'])) {
            throw new BuilderException("Given JSON type '{$type}' is not implemented!");
        }

        $reset && $this->reset();

        static $server, $serverVersion, $serverVersionMin, $fnJsonObject, $fnJsonArray, $toField, $toJson;

        if ($server == null) {
            if ($this->agent->isMysql()) {
                $server = 'MySQL'; $serverVersionMin = '5.7.8';
                $fnJsonObject = 'json_object'; $fnJsonArray = 'json_array';
            } elseif ($this->agent->isPgsql()) {
                $server = 'PostgreSQL'; $serverVersionMin = '9.4';
                $fnJsonObject = 'json_build_object'; $fnJsonArray = 'json_build_array';
            }

            $serverVersion = $this->link->getDatabase()->getInfo('serverVersion');
            if (version_compare($serverVersion, $serverVersionMin) == -1) {
                throw new BuilderException(sprintf('JSON not supported by %s/v%s, minimum v%s required',
                    $server, $serverVersion, $serverVersionMin));
            }

            $toField = function ($field) use ($escapeField) {
                return $escapeField || strpos($field, '.') ? $this->agent->escapeIdentifier($field) : $field;
            };

            $toJson = function ($values) use (&$toJson, &$toField, $escapeField, $fnJsonArray, $fnJsonObject) {
                $json = [];
                foreach ($values as $key => $value) {
                    $keyType = gettype($key);
                    $valueType = gettype($value);
                    if ($valueType == 'array') {
                        // eg: 'bar' => ['baz' => ['a', ['b' => ['c:d'], ...]]]
                        $json[] = is_string($key) ? $this->agent->quote(trim($key)) .', '. $toJson($value)
                            : $toJson($value);
                    } elseif ($keyType == 'integer') {
                        // eg: ['uid: u.id' or 'uid' => ' u.id', 'user' => ['id: u.id' or 'id' => 'u.id', ...], ...]
                        if ($valueType == 'string' && strpbrk($value, ',:')) {
                            if (strpos($value, ',')) {
                                $json[] = $toJson(Util::split(',', $value));
                            } elseif (strpos($value, ':')) {
                                @ [$key, $value] = Util::split(':', $value, 2);
                                if (!isset($key, $value)) {
                                    throw new BuilderException('Field name and value must be given fo JSON objects!');
                                }

                                if (!isset($json[0])) {
                                    $json[0] = $fnJsonObject; // tick
                                }

                                $json[] = $this->agent->quote(trim($key)) .', '. $toField($value);
                            }
                        } else {
                            // eg: ['u.id', 'u.name', 1, 2, 3, ..]
                            if ($valueType == 'integer') {
                                $value = $toField($value);
                            }

                            if (!isset($json[0])) {
                                $json[0] = $fnJsonArray; // tick
                            }

                            $json[] = $value;
                        }
                    } elseif ($keyType == 'string') {
                        // eg: ['uid' => 'u.id']
                        if (!isset($json[0])) {
                            $json[0] = $fnJsonObject; // tick
                        }

                        $json[] = $this->agent->quote(trim($key)) .', '. $toField($value);
                    }
                }

                if ($json) {
                    $fn = array_shift($json);
                    $json = $fn ? $fn .'('. join(', ', $json) .')' : '';
                    if (substr($json, -2) == '()') { // .. :(
                        $json = substr($json, 0, -2);
                    }
                    return $json;
                }

                return null;
            };
        }

        $json = [];
        $jsonJoin = false;
        if (is_string($field)) {
            foreach (Util::split(',', $field) as $field) {
                if ($type == 'object') {
                    // eg: 'id: id, ...'
                    [$key, $value] = Util::split(':', $field, 2);
                    if (!isset($key, $value)) {
                        throw new BuilderException('Field name and value must be given fo JSON objects!');
                    }
                    $json[] = $this->agent->quote(trim($key));
                    $json[] = $toField($value);
                } elseif ($type == 'array') {
                    // eg: 'id, ...'
                    $json[] = $toField($field);
                }
            }
        } elseif (is_array($field)) {
            $keyIndex = 0;
            foreach ($field as $key => $value) {
                $keyType = gettype($key);
                if ($type == 'object') {
                    // eg: ['id' => 'id', ... or 0 => 'id: id, ...', ...]
                    if ($keyType == 'integer') {
                        $value = Util::split(',', $value);
                    } elseif ($keyType != 'string') {
                        throw new BuilderException("Field name must be string, {$keyType} given!");
                    }

                    if (is_array($value)) {
                        if ($keyType == 'string') {
                            // eg: ['id' => 'id', ...]
                            $key = $this->agent->quote(trim($key));
                            $json[$keyIndex][$key] = $toJson($value);
                        } else {
                            // eg: [0 => 'id: id, ...', ...]
                            $value = $toJson($value);
                            $value = preg_replace('~json(?:_build)?_(?:object|array)\((.+)\)$~', '\1', $value); // :(
                            $json[$keyIndex][] = [$value];
                        }
                        $jsonJoin = true;
                        continue;
                    } elseif (is_string($value)) {
                        $key = $this->agent->quote(trim($key));
                        $json[$keyIndex][$key] = $toJson(Util::split(',', $value));
                        $jsonJoin = true;
                        continue;
                    }

                    $json[] = $key .', '. $toField($value);
                } elseif ($type == 'array') {
                    // eg: ['id', 'name', ...]
                    if ($keyType != 'integer') {
                        throw new BuilderException("Field name must be int, {$keyType} given!");
                    }
                    $json[] = $toField($value);
                }
                $keyIndex++;
            }
        } else {
            throw new BuilderException(sprintf('String and array fields accepted only, %s given!',
                gettype($field)));
        }

        $as = $this->agent->quoteField($as);
        $fn = ($type == 'object') ? $fnJsonObject : $fnJsonArray;

        if ($jsonJoin) {
            $jsonJoin = [];
            foreach ($json[0] as $key => $value) {
                $jsonJoin[] = is_array($value) ? join(', ', $value) : $key .', '. $value;
            }
            $json = $jsonJoin;
        }

        return $this->push('select', sprintf('%s(%s) AS %s', $fn, join(', ', $json), $as));
    }

    /**
     * Select json more.
     * @param  string|array $field
     * @param  string       $as
     * @param  string       $type
     * @param  bool         $escapeField
     * @return self
     */
    public function selectJsonMore($field, string $as, string $type = 'object', bool $escapeField = true): self
    {
        return $this->selectJson($field, $as, $type, $escapeField, false);
    }

    /**
     * From
     * @param  string|Builder $field
     * @param  string         $as
     * @return self
     */
    public function from($field, string $as): self
    {
        if (is_string($field) && strpos($field, '(') === false) {
            $field = $this->prepareField($field);
        }

        $this->query['from'] = sprintf('(%s) AS %s', $field, $this->agent->quoteField($as));

        return $this;
    }

    /**
     * Insert.
     * @param  array $data
     * @return self
     */
    public function insert(array $data): self
    {
        $this->reset();
        // simply check is not assoc to prepare multi-insert
        if (!isset($data[0])) {
            $data = [$data];
        }

        return $this->push('insert', $data);
    }

    /**
     * Update.
     * @param  array $data
     * @return self
     */
    public function update(array $data): self
    {
        $this->reset();

        return $this->push('update', $data);
    }

    /**
     * Delete.
     * @return self
     */
    public function delete(): self
    {
        $this->reset();

        return $this->push('delete', true);
    }

    /**
     * Join.
     * @param  string   $to
     * @param  string   $as
     * @param  string   $on
     * @param  any|null $onParams
     * @param  string   $type
     * @return self
     */
    public function join(string $to, string $as, string $on, $onParams = null, string $type = ''): self
    {
        return $this->push('join', sprintf('%sJOIN %s AS %s ON (%s)',
            $type ? strtoupper($type) .' ' : '',
            $this->prepareField($to),
            $this->agent->quoteField($as),
            $this->agent->prepareIdentifier($on, $onParams))
        );
    }

    /**
     * Join left.
     * @param  string   $to
     * @param  string   $as
     * @param  string   $on
     * @param  any|null $onParams
     * @return self
     */
    public function joinLeft(string $to, string $as, string $on, $onParams = null): self
    {
        return $this->join($to, $as, $on, $onParams, 'LEFT');
    }

    /**
     * Right right.
     * @param  string   $to
     * @param  string   $as
     * @param  string   $on
     * @param  any|null $onParams
     * @return self
     */
    public function joinRight(string $to, string $as, string $on, $onParams = null): self
    {
        return $this->join($to, $as, $on, $onParams, 'RIGHT');
    }

    /**
     * Join using.
     * @param  string   $to
     * @param  string   $as
     * @param  string   $using
     * @param  any|null $usingParams
     * @param  string   $type
     * @return self
     */
    public function joinUsing(string $to, string $as, string $using, $usingParams = null, string $type = ''): self
    {
        return $this->push('join', sprintf('%sJOIN %s AS %s USING (%s)',
            $type ? strtoupper($type) .' ' : '',
            $this->prepareField($to),
            $this->agent->quoteField($as),
            $this->agent->prepareIdentifier($using, $usingParams))
        );
    }

    /**
     * Join left using.
     * @param  string   $to
     * @param  string   $as
     * @param  string   $using
     * @param  any|null $usingParams
     * @return self
     */
    public function joinLeftUsing(string $to, string $as, string $using, $usingParams = null): self
    {
        return $this->joinUsing($to, $as, $using, $usingParams, 'LEFT');
    }

    /**
     * Join right using.
     * @param  string   $to
     * @param  string   $as
     * @param  string   $using
     * @param  any|null $usingParams
     * @return self
     */
    public function joinRightUsing(string $to, string $as, string $using, $usingParams = null): self
    {
        return $this->joinUsing($to, $as, $using, $usingParams, 'RIGHT');
    }

    /**
     * Where.
     * @param  string|array|Builder $query
     * @param  any                  $queryParams
     * @param  string               $op
     * @return self
     */
    public function where($query, $queryParams = null, string $op = ''): self
    {
        if ($query == '') {
            throw new BuilderException('Query required!');
        }

        // sub-where
        if ($queryParams instanceof Builder) {
            // $opr argument is empty, should be exists in query (eg: id = )
            $query = $this->prepare($query, '', $queryParams);

            return $this->push('where', $query);
        }

        if (is_array($query)) {
            // eg: [['id' => ...], ...]
            foreach ($query as $field => $params) {
                // eg: ['id' => [1, '=', '?', 'or/and']], ['a.id' => [id('b.id'), ...]]
                if (is_array($params)) {
                    @ [$params, $operator, $escapeOperator, $op] = (array) $params;
                    if ($operator == null) {
                        $operator = '='; // @default=equal
                    }
                    $query = '?? '. $operator .' '. ($params instanceof Identifier ? '??' : $escapeOperator ?: '?');
                    $queryParams = [$field, $params];
                } else {
                    // eg: ['id' => 1]
                    $query = '?? = '. ($params instanceof Identifier ? '??' : '?');
                    $queryParams = [$field, $params];
                }

                $op = $op ? strtoupper($op) : self::OP_AND;
                if ($op != self::OP_OR && $op != self::OP_AND) {
                    throw new BuilderException('Available ops: OR, AND');
                }

                $query = $this->agent->prepare($query, $queryParams);

                $this->push('where', [[$query, $op]]);
            }

            return $this;
        }

        if (!is_string($query)) {
            throw new BuilderException(sprintf('String, array or Builder type queries are accepted only, %s given!',
                gettype($query)));
        }

        if ($queryParams !== null) {
            if (!is_array($queryParams) && !is_scalar($queryParams) && !($queryParams instanceof Identifier)) {
                throw new BuilderException(sprintf('Array, scalar and Query\Identifier params are accepted only, %s given!',
                    gettype($queryParams)));
            }
        }

        $query = $this->agent->prepare($query, $queryParams);

        $op = $op ? strtoupper($op) : self::OP_AND;
        if ($op != self::OP_OR && $op != self::OP_AND) {
            throw new BuilderException('Available ops: OR, AND');
        }

        return $this->push('where', [[$query, $op]]);
    }

    /**
     * Or.
     * @param  any ...$arguments
     * @return self
     */
    public function or(...$arguments): self
    {
        $op = self::OP_OR;

        if (empty($arguments)) {
            // just update last where op
            if ($this->has('where')) {
                $this->query['where'][count($this->query['where']) - 1][1] = $op;
            }

            return $this;
        }

        $query = $arguments[0] ?? null;
        $queryParams = $arguments[1] ?? null;

        return $this->where($query, $queryParams, $op);
    }

    /**
     * And.
     * @param  any ...$arguments
     * @return self
     */
    public function and(...$arguments): self
    {
        $op = self::OP_AND;

        if (empty($arguments)) {
            // just update last where op
            if ($this->has('where')) {
                $this->query['where'][count($this->query['where']) - 1][1] = $op;
            }

            return $this;
        }

        $query = $arguments[0] ?? null;
        $queryParams = $arguments[1] ?? null;

        return $this->where($query, $queryParams, $op);
    }

    /**
     * Where equal.
     * @param  string|array|Builder $field
     * @param  any                  $param
     * @param  string               $op
     * @return self
     */
    public function whereEqual($field, $param, string $op = ''): self
    {
        return $this->where($this->prepareField($field) .' = ?', $param, $op);
    }

    /**
     * Where not equal.
     * @param  string|array|Builder $field
     * @param  any                  $param
     * @param  string               $op
     * @return self
     */
    public function whereNotEqual($field, $param, string $op = ''): self
    {
        return $this->where($this->prepareField($field) .' != ?', $param, $op);
    }

    /**
     * Where null.
     * @param  string|array|Builder $field
     * @param  string               $op
     * @return self
     */
    public function whereNull($field, string $op = ''): self
    {
        return $this->where($this->prepareField($field) .' IS NULL', null, $op);
    }

    /**
     * Where not null.
     * @param  string|array|Builder $field
     * @param  string               $op
     * @return self
     */
    public function whereNotNull($field, string $op = ''): self
    {
        return $this->where($this->prepareField($field) .' IS NOT NULL', null, $op);
    }

    /**
     * Where in.
     * @param  string|array|Builder $field
     * @param  array|Builder        $param
     * @param  string               $op
     * @return self
     */
    public function whereIn($field, $param, string $op = ''): self
    {
        $ops = ['?'];
        if (is_array($param)) {
            $ops = array_fill(0, count($param), '?');
        }

        return $this->where($this->prepareField($field) .' IN ('. join(', ', $ops) .')', $param, $op);
    }

    /**
     * Where not in.
     * @param  string|array|Builder $field
     * @param  array|Builder        $param
     * @param  string               $op
     * @return self
     */
    public function whereNotIn($field, $param, string $op = ''): self
    {
        $ops = ['?'];
        if (is_array($param)) {
            $ops = array_fill(0, count($param), '?');
        }

        return $this->where($this->prepareField($field) .' NOT IN ('. join(', ', $ops) .')', $param, $op);
    }

    /**
     * Where between.
     * @param  string|array|Builder $field
     * @param  array                $params
     * @param  string               $op
     * @return self
     */
    public function whereBetween($field, array $params, string $op = ''): self
    {
        return $this->where($this->prepareField($field) .' BETWEEN ? AND ?', $params, $op);
    }

    /**
     * Where not between.
     * @param  string|array|Builder $field
     * @param  array                $params
     * @param  string               $op
     * @return self
     */
    public function whereNotBetween($field, array $params, string $op = ''): self
    {
        return $this->where($this->prepareField($field) .' NOT BETWEEN ? AND ?', $params, $op);
    }

    /**
     * Where less than.
     * @param  string|array|Builder $field
     * @param  string|number        $param
     * @param  string               $op
     * @return self
     */
    public function whereLessThan($field, $param, string $op = ''): self
    {
        return $this->where($this->prepareField($field) .' < ?', $param, $op);
    }

    /**
     * Where less than equal.
     * @param  string|array|Builder $field
     * @param  string|number        $param
     * @param  string               $op
     * @return self
     */
    public function whereLessThanEqual($field, $param, string $op = ''): self
    {
        return $this->where($this->prepareField($field) .' <= ?', $param, $op);
    }

    /**
     * Where greater than.
     * @param  string|array|Builder $field
     * @param  string|number        $param
     * @param  string               $op
     * @return self
     */
    public function whereGreaterThan($field, $param, string $op = ''): self
    {
        return $this->where($this->prepareField($field) .' > ?', $param, $op);
    }

    /**
     * Where greater than equal.
     * @param  string|array|Builder $field
     * @param  string|number        $param
     * @param  string               $op
     * @return self
     */
    public function whereGreaterThanEqual($field, $param, string $op = ''): self
    {
        return $this->where($this->prepareField($field) .' >= ?', $param, $op);
    }

    /**
     * Where like.
     * @param  string|array|Builder $field
     * @param  string|array         $arguments
     * @param  bool                 $ilike
     * @param  string               $op
     * @param  bool                 $not
     * @return self
     */
    public function whereLike($field, $arguments, bool $ilike = false, string $op = '', bool $not = false): self
    {
        // @note to me..
        // 'foo%'  Anything starts with "foo"
        // '%foo'  Anything ends with "foo"
        // '%foo%' Anything have "foo" in any position
        // 'f_o%'  Anything have "o" in the second position
        // 'f_%_%' Anything starts with "f" and are at least 3 characters in length
        // 'f%o'   Anything starts with "f" and ends with "o"

        $arguments = (array) $arguments;
        switch (count($arguments)) {
            case 1: // none, eg: 'apple', ['apple']
                [$start, $search, $end] = ['', $arguments[0], ''];
                break;
            case 2: // start/end, eg: ['apple', '%'], ['%', 'apple']
                if ($arguments[0] == '%') {
                    [$start, $search, $end] = ['%', $arguments[1], ''];
                } elseif ($arguments[1] == '%') {
                    [$start, $search, $end] = ['', $arguments[0], '%'];
                }
                break;
            case 3: // both, eg: ['%', 'apple', '%']
                [$start, $search, $end] = $arguments;
                break;
        }

        $search = trim((string) ($search ?? ''));
        if ($search == '') {
            throw new BuilderException('Like search cannot be empty!');
        }

        $op = self::OP_OR;
        $not = $not ? ' NOT ' : ' ';
        $fields = $this->agent->escapeIdentifier($field, false);
        $search = $end . $this->agent->escape($search, '%sl', false) . $start;

        if ($ilike) {
            foreach ($fields as $field) {
                if ($this->agent->isMysql()) {
                    $this->where("lower({$field}){$not}LIKE lower('{$search}')", null, $op);
                } elseif ($this->agent->isPgsql()) {
                    $this->where("{$field}{$not}ILIKE '{$search}'", null, $op);
                }
            }
        } else {
            foreach ($fields as $field) {
                $this->where("{$field}{$not}LIKE '{$search}'", null, $op);
            }
        }

        return $this;
    }

    /**
     * Where not like.
     * @param  string|array|Builder $field
     * @param  string|array         $arguments
     * @param  bool                 $ilike
     * @param  string               $op
     * @return self
     */
    public function whereNotLike($field, $arguments, bool $ilike = false, string $op = ''): self
    {
        return $this->whereLike($field, $arguments, $ilike, $op, true);
    }

    /**
     * Where like start.
     * @param  string|array|Builder $field
     * @param  string               $search
     * @param  bool                 $ilike
     * @param  string               $op
     * @return self
     */
    public function whereLikeStart($field, string $search, bool $ilike = false, string $op = ''): self
    {
        return $this->whereLike($field, ['%', $search, ''], $ilike, $op);
    }

    /**
     * Where like end.
     * @param  string|array|Builder $field
     * @param  string               $search
     * @param  bool                 $ilike
     * @param  string               $op
     * @return self
     */
    public function whereLikeEnd($field, string $search, bool $ilike = false, string $op = ''): self
    {
        return $this->whereLike($field, ['', $search, '%'], $ilike, $op);
    }

    /**
     * Where like both.
     * @param  string|array|Builder $field
     * @param  string               $search
     * @param  bool                 $ilike
     * @param  string               $op
     * @return self
     */
    public function whereLikeBoth($field, string $search, bool $ilike = false, string $op = ''): self
    {
        return $this->whereLike($field, ['%', $search, '%'], $ilike, $op);
    }

    /**
     * Where exists.
     * @param  string|Builder $query
     * @param  array|null     $queryParams
     * @param  string         $op
     * @return self
     */
    public function whereExists($query, array $queryParams = null, string $op = ''): self
    {
        if ($query instanceof Builder) {
            $query = $query->toString();
        }

        if ($queryParams != null) {
            $query = $this->agent->prepare($query, $queryParams);
        }

        return $this->where('EXISTS ('. $query .')', null, $op);
    }

    /**
     * Where not exists.
     * @param  string|Builder $query
     * @param  array|null     $queryParams
     * @param  string         $op
     * @return self
     */
    public function whereNotExists($query, array $queryParams = null, string $op = ''): self
    {
        if ($query instanceof Builder) {
            $query = $query->toString();
        }

        if ($queryParams != null) {
            $query = $this->agent->prepare($query, $queryParams);
        }

        return $this->where('NOT EXISTS ('. $query .')', null, $op);
    }

    /**
     * Where id.
     * @param  string     $field
     * @param  int|string $id
     * @param  string     $op
     * @return self
     */
    public function whereId(string $field, $id, string $op = ''): self
    {
        return $this->where('?? = ?', [$field, $id], $op);
    }

    /**
     * Where ids.
     * @param  string            $field
     * @param  array[int|string] $ids
     * @param  string            $op
     * @return self
     */
    public function whereIds(string $field, array $ids, string $op = ''): self
    {
        return $this->where('?? IN (?)', [$field, $ids], $op);
    }

    /**
     * Where match against.
     * @param  string|array|Builder $field
     * @param  array                $arguments
     * @param  string               $mode
     * @param  string               $op
     * @param  bool                 $phrase
     * @return self
     */
    public function search($field, array $arguments, string $mode = '', string $op = '', bool $phrase = false): self
    {
        if ($arguments == null) {
            throw new BuilderException('Search arguments cannot be empty!');
        }

        if (is_string($field) && strpos($field, '(') === false) {
            $field = $this->prepareField($field);
        }

        $query = '';
        $search = [];
        if ($this->agent->isMysql()) {
            // eg: ['+apple', '~banana', ...]
            foreach ($arguments as $argument) {
                if ($argument == null) {
                    throw new BuilderException('Search argument cannot be empty!');
                }

                $argument = $this->agent->escapeString($argument, false);

                $opr = '';
                // handle operators
                if (in_array($argument[0], ['+', '-', '*', '~'])) {
                    $opr = $argument[0];
                    $argument = substr($argument, 1);
                }
                // wrap phrases with ""
                if ($phrase) {
                    $argument = '"'. trim($argument, '\"') .'"';
                }

                $search[] = $opr . $argument;
            }

            $search = join(' ', $search);
            $query = "match({$field}) against('{$search}' ". ($mode ?: 'IN BOOLEAN MODE') .")";
        } elseif ($this->agent->isPgsql()) {
            // eg: ['apple', '!banana', ...], ['apple', '&' or '|', 'banana', ...]
            foreach ($arguments as $argument) {
                if ($argument == null) {
                    throw new BuilderException('Search argument cannot be empty!');
                }

                // operators
                if ($argument == '&' || $argument == '|') {
                    $search[] = $argument;
                    continue;
                }

                $argumentType = gettype($argument);
                if ($argumentType == 'array') {
                    $tmp = [];
                    foreach ($argument as $para) {
                        $tmp[] = $this->agent->escapeString($para, false);
                    }
                    $argument = '('. join(' ', $tmp) .')';
                } elseif ($argumentType == 'string') {
                    $argument = $this->agent->escapeString($argument, false);
                } else {
                    throw new BuilderException(sprintf('Array or string arguments are accepted only, '.
                        '%s given!', $argumentType));
                }

                $search[] = ($argument == ' ') ? ' ' : trim($argument);
            }

            $search = join(' ', $search);
            $query = ($mode == '') ? "to_tsvector({$field}) @@ to_tsquery('{$search}')"
                : "to_tsvector('{$mode}', {$field}) @@ to_tsquery('{$mode}', '{$search}')";
        }

        return $this->push('where', [[$query, $op ?: self::OP_AND]]);
    }

    /**
     * Search like (alias of whereLike()).
     * @param  string|array|Builder $field
     * @param  string|array         $arguments
     * @param  bool                 $ilike
     * @param  string               $op
     * @return self
     */
    public function searchLike($field, $arguments, bool $ilike = false, string $op = ''): self
    {
        return $this->whereLike($field, $arguments, $ilike, $op);
    }

    /**
     * Having.
     * @param  string|Builder $query
     * @param  array|null     $queryParams
     * @param  string         $op
     * @return self
     */
    public function having(string $query, array $queryParams = null, string $op = ''): self
    {
        if ($query instanceof Builder) {
            $query = $query->toString();
        }

        if ($queryParams != null) {
            $query = $this->agent->prepare($query, $queryParams);
        }

        // add and/or operator
        if (!empty($this->query['having'])) {
            $query = sprintf('%s %s', $op, $query);
        }

        return $this->push('having', $query);
    }

    /**
     * Group by.
     * @param  string|array|Builder $field
     * @return self
     */
    public function groupBy($field): self
    {
        return $this->push('groupBy', $this->prepareField($field));
    }

    /**
     * Order by.
     * @param  string|array|Builder $field
     * @param  string               $op
     * @return self
     * @throws Oppa\Query\BuilderException
     */
    public function orderBy($field, string $op = null): self
    {
        // check operator is valid
        if ($op == null) {
            return $this->push('orderBy', $this->prepareField($field));
        }

        $op = strtoupper($op);
        if ($op != self::OP_ASC && $op != self::OP_DESC) {
            throw new BuilderException('Available ops: ASC, DESC');
        }

        return $this->push('orderBy', $this->prepareField($field) .' '. $op);
    }

    /**
     * Order by asc.
     * @param  string|array|Builder $field
     * @return self
     */
    public function orderByAsc($field): self
    {
        return $this->orderBy($field, 'ASC');
    }

    /**
     * Order by desc.
     * @param  string|array|Builder $field
     * @return self
     */
    public function orderByDesc($field): self
    {
        return $this->orderBy($field, 'DESC');
    }

    /**
     * Limit.
     * @param  int      $limit
     * @param  int|null $offset
     * @return self
     */
    public function limit(int $limit, int $offset = null): self
    {
        $this->query['limit'] = ($offset === null)
            ? [abs($limit)] : [abs($limit), abs($offset)];

        return $this;
    }

    /**
     * Offset.
     * @param  int $offset
     * @return self
     */
    public function offset(int $offset): self
    {
        if (!isset($this->query['limit'][0])) {
            throw new BuilderException('Limit not set yet, call limit() first');
        }

        $this->query['limit'][1] = abs($offset);

        return $this;
    }

    /**
     * Aggregate.
     * @param  string      $fn
     * @param  string      $field
     * @param  string|null $as
     * @return self
     * @throws Oppa\Query\BuilderException
     */
    public function aggregate(string $fn, string $field = '*', string $as = null): self
    {
        static $fns = ['count', 'sum', 'avg', 'min', 'max'];

        $fn = strtolower($fn);
        if (!in_array($fn, $fns)) {
            throw new BuilderException(sprintf('Invalid function %s given, %s are supported only!',
                $fn, join(',', $fns)));
        }

        $field = $field ?: '*';

        // if as not provided
        if ($as == null) {
            // aggregate('count', 'x') count_x
            // aggregate('count', 'u.x') count_ux
            $as = ($field && $field != '*') ? preg_replace('~[^\w]~', '', $fn .'_'. $field) : $fn;
        }

        return $this->push('select', sprintf('%s(%s) AS %s', $fn, $field, $this->agent->quoteField($as)));
    }

    /**
     * Run.
     * @return Oppa\Query\Result\ResultInterface
     */
    public function run(): ResultInterface
    {
        return $this->agent->query($this->toString());
    }

    /**
     * Get.
     * @param  string|null $class
     * @return array|object|null
     */
    public function get(string $class = null)
    {
        return $this->agent->get($this->toString(), null, $class);
    }

    /**
     * Get all.
     * @param  string|null $class
     * @return array
     */
    public function getAll(string $class = null): array
    {
        return $this->agent->getAll($this->toString(), null, $class);
    }

    /**
     * Get array.
     * @return ?array
     */
    public function getArray(): ?array
    {
        return $this->get('array');
    }

    /**
     * Get array all.
     * @return array[array]
     */
    public function getArrayAll(): array
    {
        return $this->getAll('array');
    }

    /**
     * Get object.
     * @return ?object
     */
    public function getObject(): ?object
    {
        return $this->get('object');
    }

    /**
     * Get object all.
     * @return array[object]
     */
    public function getObjectAll(): array
    {
        return $this->getAll('object');
    }

    /**
     * Count.
     * @return ?int
     */
    public function count(): ?int
    {
        // no where, count all simply
        $where = $this->query['where'] ?? '';
        if ($where == '') {
            return $this->agent->count($this->table);
        }

        $from = $this->toString();
        if ($from == '') {
            $from = "SELECT 1 FROM {$this->table} WHERE ". join(' ', $where);
        }

        $result = (array) $this->agent->get("SELECT count(*) AS count FROM ({$from}) AS tmp");

        return isset($result['count']) ? intval($result['count']) : null;
    }

    /**
     * To array.
     * @return array
     */
    public function toArray(): array
    {
        return $this->query;
    }

    /**
     * To string.
     * @param  bool $pretty
     * @return string
     */
    public function toString(bool $pretty = true): string
    {
        $string = '';
        if (!empty($this->query)) {
            if (isset($this->query['select'])) {
                $string = $this->toQueryString('select', $pretty);
            } elseif (isset($this->query['insert'])) {
                $string = $this->toQueryString('insert', $pretty);
            } elseif (isset($this->query['update'])) {
                $string = $this->toQueryString('update', $pretty);
            } elseif (isset($this->query['delete'])) {
                $string = $this->toQueryString('delete', $pretty);
            }
        }

        if ($string != '' && $this->isSub) {
            $string .= "\n\t";
        }

        return $string;
    }

    /**
     * To query string.
     * @param  string $key
     * @param  bool   $pretty
     * @return ?string
     * @throws Oppa\Query\BuilderException
     */
    public function toQueryString(string $key, bool $pretty = true): ?string
    {
        if ($this->table == null) {
            throw new BuilderException(
                "Table is not defined yet! Call 'setTable()' to set target table first.");
        }

        $s = ''; $n = $t = $nt = ''; $ns = ' ';
        if ($pretty) {
            $s = "\t"; $n = "\n"; $t = "\t"; $nt = $n.$t; $ns = $n;
            if ($this->isSub) {
                $ns = $nt.$t; $t = $t.$t;
            }
        }

        $string = '';
        switch ($key) {
            case 'select':
                $select = $pretty ? $n . $t . join(', '. $n . $t, $this->query['select'])
                    : join(', ', $this->query['select']);

                $string = sprintf("SELECT %s%s {$n}{$t}FROM %s",
                    $select,
                    $this->toQueryString('aggregate', $pretty),
                    $this->toQueryString('from', $pretty)
                );

                $string = trim(
                    $string
                    . $this->toQueryString('join', $pretty)
                    . $this->toQueryString('where', $pretty)
                    . $this->toQueryString('groupBy', $pretty)
                    . $this->toQueryString('having', $pretty)
                    . $this->toQueryString('orderBy', $pretty)
                    . $this->toQueryString('limit', $pretty)
                );
                break;
            case 'from':
                if ($this->has('from')) {
                    if ($pretty) {
                        $from = preg_split('~^\((.+)\)+\s*(?:AS\s+(.+))~s', $this->query['from'], 2, 3);
                        $from = '('. $nt . $t . implode($nt . $t, explode($n, $from[0])) . $nt .') AS '. $from[1];
                    } else {
                        $from = $this->query['from'];
                    }
                    $string = $from;
                } else {
                    $string = $this->table;
                }
                break;
            case 'insert':
                if ($this->has('insert')) {
                    $data = $this->query['insert'];

                    $keys = $this->agent->escapeIdentifier(array_keys($data[0]));
                    $values = [];
                    foreach ($data as $dat) {
                        $values[] = '('. $this->agent->escape(array_values($dat)) .')';
                    }

                    $string = "INSERT INTO {$this->table} {$nt}({$keys}) {$nt}VALUES ".
                        join(', ', $values);
                }
                break;
            case 'update':
                if ($this->has('update')) {
                    $data = $this->query['update'];

                    $set = [];
                    foreach ($data as $key => $value) {
                        $set[] = $this->agent->escapeIdentifier($key) .' = '. $this->agent->escape($value);
                    }

                    $string = trim(
                        "UPDATE {$this->table} SET {$nt}". join(', ', $set)
                        . $this->toQueryString('where', $pretty)
                        . $this->toQueryString('orderBy', $pretty)
                        . $this->toQueryString('limit', $pretty)
                    );
                }
                break;
            case 'delete':
                if ($this->has('delete')) {
                    $string = trim(
                        "DELETE FROM {$this->table}"
                        . $this->toQueryString('where', $pretty)
                        . $this->toQueryString('orderBy', $pretty)
                        . $this->toQueryString('limit', $pretty)
                    );
                }
                break;
            case 'where':
                if ($this->has('where')) {
                    $ws = []; $wsp = 0;
                    $wheres = $this->query['where'];
                    foreach ($wheres as $i => $where) {
                        [$where, $op] = $where;
                        $nx = $wheres[$i + 1] ?? null;
                        $nxn = isset($wheres[$i + 2]);
                        $nxOp = strtoupper($nx[1] ?? '');
                        $ws[] = $where;
                        if ($nx) {
                            $ws[] = $op;
                        }
                        if ($op != $nxOp && $nxOp && $nxn) {
                            $ws[] = '(';
                            $wsp++;
                        }
                    }

                    $string = preg_replace('~ (OR|AND) \( +(["`])?~i', ' \1 (\2', join(' ', $ws)); // :(
                    $string = $string . str_repeat(')', $wsp); // close parentheses
                    if ($this->isSub) {
                        $n = $n.$t; $nt = $n.$s;
                    }
                    $string = $ns . 'WHERE ('. $nt . $string . $n . ')';
                }
                break;
            case 'groupBy':
                if ($this->has('groupBy')) {
                    $string = $ns .'GROUP BY '. join(', ', $this->query['groupBy']);
                }
                break;
            case 'orderBy':
                if ($this->has('orderBy')) {
                    $string = $ns .'ORDER BY '. join(', ', $this->query['orderBy']);
                }
                break;
            case 'limit':
                if ($this->has('limit')) {
                    $string = isset($this->query['limit'][1])
                        ? $ns .'LIMIT '. $this->query['limit'][0] .' OFFSET '. $this->query['limit'][1]
                        : $ns .'LIMIT '. $this->query['limit'][0];
                }
                break;
            case 'join':
                if ($this->has('join')) {
                    $string = '';
                    foreach ($this->query['join'] as $join) {
                        $string .= $ns . $join;
                    }
                }
                break;
            case 'having':
                if ($this->has('having')) {
                    $string = $ns .'HAVING ('. join(' ', $this->query['having']). ')';
                }
                break;
            case 'aggregate':
                if ($this->has('aggregate')) {
                    $string = ', '. join(', ', $this->query['aggregate']);
                }
                break;
            default:
                throw new BuilderException("Unknown key '{$key}' given");
        }

        return $string ?: null;
    }

    /**
     * Push.
     * @param  string       $key
     * @param  string|array $value
     * @return self
     */
    private function push(string $key, $value): self
    {
        if (!isset($this->query[$key])) {
            $this->query[$key] = [];
        }

        $this->query[$key] = array_merge($this->query[$key], (array) $value);

        return $this;
    }

    /**
     * Prepare.
     * @param  string|array|Builder $field
     * @param  string               $opr
     * @param  array|Builder        $params
     * @return string
     */
    private function prepare($field, string $opr, $params): string
    {
        $query[] = $this->prepareField($field);
        $query[] = $opr;
        if ($params instanceof Builder) {
            $query[] = '('. $params->toString() .')';
        } else {
            if ($params && !is_array($params) && !is_scalar($params)) {
                throw new BuilderException(sprintf('Scalar or array params are accepted only'.
                    ', %s given!', gettype($params)));
            }

            $query[] = $this->agent->prepare('(?)', (array) $params);
        }

        return join(' ', array_filter($query));
    }

    /**
     * Prepare field.
     * @param  string|array|Builder $field
     * @param  bool                 $join
     * @return string|array
     */
    private function prepareField($field, bool $join = true)
    {
        if ($field instanceof Builder || $field instanceof Sql) {
            return '('. $field->toString() .')';
        } elseif ($field instanceof Identifier) {
            return $this->agent->escapeIdentifier($field);
        }

        if (is_string($field)) {
            $field = Util::split(',', trim($field));
        }

        if (is_array($field)) {
            return $this->agent->escapeIdentifier($field, $join);
        }

        throw new BuilderException(sprintf('String, array or Builder type fields are accepted only,'.
            ' %s given', gettype($field)));
    }
}
