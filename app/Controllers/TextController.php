<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Models\TextModel;

/** یادداشت‌های شخصی (v6.0، قبلاً «متن‌های آماده»): یادداشت چسبان رنگی با مرتب‌سازی دستی. */
final class TextController extends Controller
{
    public function index()
    {
        $this->view('texts/index', ['texts' => TextModel::forUser($this->uid())], ['title' => 'یادداشت‌ها', 'page' => 'texts']);
    }

    /** آدرس قدیمی /texts ← /notes */
    public function legacy()
    {
        $this->redirect('/notes');
    }

    public function data()
    {
        $rows = TextModel::forUser($this->uid(), $this->request->str('search', 100));
        $this->json(['success' => true, 'data' => $rows, 'total' => count($rows)]);
    }

    public function store()
    {
        $title = $this->request->str('title', 255);
        $content = $this->request->str('content', 20000);
        if ($content === '') {
            $this->json(['success' => false, 'error' => 'متن یادداشت نمی‌تواند خالی باشد'], 422);
        }
        if ($lim = \App\Services\Limits::create('text', $this->uid())) {
            $this->json(['success' => false, 'error' => $lim, 'message' => $lim], 429);
        }
        $row = TextModel::create($this->uid(), $title, $content, $this->request->str('color', 12));
        $this->json(['success' => true, 'data' => $row, 'message' => 'یادداشت ذخیره شد']);
    }

    public function update(string $id)
    {
        $uid = $this->uid();
        $old = TextModel::owned($id, $uid);
        if ($old === null) {
            $this->json(['success' => false, 'error' => 'یادداشت مورد نظر یافت نشد'], 404);
        }
        // فقط رنگ (کلیک روی رنگ‌های کارت) یا متن کامل
        $only = $this->request->input('content', null) === null;
        $title = $only ? (string) $old['title'] : $this->request->str('title', 255);
        $content = $only ? (string) $old['content'] : $this->request->str('content', 20000);
        if ($content === '') {
            $this->json(['success' => false, 'error' => 'متن یادداشت نمی‌تواند خالی باشد'], 422);
        }
        $color = $this->request->input('color', null) === null ? (string) $old['color'] : TextModel::color($this->request->str('color', 12));
        DB::update('texts', ['title' => $title, 'content' => $content, 'color' => $color], 'id = ? AND user_id = ?', [$id, $uid]);
        $this->json(['success' => true, 'data' => TextModel::owned($id, $uid), 'message' => $only ? 'رنگ یادداشت عوض شد' : 'یادداشت ذخیره شد']);
    }

    public function destroy(string $id)
    {
        $uid = $this->uid();
        if (TextModel::owned($id, $uid) === null) {
            $this->json(['success' => false, 'error' => 'یادداشت مورد نظر یافت نشد'], 404);
        }
        DB::run('DELETE FROM texts WHERE id = ? AND user_id = ?', [$id, $uid]);
        $this->json(['success' => true, 'message' => 'یادداشت حذف شد']);
    }

    public function reorder()
    {
        $order = $this->request->arr('order');
        if (!$order) {
            $this->json(['success' => false, 'error' => 'ترتیب ارسالی نامعتبر است'], 422);
        }
        TextModel::reorder($this->uid(), $order);
        $this->json(['success' => true, 'message' => 'ترتیب ذخیره شد']);
    }
}
