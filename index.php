<?php /* index.php — Стол заказов (PHP+MySQL) — Showroom + одноразовые купоны (фиксируются на заказ) */ ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Стол заказов</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
:root{
  --bg:#f8fafc; --txt:#0f172a;
  --st-new-bg:#fde68a;   --st-new-tx:#7c5a00;
  --st-wip-bg:#bae6fd;   --st-wip-tx:#0b4c6a;
  --st-done-bg:#bbf7d0;  --st-done-tx:#14532d;
  --overdue-bg:#fee2e2;  --overdue-bd:#ef9a9a;
  --ac-bg:#ffffff; --ac-bd:#e5e7eb; --ac-hover:#f3f4f6; --ac-active:#e5e7eb;
}
* { box-sizing: border-box; }
body{ color:var(--txt); background:var(--bg); }
table.table td, table.table th{ vertical-align:top; }
.truncate{ display:inline-block; max-width: 40ch; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: bottom; }

/* верхняя перекладина */
.topbar{ position:sticky; top:0; z-index:1030; background:#fff; border-bottom:1px solid #e9ecef; }
.topbar .container{ display:flex; gap:.5rem; align-items:center; justify-content:space-between; padding:.5rem 1rem; }

/* панель купона (скрывается, если showroom OFF) */
.discount-bar{ position:sticky; top:48px; z-index:1025; background:#fff; border-bottom:1px solid #eef1f4; }
.discount-bar .container{ display:flex; gap:.5rem; align-items:center; padding:.5rem 1rem; }
body:not(.showroom-on) .discount-bar{ display:none !important; }
@media (max-width:576px){ .discount-bar .container{ flex-wrap:wrap; } }

/* tags-input */
.tags-input{ cursor:text; min-height:44px; align-items:center; }
.tags-input input{ outline:none; min-width:120px; }

/* статус */
.badge-status{ cursor:default; user-select:none; font-weight:600; border:1px solid transparent; padding:.5rem .8rem; font-size:1rem; }
.badge-status.status-new{background:var(--st-new-bg);color:var(--st-new-tx);}
.badge-status.status-wip{background:var(--st-wip-bg);color:var(--st-wip-tx);}
.badge-status.status-done{background:var(--st-done-bg);color:var(--st-done-tx);}
.overdue{ background:var(--overdue-bg)!important; padding:.1rem .4rem; border-radius:.375rem; }
.overdue-badge{ box-shadow:0 0 0 2px var(--overdue-bd) inset; }

/* автодоп телефонов */
.ac-list{ position:absolute; z-index:10; top:100%; left:0; right:0; background:var(--ac-bg); border:1px solid var(--ac-bd); border-top:none; border-radius:0 0 .5rem .5rem; box-shadow:0 4px 12px rgba(0,0,0,.06); display:none; max-height:240px; overflow:auto; }
.ac-list.show{ display:block; }
.ac-item{ display:flex; align-items:center; gap:.5rem; width:100%; padding:.75rem 1rem; border:0; background:transparent; text-align:left; font-size:1rem; }
.ac-item:hover{ background:var(--ac-hover); } .ac-item.active{ background:var(--ac-active); }

/* бэйдж скидки — показываем только если скидка зафиксирована на КОНКРЕТНОМ заказе */
.badge-discount{ background:#ffe9ae; color:#6a5200; font-weight:700; border:1px solid #f4d97b; }
body:not(.showroom-on) .badge-discount{ display:none !important; }

/* mobile */
@media (max-width:576px){
  thead.table-light{ display:none; }
  table.table tr{ display:block; margin:10px 12px; padding:12px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.05); background:#fff; }
  table.table td{ display:flex; gap:.5rem; padding:.4rem 0; border:0!important; }
  table.table td::before{ content:attr(data-label); min-width:98px; font-weight:600; color:#64748b; }
}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <div class="container">
    <div class="d-flex align-items-center gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="/test/">← Электронный склад</a>
      <span class="text-secondary small">Стол заказов</span>
    </div>
    <div class="form-check form-switch ms-auto">
      <input class="form-check-input" type="checkbox" id="showroomToggle">
      <label class="form-check-label" for="showroomToggle">Showroom</label>
    </div>
  </div>
</div>

<!-- Панель выдачи купона (одноразовая скидка по телефону) -->
<div class="discount-bar">
  <div class="container">
    <span class="text-secondary">Купон (1 покупка):</span>
    <input id="discPhone" type="tel" class="form-control form-control-sm" placeholder="+371XXXXXXXX" style="max-width:210px">
    <select id="discPercent" class="form-select form-select-sm" style="max-width:140px">
      <option value="0">0%</option><option value="3">3%</option><option value="5">5%</option>
      <option value="7">7%</option><option value="10">10%</option><option value="12">12%</option><option value="15">15%</option>
    </select>
    <button id="discSave" class="btn btn-sm btn-primary"><i class="bi bi-ticket-perforated"></i> Выдать</button>
    <button id="discClear" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i> Снять</button>
    <small id="discHint" class="text-secondary ms-2"></small>
  </div>
</div>

<div class="container py-3">
  <div class="d-flex flex-column flex-md-row align-items-md-end gap-2 mb-3">
    <div class="me-auto">
      <h1 class="h3 mb-1">Стол заказов</h1>
      <div class="text-secondary small">PHP + MySQL. Ленивая подгрузка, автодоп. телефона, печать наклеек, **одноразовые купоны** (фиксируются на заказ).</div>
    </div>
  </div>

  <!-- форма добавления (сокращённая версия как в твоём файле) -->
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <form id="orderForm" autocomplete="off">
        <div class="row g-3">
          <div class="col-12 col-md-6 position-relative">
            <label class="form-label">Телефон</label>
            <input type="tel" id="phone" class="form-control" required placeholder="+371XXXXXXXX">
            <div class="ac-list" id="phoneAc"></div>
            <div class="form-text d-flex gap-2 align-items-center">
              <span>Если номер без префикса — при сохранении добавим <b>+371</b>.</span>
              <span id="dupHint" class="text-warning-emphasis small d-none"><i class="bi bi-exclamation-triangle"></i> Такой номер уже есть</span>
              <span id="discBadgeAdd" class="badge badge-discount ms-2 d-none"></span>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label">Детали (теги)</label>
            <div id="tagsInput" class="tags-input form-control d-flex flex-wrap gap-2">
              <input id="partsInput" class="border-0 flex-grow-1" type="text" placeholder="Введите деталь и Enter (можно через запятую)">
            </div>
          </div>

          <div class="col-12">
            <label class="form-label">Комментарий</label>
            <textarea id="comment" class="form-control" rows="2" placeholder="Короткое примечание для этикетки"></textarea>
          </div>

          <div class="col-6 col-md-4">
            <label class="form-label">Срок (дедлайн)</label>
            <input type="date" id="deadline" class="form-control">
          </div>

          <div class="col-6 col-md-4">
            <label class="form-label">Поставщик</label>
            <select id="supplier" class="form-select">
              <option value="">— не выбран —</option>
              <option>Sils</option><option>Husqvarna</option><option>AL-KO</option>
              <option>Kober</option><option>4C</option><option>Rags</option>
              <option>LatWork</option><option>Ginalas</option><option>Viskas Sodinimkas</option>
              <option>Samalin</option><option>Hards</option><option>Grif</option>
              <option>GJ Grupa</option><option>Stokker</option>
            </select>
          </div>

          <div class="col-12 col-md-4 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-circle"></i> Добавить</button>
            <button type="button" id="clearForm" class="btn btn-outline-secondary">Очистить</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- фильтры -->
  <div class="card shadow-sm mb-3">
    <div class="card-body d-flex flex-column flex-lg-row gap-2 align-items-lg-center">
      <input id="search" class="form-control flex-grow-1" type="text" placeholder="Поиск по телефону, комментариям, тегам, поставщику">
      <div class="d-flex gap-2 w-100 w-lg-auto">
        <select id="filterStatus" class="form-select">
          <option value="">Все статусы</option>
          <option value="new">Новый</option>
          <option value="wip">В работе</option>
          <option value="done">Готов</option>
        </select>
        <select id="filterSupplier" class="form-select">
          <option value="">Все поставщики</option>
          <option>Sils</option><option>Husqvarna</option><option>AL-KO</option>
          <option>Kober</option><option>4C</option><option>Rags</option>
          <option>LatWork</option><option>Ginalas</option><option>Viskas Sodinimkas</option>
          <option>Samalin</option><option>Hards</option><option>Grif</option>
          <option>GJ Grupa</option><option>Stokker</option>
        </select>
      </div>
    </div>
  </div>

  <!-- таблица -->
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Телефон</th>
            <th class="sortable d-none d-md-table-cell" data-sort="created_at">Дата <i class="bi sort-indicator"></i></th>
            <th>Детали</th>
            <th class="d-none d-xl-table-cell">Комментарий</th>
            <th class="sortable d-none d-lg-table-cell" data-sort="deadline">Срок <i class="bi sort-indicator"></i></th>
            <th>Поставщик</th>
            <th>Статус</th>
            <th class="text-end">Действия</th>
          </tr>
        </thead>
        <tbody id="ordersBody"></tbody>
      </table>
    </div>
    <div id="infiniteHint" class="text-center py-3 text-secondary small d-none">Загружаю ещё…</div>
    <div id="endHint" class="text-center py-3 text-secondary small d-none">Это все записи</div>
  </div>
</div>

<!-- кнопка наверх -->
<button id="scrollTopBtn" class="btn btn-primary rounded-circle" aria-label="Наверх" style="position:fixed;bottom:20px;right:20px;display:none;z-index:1050">↑</button>

<!-- Модалка -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          Редактирование заказа
          <span id="editDiscountBadge" class="badge badge-discount ms-2"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <form id="editForm" autocomplete="off">
          <input type="hidden" id="editId">
          <div class="mb-3">
            <label class="form-label">Телефон</label>
            <input type="tel" id="editPhone" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Комментарий</label>
            <textarea id="editComment" class="form-control" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Детали (теги)</label>
            <div id="editTagsInput" class="tags-input form-control d-flex flex-wrap gap-2">
              <input id="editPartsInput" class="border-0 flex-grow-1" type="text" placeholder="Введите деталь и Enter (можно через запятую)">
            </div>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Срок (дедлайн)</label>
              <input type="date" id="editDeadline" class="form-control">
            </div>
            <div class="col-6">
              <label class="form-label">Поставщик</label>
              <select id="editSupplier" class="form-select">
                <option value="">— не выбран —</option>
                <option>Sils</option><option>Husqvarna</option><option>AL-KO</option>
                <option>Kober</option><option>4C</option><option>Rags</option>
                <option>LatWork</option><option>Ginalas</option><option>Viskas Sodinimkas</option>
                <option>Samalin</option><option>Hards</option><option>Grif</option>
                <option>GJ Grupa</option><option>Stokker</option>
              </select>
            </div>
          </div>

          <!-- Купон в модалке -->
          <div class="mt-3 d-flex align-items-center gap-2">
            <div class="form-check form-switch ms-auto">
              <input class="form-check-input" type="checkbox" id="editDiscountToggle">
              <label class="form-check-label" for="editDiscountToggle">Применить купон</label>
            </div>
          </div>
          <div class="small text-secondary mt-1">Купон — одноразовый. Применяется к этому заказу и затем исчезает из очереди телефона.</div>

        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-danger me-auto d-none" id="removeAppliedCouponBtn"><i class="bi bi-x-circle"></i> Снять фиксированную скидку</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
        <button id="editSaveBtn" class="btn btn-primary">Сохранить</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
'use strict';

/* ===== API ===== */
async function apiList(params={}){ const url=new URL('orders.php',location.href); url.searchParams.set('action','list'); Object.entries(params).forEach(([k,v])=> (v!=='' && v!=null) && url.searchParams.set(k,v)); const r=await fetch(url); const j=await r.json(); return j.items||[]; }
async function apiCreate(order){ const r=await fetch('orders.php?action=create',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(order)}); return r.json(); }
async function apiUpdate(order){ const r=await fetch('orders.php?action=update',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(order)}); return r.json(); }
async function apiDelete(id){ const r=await fetch('orders.php?action=delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})}); return r.json(); }
async function apiPhones(q, limit=5){ const url=new URL('orders.php',location.href); url.searchParams.set('action','phones'); url.searchParams.set('q',q); url.searchParams.set('limit',limit); const r=await fetch(url); const j=await r.json(); return j.items||[]; }

/* ===== Showroom toggle ===== */
const SHOWROOM_KEY='od_showroom';
const showroomEl = document.getElementById('showroomToggle');
const setShowroom = (on)=>{ localStorage.setItem(SHOWROOM_KEY, on ? '1':'0'); applyShowroomUI(); };
const isShowroomOn = ()=> localStorage.getItem(SHOWROOM_KEY)==='1' || false;
function applyShowroomUI(){ const on = isShowroomOn(); document.body.classList.toggle('showroom-on', on); document.querySelectorAll('.badge-discount').forEach(b=> b.style.display = on ? '' : 'none'); }
if (showroomEl){ showroomEl.checked=isShowroomOn(); showroomEl.addEventListener('change', ()=> setShowroom(showroomEl.checked)); }

/* ===== Купоны (одноразовые) =====
   od_coupons: { "+3712...": percent }  — ожидают применения
   od_coupon_applied: { orderId: percent } — уже зафиксированы на заказе
*/
const COUPON_QUEUE_KEY='od_coupons';
const COUPON_APPLIED_KEY='od_coupon_applied';

const loadJSON = (k)=>{ try{ return JSON.parse(localStorage.getItem(k)||'{}'); }catch{ return {}; } };
const saveJSON = (k,v)=> localStorage.setItem(k, JSON.stringify(v));

const normalizePhone = v => { let p=String(v||'').trim(); if(!p) return ''; if(!p.startsWith('+371') && /^\d+$/.test(p)) p='+371'+p; return p; };

const getQueuedCoupon = (phone)=> (loadJSON(COUPON_QUEUE_KEY)[normalizePhone(phone)] || 0);
const setQueuedCoupon = (phone,percent)=>{ const q=loadJSON(COUPON_QUEUE_KEY); const key=normalizePhone(phone); if(!key) return; const p=Number(percent)||0; if(p<=0) delete q[key]; else q[key]=p; saveJSON(COUPON_QUEUE_KEY,q); };
const removeQueuedCoupon = (phone)=>{ const q=loadJSON(COUPON_QUEUE_KEY); delete q[normalizePhone(phone)]; saveJSON(COUPON_QUEUE_KEY,q); };

const getAppliedCoupon = (orderId)=> (loadJSON(COUPON_APPLIED_KEY)[String(orderId)] || 0);
const setAppliedCoupon = (orderId,percent)=>{ const a=loadJSON(COUPON_APPLIED_KEY); const p=Number(percent)||0; if(p<=0) delete a[String(orderId)]; else a[String(orderId)]=p; saveJSON(COUPON_APPLIED_KEY,a); };
const removeAppliedCoupon = (orderId)=>{ const a=loadJSON(COUPON_APPLIED_KEY); delete a[String(orderId)]; saveJSON(COUPON_APPLIED_KEY,a); };

/* ===== State / Elements ===== */
let items=[], offset=0, loading=false; const LIMIT=20, STEP=5;
let sortField='created_at', sortDir='desc'; const filters={ q:'', status:'', supplier:'' };

const form=document.getElementById('orderForm');
const phoneEl=document.getElementById('phone');
const commentEl=document.getElementById('comment');
const supplierEl=document.getElementById('supplier');
const deadlineEl=document.getElementById('deadline');
const clearFormBtn=document.getElementById('clearForm');
const dupHint=document.getElementById('dupHint');
const discBadgeAdd=document.getElementById('discBadgeAdd');

const searchEl=document.getElementById('search');
const filterStatusEl=document.getElementById('filterStatus');
const filterSupplierEl=document.getElementById('filterSupplier');
const tableBody=document.getElementById('ordersBody');
const infiniteHint=document.getElementById('infiniteHint');
const endHint=document.getElementById('endHint');

const partsInput=document.getElementById('partsInput');
const tagsInput=document.getElementById('tagsInput');
let currentParts=[];

const discPhoneEl=document.getElementById('discPhone');
const discPercentEl=document.getElementById('discPercent');
const discSaveBtn=document.getElementById('discSave');
const discClearBtn=document.getElementById('discClear');
const discHint=document.getElementById('discHint');

const editModalEl = document.getElementById('editModal');
const editModal = new bootstrap.Modal(editModalEl, { backdrop: true });
const editSaveBtn = document.getElementById('editSaveBtn');
const removeAppliedCouponBtn = document.getElementById('removeAppliedCouponBtn');
const editDiscountToggle = document.getElementById('editDiscountToggle');
const editDiscountBadge = document.getElementById('editDiscountBadge');
let editParts=[]; const editPartsInput=document.getElementById('editPartsInput');

/* ===== Utils ===== */
const escapeHtml=s=>String(s).replace(/[&<>\"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const toYMD = s => {
  if (!s) return null;
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(s).trim());
  if (!m) return null;
  const y = Number(m[1]), mo = Number(m[2]), d = Number(m[3]);
  if (!y || !mo || !d) return null;
  return new Date(y, mo - 1, d);
};
const isOverdue=(deadline,status)=>{ if(!deadline||status==='done')return false; const d=toYMD(deadline); if(!d) return false; const t=new Date(); t.setHours(0,0,0,0); return d<t; };
const debounce=(fn,ms=150)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; };

/* ===== Tags (create) ===== */
function addTag(text){ const t=String(text).trim(); if(!t) return; if(t.includes(',')){ t.split(',').map(s=>s.trim()).filter(Boolean).forEach(addTag); return; } if(currentParts.includes(t)) return; currentParts.push(t); const tag=document.createElement('span'); tag.className='tag badge rounded-pill text-bg-info'; tag.dataset.value=t; tag.innerHTML=`<span>${escapeHtml(t)}</span><button type="button" class="btn-close btn-close-white ms-2" aria-label="Удалить"></button>`; tag.querySelector('button').addEventListener('click',()=>{ currentParts=currentParts.filter(v=>v!==t); tag.remove(); }); tagsInput.insertBefore(tag, partsInput); }
function clearTags(){ currentParts=[]; tagsInput.querySelectorAll('.tag').forEach(t=>t.remove()); }
partsInput.addEventListener('keydown',e=>{ if((e.key==='Enter'||e.key==='Tab')&&partsInput.value.trim()!==''){ e.preventDefault(); addTag(partsInput.value); partsInput.value=''; }});
partsInput.addEventListener('blur',()=>{ if(partsInput.value.trim()!==''){ addTag(partsInput.value); partsInput.value=''; }});
tagsInput.addEventListener('click',()=>partsInput.focus());

/* ===== Tags (edit) ===== */
function addEditTag(text){ const t=String(text).trim(); if(!t) return; if(t.includes(',')){ t.split(',').map(s=>s.trim()).filter(Boolean).forEach(addEditTag); return; } if(editParts.includes(t)) return; editParts.push(t); const tag=document.createElement('span'); tag.className='tag badge rounded-pill text-bg-info'; tag.dataset.value=t; tag.innerHTML=`<span>${escapeHtml(t)}</span><button type="button" class="btn-close btn-close-white ms-2" aria-label="Удалить"></button>`; tag.querySelector('button').addEventListener('click',()=>{ editParts=editParts.filter(v=>v!==t); tag.remove(); }); document.getElementById('editTagsInput').insertBefore(tag, editPartsInput); }
function clearEditTags(){ editParts=[]; document.querySelectorAll('#editTagsInput .tag').forEach(t=>t.remove()); }
if (editPartsInput){
  editPartsInput.addEventListener('keydown',e=>{ if((e.key==='Enter'||e.key==='Tab')&&editPartsInput.value.trim()!==''){ e.preventDefault(); addEditTag(editPartsInput.value); editPartsInput.value=''; }});
  editPartsInput.addEventListener('blur',()=>{ if(editPartsInput.value.trim()!==''){ addEditTag(editPartsInput.value); editPartsInput.value=''; }});
  document.getElementById('editTagsInput').addEventListener('click',()=>editPartsInput.focus());
}

/* ===== Phone autocomplete (create) ===== */
function attachPhoneAutocomplete(inputEl){
  const ac = inputEl.parentElement.querySelector('.ac-list');
  let suppressUntil = 0;
  const close = () => { ac.classList.remove('show'); ac.innerHTML = ''; };
  const open  = async () => {
    const raw = inputEl.value.trim();
    const qd  = (raw||'').replace(/\D/g,'');
    if (Date.now() < suppressUntil || !qd || qd.length < 2) { close(); return; }
    const list = await apiPhones(qd, 5);
    if (!list.length || list[0] === raw) { close(); return; }
    ac.innerHTML = list.map((ph,i)=>`
      <button type="button" class="ac-item${i===0?' active':''}" data-val="${escapeHtml(ph)}">
        <i class="bi bi-telephone"></i> ${escapeHtml(ph)}
      </button>`).join('');
    ac.classList.add('show');
    ac.querySelectorAll('.ac-item').forEach(btn=>{
      btn.addEventListener('pointerdown', (ev)=>{
        ev.preventDefault();
        inputEl.value = btn.dataset.val;
        suppressUntil = Date.now() + 500;
        close();
        inputEl.dispatchEvent(new Event('input', {bubbles:true}));
      });
    });
  };
  inputEl.addEventListener('input', debounce(open, 80));
  inputEl.addEventListener('focus', open);
  document.addEventListener('mousedown', e=>{ if (ac.classList.contains('show') && !inputEl.parentElement.contains(e.target)) close(); });
  inputEl.addEventListener('blur', ()=> setTimeout(close, 0));
}
attachPhoneAutocomplete(phoneEl);

/* ===== Render helpers ===== */
function statusBadgeHTML(status){ const map={ new:{cls:'badge-status status-new',text:'Новый'}, wip:{cls:'badge-status status-wip',text:'В работе'}, done:{cls:'badge-status status-done',text:'Готов'} }; const m=map[status]||map.new; return `<span class="badge rounded-pill ${m.cls}">${m.text}</span>`; }
function deadlineCellHTML(o){ if(!o.deadline) return '<span class="text-secondary">—</span>'; const od=isOverdue(o.deadline,o.status); return `<span class="${od?'overdue':''}">${escapeHtml(o.deadline)}</span>`; }
function appliedBadgeHTML(orderId){ const d=getAppliedCoupon(orderId); return d>0 ? ` <span class="badge badge-discount ms-1">−${d}%</span>` : ''; }

function rowHTML(o){
  const commentCell=o.comment ? `<span class="truncate" data-bs-toggle="tooltip" data-bs-title="${escapeHtml(o.comment)}">${escapeHtml(o.comment)}</span>` : '<span class="text-secondary">—</span>';
  return `<tr data-id="${o.id}">
    <td data-label="Телефон"><a href="tel:${escapeHtml(o.phone)}">${escapeHtml(o.phone)}</a>${appliedBadgeHTML(o.id)}</td>
    <td class="d-none d-md-table-cell" data-label="Дата">${escapeHtml(o.date)}</td>
    <td data-label="Детали">${(o.parts||[]).length ? (o.parts||[]).map(p=>`<span class="badge rounded-pill text-bg-info me-1">${escapeHtml(p)}</span>`).join(' ') : '<span class="text-secondary">—</span>'}</td>
    <td class="d-none d-xl-table-cell" data-label="Комментарий">${commentCell}</td>
    <td class="d-none d-lg-table-cell" data-label="Срок">${deadlineCellHTML(o)}</td>
    <td data-label="Поставщик">${o.supplier?`<span class="badge rounded-pill text-bg-light border">${escapeHtml(o.supplier)}</span>`:'<span class="text-secondary">—</span>'}</td>
    <td data-label="Статус">${statusBadgeHTML(o.status)}</td>
    <td data-label="Действия" class="text-end">
      <div class="btn-group btn-group-sm">
        <button class="btn btn-outline-primary" data-act="edit" data-id="${o.id}" title="Редактировать"><i class="bi bi-pencil"></i></button>
        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-label="Меню действий"></button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li class="dropdown-header">Действия</li>
          <li><button class="dropdown-item" data-act="setStatus" data-id="${o.id}" data-val="new">Сделать «Новый»</button></li>
          <li><button class="dropdown-item" data-act="setStatus" data-id="${o.id}" data-val="wip">Сделать «В работе»</button></li>
          <li><button class="dropdown-item" data-act="setStatus" data-id="${o.id}" data-val="done">Сделать «Готов»</button></li>
          <li><button class="dropdown-item" data-act="print" data-id="${o.id}"><i class="bi bi-printer me-2"></i>Печать наклеек</button></li>
          <li><hr class="dropdown-divider"></li>
          <li><button class="dropdown-item text-danger" data-act="delete" data-id="${o.id}">Удалить</button></li>
        </ul>
      </div>
    </td>
  </tr>`;
}
function updateRowDOM(o){
  const tr = tableBody.querySelector(`tr[data-id="${o.id}"]`);
  if(!tr) return;
  tr.children[0].innerHTML = `<a href="tel:${escapeHtml(o.phone)}">${escapeHtml(o.phone)}</a>${appliedBadgeHTML(o.id)}`;
  tr.children[2].innerHTML = (o.parts||[]).length ? o.parts.map(p=>`<span class="badge rounded-pill text-bg-info me-1">${escapeHtml(p)}</span>`).join(' ') : '<span class="text-secondary">—</span>';
  tr.children[3].innerHTML = o.comment ? `<span class="truncate" data-bs-toggle="tooltip" data-bs-title="${escapeHtml(o.comment)}">${escapeHtml(o.comment)}</span>` : '<span class="text-secondary">—</span>';
  tr.children[4].innerHTML = deadlineCellHTML(o);
  tr.children[6].innerHTML = statusBadgeHTML(o.status);
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=> new bootstrap.Tooltip(el));
}

/* ===== Label (печать) — только зафиксированная скидка по заказу ===== */
function buildLabelHTML(order){
  const safe = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  const phone = safe(order.phone);
  const created = (()=>{ const d=new Date(order.dateRaw||Date.now()); const dd=String(d.getDate()).padStart(2,'0'); const mm=String(d.getMonth()+1).padStart(2,'0'); const yy=d.getFullYear(); return `${dd}.${mm}.${yy}`; })();
  const note = (()=>{ const t=(order.comment||'').trim(); if(!t) return ''; const max=60; return safe(t.length>max?t.slice(0,max-1)+'…':t); })();
  const partsCount = Array.isArray(order.parts) ? order.parts.length : 0;
  const rightMeta = partsCount>0 ? `Позиций: ${partsCount}` : '';
  const discount = getAppliedCoupon(order.id); // только если закреплён
  const discLine = (discount>0) ? `<div class="disc">Скидка: ${discount}%</div>` : '';
  return `<!doctype html>
<html><head><meta charset="utf-8"><title>Label</title>
<style>
  @page { size: 58mm 40mm; margin: 3mm; }
  *{box-sizing:border-box} body{font:12px/1.25 -apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;color:#000}
  .label{width:100%;height:100%;display:flex;flex-direction:column;gap:4px}
  .row{display:flex;justify-content:space-between;align-items:flex-start;gap:8px}
  .phone{font-size:16px;font-weight:700}.date{font-size:10px;white-space:nowrap}
  .note{font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .util{font-size:10px;display:flex;justify-content:space-between;align-items:center;gap:8px}
  .checks{display:flex;gap:8px}.box{display:inline-block;width:9px;height:9px;border:1px solid #000;margin-right:4px;vertical-align:middle}
  .disc{font-size:10px}
  hr{border:0;border-top:1px dashed #000;margin:2px 0}
</style></head>
<body onload="window.print(); setTimeout(function(){ window.close(); }, 400);" onafterprint="window.close()">
  <div class="label">
    <div class="row"><div class="phone">${phone}</div><div class="date">Заявка: ${created}</div></div>
    ${note?`<div class="note">${note}</div>`:''}
    ${discLine}
    <hr>
    <div class="util">
      <div class="checks">
        <span><span class="box"></span>Звонок</span>
        <span><span class="box"></span>СМС</span>
        <span><span class="box"></span>Выдано</span>
      </div>
      <div>${rightMeta}</div>
    </div>
  </div>
</body></html>`;
}
function printOrderLabel(id){
  const o = items.find(x=>x.id===id); if(!o) return;
  const w = window.open('', '_blank', 'width=420,height=480'); if(!w){ alert('Блокировано всплывающее окно'); return; }
  w.document.open(); w.document.write(buildLabelHTML(o)); w.document.close();
}

/* ===== Fetch & render ===== */
async function reload(fromStart=true){
  if (fromStart){ items=[]; offset=0; tableBody.innerHTML=''; endHint.classList.add('d-none'); }
  if (loading) return; loading=true; infiniteHint.classList.remove('d-none');

  const batch = await apiList({ q:filters.q, status:filters.status, supplier:filters.supplier, sort:sortField, dir:sortDir, limit:fromStart?LIMIT:STEP, offset });
  items = items.concat(batch); offset += batch.length;

  const frag=document.createDocumentFragment();
  batch.forEach(o=>{ const t=document.createElement('tbody'); t.innerHTML=rowHTML(o); frag.appendChild(t.firstElementChild); });
  tableBody.appendChild(frag);

  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=> new bootstrap.Tooltip(el));
  infiniteHint.classList.add('d-none');
  if (batch.length===0) endHint.classList.remove('д-none');
  loading=false;
}

/* ===== Handlers: добавить заказ ===== */
form.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const order = {
    phone: normalizePhone(phoneEl.value.trim()),
    comment: commentEl.value.trim(),
    status: 'new',
    supplier: supplierEl.value || '',
    deadline: deadlineEl.value || '',
    parts: [...currentParts]
  };
  if (!order.phone){ alert('Укажите телефон'); return; }

  const res = await apiCreate(order);
  if (!res.ok){ alert('Ошибка создания'); return; }

  // если у телефона есть ожидающий купон — применяем к ТОЛЬКО ЧТО созданному заказу и списываем
  const newId = res.id; // сервер должен вернуть id
  const queued = getQueuedCoupon(order.phone);
  if (newId && queued>0){
    setAppliedCoupon(newId, queued);
    removeQueuedCoupon(order.phone);
  }

  if (res.duplicate) { dupHint.classList.remove('d-none'); setTimeout(()=>dupHint.classList.add('d-none'), 3000); }
  form.reset(); clearTags(); phoneEl.focus();
  updateAddFormDiscountBadge(''); // очистим подсказку о купоне
  await reload(true);
});
document.getElementById('clearForm').addEventListener('click',()=>{ form.reset(); clearTags(); dupHint.classList.add('d-none'); updateAddFormDiscountBadge(''); phoneEl.focus(); });

