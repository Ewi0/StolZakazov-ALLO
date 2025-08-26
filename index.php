<?php /* index.php — фронт для Стола заказов (PHP+MySQL) */ ?>
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
body{ color:var(--txt); background:var(--bg); }
.tags-input{ cursor:text; min-height:42px; align-items:center; }
.tags-input input{ outline:none; min-width:140px; }
table.table td, table.table th{ vertical-align:top; }
.truncate{ display:inline-block; max-width: 40ch; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: bottom; }
.badge-status{ cursor:pointer; user-select:none; font-weight:600; border:1px solid transparent; }
.badge-status.status-new{  background:var(--st-new-bg);  color:var(--st-new-tx); }
.badge-status.status-wip{  background:var(--st-wip-bg);  color:var(--st-wip-tx); }
.badge-status.status-done{ background:var(--st-done-bg); color:var(--st-done-tx); }
.badge-status:hover{ opacity:.9; } .badge-status:active{ transform:translateY(1px); }
.overdue{ background:var(--overdue-bg)!important; padding:.1rem .4rem; border-radius:.375rem; }
.overdue-badge{ box-shadow: 0 0 0 2px var(--overdue-bd) inset; }
th.sortable{ cursor:pointer; user-select:none; } th.sortable .sort-indicator{ margin-left:.25rem; }
.ac-list{ position:absolute; z-index: 10; top: 100%; left: 0; right: 0; background: var(--ac-bg); border: 1px solid var(--ac-bd); border-top: none; border-radius: 0 0 .5rem .5rem; box-shadow: 0 4px 12px rgba(0,0,0,.06); display: none; max-height: 220px; overflow: auto; }
.ac-list.show{ display:block; }
.ac-item{ display:flex; align-items:center; gap:.25rem; width:100%; padding:.5rem .75rem; border:0; background:transparent; text-align:left; font-size:.95rem; }
.ac-item:hover{ background:var(--ac-hover); } .ac-item.active{ background:var(--ac-active); }
.ac-mark{ background:transparent; font-weight:700; }
@media (max-width:576px){ .btn-group>.btn,.btn{ padding:.45rem .6rem; } .truncate{ max-width:26ch; } }
#scrollTopBtn{ position:fixed; bottom:20px; right:20px; display:none; z-index:1050; }
</style>
</head>
<body>
<div class="container py-3">
  <div class="d-flex flex-column flex-md-row align-items-md-end gap-2 mb-3">
    <div class="me-auto">
      <h1 class="h3 mb-1">Стол заказов</h1>
      <div class="text-secondary small">PHP + MySQL. Ленивая подгрузка, автодоп. телефона, печать наклеек.</div>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <form id="orderForm" autocomplete="off">
        <div class="row g-3">
          <div class="col-12 col-md-6 position-relative">
            <label for="phone" class="form-label">Телефон</label>
            <input type="tel" id="phone" class="form-control phone-input" required placeholder="+371XXXXXXXX">
            <div class="ac-list" id="phoneAc"></div>
            <div class="form-text d-flex gap-2 align-items-center">
              <span>Если номер без префикса — при сохранении добавим <b>+371</b>.</span>
              <span id="dupHint" class="text-warning-emphasis small d-none"><i class="bi bi-exclamation-triangle"></i> Такой номер уже есть</span>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label">Детали (теги)</label>
            <div id="tagsInput" class="tags-input form-control d-flex flex-wrap gap-2">
              <input id="partsInput" class="border-0 flex-grow-1" type="text" placeholder="Введите деталь и Enter (можно через запятую)">
            </div>
          </div>

          <div class="col-12">
            <label for="comment" class="form-label">Комментарий</label>
            <textarea id="comment" class="form-control" rows="2" placeholder="Короткое примечание для этикетки"></textarea>
          </div>

          <div class="col-12 col-md-4">
            <label for="deadline" class="form-label">Срок (дедлайн)</label>
            <input type="date" id="deadline" class="form-control">
          </div>

          <div class="col-12 col-md-4">
            <label for="supplier" class="form-label">Поставщик</label>
            <select id="supplier" class="form-select">
              <option value="">— не выбран —</option>
              <option>Stiga</option><option>Husqvarna</option><option>AL-KO</option>
              <option>Briggs &amp; Stratton</option><option>Makita</option><option>Oregon</option>
              <option>Partner</option><option>Gardena</option><option>Stihl</option>
              <option>Echo</option><option>MTD</option>
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

  <div class="card shadow-sm mb-3">
    <div class="card-body d-flex flex-column flex-lg-row gap-2 align-items-lg-center">
      <input id="search" class="form-control flex-grow-1" type="text" placeholder="Поиск по телефону, комментариям, тегам, поставщику">
      <div class="d-flex gap-2 w-100 w-lg-auto">
        <select id="filterStatus" class="form-select">
          <option value="">Все статусы</option><option value="new">Новый</option><option value="wip">В работе</option><option value="done">Готов</option>
        </select>
        <select id="filterSupplier" class="form-select">
          <option value="">Все поставщики</option>
          <option>Stiga</option><option>Husqvarna</option><option>AL-KO</option>
          <option>Briggs &amp; Stratton</option><option>Makita</option><option>Oregon</option>
          <option>Partner</option><option>Gardena</option><option>Stihl</option>
          <option>Echo</option><option>MTD</option>
        </select>
      </div>
    </div>
  </div>

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

