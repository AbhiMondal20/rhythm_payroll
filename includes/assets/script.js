
/* ──── DATA ──── */
const EMPLOYEES = [
  {id:1,name:'Dr. Anjali Sharma',dept:'Medical',desig:'Sr. Gynaecologist',join:'2019-03-12',gross:62000,status:'Active',ph:'9832100001',email:'anjali@rk.in'},
  {id:2,name:'Rajib Das',dept:'Nursing',desig:'Head Nurse',join:'2020-04-16',gross:38500,status:'Active',ph:'9832100002',email:'rajib@rk.in'},
  {id:3,name:'Sunita Paul',dept:'Reception',desig:'Sr. Receptionist',join:'2021-04-20',gross:28000,status:'Active',ph:'9832100003',email:'sunita@rk.in'},
  {id:4,name:'Amit Roy',dept:'Lab Tech',desig:'Lab Technician',join:'2022-06-01',gross:32000,status:'Active',ph:'9832100004',email:'amit@rk.in'},
  {id:5,name:'Priya Sen',dept:'Administration',desig:'Admin Executive',join:'2021-09-15',gross:35000,status:'Active',ph:'9832100005',email:'priya@rk.in'},
  {id:6,name:'Mohan Das',dept:'Accounts',desig:'Accountant',join:'2020-11-01',gross:42000,status:'Active',ph:'9832100006',email:'mohan@rk.in'},
  {id:7,name:'Dr. Suman Bose',dept:'Medical',desig:'Embryologist',join:'2018-07-22',gross:71000,status:'Active',ph:'9832100007',email:'suman@rk.in'},
  {id:8,name:'Rina Chatterjee',dept:'Nursing',desig:'Staff Nurse',join:'2023-01-10',gross:27000,status:'Active',ph:'9832100008',email:'rina@rk.in'},
  {id:9,name:'Deepak Sharma',dept:'Lab Tech',desig:'Sr. Lab Tech',join:'2019-08-05',gross:36000,status:'Active',ph:'9832100009',email:'deepak@rk.in'},
  {id:10,name:'Kavya Nair',dept:'Administration',desig:'Office Manager',join:'2017-05-14',gross:44000,status:'Active',ph:'9832100010',email:'kavya@rk.in'},
  {id:11,name:'Arjun Mehta',dept:'Medical',desig:'Consultant',join:'2022-02-28',gross:58000,status:'Active',ph:'9832100011',email:'arjun@rk.in'},
  {id:12,name:'Meera Joshi',dept:'Nursing',desig:'ICU Nurse',join:'2020-07-19',gross:33000,status:'Active',ph:'9832100012',email:'meera@rk.in'},
];

const PAYROLL_ROWS = [
  {name:'Dr. Anjali Sharma',dept:'Medical',gross:'₹62,000',ded:'₹9,400',net:'₹52,600',status:'processed'},
  {name:'Rajib Das',dept:'Nursing',gross:'₹38,500',ded:'₹5,800',net:'₹32,700',status:'processed'},
  {name:'Sunita Paul',dept:'Reception',gross:'₹28,000',ded:'₹4,100',net:'₹23,900',status:'processed'},
  {name:'Amit Roy',dept:'Lab Tech',gross:'₹32,000',ded:'₹5,000',net:'₹27,000',status:'pending'},
  {name:'Priya Sen',dept:'Administration',gross:'₹35,000',ded:'₹5,400',net:'₹29,600',status:'pending'},
  {name:'Mohan Das',dept:'Accounts',gross:'₹42,000',ded:'₹6,500',net:'₹35,500',status:'processed'},
];

let currentPage = 'dashboard';
let payrollFilter = 'all';
let empSearch = '';
let charts = {};
let attendanceRecords = [
  {name:'Dr. Anjali Sharma',dept:'Medical',in:'08:52',out:'—',status:'Present'},
  {name:'Rajib Das',dept:'Nursing',in:'09:05',out:'—',status:'Present'},
  {name:'Sunita Paul',dept:'Reception',in:'08:45',out:'—',status:'Present'},
  {name:'Amit Roy',dept:'Lab Tech',in:'—',out:'—',status:'Absent'},
  {name:'Priya Sen',dept:'Administration',in:'09:30',out:'—',status:'Present'},
  {name:'Mohan Das',dept:'Accounts',in:'—',out:'—',status:'On Leave'},
  {name:'Dr. Suman Bose',dept:'Medical',in:'08:00',out:'—',status:'Present'},
  {name:'Rina Chatterjee',dept:'Nursing',in:'—',out:'—',status:'Absent'},
];

/* ──── AUTH ──── */
function doLogin(){
  const e=document.getElementById('loginEmail').value.trim();
  const p=document.getElementById('loginPass').value.trim();
  const err=document.getElementById('loginErr');
  if(e==='admin@ramkrishnaivf.in'&&p==='admin123'){
    err.style.display='none';
    document.getElementById('loginPage').style.display='none';
    document.getElementById('appShell').style.display='block';
    nav('dashboard');
  } else {
    err.style.display='block';
  }
}
document.addEventListener('keydown',e=>{if(e.key==='Enter'&&document.getElementById('loginPage').style.display!=='none')doLogin()});

function doLogout(){
  document.getElementById('appShell').style.display='none';
  document.getElementById('loginPage').style.display='flex';
  // destroy charts
  Object.values(charts).forEach(c=>{try{c.destroy()}catch(e){}});
  charts={};
}

/* ──── NAVIGATION ──── */
function nav(page){
  currentPage=page;
  document.querySelectorAll('.nav-item').forEach(el=>{
    el.classList.toggle('active',el.dataset.page===page);
  });
  const titles={
    dashboard:'Dashboard',employees:'Employee List',approvals:'Approvals',
    attendance:'Attendance',leave:'Leave Management',payroll:'Payroll',
    taxes:'Taxes',reports:'Reports',import:'Data Import',users:'Users',config:'Configuration'
  };
  document.getElementById('pageTitle').textContent=titles[page]||page;
  renderPage(page);
  // close mobile sidebar
  if(window.innerWidth<=1024) closeSidebar();
}

function renderPage(page){
  const c=document.getElementById('pageContainer');
  // destroy previous charts
  Object.values(charts).forEach(ch=>{try{ch.destroy()}catch(e){}});
  charts={};
  c.innerHTML='';
  const fns={
    dashboard:renderDashboard,employees:renderEmployees,approvals:renderApprovals,
    attendance:renderAttendance,leave:renderLeave,payroll:renderPayroll,
    taxes:renderTaxes,reports:renderReports,import:renderImport,
    users:renderUsers,config:renderConfig
  };
  if(fns[page]) fns[page](c);
}

/* ──── SIDEBAR ──── */
function toggleSidebar(){
  const sb=document.getElementById('sidebar');
  const ma=document.getElementById('mainArea');
  const ov=document.getElementById('mobOverlay');
  if(window.innerWidth<=1024){
    sb.classList.toggle('mob-open');
    ov.style.display=sb.classList.contains('mob-open')?'block':'none';
  } else {
    sb.classList.toggle('hidden');
    ma.classList.toggle('full');
  }
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('mob-open');
  document.getElementById('mobOverlay').style.display='none';
}

/* ──── MODALS ──── */
function openModal(id){document.getElementById(id).style.display='flex'}
function closeModal(id){document.getElementById(id).style.display='none'}
function closeModalBg(e,id){if(e.target===document.getElementById(id))closeModal(id)}
function runPayroll(){openModal('runPayrollModal')}
function saveEmployee(){closeModal('addEmployeeModal');showToast('Employee added successfully!')}
function confirmPayroll(){closeModal('runPayrollModal');showToast('Payroll processed for April 2026!')}

/* ──── TOAST ──── */
function showToast(msg){
  const t=document.getElementById('toast');
  document.getElementById('toastMsg').textContent=msg;
  t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'),3000);
}