/* ===== Edit / Actions ===== */
let editingItem=null, editSaveHandler=null;

document.addEventListener('click', async (e)=>{
  const el = e.target.closest('[data-act]'); if(!el) return;
  const id = parseInt(el.dataset.id||'0',10);
  const act = el.dataset.act;

  if (act==='delete'){
    if (!confirm('Удалить заказ?')) return;
    const res = await apiDelete(id);
    if (res.ok) { await reload(true); } else { alert('Не удалилось'); }
    return;
  }

  if (act==='edit'){
    const item = items.find(x=>x.id===id); if(!item) return;
    editingItem = item;

    // заполняем форму
    document.getElementById('editId').value = item.id;
    document.getElementById('editPhone').value = item.phone;
    document.getElementById('editComment').value = item.comment || '';
    document.getElementById('editDeadline').value = item.deadline || '';
    document.getElementById('editSupplier').value = item.supplier || '';
    clearEditTags(); (item.parts||[]).forEach(addEditTag);

    // купоны: статус
    const applied = getAppliedCoupon(item.id);
    const queued = getQueuedCoupon(item.phone);
    editDiscountToggle.checked = queued>0; // можно применить, если в очереди есть купон
    editDiscountToggle.disabled = (queued<=0); // нельзя включить, если нет купона
    editDiscountBadge.textContent = applied>0 ? ('−'+applied+'%') : '';
    editDiscountBadge.style.display = applied>0 ? 'inline-block' : 'none';
    removeAppliedCouponBtn.classList.toggle('d-none', applied<=0);

    editModal.show();

    if (editSaveHandler){ editSaveBtn.removeEventListener('click', editSaveHandler); editSaveHandler=null; }
    editSaveHandler = async ()=>{
      const upd = {
        id: item.id,
        phone: normalizePhone(document.getElementById('editPhone').value.trim()),
        comment: document.getElementById('editComment').value.trim(),
        deadline: document.getElementById('editDeadline').value || '',
        supplier: document.getElementById('editSupplier').value || '',
        status: item.status,
        parts: [...editParts]
      };
      const res = await apiUpdate(upd);
      if (res.ok){
        Object.assign(item, upd);

        // применение купона по тумблеру:
        const queuedNow = getQueuedCoupon(item.phone);
        if (editDiscountToggle.checked && queuedNow>0){
          setAppliedCoupon(item.id, queuedNow);
          removeQueuedCoupon(item.phone); // списать одноразовый купон
        }

        updateRowDOM(item);
        editModal.hide();
      } else { alert('Не сохранилось'); }
    };
    editSaveBtn.addEventListener('click', editSaveHandler);
    return;
  }

  if (act==='setStatus'){
    const item = items.find(x=>x.id===id); if(!item) return;
    const res = await apiUpdate({ id, phone:item.phone, comment:item.comment, status:el.dataset.val, supplier:item.supplier, deadline:item.deadline, parts:item.parts });
    if (res.ok){ item.status = el.dataset.val; updateRowDOM(item); } else { alert('Не сохранилось'); }
    return;
  }

  if (act==='print'){ printOrderLabel(id); return; }
});

