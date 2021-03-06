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

namespace Oppa\Query\Result;

use Oppa\Resource;
use Oppa\Agent\AgentInterface;
use Oppa\Exception\{InvalidValueException, MethodException};

/**
 * @package Oppa
 * @object  Oppa\Query\Result\Result
 * @author  Kerem Güneş <k-gun@mail.com>
 */
abstract class Result implements ResultInterface
{
    /**
     * Agent.
     * @var Oppa\Agent\AgentInterface
     */
    protected $agent;

    /**
     * Resource.
     * @var Oppa\Resource
     */
    protected $resource;

    /**
     * Fetch type.
     * @var int
     */
    protected $fetchType = ResultInterface::AS_OBJECT;

    /**
     * Fetch limit.
     * @var int
     */
    protected $fetchLimit = ResultInterface::LIMIT;

    /**
     * Fetch object (aka DTO. @link https://en.wikipedia.org/wiki/Data_transfer_object).
     * @var string
     */
    protected $fetchObject = 'stdClass';


    /**
     * Data.
     * @var array
     */
    protected $data = [];

    /**
     * Ids.
     * @var array
     */
    protected $ids = [];

    /**
     * Rows count.
     * @var int
     */
    protected $rowsCount = 0;

    /**
     * Rows affected.
     * @var int
     */
    protected $rowsAffected = 0;

    /**
     * Destructor.
     */
    public final function __destruct()
    {
        $this->free();
    }

    /**
     * Get agent.
     * @return Oppa\Agent\AgentInterface
     */
    public final function getAgent(): AgentInterface
    {
        return $this->agent;
    }

    /**
     * Get resource.
     * @return ?Oppa\Resource
     */
    public final function getResource(): ?Resource
    {
        return $this->resource;
    }

    /**
     * Free.
     * @return void
     */
    public final function free(): void
    {
        $this->resource && $this->resource->free();
    }

    /**
     * Reset.
     * @return void
     */
    public final function reset(): void
    {
        // reset data
        $this->data = [];
        // reset properties
        $this->ids = [];
        $this->rowsCount = 0;
        $this->rowsAffected = 0;
    }

    /**
     * Detect fetch type.
     * @param  int|string $fetchType
     * @return int
     * @throws Oppa\Exception\InvalidValueException
     */
    public final function detectFetchType($fetchType): int
    {
        switch (gettype($fetchType)) {
            case 'NULL':
                return ResultInterface::AS_OBJECT;
            case 'integer':
                if (in_array($fetchType, [ResultInterface::AS_OBJECT, ResultInterface::AS_ARRAY_ASC,
                    ResultInterface::AS_ARRAY_NUM, ResultInterface::AS_ARRAY_ASCNUM])) {
                    return $fetchType;
                }
                break;
            case 'string':
                // shorthands
                if ($fetchType == 'array') return ResultInterface::AS_ARRAY_ASC;
                if ($fetchType == 'object') return ResultInterface::AS_OBJECT;

                //  object, array_asc etc.
                $fetchTypeConst = 'Oppa\Query\Result\ResultInterface::AS_'. strtoupper($fetchType);
                if (defined($fetchTypeConst)) {
                    return constant($fetchTypeConst);
                }

                // user classes
                if (class_exists($fetchType)) {
                    $this->setFetchObject($fetchType);

                    return ResultInterface::AS_OBJECT;
                }
                break;
        }

        throw new InvalidValueException("Given '{$fetchType}' fetch type is not implemented!");
    }

    /**
     * Set fetch type.
     * @param  int|string $fetchType
     * @return void
     */
    public final function setFetchType($fetchType): void
    {
        $this->fetchType = $this->detectFetchType($fetchType);
    }

    /**
     * Get fetch type.
     * @return int
     */
    public final function getFetchType(): int
    {
        return $this->fetchType;
    }

    /**
     * Set fetch limit.
     * @param  int $fetchLimit
     * @return void
     */
    public final function setFetchLimit(int $fetchLimit): void
    {
        $this->fetchLimit = $fetchLimit;
    }

    /**
     * Get fetch limit.
     * @return int
     */
    public final function getFetchLimit(): int
    {
        return $this->fetchLimit;
    }

    /**
     * Set fetch object.
     * @param  string $fetchObject
     * @return void
     * @throws Oppa\Exception\InvalidValueException
     */
    public final function setFetchObject(string $fetchObject): void
    {
        if (!class_exists($fetchObject)) {
            throw new InvalidValueException("Fetch object class '{$fetchObject}' not found!");
        }

        $this->fetchObject = $fetchObject;
    }