/* ══════════════════════════════════════
   DASHBOARD PAGE
══════════════════════════════════════ */
function renderDashboard(c){
  c.innerHTML=`
  <div class="page">
  <div class="ph">
    <div><h1>Dashboard</h1><p>Thursday, 16 April 2026 · Siliguri, West Bengal</p></div>
    <button onclick="nav('dashboard')" style="display:flex;align-items:center;gap:6px;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:7px 13px;cursor:pointer;font-size:13px;font-weight:500;color:var(--text)">
      <svg id="refIcon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>Refresh
    </button>
  </div>

  <div class="stats-grid">
    <div class="sc">
      <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
          <p class="sc-label">HEADCOUNT</p>
          <p class="sc-val">65</p>
          <p class="sc-sub" style="color:var(--green)">+2 this month</p>
        </div>
        <div class="sc-icon" style="background:var(--purple-l)"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6D28D9" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
      </div>
    </div>
    <div class="sc" style="border-left:3px solid var(--green)">
      <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
          <p class="sc-label">AT WORK</p>
          <p class="sc-val">49</p>
          <div class="pb" style="width:90px;margin-top:5px"><div class="pf" style="width:75%;background:var(--green)"></div></div>
        </div>
        <div class="sc-icon" style="background:var(--green-l)"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
      </div>
    </div>
    <div class="sc" style="border-left:3px solid #F59E0B">
      <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
          <p class="sc-label">ON LEAVE</p>
          <p class="sc-val">1</p>
          <p class="sc-sub" style="color:var(--muted)">1.5% of total</p>
        </div>
        <div class="sc-icon" style="background:var(--orange-l)"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
      </div>
    </div>
    <div class="sc" style="border-left:3px solid var(--red)">
      <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
          <p class="sc-label">ABSENT</p>
          <p class="sc-val">15</p>
          <p class="sc-sub" style="color:var(--red)">23% of total</p>
        </div>
        <div class="sc-icon" style="background:var(--red-l)"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
      </div>
    </div>
    <div class="sc">
      <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
          <p class="sc-label">APR PAYROLL</p>
          <p class="sc-val" style="font-size:22px">₹8.4L</p>
          <p class="sc-sub" style="color:var(--muted)">↓ 6% vs Mar</p>
        </div>
        <div class="sc-icon" style="background:var(--blue-l)"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px">
    <div class="card">
      <div class="card-header">
        <div><h2>Payroll Cost Trend</h2><p style="font-size:12px;color:var(--muted);margin-top:2px">Nov 2025 — Apr 2026</p></div>
        <div style="display:flex;gap:16px;flex-wrap:wrap">
          <div><div style="font-size:11px;color:var(--muted);font-weight:600">TOTAL PAID</div><div style="font-size:16px;font-weight:700">₹55.0L</div></div>
          <div><div style="font-size:11px;color:var(--muted);font-weight:600">PF DEDUCTED</div><div style="font-size:16px;font-weight:700;color:var(--purple)">₹6.6L</div></div>
        </div>
      </div>
      <div class="card-body"><canvas id="payrollChart" height="180"></canvas></div>
    </div>
    <div class="card">
      <div class="card-header"><h2>Upcoming Holidays</h2><span class="badge" style="background:var(--purple-l);color:var(--purple)">3 ahead</span></div>
      <div class="card-body">
        <div class="hpill"><div class="hdate"><div style="font-size:17px;font-weight:700;line-height:1">1</div><div style="font-size:9px;opacity:.7">May</div></div><div><div style="font-size:13px;font-weight:600;color:#4C1D95">May Day</div><div style="font-size:11px;color:var(--purple)">Friday · National</div></div></div>
        <div class="hpill"><div class="hdate"><div style="font-size:17px;font-weight:700;line-height:1">15</div><div style="font-size:9px;opacity:.7">Aug</div></div><div><div style="font-size:13px;font-weight:600;color:#4C1D95">Independence Day</div><div style="font-size:11px;color:var(--purple)">Saturday · National</div></div></div>
        <div class="hpill"><div class="hdate"><div style="font-size:17px;font-weight:700;line-height:1">2</div><div style="font-size:9px;opacity:.7">Oct</div></div><div><div style="font-size:13px;font-weight:600;color:#4C1D95">Gandhi Jayanti</div><div style="font-size:11px;color:var(--purple)">Friday · National</div></div></div>
        <button onclick="nav('leave')" style="width:100%;margin-top:6px;padding:7px;border:1px dashed #DDD6FE;border-radius:8px;font-size:12px;color:var(--purple);cursor:pointer;background:none;font-weight:500">View All Holidays →</button>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px">
    <div class="card">
      <div class="card-header">
        <h2>Recent Payroll</h2>
        <div style="display:flex;gap:6px">
          <button class="tab-btn active" id="dbt-all" onclick="dashPayrollTab('all')">All</button>
          <button class="tab-btn" id="dbt-processed" onclick="dashPayrollTab('processed')">Processed</button>
          <button class="tab-btn" id="dbt-pending" onclick="dashPayrollTab('pending')">Pending</button>
        </div>
      </div>
      <div style="overflow-x:auto"><table id="dashPayrollTable"><thead><tr><th>EMPLOYEE</th><th>DEPARTMENT</th><th style="text-align:right">GROSS</th><th style="text-align:right">DEDUCTIONS</th><th style="text-align:right">NET</th><th style="text-align:center">STATUS</th></tr></thead><tbody id="dashPayrollBody"></tbody></table></div>
      <div style="padding:10px 20px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:12px;color:var(--muted)">Showing 6 of 65</span>
        <button onclick="nav('payroll')" style="font-size:12px;color:var(--purple);background:none;border:none;cursor:pointer;font-weight:500">View All →</button>
      </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:14px">
      <div class="card">
        <div class="card-header"><h2>Today's Attendance</h2></div>
        <div class="card-body">
          <div style="display:flex;justify-content:center;margin-bottom:12px"><canvas id="attDonut" width="100" height="100"></canvas></div>
          <div style="display:flex;justify-content:space-around">
            <div style="text-align:center"><div style="font-size:18px;font-weight:700;color:var(--green)">49</div><div style="font-size:11px;color:var(--muted)">At Work</div></div>
            <div style="text-align:center"><div style="font-size:18px;font-weight:700;color:#F59E0B">1</div><div style="font-size:11px;color:var(--muted)">On Leave</div></div>
            <div style="text-align:center"><div style="font-size:18px;font-weight:700;color:#EF4444">15</div><div style="font-size:11px;color:var(--muted)">Absent</div></div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><h2>Dept. Headcount</h2></div>
        <div class="card-body" id="deptList"></div>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-bottom:16px">
    <div class="card">
      <div class="card-header"><h2>🎂 Cheers to Peers</h2></div>
      <div>
        <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid var(--border)">
          <div class="av" style="background:var(--orange-l);color:#92400E;width:38px;height:38px;font-size:12px">RD</div>
          <div style="flex:1"><div style="font-size:13px;font-weight:600">Rajib Das</div><div style="font-size:11px;color:var(--muted)">Birthday today 🎉</div></div>
          <div style="background:var(--orange-l);border-radius:7px;padding:4px 8px;text-align:center"><div style="font-size:15px;font-weight:700;color:#92400E;line-height:1">16</div><div style="font-size:9px;color:#B45309;font-weight:600">APR</div></div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;padding:12px 16px">
          <div class="av" style="background:var(--blue-l);color:#1E40AF;width:38px;height:38px;font-size:12px">SP</div>
          <div style="flex:1"><div style="font-size:13px;font-weight:600">Sunita Paul</div><div style="font-size:11px;color:var(--muted)">Work anniversary · 3 yrs</div></div>
          <div style="background:var(--blue-l);border-radius:7px;padding:4px 8px;text-align:center"><div style="font-size:15px;font-weight:700;color:#1E40AF;line-height:1">20</div><div style="font-size:9px;color:#2563EB;font-weight:600">APR</div></div>
        </div>
        <div style="padding:10px 16px"><button style="width:100%;padding:7px;border:1px dashed var(--border);border-radius:8px;font-size:12px;color:var(--muted);cursor:pointer;background:none">Send a cheer 👏</button></div>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h2>Pending Approvals</h2><span class="badge" style="background:var(--red-l);color:var(--red)">3</span></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
        <div class="approval-row"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><div style="flex:1"><div style="font-size:12px;font-weight:600">Leave Request</div><div style="font-size:11px;color:var(--muted)">Amit Roy · 2 days</div></div><button class="btn-sm btn-green" onclick="approve(this)">Approve</button></div>
        <div class="approval-row"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><div style="flex:1"><div style="font-size:12px;font-weight:600">Overtime Request</div><div style="font-size:11px;color:var(--muted)">Priya Sen · 4 hrs</div></div><button class="btn-sm btn-green" onclick="approve(this)">Approve</button></div>
        <div class="approval-row"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--orange)" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><div style="flex:1"><div style="font-size:12px;font-weight:600">Salary Revision</div><div style="font-size:11px;color:var(--muted)">Mohan Das · +8%</div></div><button class="btn-sm btn-green" onclick="approve(this)">Approve</button></div>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h2>CTC Template</h2><span class="badge" style="background:var(--green-l);color:#065F46">Default</span></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#F9FAFB;border-radius:8px"><span style="font-size:12px;font-weight:500">Profession Tax (PT)</span><span class="badge" style="background:var(--green-l);color:#065F46">WB ✓</span></div>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#F9FAFB;border-radius:8px"><span style="font-size:12px;font-weight:500">Provident Fund (PF)</span><span class="badge" style="background:var(--green-l);color:#065F46">Applied ✓</span></div>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#F9FAFB;border-radius:8px"><span style="font-size:12px;font-weight:500">ESI</span><span class="badge" style="background:var(--green-l);color:#065F46">Applied ✓</span></div>
        <button onclick="nav('config')" style="margin-top:4px;padding:8px;border:1px solid var(--border);border-radius:8px;font-size:12px;cursor:pointer;background:#fff;font-weight:500">Edit CTC Template</button>
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom:16px">
    <div class="card-header"><h2>Employee Probations</h2><button style="font-size:12px;color:var(--purple);background:none;border:none;cursor:pointer;font-weight:500">View All</button></div>
    <div class="card-body" style="display:flex;align-items:center;gap:10px;color:var(--muted)">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <p style="font-size:13px">No employees whose probation ends within the next 30 days.</p>
    </div>
  </div>
  </div>`;

  // Render dashboard payroll table
  dashPayrollTab('all');
  // Dept list
  const depts=[{name:'Medical',count:18,color:'#6D28D9'},{name:'Nursing',count:22,color:'#059669'},{name:'Administration',count:10,color:'#1D4ED8'},{name:'Lab Tech',count:8,color:'#EA580C'},{name:'Accounts',count:7,color:'#D97706'}];
  const dl=document.getElementById('deptList');
  dl.innerHTML=depts.map(d=>`<div class="lb-row"><div style="display:flex;justify-content:space-between;margin-bottom:3px"><span style="font-size:12px;font-weight:500">${d.name}</span><span style="font-size:12px;font-weight:700">${d.count}</span></div><div class="pb"><div class="pf" style="width:${Math.round(d.count/65*100)}%;background:${d.color}"></div></div></div>`).join('');

  // Charts
  setTimeout(()=>{
    const pc=document.getElementById('payrollChart');
    if(pc) charts.payroll=new Chart(pc,{type:'line',data:{labels:['Nov','Dec','Jan','Feb','Mar','Apr'],datasets:[{label:'₹L',data:[9.2,9.5,9.8,9.1,9.0,8.4],borderColor:'#6D28D9',backgroundColor:'rgba(109,40,217,.08)',fill:true,tension:.4,pointBackgroundColor:'#6D28D9',pointRadius:4,borderWidth:2},{label:'PF',data:[1.1,1.1,1.2,1.1,1.1,1.0],borderColor:'#1D4ED8',backgroundColor:'transparent',fill:false,tension:.4,pointRadius:3,borderWidth:1.5,borderDash:[4,2]}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>`₹${ctx.raw}L`}}},scales:{y:{beginAtZero:false,min:0,max:12,ticks:{callback:v=>`₹${v}L`,font:{size:11},color:'#9CA3AF'},grid:{color:'rgba(0,0,0,.03)'},border:{display:false}},x:{ticks:{font:{size:11},color:'#9CA3AF'},grid:{display:false},border:{display:false}}}}});
    const ad=document.getElementById('attDonut');
    if(ad) charts.att=new Chart(ad,{type:'doughnut',data:{labels:['At Work','On Leave','Absent'],datasets:[{data:[49,1,15],backgroundColor:['#059669','#F59E0B','#EF4444'],borderWidth:0,hoverOffset:4}]},options:{responsive:false,cutout:'72%',plugins:{legend:{display:false}}}});
  },50);
}

