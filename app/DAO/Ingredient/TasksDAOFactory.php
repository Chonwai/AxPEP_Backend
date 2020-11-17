<?php

namespace App\DAO\Ingredient;

use App\DAO\Ingredient\BaseDAOFactory;
use App\Models\Tasks;
use App\Models\User;
use Illuminate\Support\Str;

class TasksDAOFactory implements BaseDAOFactory
{
    public function getAll()
    {
        $data = Tasks::paginate(20);
        return $data;
    }

    public function getSpecify($request)
    {
        $data = Tasks::where('id', $request->response_id ? $request->response_id : $request->id)->get();

        return $data;
    }

    public function insert($request)
    {
        $data = Tasks::create([
            'id' => Str::uuid()->toString(),
            'email' => $request->email,
            'action' => 'ready',
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
        $data = User::where('id', $request->id)->update($request->all());
        return $data;
    }

    public function countAll()
    {
        $data = User::count('id');
        return $data;
    }
}
