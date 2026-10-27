<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Models\SubjectModel;

final class SubjectController extends Controller
{
    public function index()
    {
        $this->view('tasks/subjects', ['subjects' => SubjectModel::forUser($this->uid())], ['title' => 'موضوعات', 'page' => 'subjects']);
    }

    private function color(): string
    {
        $c = $this->request->str('color', 20, '#22c55e');
        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : '#22c55e';
    }

    public function store()
    {
        $title = $this->request->str('title', 150);
        if ($title === '') {
            $this->respond(false, 'عنوان موضوع را وارد کنید.', '/subjects');
        }
        $id = DB::insert('subjects', ['user_id' => $this->uid(), 'title' => $title, 'color' => $this->color()]);
        $this->respond(true, 'موضوع ایجاد شد.', '/subjects', ['id' => $id]);
    }

    public function update(string $id)
    {
        $uid = $this->uid();
        $title = $this->request->str('title', 150);
        if ($title === '' || SubjectModel::owned((int) $id, $uid) === null) {
            $this->respond(false, 'موضوع نامعتبر است.', '/subjects');
        }
        DB::update('subjects', ['title' => $title, 'color' => $this->color()], 'id = ? AND user_id = ?', [(int) $id, $uid]);
        $this->respond(true, 'موضوع ویرایش شد.', '/subjects');
    }

    public function destroy(string $id)
    {
        $uid = $this->uid();
        if (SubjectModel::owned((int) $id, $uid) === null) {
            $this->respond(false, 'موضوع یافت نشد.', '/subjects');
        }
        DB::run('DELETE FROM subjects WHERE id = ? AND user_id = ?', [(int) $id, $uid]);
        $this->respond(true, 'موضوع حذف شد (تسک‌های آن بدون موضوع باقی ماندند).', '/subjects');
    }
}
