<?php
require_once 'includes/config.php';
$page_title = 'Attendance';

ob_start();
?>
<link rel="stylesheet" href="includes/assets/style.css">

<div class="min-h-screen bg-[#f4f7fb] p-5">

  <!-- Page Header -->
  <div class="mb-4 flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold text-slate-900">Attendance</h1>
      <p class="text-sm text-slate-500">Manage employee time entries and attendance records</p>
    </div>
  </div>

  <!-- Tabs -->
  <div class="mb-4 overflow-x-auto border-b border-slate-200">
    <div class="flex gap-1 min-w-max">
      <button class="tab-btn active px-4 py-2 text-sm font-semibold border-b-2 border-blue-600 text-blue-600" data-tab="time">
        Time Entries
      </button>
      <button class="tab-btn px-4 py-2 text-sm text-slate-600 hover:text-blue-600" data-tab="calendar">
        Calendar View
      </button>
      <button class="tab-btn px-4 py-2 text-sm text-slate-600 hover:text-blue-600" data-tab="manual">
        Manual Attendance
      </button>
      <button class="tab-btn px-4 py-2 text-sm text-slate-600 hover:text-blue-600" data-tab="discrepancies">
        Discrepancies
      </button>
      <button class="tab-btn px-4 py-2 text-sm text-slate-600 hover:text-blue-600" data-tab="process">
        Process Time Card
      </button>
      <button class="tab-btn px-4 py-2 text-sm text-slate-600 hover:text-blue-600" data-tab="overtime">
        Approve Overtime
      </button>
    </div>
  </div>

  <!-- Main Card -->
  <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4">

    <div class="flex items-center justify-between mb-4">
      <h2 id="sectionTitle" class="text-sm font-bold text-slate-900 uppercase">Time Entries</h2>
      <button onclick="openAddModal()" class="hidden md:inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
        <i class="fa-solid fa-plus"></i> Add Entry
      </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-6">
      <div class="relative">
        <i class="fa-solid fa-search absolute left-3 top-3 text-slate-400 text-sm"></i>
        <input 
          type="text" 
          id="searchInput"
          onkeyup="filterEmployees()"
          placeholder="Search by name or #code"
          class="w-80 max-w-full rounded-lg border border-slate-300 pl-10 pr-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        >
      </div>

      <div class="flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2">
        <i class="fa-regular fa-calendar text-slate-500"></i>
        <input id="fromDate" type="date" value="2026-04-01" class="text-sm outline-none">
        <span class="text-slate-400">-</span>
        <input id="toDate" type="date" value="2026-04-27" class="text-sm outline-none">
      </div>

      <button onclick="applyDate()" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">
        Apply
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
        <p class="text-xs text-slate-500">Total Employees</p>
        <h3 id="totalEmp" class="text-2xl font-bold text-slate-900">0</h3>
      </div>
      <div class="rounded-xl border border-green-200 bg-green-50 p-4">
        <p class="text-xs text-green-600">Present</p>
        <h3 id="presentCount" class="text-2xl font-bold text-green-700">0</h3>
      </div>
      <div class="rounded-xl border border-red-200 bg-red-50 p-4">
        <p class="text-xs text-red-600">Absent</p>
        <h3 id="absentCount" class="text-2xl font-bold text-red-700">0</h3>
      </div>
      <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4">
        <p class="text-xs text-yellow-700">Late</p>
        <h3 id="lateCount" class="text-2xl font-bold text-yellow-700">0</h3>
      </div>
    </div>

    <!-- Empty State -->
    <div id="emptyState" class="hidden py-20 text-center">
      <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-blue-50">
        <i class="fa-solid fa-user-clock text-3xl text-blue-600"></i>
      </div>
      <h3 class="font-bold text-slate-900">Search employees to edit their time entries</h3>
      <p class="mt-1 text-sm text-slate-500">No employee found for your search.</p>
    </div>

    <!-- Table -->
    <div id="tableWrap" class="overflow-x-auto rounded-xl border border-slate-200">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600">
          <tr>
            <th class="px-4 py-3 text-left font-semibold">Employee</th>
            <th class="px-4 py-3 text-left font-semibold">Date</th>
            <th class="px-4 py-3 text-left font-semibold">Check In</th>
            <th class="px-4 py-3 text-left font-semibold">Check Out</th>
            <th class="px-4 py-3 text-left font-semibold">Total Hours</th>
            <th class="px-4 py-3 text-left font-semibold">Status</th>
            <th class="px-4 py-3 text-right font-semibold">Action</th>
          </tr>
        </thead>
        <tbody id="attendanceBody" class="divide-y divide-slate-100"></tbody>
      </table>
    </div>

  </div>
