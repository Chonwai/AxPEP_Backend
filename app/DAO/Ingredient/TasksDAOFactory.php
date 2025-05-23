<?php

namespace App\DAO\Ingredient;

use App\Models\Tasks;
use App\Utils\GenerateUtils;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
        $data = Tasks::where('email', $request->email)->where('application', $request->application)->orderBy('updated_at', 'DESC')->paginate(20);

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
            'application' => $request->application,
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

    public function failed($id)
    {
        $data = Tasks::where('id', $id)->update(['action' => 'failed']);

        return $data;
    }

    public function countAll()
    {
        $data = Tasks::count('id');

        return $data;
    }

    public function countDistinctIpNDays($request)
    {
        $tasks = DB::table('tasks')->select(DB::raw('DISTINCT ip'))->where('created_at', '>=', Carbon::now()->subDays($request->days_ago + 1))->where('tasks.application', $request->application)->get();
        $data = GenerateUtils::generateLocationsListByIps($tasks);

        return $data;
    }

    public function countTasksNDays($request)
    {
        $tasks = Tasks::select(DB::raw('DATE(created_at) as date'), DB::raw('count(created_at) as total'))->where('created_at', '>=', Carbon::now()->subDays($request->days_ago + 1)->toDateString())->where('tasks.application', $request->application)->groupBy(DB::raw('DATE(created_at)'))->get();
        $data = GenerateUtils::generateCountTasksNumber($tasks, $request->days_ago);

        return $data;
    }

    public function countEachMethods($request)
    {
        $data = DB::table('tasks')->join('tasks_methods', 'tasks.id', '=', 'tasks_methods.task_id')->select('tasks_methods.method', DB::raw('count(tasks_methods.method) as total'))->where('tasks.application', $request->application)->groupBy('tasks_methods.method')->get();

        return $data;
    }
}
