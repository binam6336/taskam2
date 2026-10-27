<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Models\TextModel;

/** متن‌های آماده (snippets) با مرتب‌سازی دستی. */
final class TextController extends Controller
{
    public function index()
    {
        $this->view('texts/index', ['texts' => TextModel::forUser($this->uid())], ['title' => 'متن‌های آماده', 'page' => 'texts']);
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
        if ($title === '' || $content === '') {
            $this->json(['success' => false, 'error' => 'عنوان و متن نمی‌توانند خالی باشند'], 422);
        }
        $row = TextModel::create($this->uid(), $title, $content);
        $this->json(['success' => true, 'data' => $row, 'message' => 'متن با موفقیت افزوده شد']);
    }

    public function update(string $id)
    {
        $uid = $this->uid();
        $title = $this->request->str('title', 255);
        $content = $this->request->str('content', 20000);
        if ($title === '' || $content === '') {
            $this->json(['success' => false, 'error' => 'عنوان و متن الزامی هستند'], 422);
        }
        if (TextModel::owned($id, $uid) === null) {
            $this->json(['success' => false, 'error' => 'متن مورد نظر یافت نشد'], 404);
        }
        DB::update('texts', ['title' => $title, 'content' => $content], 'id = ? AND user_id = ?', [$id, $uid]);
        $this->json(['success' => true, 'data' => TextModel::owned($id, $uid), 'message' => 'متن با موفقیت ویرایش شد']);
    }

    public function destroy(string $id)
    {
        $uid = $this->uid();
        if (TextModel::owned($id, $uid) === null) {
            $this->json(['success' => false, 'error' => 'متن مورد نظر یافت نشد'], 404);
        }
        DB::run('DELETE FROM texts WHERE id = ? AND user_id = ?', [$id, $uid]);
        $this->json(['success' => true, 'message' => 'متن با موفقیت حذف شد']);
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
