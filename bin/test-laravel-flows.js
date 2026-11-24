// ایجاد و ویرایش داده فقط با حساب‌های ساخته‌شده در دیتابیس _test؛ توکن‌ها چاپ نمی‌شوند.
const assert = require("node:assert/strict");
const fixtures = require(
  process.env.KARLIX_TEST_FIXTURES ||
    "../storage/cache/laravel-test-fixtures.json",
);
const bases = [
  process.env.LEGACY_TEST_URL || "http://127.0.0.1:8081",
  process.env.LARAVEL_TEST_URL || "http://127.0.0.1:8082",
];
assert(
  fixtures.every((u) => u.testDatabase?.endsWith("_test")),
  "فایل fixture دیتابیس آزمایشی لازم است",
);
(async () => {
  const made = [];
  for (let i = 0; i < 2; i++) {
    const base = bases[i],
      u = fixtures[i];
    async function api(path, data, read = false) {
      const r = await fetch(base + "/api/v1" + path, {
        method: data ? "POST" : "GET",
        headers: {
          Accept: "application/json",
          Authorization: "Bearer " + (read ? u.read : u.token),
          "Content-Type": "application/json",
        },
        body: data ? JSON.stringify(data) : undefined,
      });
      const b = await r.json();
      assert(r.status < 500, JSON.stringify(b));
      return { http: r.status, ...b };
    }
    const project = await api("/projects/create", {
      title: "تست مهاجرت",
      description: "توضیحات رمزگذاری شده",
    });
    assert.equal(project.status, "success", JSON.stringify(project));
    const pid = project.data.id;
    const task = await api("/tasks/create", {
      project_id: pid,
      title: "تسک مهاجرت رمزگذاری",
      description: "متن خصوصی",
      due_date: new Date().toISOString().slice(0, 10),
      repeat_enabled: true,
      repeat_kind: "daily",
    });
    assert.equal(task.status, "success", JSON.stringify(task));
    const tid = task.data.id;
    const update = await api("/tasks/update", {
      id: tid,
      title: "نام ویرایش شده",
    });
    assert.equal(update.status, "success", JSON.stringify(update));
    const text = await api("/texts/create", {
      title: "یادداشت",
      content: "متن رمزگذاری شده",
    });
    assert.equal(text.status, "success", JSON.stringify(text));
    const texts = await api("/texts/list?q=" + encodeURIComponent("رمزگذاری"));
    assert.equal(texts.status, "success", JSON.stringify(texts));
    const readonly = await api(
      "/tasks/update",
      { id: tid, title: "نباید ذخیره شود" },
      true,
    );
    assert.equal(readonly.http, 403, JSON.stringify(readonly));
    made.push({
      pid,
      tid,
      title: update.data.title,
      description: task.data.description,
      recurrence: task.data.recurrence,
      text: text.data.content,
    });
    console.log(
      "PASS " +
        (i ? "native" : "legacy") +
        " token/project/task/update/recurrence/text/search/read-scope",
    );
  }
  assert.deepEqual(
    { ...made[0], pid: "id", tid: "id" },
    { ...made[1], pid: "id", tid: "id" },
  );
  // توکن معتبر یک کاربر نباید پروژه و تسک کاربر دیگر را ببیند یا ویرایش کند.
  const foreign = await fetch(bases[1] + "/api/v1/tasks/update", {
    method: "POST",
    headers: {
      Authorization: "Bearer " + fixtures[0].token,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ id: made[1].tid, title: "غیرمجاز" }),
  });
  assert([403, 404].includes(foreign.status), "IDOR " + foreign.status);
  require("fs").writeFileSync(
    process.env.KARLIX_TEST_RESULTS ||
      require("path").resolve(
        __dirname,
        "../storage/cache/laravel-test-results.json",
      ),
    JSON.stringify(made),
  );
  console.log("PASS write flow contracts and cross-project IDOR");
})();
