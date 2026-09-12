(function(){
  const menuBtn=document.getElementById('menuBtn'), sidebar=document.getElementById('sidebar'), overlay=document.getElementById('overlay');
  if(menuBtn){menuBtn.addEventListener('click',()=>{sidebar.classList.toggle('active');overlay.classList.toggle('active');});}
  if(overlay){overlay.addEventListener('click',()=>{sidebar.classList.remove('active');overlay.classList.remove('active');});}
  document.addEventListener('keydown',e=>{if(e.key==='Escape'){sidebar.classList.remove('active');overlay.classList.remove('active');}});
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
})();
