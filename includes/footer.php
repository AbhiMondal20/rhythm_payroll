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
</script>
<?php if (!empty($extra_scripts)) echo $extra_scripts; ?>
</body>
</html>