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

namespace Oppa\Agent;

use Oppa\Config;
use Oppa\Query\Result\ResultInterface;

/**
 * @package Oppa
 * @object  Oppa\Agent\AgentInterface
 * @author  Kerem Güneş <k-gun@mail.com>
 */
interface AgentInterface
{
    /**
     * Init.
     * @param  Oppa\Config $config
     * @return void
     * @throws Oppa\Agent\AgentException
     */
    public function init(Config $config): void;

    /**
     * Connect.
     * @return void
     */
    public function connect(): void;

    /**
     * Disconnect.
     * @return void
     */
    public function disconnect(): void;

    /**
     * Is connected.
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * Query.
     * @param  string $query
     * @param  array  $queryParams
     * @return Oppa\Query\Result\ResultInterface
     */
    public function query(string $query, array $queryParams = null): ResultInterface;

    /**
     * Select.
     * @param  string            $table
     * @param  string|array|null $fields
     * @param  string|array|null $where
     * @param  any|null          $whereParams
     * @param  array|string|null $order
     * @param  int|string|null   $fetchType
     * @return any
     */
    public function select(string $table, $fields = null, $where = null, $whereParams = null,
        $order = null, $fetchType = null);

    /**
     * Select all.
     * @param  string            $table
     * @param  string|array|null $fields
     * @param  string|array|null $where
     * @param  any|null          $whereParams
     * @param  array|string|null $order
     * @param  int|array|null    $limit
     * @param  int|string|null   $fetchType
     * @return ?array
     */
    public function selectAll(string $table, $fields = null, $where = null, $whereParams = null,
        $order = null, $limit = null, $fetchType = null): ?array;

    /**
     * Insert.
     * @param  string $table
     * @param  array  $data
     * @return ?int
     * @throws Oppa\Exception\InvalidValueException
     */
    public function insert(string $table, array $data): ?int;

    /**
     * Insert all.
     * @param  string $table
     * @param  array  $data
     * @return ?array
     * @throws Oppa\Exception\InvalidValueException
     */
    public function insertAll(string $table, array $data): ?array;

    /**
     * Update.
     * @param  string            $table
     * @param  array             $data
     * @param  string|array|null $where
     * @param  any|null          $whereParams
     * @return int
     * @throws Oppa\Exception\InvalidValueException
     */
    public function update(string $table, array $data, $where = null, $whereParams = null): int;

    /**
     * Update all.
     * @param  string            $table
     * @param  array             $data
     * @param  string|array|null $where
     * @param  any|null          $whereParams
     * @param  int|null          $limit
     * @return int
     * @throws Oppa\Exception\InvalidValueException
     */
    public function updateAll(string $table, array $data, $where = null, $whereParams = null,
        int $limit = null): int;

    /**
     * Delete.
     * @param  string            $table
     * @param  string|array|null where
     * @param  any|null          $whereParams
     * @return int
     */
    public function delete(string $table, $where = null, $whereParams = null): int;

    /**
     * Delete all.
     * @param  string            $table
     * @param  string|array|null $where
     * @param  any|null          $whereParams
     * @param  int|array|null    $limit
     * @return int
     */
    public function deleteAll(string $table, $where = null, $whereParams = null,
        $limit = null): int;

    /**
     * Count.
     * @param  string            $table
     * @param  string|array|null where
     * @param  any|null          $whereParams
     * @return ?int
     */
    public function count(string $table, $where = null, $whereParams = null): ?int;
}