window.dashPayrollTab=function(f){
  ['all','processed','pending'].forEach(t=>{
    const b=document.getElementById('dbt-'+t);
    if(b)b.classList.toggle('active',t===f);
  });
  const rows=f==='all'?PAYROLL_ROWS:PAYROLL_ROWS.filter(r=>r.status===f);
  const b=document.getElementById('dashPayrollBody');
  if(!b)return;
  b.innerHTML=rows.map(r=>`<tr><td><div style="display:flex;align-items:center;gap:9px"><div class="av" style="background:var(--purple-l);color:var(--purple);width:30px;height:30px;font-size:10px">${r.name.split(' ').map(n=>n[0]).join('').slice(0,2)}</div><span style="font-weight:500">${r.name}</span></div></td><td style="color:var(--muted)">${r.dept}</td><td style="text-align:right;font-weight:500">${r.gross}</td><td style="text-align:right;color:var(--red)">${r.ded}</td><td style="text-align:right;font-weight:600;color:var(--green)">${r.net}</td><td style="text-align:center"><span class="badge" style="background:${r.status==='processed'?'var(--green-l)':'var(--orange-l)'};color:${r.status==='processed'?'#065F46':'#92400E'}">${r.status==='processed'?'✓ Processed':'⏳ Pending'}</span></td></tr>`).join('');
};

window.approve=function(btn){
  btn.closest('.approval-row').style.opacity='.4';
  btn.textContent='✓ Done';
  btn.disabled=true;
  showToast('Approval processed!');
};

/* ══════════════════════════════════════
   EMPLOYEES PAGE
══════════════════════════════════════ */
function renderEmployees(c){
  const colors=['#6D28D9','#059669','#1D4ED8','#D97706','#DC2626','#0891B2'];
  c.innerHTML=`<div class="page">
  <div class="ph">
    <div><h1>Employee List</h1><p>65 active employees across all departments</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <div class="search-wrap"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" id="empSearch" placeholder="Search employees..." oninput="filterEmps()" style=""></div>
      <select onchange="filterEmps()" id="empDeptFilter" style="padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;outline:none;font-family:'DM Sans',sans-serif"><option value="">All Depts</option><option>Medical</option><option>Nursing</option><option>Administration</option><option>Lab Tech</option><option>Accounts</option><option>Reception</option></select>
      <button onclick="openModal('addEmployeeModal')" class="btn-sm btn-yellow">+ Add Employee</button>
    </div>
  </div>
  <div class="emp-grid" id="empGrid"></div>
  </div>`;
  renderEmpGrid(EMPLOYEES);
}

function renderEmpGrid(emps){
  const g=document.getElementById('empGrid');
  if(!g)return;
  const colors=['#6D28D9','#059669','#1D4ED8','#D97706','#DC2626','#0891B2','#7C2D12','#065F46'];
  const deptColors={'Medical':'#6D28D9','Nursing':'#059669','Administration':'#1D4ED8','Lab Tech':'#D97706','Accounts':'#DC2626','Reception':'#0891B2'};
  g.innerHTML=emps.map((e,i)=>{
    const col=deptColors[e.dept]||colors[i%colors.length];
    const initials=e.name.split(' ').map(n=>n[0]).join('').slice(0,2);
    const yrs=Math.floor((new Date()-new Date(e.join))/(365.25*24*3600*1000));
    return `<div class="emp-card" onclick="showEmpDetail(${e.id})">
      <div class="av" style="background:${col}22;color:${col};width:52px;height:52px;font-size:16px;margin:0 auto 12px">${initials}</div>
      <div style="font-weight:600;font-size:14px;margin-bottom:2px">${e.name}</div>
      <div style="font-size:12px;color:var(--muted);margin-bottom:8px">${e.desig}</div>
      <span class="badge" style="background:${col}18;color:${col}">${e.dept}</span>
      <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);display:flex;justify-content:space-between;font-size:11px;color:var(--muted)">
        <span>${yrs} yr${yrs!==1?'s':''}</span>
        <span style="font-weight:600;color:var(--text)">₹${(e.gross/1000).toFixed(1)}k</span>
        <span class="badge" style="background:var(--green-l);color:#065F46;font-size:10px">${e.status}</span>
      </div>
    </div>`;
  }).join('');
}

