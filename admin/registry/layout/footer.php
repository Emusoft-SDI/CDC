  </div> <!-- .registry-content -->
</div> <!-- .registry-shell -->

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

<?php if (!empty($registryNotice)): ?>showToast(<?= json_encode($registryNotice) ?>);<?php endif; ?>
<?php if (!empty($registryError)): ?>showToast(<?= json_encode($registryError) ?>);<?php endif; ?>
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php admin_page_end(); ?>
