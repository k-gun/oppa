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

namespace Oppa\Batch;

use Oppa\Agent;

/**
 * @package    Oppa
 * @subpackage Oppa\Batch
 * @object     Oppa\Batch\Pgsql
 * @author     Kerem Güneş <k-gun@mail.com>
 */
final class Pgsql extends Batch
{
    /**
     * Constructor.
     * @param Oppa\Agent\Pgsql $agent
     */
    final public function __construct(Agent\Pgsql $agent)
    {
        $this->agent = $agent;
    }

    /**
     * Lock.
     * @return bool
     */
    final public function lock(): bool
    {
        return true;
    }

    /**
     * Unlock.
     * @return bool
     */
    final public function unlock(): bool
    {
        return true;
    }

    /**
     * Start.
     * @return bool
     */
    final protected function start(): bool
    {
        return ($result = pg_query($this->agent->getResource()->getObject(), 'BEGIN'))
            && (pg_result_status($result) === PGSQL_COMMAND_OK);
    }

    /**
     * End.
     * @return bool
     */
    final protected function end(): bool
    {
        return ($result = pg_query($this->agent->getResource()->getObject(), 'COMMIT'))
            && (pg_result_status($result) === PGSQL_COMMAND_OK);
    }

    /**
     * Undo.
     * @return void
     */
    final public function undo(): void
    {
        // mayday mayday
        pg_query($this->agent->getResource()->getObject(), 'ROLLBACK');

        $this->reset();
    }
}
