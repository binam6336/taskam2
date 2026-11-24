// نیازمند Playwright و Chromium؛ حساب‌ها فقط از fixture آزمایشی خوانده می‌شوند.
const { chromium } = require("playwright");
const assert = require("assert");
const f = require(
  process.env.KARLIX_TEST_FIXTURES ||
    "../storage/cache/laravel-test-fixtures.json",
)[1];
const flows = require(
  process.env.KARLIX_TEST_RESULTS ||
    "../storage/cache/laravel-test-results.json",
)[1];
const base = process.env.LARAVEL_TEST_URL || "http://127.0.0.1:8082";
(async () => {
  const b = await chromium.launch({
    executablePath: process.env.CHROMIUM_PATH || "/usr/bin/chromium",
    args: ["--no-sandbox"],
  });
  const p = await b.newPage({ viewport: { width: 1365, height: 950 } });
  const errors = [];
  assert(f.testDatabase?.endsWith("_test"), "test fixtures required");
  p.on("pageerror", (e) => errors.push(e.message));
  p.on("response", (r) => {
    if (!new URL(r.url()).pathname.startsWith("/assets"))
      console.log(
        r.request().method(),
        new URL(r.url()).pathname,
        r.status(),
        r.headers()["location"] || "",
      );
  });
  await p.goto(base + "/login");
  await p.locator("[name=mobile]").first().fill(f.mobile);
  await p.locator("[name=password]").first().fill(f.password);
  await p.locator('form[action$="/login"] button[type=submit]').click();
  await p.waitForURL("**/dashboard", {
    timeout: 15000,
    waitUntil: "domcontentloaded",
  });
  await p.waitForLoadState("networkidle");
  for (const path of [
    "/dashboard",
    "/tasks",
    "/calendar",
    "/projects",
    "/notes",
    "/profile#ai",
    "/chat",
    "/board?project=" + flows.pid,
    "/projects/" + flows.pid + "/analytics",
  ]) {
    const r = await p.goto(base + path);
    assert.equal(r.status(), 200, path + " " + r.status());
    console.log("PASS native page", path);
  }
  await p.goto(base + "/tasks");
  const csrf = await p.locator("[name=_csrf]").first().inputValue();
  const headers = {
    "X-Requested-With": "XMLHttpRequest",
    Accept: "application/json",
  };
  const note = await p.request.post(base + "/tasks/" + flows.tid + "/notes", {
    headers,
    multipart: {
      _csrf: csrf,
      note: "گزارش مهاجرت",
      "attachments[]": {
        name: "migration.txt",
        mimeType: "text/plain",
        buffer: Buffer.from("native-file-data"),
      },
    },
  });
  const n = await note.json();
  assert.equal(n.status, "success", JSON.stringify(n));
  const task = await (
    await p.request.get(base + "/api/v1/tasks/show?id=" + flows.tid, {
      headers: { Authorization: "Bearer " + f.token },
    })
  ).json();
  assert.equal(task.status, "success");
  assert(task.data.notes.some((x) => x.note === "گزارش مهاجرت"));
  const csrfDenied = await p.request.post(base + "/api/v1/tasks/update", {
    data: { id: flows.tid, title: "نباید ذخیره شود" },
  });
  assert.equal(csrfDenied.status(), 419, "cookie API write requires CSRF");
  const csrfAllowed = await p.request.post(base + "/api/v1/tasks/update", {
    headers: { "X-CSRF-Token": csrf },
    data: { id: flows.tid, title: "نام ویرایش شده" },
  });
  assert.equal(csrfAllowed.status(), 200);
  const invalid = await p.request.post(base + "/api/v1/tasks/create", {
    headers: { Authorization: "Bearer " + f.token },
    data: { title: ["bad"], project_id: flows.pid },
  });
  assert.equal(invalid.status(), 400);
  assert.equal((await invalid.json()).error_code, "INVALID_INPUT");
  await p.goto(base + "/profile");
  const avatar = await p.request.post(base + "/profile/avatar", {
    headers,
    multipart: {
      _csrf: csrf,
      avatar: {
        name: "avatar.png",
        mimeType: "image/png",
        buffer: Buffer.from(
          "iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAIAAAAlC+aJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAIklEQVRoge3BAQ0AAADCoPdPbQ8HFAAAAAAAAAAAAAAA8G4wQAAB9qDgAAAAAABJRU5ErkJggg==",
          "base64",
        ),
      },
    },
  });
  const a = await avatar.json();
  assert.equal(a.status, "success", JSON.stringify(a));
  const image = await p.request.get(base + a.avatar_url);
  assert.equal(image.status(), 200);
  assert((await image.body()).length > 0);
  await p.goto(base + "/projects");
  assert.deepEqual(errors, []);
  console.log("PASS native note/upload/download/validation and JS");
  await b.close();
})();
