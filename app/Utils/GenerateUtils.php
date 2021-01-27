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

    public static function generateFinishedTasksObject($task_id, $action_state)
    {
        $accessObject = (object) ['id' => $task_id, 'action' => $action_state];
        return $accessObject;
    }

    public static function generateLocationsListByIps($records)
    {
        $object = [];
        foreach ($records as $record) {
            $location = geoip($record->ip);
            array_push($object, (object) ['latitude' => $location->lat, 'longitude' => $location->lon]);
        }
        return $object;
    }
}