editModalEl.addEventListener('hidden.bs.modal', ()=>{
  if (editSaveHandler){ editSaveBtn.removeEventListener('click', editSaveHandler); editSaveHandler=null; }
  editingItem = null; clearEditTags();
});

removeAppliedCouponBtn.addEventListener('click', ()=>{
  const id = Number(document.getElementById('editId').value||0);
  if (!id) return;
  removeAppliedCoupon(id);
  editDiscountBadge.style.display='none';
  editDiscountBadge.textContent='';
  removeAppliedCouponBtn.classList.add('d-none');
  const item = items.find(x=>x.id===id); if (item) updateRowDOM(item);
});

/* ===== Поиск/фильтры/сортировка (как раньше) ===== */
searchEl.addEventListener('input', debounce(()=>{ filters.q=searchEl.value.trim(); maybeSyncCouponBarFrom(searchEl.value); reload(true); }, 200));
filterStatusEl.addEventListener('change', ()=>{ filters.status=filterStatusEl.value; reload(true); });
filterSupplierEl.addEventListener('change', ()=>{ filters.supplier=filterSupplierEl.value; reload(true); });
document.addEventListener('click', (e)=>{
  const th = e.target.closest('th.sortable'); if(!th) return;
  const fld = th.dataset.sort || 'created_at';
  if (sortField===fld) sortDir = (sortDir==='asc') ? 'desc' : 'asc';
  else { sortField=fld; sortDir = (fld==='created_at') ? 'desc' : 'asc'; }
  reload(true);
});

