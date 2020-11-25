<?php

namespace App\DAO\Ingredient;

use App\DAO\Ingredient\BaseDAOFactory;
use App\Models\TasksMethods;
use Illuminate\Support\Str;

class TasksMethodsDAOFactory implements BaseDAOFactory
{
    public function getAll()
    {
        $data = TasksMethods::paginate(20);
        return $data;
    }

    public function getSpecify($request)
    {
        $data = TasksMethods::where('id', $request->id)->get();
        return $data;
    }

    public function getSpecifyByTaskID($task_id)
    {
        $data = TasksMethods::where('task_id', $task_id)->get();
        return $data;
    }

    public function insert($request)
    {
        $data = TasksMethods::create([
            'id' => Str::uuid()->toString(),
            'task_id' => $request->task_id,
            'method' => $request->method,
        ]);
        return $data;
    }

    public function delete($request)
    {
        //
    }

    public function update($request)
    {
        $data = TasksMethods::where('id', $request->id)->update($request->all());
        return $data;
    }

    public function countAll()
    {
        $data = TasksMethods::count('id');
        return $data;
    }
}