window.filterEmps=function(){
  const q=(document.getElementById('empSearch')||{}).value||'';
  const d=(document.getElementById('empDeptFilter')||{}).value||'';
  const filtered=EMPLOYEES.filter(e=>{
    const mq=!q||e.name.toLowerCase().includes(q.toLowerCase())||e.desig.toLowerCase().includes(q.toLowerCase());
    const md=!d||e.dept===d;
    return mq&&md;
  });
  renderEmpGrid(filtered);
};

window.showEmpDetail=function(id){
  const e=EMPLOYEES.find(x=>x.id===id);
  if(!e)return;
  showToast(`Viewing: ${e.name}`);
};

/* ══════════════════════════════════════
   APPROVALS PAGE
══════════════════════════════════════ */
function renderApprovals(c){
  const items=[
    {type:'Leave Request',icon:'📅',person:'Amit Roy',dept:'Lab Tech',detail:'Annual Leave · 16–17 Apr 2026',days:'2 days',priority:'high'},
    {type:'Overtime Request',icon:'⏱',person:'Priya Sen',dept:'Administration',detail:'Overtime · 14 Apr 2026',days:'4 hrs',priority:'medium'},
    {type:'Salary Revision',icon:'💰',person:'Mohan Das',dept:'Accounts',detail:'Increment Request · +8%',days:'+₹3,360/mo',priority:'high'},
    {type:'Leave Request',icon:'📅',person:'Rina Chatterjee',dept:'Nursing',detail:'Sick Leave · 18–19 Apr',days:'2 days',priority:'low'},
    {type:'Expense Claim',icon:'🧾',person:'Dr. Suman Bose',dept:'Medical',detail:'Conference · ₹12,500',days:'₹12,500',priority:'medium'},
  ];
  c.innerHTML=`<div class="page">
  <div class="ph"><div><h1>Approvals</h1><p>Pending items requiring your action</p></div>
  <div style="display:flex;gap:8px"><button class="tab-btn active" id="apr-all" onclick="aprTab('all')">All</button><button class="tab-btn" id="apr-leave" onclick="aprTab('leave')">Leave</button><button class="tab-btn" id="apr-salary" onclick="aprTab('salary')">Salary</button></div>
  </div>
  <div class="card"><div class="card-header"><h2>Pending Approvals</h2><span class="badge" style="background:var(--red-l);color:var(--red)">${items.length} pending</span></div>
  <div style="overflow-x:auto"><table>
    <thead><tr><th>TYPE</th><th>EMPLOYEE</th><th>DEPARTMENT</th><th>DETAIL</th><th>AMOUNT/DAYS</th><th>PRIORITY</th><th style="text-align:center">ACTION</th></tr></thead>
    <tbody>${items.map(it=>{
      const pc=it.priority==='high'?['var(--red-l)','var(--red)']:it.priority==='medium'?['var(--orange-l)','#B45309']:['var(--green-l)','#065F46'];
      return `<tr id="apr-row-${it.person.replace(' ','')}">
        <td><span style="display:flex;align-items:center;gap:6px;font-size:13px">${it.icon} <span style="font-weight:500">${it.type}</span></span></td>
        <td style="font-weight:600">${it.person}</td><td style="color:var(--muted)">${it.dept}</td>
        <td style="color:var(--muted);font-size:12px">${it.detail}</td>
        <td style="font-weight:600">${it.days}</td>
        <td><span class="badge" style="background:${pc[0]};color:${pc[1]}">${it.priority}</span></td>
        <td style="text-align:center">
          <div style="display:flex;gap:6px;justify-content:center">
            <button class="btn-sm btn-green" onclick="aprApprove(this)">✓ Approve</button>
            <button class="btn-sm btn-red" onclick="aprReject(this)">✕ Reject</button>
          </div>
        </td>
      </tr>`;
    }).join('')}</tbody>
  </table></div></div>
  </div>`;
}
window.aprApprove=function(btn){const row=btn.closest('tr');row.style.background='#F0FDF4';row.querySelectorAll('button').forEach(b=>{b.disabled=true;b.style.opacity='.5'});btn.textContent='✓ Approved';showToast('Request approved!')};
window.aprReject=function(btn){const row=btn.closest('tr');row.style.background='#FFF5F5';row.querySelectorAll('button').forEach(b=>{b.disabled=true;b.style.opacity='.5'});btn.textContent='✕ Rejected';showToast('Request rejected.')};
window.aprTab=function(t){['all','leave','salary'].forEach(x=>{const b=document.getElementById('apr-'+x);if(b)b.classList.toggle('active',x===t)})};

