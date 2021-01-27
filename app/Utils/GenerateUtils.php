<?php

namespace App\Utils;

use Carbon\Carbon;
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

    public static function generateCountTasksNumber($records, $days)
    {
        $object = [];
        for ($i = 1; $i <= $days; $i++) {
            array_push($object, (object) ['days_ago' => $i, 'date' => Carbon::now()->subDays($i)->toDateString(), 'total' => 0]);
            foreach ($records as $record) {
                if ($record->date === $object[$i - 1]->date) {
                    $object[$i - 1]->total = $record->total;
                }
            }
        }
        return $object;
    }
}
