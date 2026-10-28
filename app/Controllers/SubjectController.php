<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\SubjectModel;
use App\Services\LabelService;

final class SubjectController extends Controller
{
    public function index()
    {
        $this->view('tasks/subjects', ['subjects' => SubjectModel::forUser($this->uid())], ['title' => 'لیبل‌ها', 'page' => 'subjects']);
    }

    /** آدرس قدیمی /subjects (بوک‌مارک‌ها) */
    public function legacy()
    {
        $this->redirect('/labels');
    }

    public function store()
    {
        $res = LabelService::create($this->uid(), ['title' => $this->request->input('title', ''), 'color' => $this->request->input('color', '')]);
        $this->respond($res['ok'], $res['message'], '/labels', $res['ok'] ? ['id' => (int) $res['data']['id']] : []);
    }

    public function update(string $id)
    {
        $res = LabelService::update($this->uid(), (int) $id, ['title' => $this->request->input('title', ''), 'color' => $this->request->input('color', '')]);
        $this->respond($res['ok'], $res['message'], '/labels');
    }

    public function destroy(string $id)
    {
        $res = LabelService::delete($this->uid(), (int) $id);
        $this->respond($res['ok'], $res['message'], '/labels');
    }
}