/* ══════════════════════════════════════
   ATTENDANCE PAGE
══════════════════════════════════════ */
function renderAttendance(c){
  c.innerHTML=`<div class="page">
  <div class="ph"><div><h1>Attendance</h1><p>Today · Thursday, 16 April 2026</p></div>
  <div style="display:flex;gap:8px;align-items:center">
    <input type="date" value="2026-04-16" style="padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;outline:none;font-family:'DM Sans',sans-serif">
    <button onclick="showToast('Attendance exported!')" class="btn-sm" style="background:var(--card);border:1px solid var(--border)">Export CSV</button>
  </div></div>

  <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
    <div class="sc" style="border-left:3px solid var(--green)"><p class="sc-label">PRESENT</p><p class="sc-val">49</p><p class="sc-sub" style="color:var(--green)">75.4%</p></div>
    <div class="sc" style="border-left:3px solid var(--red)"><p class="sc-label">ABSENT</p><p class="sc-val">15</p><p class="sc-sub" style="color:var(--red)">23.1%</p></div>
    <div class="sc" style="border-left:3px solid #F59E0B"><p class="sc-label">ON LEAVE</p><p class="sc-val">1</p><p class="sc-sub" style="color:var(--muted)">1.5%</p></div>
    <div class="sc" style="border-left:3px solid var(--blue)"><p class="sc-label">LATE ARRIVALS</p><p class="sc-val">4</p><p class="sc-sub" style="color:var(--muted)">After 9:30 AM</p></div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
    <div class="card"><div class="card-header"><h2>Weekly Trend</h2></div><div class="card-body"><canvas id="attWeek" height="150"></canvas></div></div>
    <div class="card"><div class="card-header"><h2>Dept. Attendance Today</h2></div><div class="card-body"><canvas id="attDept" height="150"></canvas></div></div>
  </div>

  <div class="card">
    <div class="card-header"><h2>Today's Log</h2>
    <div class="search-wrap"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" placeholder="Search..."></div>
    </div>
    <table><thead><tr><th>EMPLOYEE</th><th>DEPARTMENT</th><th>CHECK-IN</th><th>CHECK-OUT</th><th style="text-align:center">STATUS</th><th style="text-align:center">ACTION</th></tr></thead>
    <tbody>${attendanceRecords.map(r=>{
      const sc=r.status==='Present'?'var(--green-l),#065F46':r.status==='Absent'?'var(--red-l),var(--red)':'var(--orange-l),#92400E';
      const [bg,col]=sc.split(',');
      return `<tr><td style="font-weight:500">${r.name}</td><td style="color:var(--muted)">${r.dept}</td><td style="font-family:'Space Mono',monospace;font-size:12px">${r.in}</td><td style="font-family:'Space Mono',monospace;font-size:12px">${r.out}</td><td style="text-align:center"><span class="badge" style="background:${bg};color:${col}">${r.status}</span></td><td style="text-align:center"><button class="btn-sm" style="background:var(--blue-l);color:var(--blue);font-size:11px" onclick="showToast('Marked!')">Mark</button></td></tr>`;
    }).join('')}</tbody></table>
  </div>
  </div>`;

  setTimeout(()=>{
    const aw=document.getElementById('attWeek');
    if(aw) charts.attWeek=new Chart(aw,{type:'bar',data:{labels:['Mon','Tue','Wed','Thu','Fri','Sat'],datasets:[{label:'Present',data:[52,50,48,49,0,0],backgroundColor:'#059669'},{label:'Absent',data:[13,15,17,16,0,0],backgroundColor:'#EF4444'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{x:{stacked:true,grid:{display:false}},y:{stacked:true,grid:{color:'rgba(0,0,0,.03)'}}}}});
    const ad=document.getElementById('attDept');
    if(ad) charts.attDept=new Chart(ad,{type:'doughnut',data:{labels:['Medical','Nursing','Admin','Lab','Accounts'],datasets:[{data:[18,22,10,8,7],backgroundColor:['#6D28D9','#059669','#1D4ED8','#D97706','#DC2626'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'60%',plugins:{legend:{position:'right',labels:{font:{size:11}}}}}});
  },50);
}

/* ══════════════════════════════════════
   LEAVE PAGE
══════════════════════════════════════ */
function renderLeave(c){
  const leaves=[
    {emp:'Mohan Das',type:'Casual Leave',from:'16 Apr',to:'17 Apr',days:2,status:'Approved',bal:8},
    {emp:'Rina Chatterjee',type:'Sick Leave',from:'18 Apr',to:'19 Apr',days:2,status:'Pending',bal:10},
    {emp:'Kavya Nair',type:'Earned Leave',from:'25 Apr',to:'30 Apr',days:6,status:'Pending',bal:14},
    {emp:'Arjun Mehta',type:'Casual Leave',from:'2 May',to:'2 May',days:1,status:'Approved',bal:9},
  ];
  const holidays=[
    {date:'1 May',day:'Friday',name:'May Day',type:'National'},
    {date:'14 Apr',day:'Tuesday',name:'Dr. Ambedkar Jayanti',type:'National'},
    {date:'15 Aug',day:'Saturday',name:'Independence Day',type:'National'},
    {date:'2 Oct',day:'Friday',name:'Gandhi Jayanti',type:'National'},
    {date:'2 Nov',day:'Monday',name:'Diwali',type:'Festival'},
    {date:'25 Dec',day:'Friday',name:'Christmas Day',type:'National'},
  ];
  c.innerHTML=`<div class="page">
  <div class="ph"><div><h1>Leave Management</h1><p>Track, apply, and manage employee leaves</p></div>
  <button onclick="openLeaveModal()" class="btn-sm btn-yellow">+ Apply Leave</button></div>

  <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
    <div class="sc"><p class="sc-label">LEAVE REQUESTS</p><p class="sc-val">4</p><p class="sc-sub" style="color:var(--muted)">This month</p></div>
    <div class="sc"><p class="sc-label">PENDING</p><p class="sc-val">2</p><p class="sc-sub" style="color:var(--orange)">Awaiting</p></div>
    <div class="sc"><p class="sc-label">APPROVED</p><p class="sc-val">2</p><p class="sc-sub" style="color:var(--green)">This month</p></div>
    <div class="sc"><p class="sc-label">HOLIDAYS LEFT</p><p class="sc-val">6</p><p class="sc-sub" style="color:var(--muted)">In 2026</p></div>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px">
    <div>
      <div class="card" style="margin-bottom:16px">
        <div class="card-header"><h2>Leave Requests</h2></div>
        <table><thead><tr><th>EMPLOYEE</th><th>TYPE</th><th>FROM</th><th>TO</th><th>DAYS</th><th>BALANCE</th><th style="text-align:center">STATUS</th></tr></thead>
        <tbody>${leaves.map(l=>{
          const [bg,col]=l.status==='Approved'?['var(--green-l)','#065F46']:['var(--orange-l)','#92400E'];
          return `<tr><td style="font-weight:500">${l.emp}</td><td style="color:var(--muted)">${l.type}</td><td>${l.from}</td><td>${l.to}</td><td style="font-weight:600">${l.days}</td><td><span style="font-size:12px;font-weight:600;color:var(--blue)">${l.bal} days</span></td><td style="text-align:center"><span class="badge" style="background:${bg};color:${col}">${l.status}</span></td></tr>`;
        }).join('')}</tbody></table>
      </div>
      <div class="card">
        <div class="card-header"><h2>Leave Balance — Sample Employees</h2></div>
        <div class="card-body">
          ${[{n:'Dr. Anjali Sharma',cl:6,sl:8,el:12},{n:'Rajib Das',cl:10,sl:10,el:18},{n:'Priya Sen',cl:4,sl:8,el:10}].map(e=>`
          <div style="margin-bottom:14px">
            <div style="font-size:13px;font-weight:600;margin-bottom:6px">${e.n}</div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
              <div style="flex:1"><div style="font-size:10px;color:var(--muted);font-weight:600;margin-bottom:3px">CASUAL (${e.cl}/12)</div><div class="pb"><div class="pf" style="width:${Math.round(e.cl/12*100)}%;background:var(--blue)"></div></div></div>
              <div style="flex:1"><div style="font-size:10px;color:var(--muted);font-weight:600;margin-bottom:3px">SICK (${e.sl}/12)</div><div class="pb"><div class="pf" style="width:${Math.round(e.sl/12*100)}%;background:var(--orange)"></div></div></div>
              <div style="flex:1"><div style="font-size:10px;color:var(--muted);font-weight:600;margin-bottom:3px">EARNED (${e.el}/21)</div><div class="pb"><div class="pf" style="width:${Math.round(e.el/21*100)}%;background:var(--green)"></div></div></div>
            </div>
          </div>`).join('')}
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h2>Holiday Calendar 2026</h2></div>
      <div class="card-body">${holidays.map(h=>`<div class="hpill" style="margin-bottom:7px"><div class="hdate" style="min-width:44px;padding:4px 8px"><div style="font-size:13px;font-weight:700;line-height:1">${h.date.split(' ')[0]}</div><div style="font-size:9px;opacity:.7">${h.date.split(' ')[1]}</div></div><div><div style="font-size:12px;font-weight:600;color:#4C1D95">${h.name}</div><div style="font-size:11px;color:var(--purple)">${h.day} · ${h.type}</div></div></div>`).join('')}</div>
    </div>
  </div>
  </div>`;
}
window.openLeaveModal=function(){showToast('Leave application form opened!')};

/* ══════════════════════════════════════
   PAYROLL PAGE
══════════════════════════════════════ */
function renderPayroll(c){
  const months=['April 2026','March 2026','February 2026','January 2026'];
  c.innerHTML=`<div class="page">
  <div class="ph"><div><h1>Payroll</h1><p>Manage and process monthly payroll</p></div>
  <div style="display:flex;gap:8px;align-items:center">
    <select id="payMonth" onchange="filterPayroll()" style="padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:'DM Sans',sans-serif;outline:none">${months.map(m=>`<option>${m}</option>`).join('')}</select>
    <button onclick="runPayroll()" class="btn-sm btn-yellow">▶ Run Payroll</button>
    <button onclick="showToast('Payslips sent!')" class="btn-sm" style="background:var(--card);border:1px solid var(--border)">Send Payslips</button>
  </div></div>

  <div class="stats-grid" style="margin-bottom:16px">
    <div class="sc"><p class="sc-label">GROSS PAYROLL</p><p class="sc-val" style="font-size:22px">₹8.42L</p><p class="sc-sub" style="color:var(--muted)">Apr 2026</p></div>
    <div class="sc"><p class="sc-label">PF EMPLOYER</p><p class="sc-val" style="font-size:22px">₹1.01L</p><p class="sc-sub" style="color:var(--purple)">12% of basic</p></div>
    <div class="sc"><p class="sc-label">ESI EMPLOYER</p><p class="sc-val" style="font-size:22px">₹27.4k</p><p class="sc-sub" style="color:var(--blue)">3.25%</p></div>
    <div class="sc"><p class="sc-label">NET PAYABLE</p><p class="sc-val" style="font-size:22px">₹7.23L</p><p class="sc-sub" style="color:var(--green)">After deductions</p></div>
    <div class="sc"><p class="sc-label">PT DEDUCTED</p><p class="sc-val" style="font-size:22px">₹9,750</p><p class="sc-sub" style="color:var(--muted)">West Bengal</p></div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>Payroll Register — April 2026</h2>
      <div style="display:flex;gap:6px">
        <button class="tab-btn active" id="pt-all" onclick="payTab('all')">All</button>
        <button class="tab-btn" id="pt-processed" onclick="payTab('processed')">Processed</button>
        <button class="tab-btn" id="pt-pending" onclick="payTab('pending')">Pending</button>
      </div>
    </div>
    <div style="overflow-x:auto"><table>
      <thead><tr><th>EMPLOYEE</th><th>DEPARTMENT</th><th style="text-align:right">BASIC</th><th style="text-align:right">HRA</th><th style="text-align:right">GROSS</th><th style="text-align:right">PF EMP.</th><th style="text-align:right">ESI EMP.</th><th style="text-align:right">PT</th><th style="text-align:right">NET</th><th style="text-align:center">STATUS</th></tr></thead>
      <tbody id="payrollBody2">${buildPayrollRows('all')}</tbody>
    </table></div>
    <div style="padding:10px 20px;border-top:1px solid var(--border);display:flex;justify-content:space-between">
      <span style="font-size:12px;color:var(--muted)">Showing top employees</span>
      <button onclick="showToast('Full register exported!')" style="font-size:12px;color:var(--purple);background:none;border:none;cursor:pointer;font-weight:500">Export All →</button>
    </div>
  </div>
  </div>`;
}

function buildPayrollRows(f){
  const data=[
    {name:'Dr. Anjali Sharma',dept:'Medical',basic:37200,hra:14880,gross:62000,status:'processed'},
    {name:'Rajib Das',dept:'Nursing',basic:23100,hra:9240,gross:38500,status:'processed'},
    {name:'Sunita Paul',dept:'Reception',basic:16800,hra:6720,gross:28000,status:'processed'},
    {name:'Amit Roy',dept:'Lab Tech',basic:19200,hra:7680,gross:32000,status:'pending'},
    {name:'Priya Sen',dept:'Administration',basic:21000,hra:8400,gross:35000,status:'pending'},
    {name:'Mohan Das',dept:'Accounts',basic:25200,hra:10080,gross:42000,status:'processed'},
    {name:'Dr. Suman Bose',dept:'Medical',basic:42600,hra:17040,gross:71000,status:'processed'},
  ];
  const rows=f==='all'?data:data.filter(r=>r.status===f);
  return rows.map(r=>{
    const pf=Math.round(r.basic*.12);
    const esi=r.gross<=21000?Math.round(r.gross*.0325):0;
    const pt=r.gross>20000?200:r.gross>15000?150:r.gross>10000?110:0;
    const net=r.gross-Math.round(r.basic*.12)-Math.round(r.gross*.0075)-pt;
    const [bg,col]=r.status==='processed'?['var(--green-l)','#065F46']:['var(--orange-l)','#92400E'];
    return `<tr><td style="font-weight:500">${r.name}</td><td style="color:var(--muted)">${r.dept}</td><td style="text-align:right">₹${r.basic.toLocaleString()}</td><td style="text-align:right">₹${r.hra.toLocaleString()}</td><td style="text-align:right;font-weight:600">₹${r.gross.toLocaleString()}</td><td style="text-align:right;color:var(--purple)">₹${pf.toLocaleString()}</td><td style="text-align:right;color:var(--blue)">₹${esi||'—'}</td><td style="text-align:right;color:var(--muted)">₹${pt||'—'}</td><td style="text-align:right;font-weight:700;color:var(--green)">₹${net.toLocaleString()}</td><td style="text-align:center"><span class="badge" style="background:${bg};color:${col}">${r.status==='processed'?'✓ Done':'⏳ Pending'}</span></td></tr>`;
  }).join('');
}

window.payTab=function(f){
  ['all','processed','pending'].forEach(t=>{const b=document.getElementById('pt-'+t);if(b)b.classList.toggle('active',t===f)});
  const b=document.getElementById('payrollBody2');
  if(b)b.innerHTML=buildPayrollRows(f);
};
window.filterPayroll=function(){showToast('Showing payroll for selected month')};

/* ══════════════════════════════════════
   TAXES PAGE
══════════════════════════════════════ */
function renderTaxes(c){
  c.innerHTML=`<div class="page">
  <div class="ph"><div><h1>Taxes</h1><p>Statutory compliance — PF, ESI, PT, TDS</p></div>
  <button onclick="showToast('Tax report generated!')" class="btn-sm btn-yellow">Generate Tax Report</button></div>

  <div class="stats-grid" style="margin-bottom:16px">
    <div class="sc" style="border-left:3px solid var(--purple)"><p class="sc-label">PF EMPLOYEE</p><p class="sc-val" style="font-size:20px">₹1.01L</p><p class="sc-sub" style="color:var(--muted)">Apr 2026</p></div>
    <div class="sc" style="border-left:3px solid var(--purple)"><p class="sc-label">PF EMPLOYER</p><p class="sc-val" style="font-size:20px">₹1.01L</p><p class="sc-sub" style="color:var(--muted)">Apr 2026</p></div>
    <div class="sc" style="border-left:3px solid var(--blue)"><p class="sc-label">ESI EMPLOYEE</p><p class="sc-val" style="font-size:20px">₹6,320</p><p class="sc-sub" style="color:var(--muted)">0.75%</p></div>
    <div class="sc" style="border-left:3px solid var(--blue)"><p class="sc-label">ESI EMPLOYER</p><p class="sc-val" style="font-size:20px">₹27,390</p><p class="sc-sub" style="color:var(--muted)">3.25%</p></div>
    <div class="sc" style="border-left:3px solid var(--orange)"><p class="sc-label">PT (WB)</p><p class="sc-val" style="font-size:20px">₹9,750</p><p class="sc-sub" style="color:var(--muted)">Apr 2026</p></div>
    <div class="sc" style="border-left:3px solid var(--green)"><p class="sc-label">TDS DEDUCTED</p><p class="sc-val" style="font-size:20px">₹24,500</p><p class="sc-sub" style="color:var(--muted)">FY 2025-26</p></div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
    <div class="card">
      <div class="card-header"><h2>PF Challan Summary</h2><span class="badge" style="background:var(--green-l);color:#065F46">Filed ✓</span></div>
      <div class="card-body">
        <table><thead><tr><th>COMPONENT</th><th>RATE</th><th style="text-align:right">AMOUNT (APR)</th></tr></thead>
        <tbody>
          <tr><td>Employee PF (EE)</td><td style="color:var(--muted)">12% of Basic</td><td style="text-align:right;font-weight:600">₹1,01,160</td></tr>
          <tr><td>Employer PF (ER)</td><td style="color:var(--muted)">3.67% of Basic</td><td style="text-align:right;font-weight:600">₹30,926</td></tr>
          <tr><td>EPS (Pension)</td><td style="color:var(--muted)">8.33% of Basic</td><td style="text-align:right;font-weight:600">₹70,112</td></tr>
          <tr><td>EDLI Admin</td><td style="color:var(--muted)">0.5%</td><td style="text-align:right;font-weight:600">₹4,215</td></tr>
          <tr><td style="font-weight:700">Total PF Outflow</td><td></td><td style="text-align:right;font-weight:700;color:var(--purple)">₹2,06,413</td></tr>
        </tbody></table>
        <button onclick="showToast('PF challan downloaded!')" style="margin-top:12px;width:100%;padding:8px;border:1px solid var(--border);border-radius:8px;font-size:12px;cursor:pointer;background:#fff">📥 Download ECR File</button>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h2>ESI Challan Summary</h2><span class="badge" style="background:var(--green-l);color:#065F46">Filed ✓</span></div>
      <div class="card-body">
        <table><thead><tr><th>COMPONENT</th><th>RATE</th><th style="text-align:right">AMOUNT (APR)</th></tr></thead>
        <tbody>
          <tr><td>Employee ESI</td><td style="color:var(--muted)">0.75%</td><td style="text-align:right;font-weight:600">₹6,319</td></tr>
          <tr><td>Employer ESI</td><td style="color:var(--muted)">3.25%</td><td style="text-align:right;font-weight:600">₹27,382</td></tr>
          <tr><td>ESI Eligible Employees</td><td style="color:var(--muted)">&lt; ₹21,000/mo</td><td style="text-align:right;font-weight:600">28 employees</td></tr>
          <tr><td style="font-weight:700">Total ESI Outflow</td><td></td><td style="text-align:right;font-weight:700;color:var(--blue)">₹33,701</td></tr>
        </tbody></table>
        <button onclick="showToast('ESI challan downloaded!')" style="margin-top:12px;width:100%;padding:8px;border:1px solid var(--border);border-radius:8px;font-size:12px;cursor:pointer;background:#fff">📥 Download ESI File</button>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2>Profession Tax (WB) — Slab Rates</h2></div>
    <div class="card-body">
      <table><thead><tr><th>SALARY RANGE</th><th>PT AMOUNT / MONTH</th><th>EMPLOYEES</th></tr></thead>
      <tbody>
        <tr><td>Up to ₹10,000</td><td>Nil</td><td>0</td></tr>
        <tr><td>₹10,001 – ₹15,000</td><td style="font-weight:600">₹110</td><td>8</td></tr>
        <tr><td>₹15,001 – ₹20,000</td><td style="font-weight:600">₹150</td><td>19</td></tr>
        <tr><td>₹20,001 and above</td><td style="font-weight:600">₹200</td><td>38</td></tr>
        <tr style="background:#F9FAFB"><td style="font-weight:700">Total PT Collected</td><td style="font-weight:700;color:var(--orange)">₹9,750</td><td style="font-weight:700">65</td></tr>
      </tbody></table>
    </div>
  </div>
  </div>`;
}

/* ══════════════════════════════════════
   REPORTS PAGE
══════════════════════════════════════ */
function renderReports(c){
  const reps=[
    {icon:'💰',title:'Payroll Summary',desc:'Monthly payroll register with all deductions',color:'#6D28D9',bg:'var(--purple-l)'},
    {icon:'📋',title:'Attendance Report',desc:'Daily, weekly, and monthly attendance logs',color:'#059669',bg:'var(--green-l)'},
    {icon:'🏖',title:'Leave Report',desc:'Leave balances, applications, and approvals',color:'#1D4ED8',bg:'var(--blue-l)'},
    {icon:'📊',title:'Tax Compliance',desc:'PF, ESI, PT, TDS challan reports',color:'#D97706',bg:'var(--orange-l)'},
    {icon:'👥',title:'Headcount Report',desc:'Department-wise employee distribution',color:'#DC2626',bg:'var(--red-l)'},
    {icon:'💼',title:'CTC Report',desc:'Employee cost-to-company breakdown',color:'#0891B2',bg:'#E0F2FE'},
    {icon:'⏱',title:'Overtime Report',desc:'Employee overtime hours and payments',color:'#7C2D12',bg:'#FFF7ED'},
    {icon:'📈',title:'Salary Trend',desc:'Month-over-month salary cost analysis',color:'#065F46',bg:'#ECFDF5'},
  ];
  c.innerHTML=`<div class="page">
  <div class="ph"><div><h1>Reports</h1><p>Generate and download HR & Payroll reports</p></div>
  <div style="display:flex;gap:8px;align-items:center">
    <select style="padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:'DM Sans',sans-serif;outline:none"><option>April 2026</option><option>March 2026</option><option>FY 2025-26</option></select>
  </div></div>

  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;margin-bottom:24px">
    ${reps.map(r=>`<div class="report-card" onclick="genReport('${r.title}')">
      <div class="rc-icon" style="background:${r.bg}"><span style="font-size:20px">${r.icon}</span></div>
      <div><div style="font-size:14px;font-weight:600;margin-bottom:4px;color:${r.color}">${r.title}</div><div style="font-size:12px;color:var(--muted);line-height:1.4">${r.desc}</div></div>
    </div>`).join('')}
  </div>

  <div class="card">
    <div class="card-header"><h2>Payroll Cost — 6 Month Overview</h2></div>
    <div class="card-body"><canvas id="reportChart" height="200"></canvas></div>
  </div>
  </div>`;

  setTimeout(()=>{
    const rc=document.getElementById('reportChart');
    if(rc) charts.report=new Chart(rc,{type:'bar',data:{labels:['Nov','Dec','Jan','Feb','Mar','Apr'],datasets:[{label:'Gross Payroll (₹L)',data:[9.2,9.5,9.8,9.1,9.0,8.4],backgroundColor:'rgba(109,40,217,.7)',borderRadius:6},{label:'Net Payroll (₹L)',data:[7.8,8.1,8.4,7.7,7.6,7.2],backgroundColor:'rgba(5,150,105,.7)',borderRadius:6}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:false,min:6,ticks:{callback:v=>`₹${v}L`},grid:{color:'rgba(0,0,0,.03)'}},x:{grid:{display:false}}}}});
  },50);
}
window.genReport=function(t){showToast(`${t} report generated!`)};

/* ══════════════════════════════════════
   DATA IMPORT PAGE
══════════════════════════════════════ */
function renderImport(c){
  c.innerHTML=`<div class="page">
  <div class="ph"><div><h1>Data Import</h1><p>Bulk upload employee, payroll, and attendance data</p></div></div>

  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-bottom:24px">
    ${[
      {title:'Employee Data',desc:'Import new employees via Excel / CSV template',icon:'👥',color:'var(--purple)'},
      {title:'Attendance Logs',desc:'Upload biometric or manual attendance records',icon:'⏱',color:'var(--green)'},
      {title:'Salary Revisions',desc:'Bulk update employee salaries',icon:'💰',color:'var(--blue)'},
      {title:'Leave Balances',desc:'Set opening leave balances for employees',icon:'🏖',color:'var(--orange)'},
    ].map(item=>`<div class="card" style="padding:20px">
      <div style="font-size:24px;margin-bottom:10px">${item.icon}</div>
      <div style="font-size:14px;font-weight:600;color:${item.color};margin-bottom:4px">${item.title}</div>
      <div style="font-size:12px;color:var(--muted);margin-bottom:14px;line-height:1.5">${item.desc}</div>
      <div style="border:2px dashed var(--border);border-radius:8px;padding:20px;text-align:center;cursor:pointer;transition:.15s" onmouseenter="this.style.borderColor='${item.color}'" onmouseleave="this.style.borderColor='var(--border)'" onclick="showToast('File uploaded successfully!')">
        <div style="font-size:22px;margin-bottom:6px">📁</div>
        <div style="font-size:12px;color:var(--muted)">Drop file here or <span style="color:${item.color};font-weight:600">browse</span></div>
        <div style="font-size:10px;color:#9CA3AF;margin-top:4px">.xlsx, .csv supported</div>
      </div>
      <button onclick="showToast('Template downloaded!')" style="margin-top:10px;width:100%;padding:7px;border:1px solid var(--border);border-radius:7px;font-size:12px;cursor:pointer;background:#fff">📥 Download Template</button>
    </div>`).join('')}
  </div>

  <div class="card">
    <div class="card-header"><h2>Import History</h2></div>
    <table><thead><tr><th>FILE NAME</th><th>TYPE</th><th>RECORDS</th><th>IMPORTED BY</th><th>DATE</th><th style="text-align:center">STATUS</th></tr></thead>
    <tbody>
      <tr><td style="font-family:'Space Mono',monospace;font-size:12px">employees_apr2026.xlsx</td><td>Employee Data</td><td>3</td><td>Admin</td><td>1 Apr 2026</td><td style="text-align:center"><span class="badge" style="background:var(--green-l);color:#065F46">✓ Success</span></td></tr>
      <tr><td style="font-family:'Space Mono',monospace;font-size:12px">attendance_mar2026.csv</td><td>Attendance</td><td>1,860</td><td>Admin</td><td>1 Apr 2026</td><td style="text-align:center"><span class="badge" style="background:var(--green-l);color:#065F46">✓ Success</span></td></tr>
      <tr><td style="font-family:'Space Mono',monospace;font-size:12px">salary_rev_mar.xlsx</td><td>Salary Revision</td><td>12</td><td>Admin</td><td>25 Mar 2026</td><td style="text-align:center"><span class="badge" style="background:var(--green-l);color:#065F46">✓ Success</span></td></tr>
    </tbody></table>
  </div>
  </div>`;
}

/* ══════════════════════════════════════
   USERS PAGE
══════════════════════════════════════ */
function renderUsers(c){
  const users=[
    {name:'Admin',email:'admin@ramkrishnaivf.in',role:'Super Admin',last:'Now',status:'Active'},
    {name:'Dr. Anjali Sharma',email:'anjali@rk.in',role:'HR Manager',last:'15 Apr 2026',status:'Active'},
    {name:'Kavya Nair',email:'kavya@rk.in',role:'Payroll Officer',last:'14 Apr 2026',status:'Active'},
    {name:'Mohan Das',email:'mohan@rk.in',role:'Accounts',last:'12 Apr 2026',status:'Inactive'},
  ];
  const cols=['#6D28D9','#059669','#1D4ED8','#D97706'];
  c.innerHTML=`<div class="page">
  <div class="ph"><div><h1>Users</h1><p>Manage system access and roles</p></div>
  <button onclick="showToast('Invite sent!')" class="btn-sm btn-yellow">+ Invite User</button></div>

  <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px">
    <div class="sc"><p class="sc-label">TOTAL USERS</p><p class="sc-val">4</p></div>
    <div class="sc"><p class="sc-label">ACTIVE</p><p class="sc-val" style="color:var(--green)">3</p></div>
    <div class="sc"><p class="sc-label">INACTIVE</p><p class="sc-val" style="color:var(--red)">1</p></div>
  </div>

  <div class="card">
    <div class="card-header"><h2>System Users</h2></div>
    <table><thead><tr><th>USER</th><th>EMAIL</th><th>ROLE</th><th>LAST ACTIVE</th><th style="text-align:center">STATUS</th><th style="text-align:center">ACTION</th></tr></thead>
    <tbody>${users.map((u,i)=>{
      const col=cols[i];
      const init=u.name.split(' ').map(n=>n[0]).join('').slice(0,2);
      return `<tr>
        <td><div style="display:flex;align-items:center;gap:9px"><div class="av" style="background:${col}22;color:${col};width:32px;height:32px;font-size:11px">${init}</div><span style="font-weight:500">${u.name}</span></div></td>
        <td style="color:var(--muted)">${u.email}</td>
        <td><span class="badge" style="background:${col}18;color:${col}">${u.role}</span></td>
        <td style="color:var(--muted);font-size:12px">${u.last}</td>
        <td style="text-align:center"><span class="badge" style="background:${u.status==='Active'?'var(--green-l)':'#F3F4F6'};color:${u.status==='Active'?'#065F46':'#6B7280'}">${u.status}</span></td>
        <td style="text-align:center"><button class="btn-sm btn-outline" onclick="showToast('Editing ${u.name}')">Edit</button></td>
      </tr>`;
    }).join('')}</tbody></table>
  </div>

  <div class="card" style="margin-top:16px">
    <div class="card-header"><h2>Role Permissions</h2></div>
    <div class="card-body">
      <table><thead><tr><th>FEATURE</th><th style="text-align:center">SUPER ADMIN</th><th style="text-align:center">HR MANAGER</th><th style="text-align:center">PAYROLL OFFICER</th><th style="text-align:center">ACCOUNTS</th></tr></thead>
      <tbody>
        ${[
          ['View Dashboard','✅','✅','✅','✅'],
          ['Manage Employees','✅','✅','❌','❌'],
          ['Process Payroll','✅','✅','✅','❌'],
          ['View Reports','✅','✅','✅','✅'],
          ['Tax Filing','✅','❌','✅','✅'],
          ['User Management','✅','❌','❌','❌'],
          ['System Config','✅','❌','❌','❌'],
        ].map(row=>`<tr><td style="font-weight:500">${row[0]}</td>${row.slice(1).map(v=>`<td style="text-align:center;font-size:16px">${v}</td>`).join('')}</tr>`).join('')}
      </tbody></table>
    </div>
  </div>
  </div>`;
}

/* ══════════════════════════════════════
   CONFIG PAGE
══════════════════════════════════════ */
function renderConfig(c){
  c.innerHTML=`<div class="page">
  <div class="ph"><div><h1>Configuration</h1><p>System settings and CTC template management</p></div>
  <button onclick="showToast('Settings saved!')" class="btn-sm btn-yellow">💾 Save Changes</button></div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div>
      <div class="card" style="margin-bottom:16px">
        <div class="card-header"><h2>Organisation Details</h2></div>
        <div class="card-body">
          <div class="form-group" style="margin-bottom:12px"><label>ORGANISATION NAME</label><input type="text" value="Ramkrishna IVF Centre"></div>
          <div class="form-group" style="margin-bottom:12px"><label>PAN NUMBER</label><input type="text" value="AABCR1234F"></div>
          <div class="form-group" style="margin-bottom:12px"><label>STATE</label><select><option selected>West Bengal</option><option>Delhi</option><option>Maharashtra</option></select></div>
          <div class="form-group"><label>REGISTERED ADDRESS</label><textarea rows="2" style="resize:none">123 Healthcare Road, Siliguri, WB 734001</textarea></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2>CTC Components</h2></div>
        <div class="card-body">
          ${[
            {label:'Basic Salary',val:'60% of Gross',toggle:true},
            {label:'HRA',val:'40% of Basic',toggle:true},
            {label:'Special Allowance',val:'Balance',toggle:true},
            {label:'PF Deduction (Employee)',val:'12% of Basic',toggle:true},
            {label:'PF Contribution (Employer)',val:'12% of Basic',toggle:true},
            {label:'ESI Employee',val:'0.75% (if ≤ ₹21k)',toggle:true},
            {label:'ESI Employer',val:'3.25% (if ≤ ₹21k)',toggle:true},
            {label:'Profession Tax (WB)',val:'As per slab',toggle:true},
          ].map(i=>`<div class="cfg-row"><div><div style="font-size:13px;font-weight:500">${i.label}</div><div style="font-size:11px;color:var(--muted)">${i.val}</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>`).join('')}
        </div>
      </div>
    </div>

    <div>
      <div class="card" style="margin-bottom:16px">
        <div class="card-header"><h2>Leave Policy</h2></div>
        <div class="card-body">
          ${[
            {type:'Casual Leave',days:12,color:'var(--blue)'},
            {type:'Sick Leave',days:12,color:'var(--orange)'},
            {type:'Earned Leave',days:21,color:'var(--green)'},
            {type:'Maternity Leave',days:182,color:'var(--purple)'},
            {type:'Paternity Leave',days:15,color:'#0891B2'},
          ].map(l=>`<div class="cfg-row"><div style="display:flex;align-items:center;gap:10px"><span style="width:10px;height:10px;background:${l.color};border-radius:50%;display:inline-block"></span><span style="font-size:13px;font-weight:500">${l.type}</span></div><div style="display:flex;align-items:center;gap:8px"><input type="number" value="${l.days}" style="width:60px;padding:5px 8px;border:1.5px solid var(--border);border-radius:6px;font-size:13px;text-align:center;outline:none"><span style="font-size:12px;color:var(--muted)">days/yr</span></div></div>`).join('')}
        </div>
      </div>

      <div class="card" style="margin-bottom:16px">
        <div class="card-header"><h2>Payroll Settings</h2></div>
        <div class="card-body">
          ${[
            {label:'Auto-process payroll on 28th',val:''},
            {label:'Send payslip via email',val:''},
            {label:'Lock attendance before payroll',val:''},
            {label:'Allow salary advance',val:''},
          ].map((i,idx)=>`<div class="cfg-row"><span style="font-size:13px;font-weight:500">${i.label}</span><label class="toggle"><input type="checkbox" ${idx<2?'checked':''}><span class="toggle-slider"></span></label></div>`).join('')}
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2>Notification Settings</h2></div>
        <div class="card-body">
          ${[
            {label:'Payroll processed',on:true},
            {label:'Leave approval pending',on:true},
            {label:'Employee birthday',on:true},
            {label:'Work anniversary',on:false},
            {label:'Subscription renewal',on:true},
          ].map(i=>`<div class="cfg-row"><span style="font-size:13px;font-weight:500">${i.label}</span><label class="toggle"><input type="checkbox" ${i.on?'checked':''}><span class="toggle-slider"></span></label></div>`).join('')}
        </div>
      </div>
    </div>
  </div>
  </div>`;
}

/* ──── INIT ──── */
document.addEventListener('keydown',e=>{
  if(e.key==='Enter'&&document.getElementById('loginPage').style.display!=='none') doLogin();
});
