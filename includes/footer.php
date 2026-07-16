
 </main>
</div>
<script>
function toggleSidebar(){
  const sb=document.getElementById('sidebar');
  const ov=document.getElementById('overlay');
  if(window.innerWidth<=1024){sb.classList.toggle('mobile-open');ov.classList.toggle('show');}
  else{sb.classList.toggle('collapsed');document.getElementById('mainContent').classList.toggle('expanded');}
}
function closeSidebar(){document.getElementById('sidebar').classList.remove('mobile-open');document.getElementById('overlay').classList.remove('show');}

// show Alert Toast
function showToast(type, message) {
    const container = document.getElementById('toast-container');

    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerText = message;

    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>
<?php if (!empty($extra_scripts)) echo $extra_scripts; ?>
<div style="text-align: center; padding: 10px; font-size: 12px; color: #999; border-top: 1px solid #ddd; margin-top: 20px;">
    <p style="margin: 5px 0;">&copy; <?php echo date('Y'); ?> Rhythm Payroll. Beta version 1.0.0. All rights reserved.</p>
</div>

</body>
</html>