</div>

<!-- Edit/Add Modal -->
<div id="entryModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl">
    <div class="flex items-center justify-between border-b border-slate-200 p-5">
      <h3 id="modalTitle" class="text-lg font-bold text-slate-900">Edit Time Entry</h3>
      <button onclick="closeModal()" class="text-slate-400 hover:text-red-500">
        <i class="fa-solid fa-xmark text-xl"></i>
      </button>
    </div>

    <form onsubmit="saveEntry(event)" class="p-5 space-y-4">
      <input type="hidden" id="editId">

      <div>
        <label class="mb-1 block text-xs font-bold text-slate-500">Employee Name</label>
        <input id="empName" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="mb-1 block text-xs font-bold text-slate-500">Code</label>
          <input id="empCode" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
        </div>
        <div>
          <label class="mb-1 block text-xs font-bold text-slate-500">Date</label>
          <input id="entryDate" type="date" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="mb-1 block text-xs font-bold text-slate-500">Check In</label>
          <input id="checkIn" type="time" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
        </div>
        <div>
          <label class="mb-1 block text-xs font-bold text-slate-500">Check Out</label>
          <input id="checkOut" type="time" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
        </div>
      </div>

      <div>
        <label class="mb-1 block text-xs font-bold text-slate-500">Status</label>
        <select id="entryStatus" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500">
          <option>Present</option>
          <option>Absent</option>
          <option>Late</option>
          <option>Half Day</option>
          <option>Overtime</option>
        </select>
      </div>

      <div class="flex justify-end gap-2 pt-3">
        <button type="button" onclick="closeModal()" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
          Cancel
        </button>
        <button class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">
          Save Entry
        </button>
      </div>
    </form>
  </div>
</div>

<script>
let attendanceData = [
  {id:1, name:"Anita Sharma", code:"EMP001", date:"2026-04-27", in:"09:12", out:"18:05", status:"Late"},
  {id:2, name:"Rahul Das", code:"EMP002", date:"2026-04-27", in:"09:00", out:"18:00", status:"Present"},
  {id:3, name:"Priya Gupta", code:"EMP003", date:"2026-04-27", in:"", out:"", status:"Absent"},
  {id:4, name:"Suman Roy", code:"EMP004", date:"2026-04-26", in:"08:55", out:"18:20", status:"Overtime"},
  {id:5, name:"Neha Singh", code:"EMP005", date:"2026-04-26", in:"09:05", out:"14:00", status:"Half Day"},
  {id:6, name:"Arjun Mondal", code:"EMP006", date:"2026-04-25", in:"09:00", out:"18:00", status:"Present"}
];

let currentTab = 'time';

function calculateHours(start, end) {
  if (!start || !end) return '-';
  const [sh, sm] = start.split(':').map(Number);
  const [eh, em] = end.split(':').map(Number);
  let mins = (eh * 60 + em) - (sh * 60 + sm);
  if (mins < 0) mins += 1440;
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return `${h}h ${m}m`;
}

function badge(status) {
  const map = {
    "Present": "bg-green-100 text-green-700",
    "Absent": "bg-red-100 text-red-700",
    "Late": "bg-yellow-100 text-yellow-700",
    "Half Day": "bg-orange-100 text-orange-700",
    "Overtime": "bg-blue-100 text-blue-700"
  };
  return `<span class="rounded-full px-3 py-1 text-xs font-bold ${map[status] || 'bg-slate-100 text-slate-700'}">${status}</span>`;
}

