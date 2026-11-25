// مقایسه غیرمخرب دو سرور آزمایشی؛ هیچ توکن واقعی در کد یا خروجی نوشته نمی‌شود.
const assert = require("node:assert/strict");
const legacy = process.env.LEGACY_TEST_URL || "http://127.0.0.1:8081";
const laravel = process.env.LARAVEL_TEST_URL || "http://127.0.0.1:8082";
const cases = [
  ["GET", "/api/v1/tasks/list"],
  ["GET", "/api/v1/projects/list"],
  ["GET", "/tasks"],
  ["GET", "/path-not-present"],
  ["POST", "/path-not-present"],
  ["POST", "/login"],
  ["OPTIONS", "/api/v1/tasks/list"],
  ["POST", "/api/v1/tasks/list"],
  ["GET", "/admin/analysis"],
];
async function snapshot(base, method, path, cookie = "") {
  const r = await fetch(base + path, {
    method,
    headers: { Accept: "application/json", Cookie: cookie },
    redirect: "manual",
  });
  const text = await r.text();
  let body;
  try {
    body = JSON.parse(text);
    delete body.ref;
    delete body.trace;
    delete body.file;
    delete body.line;
  } catch {
    body = text;
  }
  return {
    status: r.status,
    location: r.headers.get("location"),
    type: r.headers.get("content-type"),
    security: Object.fromEntries(
      [
        "access-control-allow-origin",
        "access-control-allow-methods",
        "access-control-allow-headers",
        "cache-control",
        "x-frame-options",
        "x-content-type-options",
        "content-security-policy",
      ].map((k) => [k, r.headers.get(k)]),
    ),
    body,
  };
}
const routes = require("../docs/migration/user-routes.json");
for (const r of routes) {
  if (
    r.path.startsWith("/api/v1/") &&
    !r.path.includes("{") &&
    !cases.some((x) => x[0] === r.method && x[1] === r.path)
  )
    cases.push([r.method, r.path]);
}
(async () => {
  for (const [method, path] of cases) {
    assert.deepEqual(
      await snapshot(laravel, method, path),
      await snapshot(legacy, method, path),
      method + " " + path,
    );
    console.log("PASS", method, path);
  }
  if (process.env.KARLIX_TEST_MOBILE && process.env.KARLIX_TEST_PASSWORD) {
    async function login(
      base,
      mobile = process.env.KARLIX_TEST_MOBILE,
      password = process.env.KARLIX_TEST_PASSWORD,
    ) {
      const r = await fetch(base + "/login");
      const html = await r.text();
      const csrf = html.match(/name="_csrf" value="([^"]+)"/)[1];
      let cookie = r.headers
        .getSetCookie()
        .map((x) => x.split(";")[0])
        .join("; ");
      const result = await fetch(base + "/login", {
        method: "POST",
        redirect: "manual",
        headers: {
          Cookie: cookie,
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({ _csrf: csrf, mobile, password }),
      });
      assert.equal(result.status, 302, "login");
      const updated = result.headers.getSetCookie().map((x) => x.split(";")[0]);
      if (updated.length) cookie = updated.join("; ");
      return cookie;
    }
    const cookies = [await login(legacy), await login(laravel)];
    for (const path of [
      "/api/v1/tasks/list",
      "/api/v1/projects/list",
      "/api/v1/texts/list",
      "/api/v1/colleagues/list",
      "/api/v1/projects/show?id=123",
      "/api/v1/tasks/show?id=123",
    ]) {
      const before = await snapshot(legacy, "GET", path, cookies[0]);
      const after = await snapshot(laravel, "GET", path, cookies[1]);
      assert(before.status < 500 && after.status < 500, "server error " + path);
      assert.deepEqual(after, before, path);
      console.log("PASS authenticated", path);
    }
    if (
      process.env.KARLIX_TEST_ADMIN_MOBILE &&
      process.env.KARLIX_TEST_ADMIN_PASSWORD
    ) {
      const adminCookies = [
        await login(
          legacy,
          process.env.KARLIX_TEST_ADMIN_MOBILE,
          process.env.KARLIX_TEST_ADMIN_PASSWORD,
        ),
        await login(
          laravel,
          process.env.KARLIX_TEST_ADMIN_MOBILE,
          process.env.KARLIX_TEST_ADMIN_PASSWORD,
        ),
      ];
      const before = await snapshot(
        legacy,
        "GET",
        "/admin/analysis",
        adminCookies[0],
      );
      const after = await snapshot(
        laravel,
        "GET",
        "/admin/analysis",
        adminCookies[1],
      );
      assert.equal(before.status, 200);
      assert.equal(after.status, 200);
      // ردیف‌های امتیاز مساوی ترتیب تضمین‌شده ندارند؛ audit نیز با هر ورود تغییر می‌کند.
      const normalizeAdmin = (html) =>
        html
          .replace(
            /(<div class="list">)((?:<div class="flex (?:items-center gap-1|between)">[\s\S]*?<\/div>)*)(<\/div>)/g,
            (_, open, rows, close) =>
              open +
              (
                rows.match(
                  /<div class="flex (?:items-center gap-1|between)">[\s\S]*?<\/div>/g,
                ) || []
              )
                .sort()
                .join("") +
              close,
          )
          .replace(/[a-f0-9]{64}/g, "CSRF")
          .replace(/("clock":\{"now":)\d+/g, '$1CLOCK')
          .replace(
            /(<h3><i class="fa-solid fa-shield-halved"><\/i> رویدادهای امنیتی اخیر<\/h3>[\s\S]*?<tbody>)[\s\S]*?(<\/tbody>)/,
            "$1AUDIT_ROWS$2",
          );
      before.body = normalizeAdmin(before.body);
      after.body = normalizeAdmin(after.body);
      if (after.body !== before.body) {
        let i = 0;
        while (i < after.body.length && after.body[i] === before.body[i]) i++;
        assert.fail(
          "admin HTML differs near " +
            i +
            ": " +
            JSON.stringify([
              before.body.slice(i - 90, i + 180),
              after.body.slice(i - 90, i + 180),
            ]),
        );
      }
      assert.deepEqual(after, before, "admin analysis");
      console.log("PASS authenticated admin analysis");
    }
  }
})();
