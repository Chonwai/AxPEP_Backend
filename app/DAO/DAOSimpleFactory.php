<?php

namespace App\DAO;

use App\DAO\Ingredient\TasksDAOFactory;

class DAOSimpleFactory
{
    public static function createTasksDAO()
    {
        return new TasksDAOFactory();
    }
}
