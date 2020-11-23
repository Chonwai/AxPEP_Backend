<?php

namespace App\DAO;

use App\DAO\Ingredient\TasksDAOFactory;
use App\DAO\Ingredient\TasksMethodsDAOFactory;

class DAOSimpleFactory
{
    public static function createTasksDAO()
    {
        return new TasksDAOFactory();
    }

    public static function createTasksMethodsDAO()
    {
        return new TasksMethodsDAOFactory();
    }
}
