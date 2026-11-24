// سناریوی درخواست دیررس مهمان، عدم تثبیت نشست و حفظ نشست مدیر در impersonation.
const assert = require("node:assert/strict");
const fixtures = require(
  process.env.KARLIX_TEST_FIXTURES ||
    "../storage/cache/laravel-test-fixtures.json",
);
const base = process.env.LARAVEL_TEST_URL || "http://127.0.0.1:8082";
assert(fixtures.every((u) => u.testDatabase?.endsWith("_test")));
const headers = {
  "User-Agent": "Karlix-session-regression",
  Accept: "application/json",
};
const sessionCookie = (response) =>
  response.headers
    .getSetCookie()
    .filter((x) => x.startsWith("taskam_sid="))
    .at(-1)
    ?.split(";")[0];
async function login(mobile, password) {
  const page = await fetch(base + "/login", { headers });
  const csrf = (await page.text()).match(/name="_csrf" value="([^"]+)"/)[1];
  const guest = sessionCookie(page);
  const response = await fetch(base + "/login", {
    method: "POST",
    redirect: "manual",
    headers: {
      ...headers,
      Cookie: guest,
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams({ _csrf: csrf, mobile, password }),
  });
  assert.equal(response.status, 302);
  const cookie = sessionCookie(response);
  assert(cookie && cookie !== guest, "session fixation");
  return { cookie, guest };
}
(async () => {
  const { cookie, guest } = await login(
    fixtures[1].mobile,
    fixtures[1].password,
  );
  const late = await fetch(base + "/service-worker", {
    headers: { ...headers, Cookie: guest },
  });
  assert.equal(late.status, 200);
  assert.equal(
    sessionCookie(late),
    undefined,
    "late guest response must not overwrite login cookie",
  );
  const old = await fetch(base + "/api/v1/tasks/list", {
    headers: { ...headers, Cookie: guest },
  });
  assert.equal(
    old.status,
    401,
    "old guest identifier must never gain the logged-in identity",
  );
  for (let i = 0; i < 3; i++) {
    const page = await fetch(base + "/dashboard", {
      headers: { ...headers, Cookie: cookie },
      redirect: "manual",
    });
    assert.equal(page.status, 200, "authenticated session lost");
  }
  console.log(
    "PASS late guest request/rotated ID/no fixation/persistent login",
  );
  if (
    process.env.KARLIX_TEST_ADMIN_MOBILE &&
    process.env.KARLIX_TEST_ADMIN_PASSWORD
  ) {
    const admin = await login(
      process.env.KARLIX_TEST_ADMIN_MOBILE,
      process.env.KARLIX_TEST_ADMIN_PASSWORD,
    );
    const page = await fetch(base + "/admin/analysis", {
      headers: { ...headers, Cookie: admin.cookie },
    });
    assert.equal(page.status, 200);
    const csrf = (await page.text()).match(/name="_csrf" value="([^"]+)"/)[1];
    const started = await fetch(
      base + "/admin/users/" + fixtures[1].pid + "/impersonate",
      {
        method: "POST",
        headers: {
          ...headers,
          Cookie: admin.cookie,
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({ _csrf: csrf, to: "/tasks" }),
      },
    );
    const window = await started.json();
    assert.equal(window.status, "success");
    const prefix = window.open.replace(/\/tasks$/, "");
    assert.match(prefix, /^\/as\/[a-f0-9]{32}$/);
    const scoped = await fetch(base + prefix + "/dashboard", {
      headers: { ...headers, Cookie: admin.cookie },
      redirect: "manual",
    });
    assert.equal(scoped.status, 200, "native impersonation");
    const password = await fetch(base + prefix + "/profile/password", {
      method: "POST",
      headers: {
        ...headers,
        Cookie: admin.cookie,
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams({ _csrf: csrf }),
    });
    assert.equal(
      password.status,
      422,
      "account changes inside impersonation must be denied",
    );
    const closed = await fetch(base + prefix + "/admin/impersonate/exit", {
      method: "POST",
      headers: {
        ...headers,
        Cookie: admin.cookie,
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams({ _csrf: csrf }),
    });
    assert.equal((await closed.json()).close, true);
    const stillAdmin = await fetch(base + "/admin/analysis", {
      headers: { ...headers, Cookie: admin.cookie },
      redirect: "manual",
    });
    assert.equal(stillAdmin.status, 200, "admin session changed");
    console.log(
      "PASS native impersonation/account mutation denied/admin session preserved",
    );
  }
})();