<button id="scrollTopBtn" class="btn btn-primary rounded-circle">↑</button>

<!-- Модалка редактирования -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Редактирование заказа</h5>
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
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Срок (дедлайн)</label>
              <input type="date" id="editDeadline" class="form-control">
            </div>
            <div class="col-6">
              <label class="form-label">Поставщик</label>
              <select id="editSupplier" class="form-select">
                <option value="">— не выбран —</option>
                <option>Stiga</option><option>Husqvarna</option><option>AL-KO</option>
                <option>Briggs &amp; Stratton</option><option>Makita</option><option>Oregon</option>
                <option>Partner</option><option>Gardena</option><option>Stihl</option>
                <option>Echo</option><option>MTD</option>
              </select>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Статус</label>
            <div class="d-flex gap-2">
              <label class="form-check"><input class="form-check-input" type="radio" name="editStatus" value="new"> Новый</label>
              <label class="form-check"><input class="form-check-input" type="radio" name="editStatus" value="wip"> В работе</label>
              <label class="form-check"><input class="form-check-input" type="radio" name="editStatus" value="done"> Готов</label>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
        <button id="editSaveBtn" class="btn btn-primary">Сохранить</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ===== API ===== */
async function apiList(params={}){ const url=new URL('orders.php',location.href); url.searchParams.set('action','list'); Object.entries(params).forEach(([k,v])=> (v!=='' && v!=null) && url.searchParams.set(k,v)); const r=await fetch(url); const j=await r.json(); return j.items||[]; }
async function apiCreate(order){ const r=await fetch('orders.php?action=create',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(order)}); return r.json(); }
async function apiUpdate(order){ const r=await fetch('orders.php?action=update',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(order)}); return r.json(); }
async function apiDelete(id){ const r=await fetch('orders.php?action=delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})}); return r.json(); }
async function apiPhones(q, limit=5){ const url=new URL('orders.php',location.href); url.searchParams.set('action','phones'); url.searchParams.set('q',q); url.searchParams.set('limit',limit); const r=await fetch(url); const j=await r.json(); return j.items||[]; }

/* ===== State ===== */
let items=[], offset=0, loading=false; const LIMIT=20, STEP=5;
let sortField='created_at', sortDir='desc'; const filters={ q:'', status:'', supplier:'' };

/* ===== Elements ===== */
const form=document.getElementById('orderForm');
const phoneEl=document.getElementById('phone');
const commentEl=document.getElementById('comment');
const supplierEl=document.getElementById('supplier');
const deadlineEl=document.getElementById('deadline');
const clearFormBtn=document.getElementById('clearForm');
const dupHint=document.getElementById('dupHint');
const searchEl=document.getElementById('search');
const filterStatusEl=document.getElementById('filterStatus');
const filterSupplierEl=document.getElementById('filterSupplier');
const tableBody=document.getElementById('ordersBody');
const infiniteHint=document.getElementById('infiniteHint');
const endHint=document.getElementById('endHint');
const partsInput=document.getElementById('partsInput');
const tagsInput=document.getElementById('tagsInput');
const scrollTopBtn=document.getElementById('scrollTopBtn');

/* ===== Utils ===== */
const escapeHtml=s=>String(s).replace(/[&<>\"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const onlyDigits=s=>(s||'').replace(/\D/g,'');
const normalizePhone=v=>{ let p=String(v).trim(); if(!p) return ''; if(!p.startsWith('+371') && /^\d+$/.test(p)) p='+371'+p; return p; };
const toYMD=s=>{ if(!s) return null; const [y,m,d]=s.split('-').map(Number); if(!y||!m||!d) return null; return new Date(y,m-1,d); };
const isOverdue=(deadline,status)=>{ if(!deadline||status==='done')return false; const d=toYMD(deadline); if(!d) return false; const t=new Date(); t.setHours(0,0,0,0); return d<t; };
const debounce=(fn,ms=150)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; };

/* tags (parts) */
let currentParts=[];
function addTag(text){ const t=String(text).trim(); if(!t) return; if(t.includes(',')){ t.split(',').map(s=>s.trim()).filter(Boolean).forEach(addTag); return; } if(currentParts.includes(t)) return; currentParts.push(t); const tag=document.createElement('span'); tag.className='tag badge rounded-pill text-bg-info'; tag.dataset.value=t; tag.innerHTML=`<span>${escapeHtml(t)}</span><button type="button" class="btn-close btn-close-white ms-2" aria-label="Удалить"></button>`; tag.querySelector('button').addEventListener('click',()=>{ currentParts=currentParts.filter(v=>v!==t); tag.remove(); }); tagsInput.insertBefore(tag, partsInput); }
function clearTags(){ currentParts=[]; tagsInput.querySelectorAll('.tag').forEach(t=>t.remove()); }
partsInput.addEventListener('keydown',e=>{ if((e.key==='Enter'||e.key==='Tab')&&partsInput.value.trim()!==''){ e.preventDefault(); addTag(partsInput.value); partsInput.value=''; }});
partsInput.addEventListener('blur',()=>{ if(partsInput.value.trim()!==''){ addTag(partsInput.value); partsInput.value=''; }});
tagsInput.addEventListener('click',()=>partsInput.focus());

/* phone autocomplete */
function attachPhoneAutocomplete(inputEl){
  const ac = inputEl.parentElement.querySelector('.ac-list');
  let suppressUntil=0;
  const close=()=>{ ac.classList.remove('show'); ac.innerHTML=''; };
  const open=async ()=>{
    const raw=inputEl.value.trim(); const qd=onlyDigits(raw);
    if(Date.now()<suppressUntil || !qd || qd.length<2){ close(); return; }
    const list = await apiPhones(qd, 5);
    if(!list.length || (list.length && list[0]===raw)){ close(); return; }
    ac.innerHTML = list.map((ph,i)=>`<button type="button" class="ac-item${i===0?' active':''}" data-val="${escapeHtml(ph)}"><i class="bi bi-telephone me-2"></i>${escapeHtml(ph)}</button>`).join('');
    ac.classList.add('show');
    ac.querySelectorAll('.ac-item').forEach(btn=> btn.onclick=()=>{
      inputEl.value = btn.dataset.val; close(); suppressUntil = Date.now()+250; inputEl.focus();
    });
  };
  inputEl.addEventListener('input', debounce(open,80));
  inputEl.addEventListener('focus', open);
  inputEl.addEventListener('blur', ()=> setTimeout(close,120));
  document.addEventListener('mousedown', e=>{ if(ac.classList.contains('show') && !inputEl.parentElement.contains(e.target)) close(); });
}
attachPhoneAutocomplete(phoneEl);

/* ===== Render helpers ===== */
function statusBadgeHTML(status, id, overdue=false){
  const map={ new:{cls:'badge-status status-new',text:'Новый'}, wip:{cls:'badge-status status-wip',text:'В работе'}, done:{cls:'badge-status status-done',text:'Готов'} };
  const m=map[status]||map.new, extra=overdue?' overdue-badge':'';
  return `<span class="badge rounded-pill ${m.cls}${extra}" data-act="cycleStatus" data-id="${id}" title="Кликните, чтобы изменить статус">${m.text}</span>`;
}
function deadlineCellHTML(o){
  if(!o.deadline) return '<span class="text-secondary">—</span>';
  const od=isOverdue(o.deadline,o.status);
  return `<span class="${od?'overdue':''}">${escapeHtml(o.deadline)}</span>`;
}
function rowHTML(o){
  const commentCell=o.comment ? `<span class="truncate" data-bs-toggle="tooltip" data-bs-title="${escapeHtml(o.comment)}">${escapeHtml(o.comment)}</span>` : '<span class="text-secondary">—</span>';
  return `<tr data-id="${o.id}">
    <td><a href="tel:${escapeHtml(o.phone)}">${escapeHtml(o.phone)}</a></td>
    <td class="d-none d-md-table-cell">${escapeHtml(o.date)}</td>
    <td>${(o.parts||[]).map(p=>`<span class="badge rounded-pill text-bg-info me-1">${escapeHtml(p)}</span>`).join(' ')||'<span class="text-secondary">—</span>'}</td>
    <td class="d-none d-xl-table-cell">${commentCell}</td>
    <td class="d-none d-lg-table-cell">${deadlineCellHTML(o)}</td>
    <td>${o.supplier?`<span class="badge rounded-pill text-bg-light border">${escapeHtml(o.supplier)}</span>`:'<span class="text-secondary">—</span>'}</td>
    <td>${statusBadgeHTML(o.status, o.id, isOverdue(o.deadline,o.status))}</td>
    <td class="text-end">
      <div class="btn-group btn-group-sm">
        <button class="btn btn-outline-primary" data-act="edit" data-id="${o.id}" title="Редактировать"><i class="bi bi-pencil"></i></button>
        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"></button>
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
  tr.children[3].innerHTML = o.comment
    ? `<span class="truncate" data-bs-toggle="tooltip" data-bs-title="${escapeHtml(o.comment)}">${escapeHtml(o.comment)}</span>`
    : '<span class="text-secondary">—</span>';
  tr.children[4].innerHTML = deadlineCellHTML(o);
  tr.children[6].innerHTML = statusBadgeHTML(o.status, o.id, isOverdue(o.deadline,o.status));
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=> new bootstrap.Tooltip(el));
}

/* ===== Label (печать) ===== */
function buildLabelHTML(order){
  const safe = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  const phone = safe(order.phone);
  const created = (()=>{ const d=new Date(order.dateRaw||Date.now()); const dd=String(d.getDate()).padStart(2,'0'); const mm=String(d.getMonth()+1).padStart(2,'0'); const yy=d.getFullYear(); return `${dd}.${mm}.${yy}`; })();
  const note = (()=>{ const t=(order.comment||'').trim(); if(!t) return ''; const max=60; return safe(t.length>max?t.slice(0,max-1)+'…':t); })();
  const partsCount = Array.isArray(order.parts) ? order.parts.length : 0;
  const rightMeta = partsCount>0 ? `Позиций: ${partsCount}` : '';
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
  hr{border:0;border-top:1px dashed #000;margin:2px 0}
</style></head>
<body onload="window.print(); setTimeout(function(){ window.close(); }, 400);" onafterprint="window.close()">
  <div class="label">
    <div class="row"><div class="phone">${phone}</div><div class="date">Заявка: ${created}</div></div>
    ${note?`<div class="note">${note}</div>`:''}
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
  if (batch.length===0) endHint.classList.remove('d-none');
  loading=false;
}

/* ===== Handlers ===== */
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

  // мягкое предупреждение о дубликате (если уже был такой номер в БД)
  if (res.duplicate) { dupHint.classList.remove('d-none'); setTimeout(()=>dupHint.classList.add('d-none'), 3000); }

  form.reset(); clearTags(); phoneEl.focus();
  await reload(true);
});
clearFormBtn.addEventListener('click',()=>{ form.reset(); clearTags(); dupHint.classList.add('d-none'); phoneEl.focus(); });

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
    document.getElementById('editId').value = item.id;
    document.getElementById('editPhone').value = item.phone;
    document.getElementById('editComment').value = item.comment || '';
    document.getElementById('editDeadline').value = item.deadline || '';
    document.getElementById('editSupplier').value = item.supplier || '';
    [...document.querySelectorAll('input[name="editStatus"]')].forEach(r=> r.checked = (r.value===item.status));
    const modal = new bootstrap.Modal(document.getElementById('editModal')); modal.show();

    const saveBtn = document.getElementById('editSaveBtn');
    const handler = async ()=>{
      const upd = {
        id: item.id,
        phone: normalizePhone(document.getElementById('editPhone').value.trim()),
        comment: document.getElementById('editComment').value.trim(),
        deadline: document.getElementById('editDeadline').value || '',
        supplier: document.getElementById('editSupplier').value || '',
        status: (document.querySelector('input[name="editStatus"]:checked')||{value:item.status}).value,
        parts: item.parts
      };
      const res = await apiUpdate(upd);
      if (res.ok){
        // показать мягкое предупреждение, если номер уже встречался
        if (res.duplicate) { dupHint.classList.remove('d-none'); setTimeout(()=>dupHint.classList.add('d-none'), 3000); }
        Object.assign(item, upd);
        updateRowDOM(item);
        bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
      } else { alert('Не сохранилось'); }
      saveBtn.removeEventListener('click', handler);
    };
    saveBtn.addEventListener('click', handler);
    return;
  }

  if (act==='setStatus'){
    const item = items.find(x=>x.id===id); if(!item) return;
    const res = await apiUpdate({ id, phone:item.phone, comment:item.comment, status:el.dataset.val, supplier:item.supplier, deadline:item.deadline, parts:item.parts });
    if (res.ok){ item.status = el.dataset.val; updateRowDOM(item); } else { alert('Не сохранилось'); }
    return;
  }

  if (act==='cycleStatus'){
    const item = items.find(x=>x.id===id); if(!item) return;
    const next = item.status==='new' ? 'wip' : (item.status==='wip' ? 'done' : 'new');
    const res = await apiUpdate({ id, phone:item.phone, comment:item.comment, status:next, supplier:item.supplier, deadline:item.deadline, parts:item.parts });
    if (res.ok){ item.status=next; updateRowDOM(item); } else { alert('Не сохранилось'); }
    return;
  }

  if (act==='print'){ printOrderLabel(id); return; }
});

/* фильтры/поиск/сортировка */
searchEl.addEventListener('input', debounce(()=>{ filters.q=searchEl.value.trim(); reload(true); }, 200));
filterStatusEl.addEventListener('change', ()=>{ filters.status=filterStatusEl.value; reload(true); });
filterSupplierEl.addEventListener('change', ()=>{ filters.supplier=filterSupplierEl.value; reload(true); });
document.addEventListener('click', (e)=>{
  const th = e.target.closest('th.sortable'); if(!th) return;
  const fld = th.dataset.sort || 'created_at';
  if (sortField===fld) sortDir = (sortDir==='asc') ? 'desc' : 'asc';
  else { sortField=fld; sortDir = (fld==='created_at') ? 'desc' : 'asc'; }
  reload(true);
});

/* бесконечная прокрутка */
window.addEventListener('scroll', debounce(async ()=>{
  if (loading) return;
  if ((window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 200)){
    await reload(false);
  }
}, 120));

/* кнопка "наверх" */
window.addEventListener('scroll', ()=>{ scrollTopBtn.style.display = (window.scrollY > 300) ? 'block' : 'none'; });
scrollTopBtn.addEventListener('click', ()=> window.scrollTo({top:0, behavior:'smooth'}));

/* init */
reload(true);
</script>
</body>
</html>