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
        $uid = $this->uid();
        $this->view('tasks/subjects', ['subjects' => SubjectModel::forUser($uid, true), 'manageable' => LabelService::manageableProjects($uid),
            'blockedCount' => \App\Services\BlockService::list('label', $uid, 1)['total']], ['title' => 'لیبل‌ها', 'page' => 'subjects']);
    }

    /** آدرس قدیمی /subjects (بوک‌مارک‌ها) */
    public function legacy()
    {
        $this->redirect('/labels');
    }

    public function store()
    {
        $res = LabelService::create($this->uid(), ['title' => $this->request->input('title', ''), 'color' => $this->request->input('color', ''), 'project_id' => $this->request->input('project_id', 0)]);
        $this->respond($res['ok'], $res['message'], '/labels', $res['ok'] ? ['id' => pid('subjects', $res['data']), 'color' => $res['data']['color']] : []);
    }

    public function update(string $id)
    {
        $in = ['title' => $this->request->input('title', ''), 'color' => $this->request->input('color', '')];
        if ((int) $this->request->input('project_id', 0) !== 0) {
            $in['project_id'] = (int) $this->request->input('project_id', 0);   // فقط انتقال لیبل شخصی قدیمی به پروژه
        }
        $res = LabelService::update($this->uid(), (int) $id, $in);
        $this->respond($res['ok'], $res['message'], '/labels');
    }
}
