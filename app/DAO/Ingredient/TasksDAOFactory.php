<?php

namespace App\DAO\Ingredient;

use App\DAO\Ingredient\BaseDAOFactory;
use App\Models\Tasks;
use Illuminate\Support\Str;

class TasksDAOFactory implements BaseDAOFactory
{
    public function getAll()
    {
        $data = Tasks::paginate(15);
        return $data;
    }

    public function getSpecify($request)
    {
        $data = Tasks::where('id', $request->id)->get();
        return $data;
    }

    public function getSpecifyTaskByEmail($request)
    {
        $data = Tasks::where('email', $request->email)->orderBy('updated_at', 'DESC')->paginate(20);
        return $data;
    }

    public function insert($request)
    {
        $data = Tasks::create([
            'id' => Str::uuid()->toString(),
            'email' => $request->email,
            'action' => 'running',
            'source' => $request->source,
            'description' => $request->description,
            'ip' => $request->ip(),
        ]);
        return $data;
    }

    public function delete($request)
    {
        //
    }

    public function update($request)
    {
        $data = Tasks::where('id', $request->id)->update($request->all());
        return $data;
    }

    public function finished($id)
    {
        $data = Tasks::where('id', $id)->update(['action' => 'finished']);
        return $data;
    }

    public function countAll()
    {
        $data = Tasks::count('id');
        return $data;
    }
}
