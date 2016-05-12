<?php
/**
 * Copyright (c) 2015 Kerem Güneş
 *    <k-gun@mail.com>
 *
 * GNU General Public License v3.0
 *    <http://www.gnu.org/licenses/gpl-3.0.txt>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace Oppa\Query\Result;

/**
 * @package    Oppa
 * @subpackage Oppa\Query\Result
 * @object     Oppa\Query\Result\ResultInterface
 * @author     Kerem Güneş <k-gun@mail.com>
 */
interface ResultInterface extends \Countable, \IteratorAggregate
{
    /**
     * Free.
     * @return void
     */
    public function free();

    /**
     * Reset.
     * @return void
     */
    public function reset();

    /**
     * Process.
     * @param object|resource $link
     * @param object|resource $result
     * @param int             $limit
     * @param string          $fetchType
     */
    public function process($link, $result, int $limit = null, int $fetchType = null): ResultInterface;
}