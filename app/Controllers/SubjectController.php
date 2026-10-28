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
        $this->view('tasks/subjects', ['subjects' => SubjectModel::forUser($this->uid())], ['title' => 'لیبل‌ها', 'page' => 'subjects']);
    }

    /** رنگ از ۱۵ رنگ آماده؛ اگر رنگی انتخاب نشده (مثلاً لیبل قدیمی با رنگ دلخواه) همان رنگ قبلی می‌ماند */
    private function color(string $fallback = '#22c55e'): string
    {
        $c = strtolower($this->request->str('color', 20, ''));
        if ($c === '') {
            return $fallback;
        }
        return preg_match('/^#[0-9a-f]{6}$/', $c) ? $c : $fallback;
    }

    public function store()
    {
        $title = $this->request->str('title', 150);
        if ($title === '') {
            $this->respond(false, 'عنوان لیبل را وارد کنید.', '/subjects');
        }
        $id = DB::insert('subjects', ['user_id' => $this->uid(), 'title' => $title, 'color' => $this->color()]);
        $this->respond(true, 'لیبل ایجاد شد.', '/subjects', ['id' => $id]);
    }

    public function update(string $id)
    {
        $uid = $this->uid();
        $title = $this->request->str('title', 150);
        $old = SubjectModel::owned((int) $id, $uid);
        if ($title === '' || $old === null) {
            $this->respond(false, 'لیبل نامعتبر است.', '/subjects');
        }
        DB::update('subjects', ['title' => $title, 'color' => $this->color((string) $old['color'])], 'id = ? AND user_id = ?', [(int) $id, $uid]);
        $this->respond(true, 'لیبل ویرایش شد.', '/subjects');
    }

    public function destroy(string $id)
    {
        $uid = $this->uid();
        if (SubjectModel::owned((int) $id, $uid) === null) {
            $this->respond(false, 'لیبل یافت نشد.', '/subjects');
        }
        DB::run('DELETE FROM subjects WHERE id = ? AND user_id = ?', [(int) $id, $uid]);
        $this->respond(true, 'لیبل حذف شد (تسک‌های آن بدون لیبل باقی ماندند).', '/subjects');
    }
}
