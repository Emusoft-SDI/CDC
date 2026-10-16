(function(){
  const menuBtn=document.getElementById('menuBtn'), sidebar=document.getElementById('sidebar'), overlay=document.getElementById('overlay');
  if(menuBtn){menuBtn.addEventListener('click',()=>{sidebar.classList.toggle('active');overlay.classList.toggle('active');});}
  if(overlay){overlay.addEventListener('click',()=>{sidebar.classList.remove('active');overlay.classList.remove('active');});}
  document.addEventListener('keydown',e=>{if(e.key==='Escape'){sidebar.classList.remove('active');overlay.classList.remove('active');}});

  // Profile dropdown and generic dropdown toggles
  function initAcademyDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown, .tb-user-dropdown');
    dropdowns.forEach(function(dd) {
      const toggle = dd.querySelector('#profileDropdownBtn, #landingProfileDropdownBtn, .tb-user, .tb-user-toggle, [data-dropdown-toggle], button') || dd;
      const menu = dd.querySelector('.dropdown-menu');
      if (!toggle || !menu) return;

      if (toggle.dataset.dropdownReady) return;
      toggle.dataset.dropdownReady = 'true';

      function closeDropdown(focusToggle) {
        dd.classList.remove('open', 'active');
        dd.classList.add('closed');
        if (toggle.setAttribute) toggle.setAttribute('aria-expanded', 'false');
        if (focusToggle && typeof toggle.focus === 'function') {
          toggle.focus();
        }
      }

      function openDropdown() {
        // Close any other open dropdowns first
        document.querySelectorAll('.dropdown.open, .tb-user-dropdown.open').forEach(function(other) {
          if (other !== dd) {
            other.classList.remove('open', 'active', 'closed');
            const otherBtn = other.querySelector('#profileDropdownBtn, #landingProfileDropdownBtn, .tb-user, .tb-user-toggle, [data-dropdown-toggle], button') || other;
            if (otherBtn && otherBtn.setAttribute) otherBtn.setAttribute('aria-expanded', 'false');
          }
        });

        dd.classList.remove('closed');
        dd.classList.add('open', 'active');
        if (toggle.setAttribute) toggle.setAttribute('aria-expanded', 'true');
      }

      toggle.addEventListener('click', function(e) {
        if (e.target.closest('.dropdown-menu a')) return;
        e.preventDefault();
        e.stopPropagation();

        const isOpen = dd.classList.contains('open');
        if (isOpen) {
          closeDropdown(false);
        } else {
          openDropdown();
        }
      });

      // Pointer enter: open if device supports hover and not explicitly closed
      dd.addEventListener('mouseenter', function() {
        if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
          if (!dd.classList.contains('closed')) {
            openDropdown();
          }
        }
      });

      // Pointer leave: clear closed suppression and close on fine-pointer devices
      dd.addEventListener('mouseleave', function() {
        dd.classList.remove('closed');
        if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
          closeDropdown(false);
          dd.classList.remove('closed');
        } else if (!dd.classList.contains('open')) {
          if (toggle.setAttribute) toggle.setAttribute('aria-expanded', 'false');
        }
      });

      // Keyboard navigation support (Arrow keys, Home, End, Escape, Tab)
      dd.addEventListener('keydown', function(e) {
        const items = Array.from(menu.querySelectorAll('a.dropdown-item, a[role="menuitem"], a'));
        if (e.key === 'Escape') {
          e.preventDefault();
          e.stopPropagation();
          closeDropdown(true);
          return;
        }

        if (e.key === 'Tab') {
          if (dd.classList.contains('open')) {
            closeDropdown(false);
            dd.classList.remove('closed');
          }
          return;
        }

        if (e.key === 'ArrowDown') {
          e.preventDefault();
          if (!dd.classList.contains('open')) {
            openDropdown();
            if (items.length) items[0].focus();
          } else {
            const activeIdx = items.indexOf(document.activeElement);
            const nextIdx = (activeIdx + 1 < items.length) ? activeIdx + 1 : 0;
            if (items[nextIdx]) items[nextIdx].focus();
          }
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          if (!dd.classList.contains('open')) {
            openDropdown();
            if (items.length) items[items.length - 1].focus();
          } else {
            const activeIdx = items.indexOf(document.activeElement);
            if (activeIdx <= 0) {
              if (typeof toggle.focus === 'function') toggle.focus();
            } else {
              items[activeIdx - 1].focus();
            }
          }
        } else if (e.key === 'Home' && dd.classList.contains('open')) {
          e.preventDefault();
          if (items.length) items[0].focus();
        } else if (e.key === 'End' && dd.classList.contains('open')) {
          e.preventDefault();
          if (items.length) items[items.length - 1].focus();
        }
      });
    });

    // Close on click outside
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.dropdown, .tb-user-dropdown')) {
        document.querySelectorAll('.dropdown.open, .tb-user-dropdown.open, .dropdown.closed, .tb-user-dropdown.closed').forEach(function(dd) {
          dd.classList.remove('open', 'active', 'closed');
          const btn = dd.querySelector('#profileDropdownBtn, #landingProfileDropdownBtn, .tb-user, .tb-user-toggle, [data-dropdown-toggle], button') || dd;
          if (btn && btn.setAttribute) btn.setAttribute('aria-expanded', 'false');
        });
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAcademyDropdowns);
  } else {
    initAcademyDropdowns();
  }
  const fundForm=document.getElementById('academy-fund-wallet'), fundResult=document.getElementById('academy-wallet-result');
  document.querySelectorAll('[data-withdrawal-form]').forEach(form=>{
    const provider=form.querySelector('[data-provider]'), bankSelect=form.querySelector('[data-bank-select]'), bankName=form.querySelector('[data-bank-name]'), bankCode=form.querySelector('[data-bank-code]'), accountNumber=form.querySelector('[data-account-number]'), accountName=form.querySelector('[data-account-name]'), status=form.querySelector('[data-resolve-status]'), submit=form.querySelector('[data-submit-withdrawal]');
    let timer=null;
    function setStatus(message,ok=true){status.className='notice '+(ok?'ok':'error'); status.textContent=message;}
    function reset(){accountName.value=''; submit.disabled=true; setStatus('Verify bank account before withdrawal.');}
    async function loadBanks(){reset(); bankSelect.innerHTML='<option value="">Loading banks...</option>'; try{const response=await fetch(form.dataset.bankUrl+'?provider='+encodeURIComponent(provider.value),{credentials:'same-origin'}); const payload=await response.json(); if(!response.ok||!payload.success) throw new Error(payload.error||'Unable to load banks.'); bankSelect.innerHTML='<option value="">Select receiving bank</option>'+payload.banks.map(bank=>'<option value="'+String(bank.code).replace(/"/g,'&quot;')+'" data-name="'+String(bank.name).replace(/"/g,'&quot;')+'">'+bank.name+'</option>').join('');}catch(error){bankSelect.innerHTML='<option value="">Bank lookup unavailable</option>'; setStatus(error.message||'Bank lookup unavailable.',false);}}
    async function resolve(){const option=bankSelect.options[bankSelect.selectedIndex]; bankCode.value=bankSelect.value||''; bankName.value=option?(option.dataset.name||''):''; accountName.value=''; submit.disabled=true; const digits=accountNumber.value.replace(/\D/g,'').slice(0,10); accountNumber.value=digits; if(!bankCode.value||digits.length!==10) return reset(); setStatus('Verifying account name...'); const data=new FormData(); data.append('_csrf',form.querySelector('[name="_csrf"]').value); data.append('provider',provider.value); data.append('bank_code',bankCode.value); data.append('account_number',digits); try{const response=await fetch(form.dataset.resolveUrl,{method:'POST',body:data,credentials:'same-origin'}); const payload=await response.json(); if(!response.ok||!payload.success||!payload.account_name) throw new Error(payload.error||'Account could not be verified.'); accountName.value=payload.account_name; submit.disabled=false; setStatus('Verified: '+payload.account_name);}catch(error){setStatus(error.message||'Account could not be verified.',false);}}
    provider.addEventListener('change',loadBanks); bankSelect.addEventListener('change',resolve); accountNumber.addEventListener('input',()=>{clearTimeout(timer); reset(); timer=setTimeout(resolve,450);}); form.addEventListener('submit',event=>{if(submit.disabled||!accountName.value){event.preventDefault(); setStatus('Verify the bank account before submitting withdrawal.',false);}}); loadBanks();
  });
  if(fundForm&&fundResult){
    fundForm.addEventListener('submit',async function(event){
      event.preventDefault();
      fundResult.style.display='block';
      fundResult.className='notice ok';
      fundResult.textContent='Initializing wallet funding...';
      try{
        const response=await fetch('../api/fund-wallet.php',{method:'POST',body:new FormData(fundForm),credentials:'same-origin'});
        const data=await response.json();
        if(!data.success){throw new Error(data.error||'Unable to initialize payment.');}
        const url=data.checkout_url||data.payment_url||data.authorization_url||'';
        if(url){
          fundResult.textContent='Funding initialized. Redirecting to payment...';
          window.location.href=url;
        }else{
          fundResult.textContent='Funding initialized. Follow the returned Monnify payment instructions.';
        }
      }catch(error){
        fundResult.className='notice err';
        fundResult.textContent=error.message||'Unable to initialize wallet funding.';
      }
    });
  }
  // Lesson player: "Listen" reads the lesson body aloud so a
  // course can be studied by reading OR by listening.
  const listenBtn = document.getElementById('listen-btn');
  const lessonBody = document.getElementById('lesson-content');
  if (listenBtn && lessonBody && 'speechSynthesis' in window) {
    let speaking = false;
    const label = listenBtn.querySelector('span');
    const original = label ? label.textContent : '';
    function stopSpeak() {
      window.speechSynthesis.cancel();
      speaking = false;
      if (label) { label.textContent = original; }
      listenBtn.classList.remove('listening');
    }
    listenBtn.addEventListener('click', function () {
      if (speaking) { stopSpeak(); return; }
      const text = (lessonBody.textContent || '').replace(/\s+/g, ' ').trim();
      if (!text) { return; }
      const utterance = new SpeechSynthesisUtterance(text);
      utterance.rate = 0.98;
      utterance.pitch = 1;
      utterance.onend = stopSpeak;
      utterance.onerror = stopSpeak;
      window.speechSynthesis.speak(utterance);
      speaking = true;
      if (label) { label.textContent = 'Stop'; }
      listenBtn.classList.add('listening');
    });
    window.addEventListener('beforeunload', function () { window.speechSynthesis.cancel(); });
  }
})();
