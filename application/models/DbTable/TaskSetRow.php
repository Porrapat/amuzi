<?php

/**
 * DbTable_TaskSetRow
 *
 * @package Amuzi
 * @version 1.0
 * Amuzi - Online music
 * Copyright (C) 2010-2013  Diogo Oliveira de Melo
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
class DbTable_TaskSetRow extends DZend_Db_Table_Row
{
    public function __get($name)
    {
        $taskParameterDb = new DbTable_TaskParameter();
        if (0 === strpos($name, 'param')) {
            $num = str_replace('param', '', $name);
            $paramsRowSet = $taskParameterDb->findByTaskSetId($this->id);
            foreach ($paramsRowSet as $paramRow) {
                if ($num == $paramRow->order) {
                    return $paramRow->param;
                }
            }

            return null;
        } elseif ('type' === $name) {
            $taskTypeDb = new DbTable_TaskType();
            return $taskTypeDb->findRowById($this->taskTypeId);
        } else {
            return parent::__get($name);
        }
    }
}
