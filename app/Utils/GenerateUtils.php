<?php

namespace App\Utils;

use Illuminate\Support\Str;

class GenerateUtils
{
    public static function generateUUID()
    {
        $id = Str::uuid()->toString();
        return $id;
    }

    /**
     * Generate ORM Insert Object which can directly insert to DB by ORM.
     *
     * @method
     * @return Array
     */
    public static function generateORMInsertObject($originalArray, $additionalArray)
    {
        return array_merge($originalArray, $additionalArray);
    }

    /**
     * Generate ORM Update Object which can directly update to DB by ORM.
     *
     * @method
     * @return Array
     */
    public static function generateFinishedTasksObject($task_id, $action_state)
    {
        $accessObject = (object) ['id' => $task_id, 'action' => $action_state];
        return $accessObject;
    }
}
