  </div>
</div>

<div class="toast" id="toast">
  <span id="toastMsg">Done!</span>
</div>

<div class="modal-bg" id="addEmployeeModal" style="display:none" onclick="closeModalBg(event,'addEmployeeModal')">
  <div class="modal">
    <div class="modal-header">
      <h3 style="font-size:16px;font-weight:700">Add New Employee</h3>
      <button onclick="closeModal('addEmployeeModal')" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:20px">×</button>
    </div>
    <div class="modal-body">
      <div class="form-row">
        <div class="form-group">
          <label>FIRST NAME</label>
          <input type="text" placeholder="e.g. Ananya">
        </div>
        <div class="form-group">
          <label>LAST NAME</label>
          <input type="text" placeholder="e.g. Ghosh">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>EMAIL</label>
          <input type="email" placeholder="name@example.com">
        </div>
        <div class="form-group">
          <label>PHONE</label>
          <input type="tel" placeholder="+91 98765 43210">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-sm btn-outline" onclick="closeModal('addEmployeeModal')">Cancel</button>
      <button class="btn-sm btn-yellow" onclick="showToast('Employee saved successfully!');closeModal('addEmployeeModal')">Save Employee</button>
    </div>
  </div>
</div>

<div class="modal-bg" id="runPayrollModal" style="display:none" onclick="closeModalBg(event,'runPayrollModal')">
  <div class="modal">
    <div class="modal-header">
      <h3 style="font-size:16px;font-weight:700">Run Payroll — April 2026</h3>
      <button onclick="closeModal('runPayrollModal')" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:20px">×</button>
    </div>
    <div class="modal-body">
      <p style="font-size:13px;color:var(--muted)">
        This will process payroll for all active employees.
      </p>
    </div>
    <div class="modal-footer">
      <button class="btn-sm btn-outline" onclick="closeModal('runPayrollModal')">Cancel</button>
      <button class="btn-sm btn-yellow" onclick="showToast('Payroll processed successfully!');closeModal('runPayrollModal')">Confirm</button>
    </div>
  </div>
</div>

<script src="assets/app.js"></script>
</body>
</html>