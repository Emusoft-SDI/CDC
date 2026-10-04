<?php
// Admin UI v2 (parallel shell) opt-in for the Registry workspace: close the shared
// chrome and skip the legacy footer. When the flag is OFF (default) the legacy footer
// below renders byte-identically.
if (function_exists('admin_ui_enabled') && admin_ui_enabled('registry')) {
    admin_ui_page_end();
    return;
}
?>
  </div> <!-- .content -->
</div> <!-- .main -->

<div class="toast" id="toast"></div>

<script>
function showToast(msg){
    const t=document.getElementById('toast');
    t.textContent=msg;
    t.style.display='block';
    setTimeout(()=>t.style.display='none',2500);
}

function openModal(id){document.getElementById(id).classList.add('active')}
function closeModal(id){document.getElementById(id).classList.remove('active')}

document.querySelectorAll('.modal-overlay').forEach(o=>{
    o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('active')})
});

const topbarToggle = document.querySelector('[data-topbar-toggle]');
const topbarMenu = document.getElementById('topbarMenu');
if (topbarToggle && topbarMenu) {
    topbarToggle.addEventListener('click', (event) => {
        event.stopPropagation();
        topbarMenu.classList.toggle('active');
    });
    document.addEventListener('click', () => topbarMenu.classList.remove('active'));
}

// Toast notification for server messages
<?php if ($registryNotice): ?>showToast(<?= json_encode($registryNotice) ?>);<?php endif; ?>
<?php if ($registryError): ?>showToast(<?= json_encode($registryError) ?>);<?php endif; ?>
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
