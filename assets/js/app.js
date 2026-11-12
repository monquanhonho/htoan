/**
 * Purple Lotus Hotel Management - Core Frontend JS (Fetch API, Animations & UX)
 */

document.addEventListener('DOMContentLoaded', () => {
  if (typeof Chart !== 'undefined') {
    Chart.defaults.font.family = "'Times New Roman', Times, Georgia, serif";
    Chart.defaults.font.size = 13;
  }

  // Mobile Sidebar Toggle
  const sidebar = document.getElementById('lumSidebar');
  const backdrop = document.getElementById('lumSidebarBackdrop');
  const toggleBtn = document.getElementById('lumSidebarToggle');

  function toggleSidebar(forceClose = false) {
    if (!sidebar || !backdrop) return;
    if (forceClose) {
      sidebar.classList.remove('show');
      backdrop.classList.remove('show');
    } else {
      sidebar.classList.toggle('show');
      backdrop.classList.toggle('show');
    }
  }

  if (toggleBtn) toggleBtn.addEventListener('click', () => toggleSidebar());
  if (backdrop) backdrop.addEventListener('click', () => toggleSidebar(true));

  // Auto-dismiss alerts after 6 seconds
  document.querySelectorAll('.alert-auto-dismiss').forEach((el) => {
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      bsAlert.close();
    }, 6000);
  });

  // Luxury Scroll-Reveal Animation for cards & KPI tiles
  initLuxuryAnimations();
});

function initLuxuryAnimations() {
  const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const targets = document.querySelectorAll('.lum-kpi, .lum-card, [data-reveal]');

  if (prefersReduced || !('IntersectionObserver' in window)) {
    targets.forEach((el) => el.classList.add('is-visible'));
    return;
  }

  targets.forEach((el, idx) => {
    // Skip elements inside modals so modal layout isn't affected
    if (el.closest('.modal')) return;
    el.classList.add('lum-reveal');
    const stagger = idx % 5;
    if (stagger > 0) {
      el.classList.add(`lum-delay-${stagger}`);
    }
  });

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.08, rootMargin: '0px 0px -20px 0px' }
  );

  targets.forEach((el) => {
    if (!el.closest('.modal')) {
      observer.observe(el);
    }
  });

  // Smooth Count-Up for numeric KPI values
  document.querySelectorAll('.lum-kpi-value').forEach((el) => {
    const raw = el.textContent.trim();
    const isCurrency = raw.includes('₫');
    const numericStr = raw.replace(/[^\d]/g, '');
    if (!numericStr) return;

    const targetVal = parseInt(numericStr, 10);
    if (isNaN(targetVal) || targetVal === 0) return;

    const duration = 700;
    const startTime = performance.now();

    function step(now) {
      const progress = Math.min((now - startTime) / duration, 1);
      // Ease-out cubic
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = Math.round(targetVal * eased);
      el.textContent = isCurrency
        ? new Intl.NumberFormat('vi-VN').format(current) + ' ₫'
        : new Intl.NumberFormat('vi-VN').format(current);
      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = raw;
      }
    }

    requestAnimationFrame(step);
  });
}

/**
 * Format VND currency in JS
 */
function formatVND(amount) {
  return new Intl.NumberFormat('vi-VN').format(Math.round(amount)) + ' ₫';
}

/**
 * Fetch API helper with CSRF header
 */
async function luminaFetch(url, options = {}) {
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const headers = Object.assign(
    {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfMeta ? csrfMeta.getAttribute('content') : '',
    },
    options.headers || {}
  );

  const response = await fetch(url, Object.assign({}, options, { headers }));
  const data = await response.json();
  if (!response.ok || data.ok === false) {
    throw new Error(data.message || 'Đã xảy ra lỗi khi xử lý yêu cầu.');
  }
  return data;
}

/**
 * Live Room Availability & Price Calculator
 */
function initBookingCalculator(apiUrl) {
  const checkInEl = document.getElementById('calcCheckIn');
  const checkOutEl = document.getElementById('calcCheckOut');
  const roomIdEl = document.getElementById('calcRoomId');
  const summaryEl = document.getElementById('bookingCalcSummary');
  const submitBtn = document.getElementById('bookingSubmitBtn');

  if (!checkInEl || !checkOutEl || !roomIdEl || !summaryEl) return;

  async function recalculate() {
    const ci = checkInEl.value;
    const co = checkOutEl.value;
    const rid = roomIdEl.value;

    if (!ci || !co || !rid) return;

    try {
      const url = `${apiUrl}?action=check_availability&room_id=${encodeURIComponent(rid)}&check_in=${encodeURIComponent(ci)}&check_out=${encodeURIComponent(co)}`;
      const res = await luminaFetch(url);
      if (res.available) {
        summaryEl.innerHTML = `
          <div class="p-3 rounded-3 border" style="background:#FAF8F4;border-color:rgba(201,169,110,0.4)!important;">
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted">Số đêm lưu trú:</span>
              <strong class="text-dark">${res.nights} đêm</strong>
            </div>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted">Đơn giá mỗi đêm:</span>
              <span>${formatVND(res.price_per_night)}</span>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-semibold">Tổng tiền phòng dự kiến:</span>
              <span class="fs-5 fw-bold" style="color:#0F1C2E;">${formatVND(res.total_amount)}</span>
            </div>
            <div class="mt-2 small text-success"><i class="fa-solid fa-circle-check me-1"></i>Phòng sẵn sàng trong khoảng thời gian này.</div>
          </div>`;
        if (submitBtn) submitBtn.disabled = false;
      } else {
        summaryEl.innerHTML = `<div class="alert alert-danger mb-0 py-2 small"><i class="fa-solid fa-triangle-exclamation me-1"></i>${res.message || 'Phòng không khả dụng trong khoảng thời gian đã chọn.'}</div>`;
        if (submitBtn) submitBtn.disabled = true;
      }
    } catch (err) {
      summaryEl.innerHTML = `<div class="alert alert-warning mb-0 py-2 small">${err.message}</div>`;
      if (submitBtn) submitBtn.disabled = true;
    }
  }

  checkInEl.addEventListener('change', recalculate);
  checkOutEl.addEventListener('change', recalculate);
  recalculate();
}