    /**
     * Get fetch object.
     * @return string
     */
    public final function getFetchObject(): string
    {
        return $this->fetchObject;
    }

    /**
     * Set id.
     * @param int $id
     */
    public final function setId(int $id): void
    {
        $this->ids[] = $id;
    }

    /**
     * Get id.
     * @return ?int
     */
    public final function getId(): ?int
    {
        $id = end($this->ids);

        return ($id !== false) ? $id : null;
    }

    /**
     * Set ids.
     * @param  array $ids
     * @return void
     */
    public final function setIds(array $ids): void
    {
        foreach ($ids as $id) {
            $this->ids[] = (int) $id;
        }
    }

    /**
     * Get ids.
     * @return array
     */
    public final function getIds(): array
    {
        return $this->ids;
    }

    /**
     * Set rows count.
     * @param  int $rowsCount
     * @return void
     */
    public final function setRowsCount(int $rowsCount): void
    {
        $this->rowsCount = $rowsCount;
    }

    /**
     * Get rows count.
     * @return int
     */
    public final function getRowsCount(): int
    {
        return $this->rowsCount;
    }

    /**
     * Set rows affected.
     * @param  int $rowsAffected
     * @return void
     */
    public final function setRowsAffected(int $rowsAffected): void
    {
        $this->rowsAffected = $rowsAffected;
    }

    /**
     * Get rows affected.
     * @return int
     */
    public final function getRowsAffected(): int
    {
        return $this->rowsAffected;
    }

    /**
     * Has data.
     * @return bool
     */
    public final function hasData(): bool
    {
        return !!$this->data;
    }

    /**
     * Get data.
     * @param  int|null $i
     * @return any
     */
    public final function getData(int $i = null)
    {
        if ($i !== null) {
            if ($i < 0) {
                $i = count($this->data) + $i; // reverse
            }
            return $this->data[$i] ?? null;
        }
        return $this->data;
    }

    /**
     * Item.
     * @param  int $i
     * @return any|null
     */
    public final function item(int $i)
    {
        return $this->data[$i] ?? null;
    }

    /**
     * Items.
     * @return array
     */
    public final function items(): array
    {
        return $this->data;
    }

    /**
     * Item first.
     * @return any|null
     */
    public final function itemFirst()
    {
        return $this->getData(0);
    }

    /**
     * Item last.
     * @return any|null
     */
    public final function itemLast()
    {
        return $this->getData(-1);
    }

    /**
     * To array.
     * @return ?array
     */
    public final function toArray(): ?array
    {
        $data = null;
        if (!empty($this->data)) {
            // no need to type-cast
            if (is_array($this->data[0])) {
                return $this->data;
            }
            $data = $this->data;
            foreach ($data as &$dat) {
                $dat = (array) $dat;
            }
        }

        return $data;
    }

    /**
     * To object.
     * @return ?array
     */
    public final function toObject(): ?array
    {
        $data = null;
        if (!empty($this->data)) {
            // no need to type-cast
            if (is_object($this->data[0])) {
                return $this->data;
            }
            $data = $this->data;
            foreach ($data as &$dat) {
                $dat = (object) $dat;
            }
        }

        return $data;
    }

    /**
     * To class.
     * @param  string $class
     * @return ?array
     */
    public final function toClass(string $class): ?array
    {
        $data = null;
        if (!empty($this->data)) {
            $data = $this->data;
            foreach ($data as &$dat) {
                $datClass = new $class();
                foreach ((array) $dat as $key => $value) {
                    $datClass->{$key} = $value;
                }
                $dat = $datClass;
            }
        }

        return $data;
    }

    /**
     * @inheritDoc \ArrayAccess
     */
    public final function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * @inheritDoc \ArrayAccess
     */
    public final function offsetGet($offset)
    {
        return $this->data[$offset] ?? null;
    }

    /**
     * @inheritDoc \ArrayAccess
     * @throws Oppa\Exception\MethodException
     */
    public final function offsetSet($offset, $value)
    {
        throw new MethodException(sprintf('Set not allowed on read-only Result object'));
    }

    /**
     * @inheritDoc \ArrayAccess
     * @throws Oppa\Exception\MethodException
     */
    public final function offsetUnset($offset)
    {
        throw new MethodException(sprintf('Unset not allowed on read-only Result object'));
    }

    /**
     * @inheritDoc \Countable
     */
    public final function count()
    {
        return count($this->data);
    }

    /**
     * @inheritDoc \IteratorAggregate
     */
    public final function getIterator()
    {
        return new \ArrayIterator($this->data);
    }
}