/* ===== Бесконечная прокрутка / Наверх ===== */
window.addEventListener('scroll', debounce(async ()=>{ if (loading) return; if ((window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 200)){ await reload(false); }}, 120));
window.addEventListener('scroll', ()=>{ document.getElementById('scrollTopBtn').style.display = (window.scrollY > 300) ? 'block' : 'none'; });
document.getElementById('scrollTopBtn').addEventListener('click', ()=> window.scrollTo({top:0, behavior:'smooth'}));

/* ===== Панель купона (выдать/снять для телефона) ===== */
function updateDiscHint(){
  if (!isShowroomOn()){ discHint.textContent=''; return; }
  const p = normalizePhone(discPhoneEl.value);
  const q = getQueuedCoupon(p);
  discHint.textContent = p ? (q>0 ? `Купон выдан: ${q}% (1 покупка)` : 'Нет купона') : '';
}
function updateAddFormDiscountBadge(val){
  const p = normalizePhone(val);
  const q = isShowroomOn() ? getQueuedCoupon(p) : 0;
  if (p && q>0){ discBadgeAdd.textContent = `Купон: −${q}%`; discBadgeAdd.classList.remove('d-none'); }
  else { discBadgeAdd.classList.add('d-none'); discBadgeAdd.textContent=''; }
}
discSaveBtn.addEventListener('click', ()=>{
  if (!isShowroomOn()) return;
  const p = normalizePhone(discPhoneEl.value);
  const d = Number(discPercentEl.value)||0;
  if(!p){ alert('Укажите телефон'); return; }
  if (d<=0){ alert('Укажите процент'); return; }
  setQueuedCoupon(p, d);
  updateDiscHint();
  if (normalizePhone(phoneEl.value)===p) updateAddFormDiscountBadge(phoneEl.value);
});
discClearBtn.addEventListener('click', ()=>{
  if (!isShowroomOn()) return;
  const p = normalizePhone(discPhoneEl.value);
  if(!p){ alert('Укажите телефон'); return; }
  removeQueuedCoupon(p);
  updateDiscHint();
  if (normalizePhone(phoneEl.value)===p) updateAddFormDiscountBadge(phoneEl.value);
});

/* синхронизация панели купона при вводе телефона */
function maybeSyncCouponBarFrom(val){
  if (!isShowroomOn()) return;
  const raw = String(val||'').trim();
  const digits = raw.replace(/\D/g,'');
  if (digits.length>=6){ discPhoneEl.value = normalizePhone(raw); updateDiscHint(); }
}
phoneEl.addEventListener('input', ()=>{ updateAddFormDiscountBadge(phoneEl.value); maybeSyncCouponBarFrom(phoneEl.value); });

/* init */
applyShowroomUI();
reload(true);
updateDiscHint();
updateAddFormDiscountBadge(phoneEl.value);

})(); // IIFE
</script>
</body>
</html>