function renderTable(data = attendanceData) {
  const tbody = document.getElementById('attendanceBody');
  const emptyState = document.getElementById('emptyState');
  const tableWrap = document.getElementById('tableWrap');

  if (!data.length) {
    emptyState.classList.remove('hidden');
    tableWrap.classList.add('hidden');
    updateStats([]);
    return;
  }

  emptyState.classList.add('hidden');
  tableWrap.classList.remove('hidden');

  tbody.innerHTML = data.map(row => `
    <tr class="hover:bg-slate-50">
      <td class="px-4 py-3">
        <div class="flex items-center gap-3">
          <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-blue-700 font-bold">
            ${row.name.charAt(0)}
          </div>
          <div>
            <div class="font-semibold text-slate-900">${row.name}</div>
            <div class="text-xs text-slate-500">#${row.code}</div>
          </div>
        </div>
      </td>
      <td class="px-4 py-3 text-slate-600">${formatDate(row.date)}</td>
      <td class="px-4 py-3 text-slate-700">${row.in || '-'}</td>
      <td class="px-4 py-3 text-slate-700">${row.out || '-'}</td>
      <td class="px-4 py-3 font-semibold text-slate-800">${calculateHours(row.in, row.out)}</td>
      <td class="px-4 py-3">${badge(row.status)}</td>
      <td class="px-4 py-3 text-right">
        <button onclick="editEntry(${row.id})" class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-600 hover:bg-blue-100">
          <i class="fa-solid fa-pen"></i> Edit
        </button>
      </td>
    </tr>
  `).join('');

  updateStats(data);
}

function updateStats(data) {
  document.getElementById('totalEmp').innerText = data.length;
  document.getElementById('presentCount').innerText = data.filter(x => x.status === 'Present' || x.status === 'Overtime').length;
  document.getElementById('absentCount').innerText = data.filter(x => x.status === 'Absent').length;
  document.getElementById('lateCount').innerText = data.filter(x => x.status === 'Late').length;
}

function formatDate(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-IN', {day:'2-digit', month:'short', year:'numeric'});
}

function filterEmployees() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  const filtered = attendanceData.filter(row =>
    row.name.toLowerCase().includes(q) ||
    row.code.toLowerCase().includes(q)
  );
  renderTable(filtered);
}

function applyDate() {
  const from = document.getElementById('fromDate').value;
  const to = document.getElementById('toDate').value;

  const filtered = attendanceData.filter(row => {
    return row.date >= from && row.date <= to;
  });

  renderTable(filtered);
}

function openAddModal() {
  document.getElementById('modalTitle').innerText = 'Add Time Entry';
  document.getElementById('editId').value = '';
  document.getElementById('empName').value = '';
  document.getElementById('empCode').value = '';
  document.getElementById('entryDate').value = new Date().toISOString().slice(0,10);
  document.getElementById('checkIn').value = '09:00';
  document.getElementById('checkOut').value = '18:00';
  document.getElementById('entryStatus').value = 'Present';
  openModal();
}

function editEntry(id) {
  const row = attendanceData.find(x => x.id === id);
  if (!row) return;

  document.getElementById('modalTitle').innerText = 'Edit Time Entry';
  document.getElementById('editId').value = row.id;
  document.getElementById('empName').value = row.name;
  document.getElementById('empCode').value = row.code;
  document.getElementById('entryDate').value = row.date;
  document.getElementById('checkIn').value = row.in;
  document.getElementById('checkOut').value = row.out;
  document.getElementById('entryStatus').value = row.status;
  openModal();
}

function openModal() {
  const modal = document.getElementById('entryModal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeModal() {
  const modal = document.getElementById('entryModal');
  modal.classList.add('hidden');
  modal.classList.remove('flex');
}

function saveEntry(e) {
  e.preventDefault();

  const id = document.getElementById('editId').value;
  const data = {
    id: id ? Number(id) : Date.now(),
    name: document.getElementById('empName').value,
    code: document.getElementById('empCode').value,
    date: document.getElementById('entryDate').value,
    in: document.getElementById('checkIn').value,
    out: document.getElementById('checkOut').value,
    status: document.getElementById('entryStatus').value
  };

  if (id) {
    attendanceData = attendanceData.map(x => x.id == id ? data : x);
  } else {
    attendanceData.unshift(data);
  }

  closeModal();
  renderTable();
}

document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.tab-btn').forEach(b => {
      b.classList.remove('active', 'border-b-2', 'border-blue-600', 'text-blue-600', 'font-semibold');
      b.classList.add('text-slate-600');
    });

    this.classList.add('active', 'border-b-2', 'border-blue-600', 'text-blue-600', 'font-semibold');
    this.classList.remove('text-slate-600');

    currentTab = this.dataset.tab;
    document.getElementById('sectionTitle').innerText = this.innerText;

    renderTable(attendanceData);
  });
});

renderTable();
</script>

<?php
$page_content = ob_get_clean();
include 'includes/header.php';
echo $page_content;
include 'includes/footer.php';
?>

<script src="includes/assets/scripts.js"></script>
