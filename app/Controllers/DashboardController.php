<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\TaskModel;

final class DashboardController extends Controller
{
    public function index()
    {
        $this->view('tasks/dashboard', ['d' => TaskModel::dashboard($this->uid())], ['title' => 'پیشخوان', 'page' => 'dashboard']);
    }
}
