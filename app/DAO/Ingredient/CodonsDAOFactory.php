<?php

namespace App\DAO\Ingredient;

use App\Models\Codons;
use Illuminate\Support\Str;

class CodonsDAOFactory implements BaseDAOFactory
{
    public function getAll()
    {
        $data = Codons::orderBy('codons_number', 'asc')->get();

        return $data;
    }

    public function getSpecify($request)
    {
        $data = Codons::where('id', $request->id)->get();

        return $data;
    }

    public function getSpecifyByNumber($codons_number)
    {
        $data = Codons::where('codons_number', $codons_number)->get();

        return $data;
    }

    public function insert($request)
    {
        $data = Codons::create([
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
        $data = Codons::where('id', $request->id)->update($request->all());

        return $data;
    }
}
