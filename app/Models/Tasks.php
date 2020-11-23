<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tasks extends Model
{
    use HasFactory;

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'email',
        'action',
        'source',
        'description',
        'ip',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'ip',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be setting the default value.
     *
     * @var array
     */
    protected $attributes = [
        'action' => 'running',
    ];

    /**
     * Return the related object.
     *
     * @return object
     */
    public function details()
    {
        return $this->hasMany('App\Models\TasksMethods', 'task_id', 'id');
    }
}
