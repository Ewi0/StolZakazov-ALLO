<?php /* index.php — Стол заказов (PHP+MySQL) — без скидочной системы */ ?>
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
 --st-new-bg:#fde68a; --st-new-tx:#7c5a00;
 --st-conf-bg:#bfdbfe; --st-conf-tx:#1e40af;
 --st-instock-bg:#bbf7d0; --st-instock-tx:#14532d;
 --st-issued-bg:#d1d5db; --st-issued-tx:#1f2937;
 --st-canceled-bg:#fecaca; --st-canceled-tx:#7f1d1d;
 --overdue-bg:#fee2e2; --overdue-bd:#ef9a9a;
 --ac-bg:#ffffff; --ac-bd:#e5e7eb; --ac-hover:#f3f4f6; --ac-active:#e5e7eb;
}
*{box-sizing:border-box}
body{ color:var(--txt); background:var(--bg); }
table.table td, table.table th{ vertical-align:top; }
.truncate{ display:inline-block; max-width: 40ch; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: bottom; }

/* верхняя перекладина */
.topbar{ position:sticky; top:0; z-index:1030; background:#fff; border-bottom:1px solid #e9ecef; }
.topbar .container{ display:flex; gap:.5rem; align-items:center; justify-content:space-between; padding:.5rem 1rem; }

/* tags-input */
.tags-input{ cursor:text; min-height:44px; align-items:center; }
.tags-input input{ outline:none; min-width:120px; }

/* статус — крупный, не кликабельный */
.badge-status{
 cursor: default;
 user-select: none;
 font-weight: 600;
 border: 1px solid transparent;
 padding: .5rem .8rem;
 font-size: 1rem;
}
.status-new{ background:var(--st-new-bg); color:var(--st-new-tx); }
.status-confirmed{ background:var(--st-conf-bg); color:var(--st-conf-tx); }
.status-instock{ background:var(--st-instock-bg); color:var(--st-instock-tx); }
.status-issued{ background:var(--st-issued-bg); color:var(--st-issued-tx); }
.status-canceled{ background:var(--st-canceled-bg); color:var(--st-canceled-tx); }

.overdue{ background:var(--overdue-bg)!important; padding:.1rem .4rem; border-radius:.375rem; }
.overdue-badge{ box-shadow: 0 0 0 2px var(--overdue-bd) inset; }

/* сортировка */
th.sortable{ cursor:pointer; user-select:none; }
th.sortable .sort-indicator{ margin-left:.25rem; }

/* автодоп телефонов */
.ac-list{ position:absolute; z-index: 10; top: 100%; left: 0; right: 0; background: var(--ac-bg); border: 1px solid var(--ac-bd); border-top: none; border-radius: 0 0 .5rem .5rem; box-shadow: 0 4px 12px rgba(0,0,0,.06); display: none; max-height: 240px; overflow: auto; }
.ac-list.show{ display:block; }
.ac-item{ display:flex; align-items:center; gap:.5rem; width:100%; padding:.75rem 1rem; border:0; background:transparent; text-align:left; font-size:1rem; }
.ac-item:hover{ background:var(--ac-hover); } .ac-item.active{ background:var(--ac-active); }

/* кнопка наверх */
#scrollTopBtn{ position:fixed; bottom:20px; right:20px; display:none; z-index:1050; }

/* ====== MOBILE FIRST ====== */
@media (max-width: 992px){
 .form-control, .form-select, .btn{ font-size:1rem; }
 .btn-group>.btn{ padding:.6rem .85rem; }
}
@media (max-width: 576px){
 .card .table-responsive{ border:0; }
 table.table{ border:0; }
 thead.table-light{ display:none; }
 table.table tr{ display:block; background:#fff; margin:10px 12px; padding:12px; border-radius:12px; box-shadow: 0 2px 10px rgba(0,0,0,.05); }
 table.table td{ display:flex; width:100%; border:0 !important; padding:.4rem 0; gap:.5rem; align-items:baseline; }
 table.table td::before{
 content: attr(data-label);
 min-width: 98px;
 font-weight:600;
 color:#64748b;
 flex: 0 0 auto;
 }
 .truncate{ max-width: 100%; }
 .card-body .form-select, .card-body .form-control{ font-size:1rem; padding:.75rem 1rem; }
 .btn-group.btn-group-sm .btn, .dropdown-menu .dropdown-item{ padding:.8rem 1rem; font-size:1rem; }
 .topbar .container{ padding:.5rem .75rem; }
}
</style>
</head>
<body>

<!-- Перекладина (поправь href под свой путь) -->
<div class="topbar">
 <div class="container">
 <a class="btn btn-outline-secondary btn-sm" href="/warehouse/">← Электронный склад</a>
 <span class="text-secondary small">Стол заказов</span>
 </div>
</div>

<div class="container py-3">
 <div class="d-flex flex-column flex-md-row align-items-md-end gap-2 mb-3">
 <div class="me-auto">
 <h1 class="h3 mb-1">Стол заказов</h1>
 <div class="text-secondary small">PHP + MySQL. Поиск, фильтры, дедлайн, теги, печать, lazy-load.</div>
 </div>
 </div>

 <!-- форма добавления -->
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
 <span id="dupHint" class="text-warning-emphasis small d-none">
 <i class="bi bi-exclamation-triangle"></i> Такой номер уже есть
 </span>
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

 <div class="col-6 col-md-4">
 <label for="deadline" class="form-label">Срок (дедлайн)</label>
 <input type="date" id="deadline" class="form-control">
 </div>

 <div class="col-6 col-md-4">
 <label for="supplier" class="form-label">Поставщик</label>
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
 <option value="confirmed">Подтверждён</option>
 <option value="instock">На складе</option>
 <option value="issued">Выдан клиенту</option>
 <option value="canceled">Отменён</option>
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

 <!-- таблица / карточки -->
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

<button id="scrollTopBtn" class="btn btn-primary rounded-circle" aria-label="Наверх">↑</button>

<!-- Модалка редактирования -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
 <div class="modal-dialog modal-dialog-scrollable">
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

 <div class="mt-3">
 <label class="form-label">Статус</label>
 <div class="d-flex gap-2 flex-wrap">
 <label class="form-check"><input class="form-check-input" type="radio" name="editStatus" value="new"> Новый</label>
 <label class="form-check"><input class="form-check-input" type="radio" name="editStatus" value="confirmed"> Подтверждён</label>
 <label class="form-check"><input class="form-check-input" type="radio" name="editStatus" value="instock"> На складе</label>
 <label class="form-check"><input class="form-check-input" type="radio" name="editStatus" value="issued"> Выдан клиенту</label>
 <label class="form-check"><input class="form-check-input" type="radio" name="editStatus" value="canceled"> Отменён</label>
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
(function(){
'use strict';

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
const normalizePhone=v=>{ let p=String(v).trim(); if(!p) return ''; if(!p.startsWith('+371') && /^\d+$/.test(p)) p='+371'+p; return p; };
const toYMD=s=>{ if(!s) return null; const [y,m,d]=s.split('-').map(Number); if(!y||!m||!d) return null; return new Date(y,m-1,d); };
const isOverdue=(deadline,status)=>{ if(!deadline||status==='issued'||status==='canceled')return false; const d=toYMD(deadline); if(!d) return false; const t=new Date(); t.setHours(0,0,0,0); return d<t; };
const debounce=(fn,ms=150)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; };

/* ====== tags (создание) ====== */
let currentParts=[];
function addTag(text){
 const t=String(text).trim(); if(!t) return;
 if(t.includes(',')){ t.split(',').map(s=>s.trim()).filter(Boolean).forEach(addTag); return; }
 if(currentParts.includes(t)) return;
 currentParts.push(t);
 const tag=document.createElement('span');
 tag.className='tag badge rounded-pill text-bg-info';
 tag.dataset.value=t;
 tag.innerHTML=`<span>${escapeHtml(t)}</span><button type="button" class="btn-close btn-close-white ms-2" aria-label="Удалить"></button>`;
 tag.querySelector('button').addEventListener('click',()=>{
 currentParts=currentParts.filter(v=>v!==t); tag.remove();
 });
 tagsInput.insertBefore(tag, partsInput);
}
function clearTags(){ currentParts=[]; tagsInput.querySelectorAll('.tag').forEach(t=>t.remove()); }
partsInput.addEventListener('keydown',e=>{
 if((e.key==='Enter'||e.key==='Tab')&&partsInput.value.trim()!==''){
 e.preventDefault(); addTag(partsInput.value); partsInput.value='';
 }
});
partsInput.addEventListener('blur',()=>{
 if(partsInput.value.trim()!==''){ addTag(partsInput.value); partsInput.value=''; }
});
tagsInput.addEventListener('click',()=>partsInput.focus());

/* ====== tags (редактирование в модалке) ====== */
let editParts = [];
function addEditTag(text){
 const t=String(text).trim(); if(!t) return;
 if(t.includes(',')){ t.split(',').map(s=>s.trim()).filter(Boolean).forEach(addEditTag); return; }
 if(editParts.includes(t)) return;
 editParts.push(t);
 const tag=document.createElement('span');
 tag.className='tag badge rounded-pill text-bg-info';
 tag.dataset.value=t;
 tag.innerHTML=`<span>${escapeHtml(t)}</span><button type="button" class="btn-close btn-close-white ms-2" aria-label="Удалить"></button>`;
 tag.querySelector('button').addEventListener('click',()=>{
 editParts=editParts.filter(v=>v!==t); tag.remove();
 });
 document.getElementById('editTagsInput').insertBefore(tag, document.getElementById('editPartsInput'));
}
function clearEditTags(){ editParts=[]; document.querySelectorAll('#editTagsInput .tag').forEach(t=>t.remove()); }
const editPartsInput=document.getElementById('editPartsInput');
if (editPartsInput){
 editPartsInput.addEventListener('keydown',e=>{
 if((e.key==='Enter'||e.key==='Tab')&&editPartsInput.value.trim()!==''){
 e.preventDefault(); addEditTag(editPartsInput.value); editPartsInput.value='';
 }
 });
 editPartsInput.addEventListener('blur',()=>{
 if(editPartsInput.value.trim()!==''){ addEditTag(editPartsInput.value); editPartsInput.value=''; }
 });
 document.getElementById('editTagsInput').addEventListener('click',()=>editPartsInput.focus());
}

/* phone autocomplete — выбор через pointerdown, авто-скрытие */
function attachPhoneAutocomplete(inputEl){
 const ac = inputEl.parentElement.querySelector('.ac-list');
 let suppressUntil = 0;
 const close = () => { ac.classList.remove('show'); ac.innerHTML = ''; };
 const open = async () => {
 const raw = inputEl.value.trim();
 const qd = (raw||'').replace(/\D/g,'');
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
function statusBadgeHTML(status){
 const map={
 new:{cls:'status-new', text:'Новый'},
 confirmed:{cls:'status-confirmed', text:'Подтверждён'},
 instock:{cls:'status-instock', text:'На складе'},
 issued:{cls:'status-issued', text:'Выдан клиенту'},
 canceled:{cls:'status-canceled', text:'Отменён'}
 };
 const m=map[status]||map.new;
 return `<span class="badge badge-status ${m.cls}">${m.text}</span>`;
}
function deadlineCellHTML(o){
 if(!o.deadline) return '<span class="text-secondary">—</span>';
 const od=isOverdue(o.deadline,o.status);
 return `<span class="${od?'overdue':''}">${escapeHtml(o.deadline)}</span>`;
}
function rowHTML(o){
 const commentCell=o.comment ? `<span class="truncate" data-bs-toggle="tooltip" data-bs-title="${escapeHtml(o.comment)}">${escapeHtml(o.comment)}</span>` : '<span class="text-secondary">—</span>';
 return `<tr data-id="${o.id}">
 <td data-label="Телефон"><a href="tel:${escapeHtml(o.phone)}">${escapeHtml(o.phone)}</a></td>
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
 <li class="dropdown-header">Сменить статус</li>
 <li><button class="dropdown-item" data-act="setStatus" data-id="${o.id}" data-val="new">Новый</button></li>
 <li><button class="dropdown-item" data-act="setStatus" data-id="${o.id}" data-val="confirmed">Подтверждён</button></li>
 <li><button class="dropdown-item" data-act="setStatus" data-id="${o.id}" data-val="instock">На складе</button></li>
 <li><button class="dropdown-item" data-act="setStatus" data-id="${o.id}" data-val="issued">Выдан клиенту</button></li>
 <li><button class="dropdown-item" data-act="setStatus" data-id="${o.id}" data-val="canceled">Отменён</button></li>
 <li><hr class="dropdown-divider"></li>
 <li><button class="dropdown-item" data-act="print" data-id="${o.id}"><i class="bi bi-printer me-2"></i>Печать наклеек</button></li>
 <li><button class="dropdown-item text-danger" data-act="delete" data-id="${o.id}">Удалить</button></li>
 </ul>
 </div>
 </td>
 </tr>`;
}
function updateRowDOM(o){
 const tr = tableBody.querySelector(`tr[data-id="${o.id}"]`);
 if(!tr) return;
 tr.children[2].innerHTML = (o.parts||[]).length
 ? o.parts.map(p=>`<span class="badge rounded-pill text-bg-info me-1">${escapeHtml(p)}</span>`).join(' ')
 : '<span class="text-secondary">—</span>';
 tr.children[3].innerHTML = o.comment
 ? `<span class="truncate" data-bs-toggle="tooltip" data-bs-title="${escapeHtml(o.comment)}">${escapeHtml(o.comment)}</span>`
 : '<span class="text-secondary">—</span>';
 tr.children[4].innerHTML = deadlineCellHTML(o);
 tr.children[6].innerHTML = statusBadgeHTML(o.status);
 document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el=> new bootstrap.Tooltip(el));
}

/* ===== Label (печать) ===== */
function buildLabelHTML(order){
 const safe = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
 const phone = safe(order.phone);
 const created = (()=>{ 
 try{ 
 const d=new Date(order.dateRaw||order.date||Date.now()); 
 const dd=String(d.getDate()).padStart(2,'0'); 
 const mm=String(d.getMonth()+1).padStart(2,'0'); 
 const yy=d.getFullYear(); 
 return `${dd}.${mm}.${yy}`; 
 }catch{ return ''; }
 })();
 const note = (()=>{ 
 const t=(order.comment||'').trim(); 
 if(!t) return ''; 
 const max=60; 
 return safe(t.length>max?t.slice(0,max-1)+'…':t); 
 })();
 const partsCount = Array.isArray(order.parts) ? order.parts.length : 0;
 const rightMeta = partsCount>0 ? `Позиций: ${partsCount}` : '';

 return `<!doctype html>
<html><head><meta charset="utf-8"><title>Label</title>
<style>
 :root{
 --label-w:58mm; 
 --label-h:38mm; 
 /* асимметричные поля — чуть больше слева, чтобы сдвинуть контент вправо */
 --pad-left:3mm; 
 --pad-right:2mm; 
 --pad-y:2mm;
 /* локальная коррекция смещения для нижнего блока (заметно правее, чтобы не резался "Звонок") */
 --shift-x:0.8mm;
 }
 /* Ровно размер носителя, без внешних полей принтера */
 @page { size: var(--label-w) var(--label-h); margin: 0; }

 *{ box-sizing: border-box; }
 html, body { height: 100%; }
 /* Базовый шрифт крупнее для читаемости на термоголове */
 body { font: 12px/1.28 -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 0; color:#000; }

 /* Каркас этикетки — ровно 58×38 мм с асимметричными внутренними полями */
 .label { 
 width: var(--label-w); 
 height: var(--label-h); 
 padding: var(--pad-y) var(--pad-right) var(--pad-y) var(--pad-left);
 display: flex; 
 flex-direction: column; 
 gap: 3.5px; 
 }
 .body{ margin-left: var(--shift-x); }
 .row{ display:flex; justify-content:space-between; align-items:flex-start; gap:6px; }
 /* Телефон — заметно крупнее и жирнее */
 .phone{ font-size: 17px; font-weight: 800; letter-spacing:.2px; }
 .date{ font-size: 10px; white-space: nowrap; }
 /* Комментарий под номером — крупнее для читаемости */
 .note{ font-size: 11px; line-height: 1.35; margin-bottom: 1mm; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
 .util{ font-size: 10.5px; display:flex; justify-content:space-between; align-items:center; gap:6px; }
 .checks{ display:flex; gap:7px; }
 .box{ display:inline-block; width:9px; height:9px; border:1px solid #000; margin-right:4px; vertical-align:middle; }
 hr{ border:0; border-top:.5pt dashed #000; margin: .8mm 0 .9mm; }

 /* Экранный предпросмотр — крупнее, с рамкой */
 @media screen{
 body{ background:#f5f5f5; }
 .label{ border:1px dashed #bbb; }
 .zoom{ transform: scale(1.8); transform-origin: top left; }
 }
 /* Печать — без масштаба и без рамки */
 @media print{
 *{ -webkit-print-color-adjust: exact; print-color-adjust: exact; }
 .label{ border:0; }
 .zoom{ transform: none !important; }
 }
</style></head>
<body onload="window.print(); setTimeout(function(){ window.close(); }, 400);" onafterprint="window.close()">
 <div class="label zoom">
 <div class="row"><div class="phone">${phone}</div><div class="date">Заявка: ${created}</div></div>
 <div class="body">
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
 </div>
</body></html>`;
}
function printOrderLabel(id){
 const o = items.find(x=>x.id===id); if(!o) return;
 const w = window.open('', '_blank', 'width=960,height=720,scrollbars=yes,resizable=yes,menubar=no,toolbar=no,location=no,status=no');
 if(!w){ alert('Блокировано всплывающее окно'); return; }
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

 clearEditTags();
 (item.parts||[]).forEach(addEditTag);

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
 parts: [...editParts]
 };
 const res = await apiUpdate(upd);
 if (res.ok){
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

})(); // IIFE
</script>
</body>
</html>
