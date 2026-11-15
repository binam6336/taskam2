/* سایدبار جمع‌شده (rail) پیش از رندر صفحه، تا منو پرش نکند (v5.8) — CSP: فایل جدا، نه اسکریپت inline */
try { if (localStorage.getItem('tk.sb.rail') === '1') document.documentElement.classList.add('sb-rail'); } catch (e) { /* حالت خصوصی */ }
