'use strict';
const $ = (id) => document.getElementById(id);
const icon = (name) => `<svg class="icon" aria-hidden="true"><use href="#${name}"/></svg>`;
const esc = (value) => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const number = (value, digits = 2) => Number(value || 0).toLocaleString('en-PH', {minimumFractionDigits:digits,maximumFractionDigits:digits});
const money = (value) => '₱' + number(value);
const fmt = (date, options) => new Intl.DateTimeFormat('en-PH', {timeZone:'Asia/Manila',...options}).format(new Date(date));
const dateLabel = (d) => fmt(d,{month:'short',day:'numeric',year:'numeric'});
const timeLabel = (d) => fmt(d,{hour:'numeric',minute:'2-digit'});
const toInput = (d = new Date()) => new Date(new Date(d).getTime()+8*3600000).toISOString().slice(0,16);
const fromInput = (v) => new Date(v+':00+08:00');
const duration = (hours) => {const minutes=Math.round(hours*60);return `${Math.floor(minutes/60)}h ${minutes%60}m`;};
let state, mode='live', editId=null, pending=null, selectedRecord=null, ecoContext='manual', clockOffset=0, deleteTarget=null;
let toastTimeout, initialLoad=true, loadPromise=null, lastLoadedAt=0, household=null;
const csrf = document.querySelector('meta[name="csrf-token"]').content;

async function api(path, method='GET', body) {
    let response;
    for(let attempt=0;attempt<(method==='GET'?2:1);attempt++) {
        const controller=new AbortController(), timeout=setTimeout(()=>controller.abort(),20000);
        try {
            response=await fetch('/api/'+path, {method, credentials:'same-origin', cache:'no-store', signal:controller.signal, headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf}, body:body===undefined?undefined:JSON.stringify(body)});
            if(method==='GET'&&attempt===0&&[502,503,504].includes(response.status)) {await new Promise(resolve=>setTimeout(resolve,500));continue;}
            break;
        } catch(error) {
            if(method==='GET'&&attempt===0) {await new Promise(resolve=>setTimeout(resolve,500));continue;}
            throw new Error(method==='GET'?'Connection interrupted. Check your internet connection and tap Retry.':'Connection interrupted. Refresh to check whether your change was saved before trying again.');
        } finally {clearTimeout(timeout);}
    }
    if (response.status===204) return null;
    if(response.status===401){window.location.assign('/login');throw new Error('Please sign in to your journal.');}
    if(response.redirected||!response.headers.get('content-type')?.includes('application/json')) throw new Error('Your sign-in may have expired. Reload the app to sign in again.');
    let data; try { data=await response.json(); } catch { throw new Error('The server returned an unexpected response. Reload the app and try again.'); }
    if (!response.ok) {
        if(response.status===401){window.location.assign('/login');throw new Error('Please sign in to your journal.');}
        if(response.status===419) throw new Error('Your page session expired. Reload the app before saving.');
        const error=new Error(data.errors ? Object.values(data.errors).flat().join(' ') : data.message || 'Something went wrong. Please try again.');
        error.status=response.status;throw error;
    }
    return data;
}
function errorAt(id, message='') { $(id).textContent=message; $(id).hidden=!message; }
function toast(message) { $('toast').textContent=message;$('toast').hidden=false;clearTimeout(toastTimeout);toastTimeout=setTimeout(()=>$('toast').hidden=true,4500); }
async function busy(button, action, errorId) {
    button.disabled=true; if(errorId) errorAt(errorId);
    try { await action(); } catch(error) {
        if(errorId) errorAt(errorId,error.message);else toast(error.message);
        if(error.status===409) {await load().catch(()=>{});if(!state?.active){$('stop-dialog').close();$('eco-dialog').close();toast(error.message);}}
    }
    finally { button.disabled=button.id==='session-submit'&&(!state||(mode==='live'&&!!state.active)); }
}
function showDialog(id) { if(!$(id).open) $(id).showModal(); }
function now() { return new Date(Date.now()+clockOffset); }
function changePage(page) {
    if(!['overview','history','settings'].includes(page)) page='overview';
    document.querySelectorAll('main>.page').forEach(p=>p.hidden=p.id!=='page-'+page);
    document.querySelectorAll('nav .nav-item').forEach(b=>{b.classList.toggle('active',b.dataset.page===page);b.setAttribute('aria-current',b.dataset.page===page?'page':'false');});
    history.replaceState(null,'','#'+page); window.scrollTo({top:0,behavior:'instant'});
    if(page==='settings') loadHousehold().catch(error=>{if($('household-members'))$('household-members').textContent=error.message;});
}
function setTemperature(value) {
    value=Math.min(30,Math.max(16,Number(value)));
    $('temperature').value=value; $('temp-output').innerHTML=value+'<span>°C</span>';
    $('temp-minus').disabled=value===16; $('temp-plus').disabled=value===30;
}
function setMode(next) {
    mode=next;
    $('started-at').max=toInput(now());$('ended-at').max=toInput(now());
    $('mode-live').classList.toggle('selected',mode==='live'); $('mode-manual').classList.toggle('selected',mode==='manual');
    $('mode-live').setAttribute('aria-pressed',mode==='live'); $('mode-manual').setAttribute('aria-pressed',mode==='manual');
    $('manual-times').hidden=mode!=='manual'; $('started-at').required=mode==='manual'; $('ended-at').required=mode==='manual';
    $('session-submit').innerHTML=icon(mode==='live'?'play':'arrow')+(mode==='live'?'Start session':editId?'Review changes':'Continue');
    $('session-submit').disabled=!state || (mode==='live' && !!state.active);
    $('mode-hint').innerHTML=icon('info')+(mode==='live'?'Tap Start when you turn on your AC.':'Next, we’ll ask about Eco mode.');
    errorAt('session-error');
}
function resetForm() {
    editId=null; pending=null; $('session-form').reset(); setTemperature(26);
    $('started-at').value=toInput(new Date(now().getTime()-3600000)); $('ended-at').value=toInput(now());
    $('started-at').max=toInput(now()); $('ended-at').max=toInput(now());
    $('log-heading').textContent='Log a cooling session'; $('cancel-edit').hidden=true; $('mode-live').disabled=false; $('mode-manual').disabled=false;
    setMode('live');
}
function applyState(data, fillSettings=false) {
    state=data; clockOffset=new Date(data.server_now).getTime()-Date.now(); lastLoadedAt=Date.now();
    render(); if(fillSettings||initialLoad) populateSettings();
    if(initialLoad) { resetForm(); initialLoad=false; }
    $('load-error').hidden=true;
}
async function load(fillSettings=false) {
    if(loadPromise) {await loadPromise; if(fillSettings)populateSettings();return;}
    loadPromise=(async()=>{
        try {applyState(await api('state'),fillSettings);}
        catch(error) {$('load-error-text').textContent=(state?'Showing the last loaded sessions. ':'')+error.message;$('load-error').hidden=false;throw error;}
    })();
    try {await loadPromise;} finally {loadPromise=null;}
}
async function refreshAfterWrite(fillSettings=false) {
    // A refresh already running may have read the journal before this write.
    if(loadPromise)await loadPromise.catch(()=>{});
    await load(fillSettings);
}
function render() {
    $('today-date').textContent=fmt(now(),{weekday:'short',day:'numeric',month:'short',year:'numeric'});
    $('month-name').textContent=fmt(now(),{month:'long'});
    $('month-cost').textContent=money(state.month.cost); $('month-kwh').textContent=number(state.month.kwh);
    $('month-hours').textContent=number(state.month.hours,1); $('today-cost').textContent=money(state.today.cost);
    $('history-count').textContent=state.sessions.length;
    const s=state.settings;
    $('rate-number').innerHTML=money(s.rate)+' <span>/ kWh</span>';
    $('rate-period').textContent=dateLabel(s.rate_from+'T12:00:00+08:00')+' – '+dateLabel(s.rate_until+'T12:00:00+08:00');
    $('rate-source').textContent=(s.rate_status==='reported'?'Reported rate':'Manually set rate')+' · view source ↗';
    $('rate-source').href=s.rate_source_url;
    const day=toInput(now()).slice(0,10); $('rate-warning').hidden=day>=s.rate_from&&day<=s.rate_until;
    $('rate-warning').textContent=day>s.rate_until?'Rate period has expired. Update it from your latest bill.':'This rate period has not started yet. Check the rate in Settings.';
    $('recent-sessions').innerHTML=sessionTable(state.sessions.slice(0,4)); renderHistory(); renderChart(); renderActive();
    $('session-submit').disabled=mode==='live'&&!!state.active;
}
function emptyState() { return `<div class="empty-state"><div class="empty-icon">${icon('clock')}</div><h3>Your cooling journal starts here</h3><p>Log your first session to see your energy and cost take shape.</p><button class="text-button" data-action="focus-log">Log a session ${icon('arrow')}</button></div>`; }
function sessionTable(records) {
    if(!records.length) return emptyState();
    return `<div class="table-scroll"><table><thead><tr><th>Session</th><th>Duration</th><th>Settings</th><th>Energy</th><th>Est. cost</th><th><span class="sr-only">Details</span></th></tr></thead><tbody>${records.map(s=>`<tr><td><span class="row-main">${dateLabel(s.started_at)}</span><small>${timeLabel(s.started_at)} – ${timeLabel(s.ended_at)}${dateLabel(s.started_at)!==dateLabel(s.ended_at)?' (+day)':''}</small></td><td>${duration(s.estimate.hours)}<small>${s.eco_at?`${Math.round(s.estimate.eco_minutes)} min in Eco`:'Normal cooling'}</small></td><td><span class="pill ${s.eco_at?'':'neutral'}">${icon(s.eco_at?'leaf':'snow')}${s.temperature}°C · ${s.eco_at?'Eco':'Cool'}</span></td><td>${number(s.estimate.kwh)} <small style="display:inline">kWh</small></td><td class="session-cost">${money(s.estimate.cost)}</td><td><button class="icon-button" data-detail="${s.id}" aria-label="View session from ${dateLabel(s.started_at)}">${icon('arrow')}</button></td></tr>`).join('')}</tbody></table></div>`;
}
function renderHistory() {
    const filter=$('history-month').value;
    const records=state.sessions.filter(s=>!filter||toInput(s.started_at).slice(0,7)===filter);
    $('all-sessions').innerHTML=records.length?sessionTable(records):filter?'<div class="empty-state"><h3>No sessions started this month</h3><p>Choose another month or show all sessions.</p></div>':emptyState();
    $('filtered-total').textContent=`${records.length} session${records.length===1?'':'s'} · ${number(records.reduce((a,s)=>a+s.estimate.kwh,0))} kWh · ${money(records.reduce((a,s)=>a+s.estimate.cost,0))}`;
}
function renderChart() {
    const max=Math.max(1,...state.week.map(d=>d.kwh)); const ceiling=Math.max(1,Math.ceil(max*1.15*2)/2);
    const hasData=state.week.some(d=>d.kwh>0);
    $('weekly-chart').innerHTML=`<div class="chart-grid">${[1,.5,0].map(f=>`<div class="grid-line"><span>${number(ceiling*f,1)}</span></div>`).join('')}</div><div class="bars">${state.week.map(d=>`<div class="bar-column" title="${dateLabel(d.date+'T12:00:00+08:00')}: ${number(d.kwh)} kWh, ${money(d.cost)}"><div class="bar" style="height:${Math.max(0,d.kwh/ceiling*100)}%">${d.kwh?`<span class="bar-value">${number(d.kwh,1)}</span>`:''}</div><span>${fmt(d.date+'T12:00:00+08:00',{weekday:'short'})}</span></div>`).join('')}</div>${hasData?'':'<div class="chart-empty">Your week is a clean slate.<small>Completed sessions will appear here.</small></div>'}`;
}
function liveEstimate(s) {
    const p=s.assumptions, start=new Date(s.started_at).getTime(), end=now().getTime();
    const eco=s.eco_at?new Date(s.eco_at).getTime():end;
    const cooling=start+p.cooldown_minutes*({comfortable:.5,warm:1,hot:1.5}[s.room_heat])*60000;
    const points=[start,end,Math.min(end,cooling),Math.min(end,eco)].sort((a,b)=>a-b);
    let kwh=0;
    for(let i=0;i<points.length-1;i++) {const a=points[i], b=points[i+1];const w=a<cooling?p.rated_watts:a>=eco?p.eco_watts:p.normal_watts;kwh+=w/1000*(b-a)/3600000;}
    return {kwh:Math.max(0,kwh),cost:Math.max(0,kwh*p.rate)};
}
function updateTimer() {
    if(!state?.active||!$('live-clock')) return;
    const s=state.active; const seconds=Math.max(0,Math.floor((now()-new Date(s.started_at))/1000));
    $('live-clock').textContent=[Math.floor(seconds/3600),Math.floor(seconds/60)%60,seconds%60].map(n=>String(n).padStart(2,'0')).join(':');
    const e=liveEstimate(s); $('live-estimate').textContent=`${number(e.kwh)} kWh · ${money(e.cost)} estimated so far`;
}
function renderActive() {
    const s=state.active; $('active-session').hidden=!s;
    if(!s) {$('active-session').innerHTML='';return;}
    $('active-session').innerHTML=`<span class="live-indicator"></span><div><div class="live-title">Cooling in progress · ${s.temperature}°C${s.eco_at?' · Eco on':''}</div><div class="live-copy" id="live-estimate"></div><button class="text-button" data-action="discard-live">Discard timer</button></div><div class="live-time" id="live-clock">00:00:00</div><div class="live-actions"><button class="btn btn-white" data-action="live-eco">${icon('leaf')}${s.eco_at?'Edit Eco time':'Eco enabled'}</button><button class="btn btn-primary" data-action="stop">${icon('stop')}Stop session</button></div>`;
    updateTimer();
}
function resultMarkup(e, data, settings) {
    return `<div class="result-summary"><div><span>Estimated session cost</span><strong>${money(e.cost)}</strong><p>Scenario: ${money(e.low_cost)} – ${money(e.high_cost)}</p></div><div><span>Estimated energy used</span><strong>${number(e.kwh)} <small>kWh</small></strong><p>Scenario: ${number(e.low_kwh)} – ${number(e.high_kwh)} kWh</p></div></div><dl class="result-meta"><div><dt>AC turned on</dt><dd>${dateLabel(data.started_at)} · ${timeLabel(data.started_at)}</dd></div><div><dt>AC turned off</dt><dd>${dateLabel(data.ended_at)} · ${timeLabel(data.ended_at)}</dd></div><div><dt>Cooling duration</dt><dd>${duration(e.hours)}</dd></div><div><dt>Eco mode</dt><dd>${data.eco_at?timeLabel(data.eco_at)+' · '+Math.round(e.eco_minutes)+' min':'Not used'}</dd></div><div><dt>Room & settings</dt><dd>${esc({comfortable:'Comfortable',warm:'Warm',hot:'Very hot'}[data.room_heat])} · ${data.temperature}°C · ${esc(data.fan)} fan</dd></div><div><dt>Saved electricity rate</dt><dd>${money(e.rate)} / kWh</dd></div></dl>${data.notes?`<p class="result-note">Note: ${esc(data.notes)}</p>`:''}<p class="result-note">Assumed initial cooling: ${number(e.cooldown_minutes,0)} min at ${settings.rated_watts} W. Settled input: ${settings.normal_watts} W normal / ${settings.eco_watts} W Eco.<br>Illustrative ±${settings.uncertainty_percent}% range, capped at ${settings.max_watts} W × duration; actual usage can fall outside it.</p><p class="result-note">Rate: ${esc(settings.rate_source)}. ${dateLabel(settings.rate_from+'T12:00:00+08:00')} – ${dateLabel(settings.rate_until+'T12:00:00+08:00')}.</p>${e.rate_outside_period?'<div class="rate-alert">This session falls outside the saved rate’s billing period. Its cost uses that rate as an assumption. Check the applicable rate before relying on the cost.</div>':''}`;
}
function openDetail(record) {
    selectedRecord=record; $('detail-content').innerHTML=resultMarkup(record.estimate,record,record.assumptions); showDialog('detail-dialog');
}
function openEco(context) {
    ecoContext=context; errorAt('eco-error');
    const start=context==='live'?new Date(state.active.started_at):fromInput(pending.started_at);
    const end=context==='live'?now():fromInput(pending.ended_at);
    const saved=context==='live'?state.active.eco_at:pending.eco_at;
    $('eco-time').value=saved?(saved.includes('+')||saved.endsWith('Z')?toInput(saved):saved):toInput(new Date(Math.min(end.getTime(),start.getTime()+10*60000)));
    $('eco-time').min=toInput(start);$('eco-time').max=toInput(end);
    $('eco-help').textContent=`Choose a time between ${timeLabel(start)} and ${timeLabel(end)}. Include the correct date for overnight use.`;
    $('no-eco').hidden=context==='live'; showDialog('eco-dialog');
}
async function preview(ecoAt) {
    pending.eco_at=ecoAt;
    const estimate=await api('estimate','POST',{...pending,session_id:editId});
    const display={...pending,started_at:fromInput(pending.started_at),ended_at:fromInput(pending.ended_at),eco_at:ecoAt?fromInput(ecoAt):null};
    const settings=editId?state.sessions.find(s=>s.id===editId).assumptions:state.settings;
    $('result-content').innerHTML=resultMarkup(estimate,display,settings);
    $('save-session').textContent=editId?'Save changes':'Save session';errorAt('result-error');
    $('eco-dialog').close();showDialog('result-dialog');
}
function populateSettings() {
    if(!state) return;
    for(const element of $('settings-form').elements) if(element.name&&state.settings[element.name]!==undefined) element.value=state.settings[element.name];
}
async function loadHousehold() {
    if(!$('household-members'))return;
    household=await api('household');
    if($('invite-form'))$('invite-form').hidden=!household.enabled||!household.owner;
    if(!household.enabled) {
        $('household-members').textContent='Invitations are available in your online journal. Sign in there to share this AC.';
        if($('household-invites'))$('household-invites').innerHTML='';return;
    }
    $('household-members').innerHTML=household.members.map(member=>`<div class="household-row"><div><strong>${esc(member.name)}</strong><small>${esc(member.email)} · ${member.is_owner?'Owner':'Member'}${member.id===household.current_user.id?' · You':''}</small></div>${household.owner&&!member.is_owner?`<button class="btn btn-white" data-remove-member="${member.id}">Remove access</button>`:''}</div>`).join('');
    if($('household-invites'))$('household-invites').innerHTML=household.owner&&household.invites.length?household.invites.map(invite=>`<div class="household-row"><div><strong>${esc(invite.email)}</strong><small>Expires ${dateLabel(invite.expires_at)} · ${timeLabel(invite.expires_at)}</small></div><button class="btn btn-white" data-revoke-invite="${invite.id}">Revoke</button></div>`).join(''):'<p class="field-help">No pending invitations.</p>';
}

document.addEventListener('click', event=>{
    const removeMember=event.target.closest('[data-remove-member]');
    if(removeMember) {
        if(window.confirm('Remove this person’s access to your shared AC journal? Your cooling sessions will stay saved.'))busy(removeMember,async()=>{await api('household/members/'+removeMember.dataset.removeMember,'DELETE');await loadHousehold();toast('Member access removed.');});
        return;
    }
    const revokeInvite=event.target.closest('[data-revoke-invite]');
    if(revokeInvite) {busy(revokeInvite,async()=>{await api('household/invites/'+revokeInvite.dataset.revokeInvite,'DELETE');await loadHousehold();$('invite-result').hidden=true;toast('Invitation revoked.');});return;}
    if(event.target.closest('[data-copy-invite]')) {
        const input=$('invite-link');input.select();
        if(navigator.clipboard)navigator.clipboard.writeText(input.value).then(()=>toast('Invitation link copied.')).catch(()=>toast('Select and copy the invitation link.'));
        else toast('Select and copy the invitation link.');return;
    }
    const close=event.target.closest('[data-close]');if(close){$(close.dataset.close).close();return;}
    const page=event.target.closest('[data-page]');if(page){changePage(page.dataset.page);return;}
    const detail=event.target.closest('[data-detail]');if(detail&&state){openDetail(state.sessions.find(s=>s.id===Number(detail.dataset.detail)));return;}
    const action=event.target.closest('[data-action]')?.dataset.action;
    if(action==='method') showDialog('method-dialog');
    if(action==='focus-log'){changePage('overview');$('log-card').scrollIntoView({behavior:'smooth',block:'center'});$('mode-'+mode).focus({preventScroll:true});}
    if(action==='live-eco') openEco('live');
    if(action==='stop'){errorAt('stop-error');$('stop-time').value=toInput(now());$('stop-time').max=toInput(now());$('stop-choice').value='now';$('stop-time-label').hidden=true;showDialog('stop-dialog');}
    if(action==='discard-live') confirmDelete(state.active.id,true);
});
$('mode-live').onclick=()=>{if(!editId)setMode('live');};$('mode-manual').onclick=()=>setMode('manual');
$('temp-minus').onclick=()=>setTemperature(Number($('temperature').value)-1);$('temp-plus').onclick=()=>setTemperature(Number($('temperature').value)+1);
$('cancel-edit').onclick=()=>resetForm();
$('session-form').onsubmit=event=>{
    event.preventDefault(); if(!state) return;
    const data=Object.fromEntries(new FormData(event.currentTarget));
    data.temperature=Number(data.temperature);
    if(mode==='live') busy($('session-submit'),async()=>{await api('sessions/start','POST',data);await refreshAfterWrite();toast('Session started. Enjoy the cool.');$('active-session').scrollIntoView({behavior:'smooth',block:'center'});},'session-error');
    else {pending=data;if(editId)pending.eco_at=state.sessions.find(s=>s.id===editId).eco_at;openEco('manual');}
};
$('eco-form').onsubmit=event=>{
    event.preventDefault();busy(event.submitter,async()=>{
        if(ecoContext==='live'){await api(`sessions/${state.active.id}/eco`,'PUT',{eco_at:$('eco-time').value});$('eco-dialog').close();await refreshAfterWrite();toast('Eco time recorded.');}
        else await preview($('eco-time').value);
    },'eco-error');
};
$('no-eco').onclick=()=>busy($('no-eco'),()=>preview(null),'eco-error');
$('save-session').onclick=()=>busy($('save-session'),async()=>{
    const record=await api(editId?`sessions/${editId}`:'sessions',editId?'PUT':'POST',pending);
    $('result-dialog').close();resetForm();await refreshAfterWrite();toast('Session saved to your journal.');openDetail(record);
},'result-error');
$('edit-session').onclick=()=>{
    const s=selectedRecord;editId=s.id;changePage('overview');$('detail-dialog').close();setMode('manual');
    $('mode-live').disabled=true;$('log-heading').textContent='Edit cooling session';$('cancel-edit').hidden=false;
    $('started-at').value=toInput(s.started_at);$('ended-at').value=toInput(s.ended_at);setTemperature(s.temperature);
    $('session-form').querySelector(`input[name="room_heat"][value="${s.room_heat}"]`).checked=true;
    $('fan').value=s.fan;$('notes').value=s.notes||'';$('log-card').scrollIntoView({behavior:'smooth',block:'center'});
    $('mode-hint').textContent='This session keeps its original rate and assumptions.';
};
function confirmDelete(id,live=false) {
    deleteTarget=id;errorAt('confirm-error');$('confirm-title').textContent=live?'Discard this timer?':'Delete this session?';
    $('confirm-copy').textContent=live?'This removes the running timer without saving an energy estimate.':'This removes the session from your journal and totals.';
    $('confirm-delete').textContent=live?'Discard timer':'Delete session';showDialog('confirm-dialog');
}
$('delete-session').onclick=()=>confirmDelete(selectedRecord.id);
$('confirm-delete').onclick=()=>busy($('confirm-delete'),async()=>{
    await api('sessions/'+deleteTarget,'DELETE');$('confirm-dialog').close();$('detail-dialog').close();
    if(editId===deleteTarget) resetForm();await refreshAfterWrite();toast('Session removed.');
},'confirm-error');
$('stop-choice').onchange=()=>{$('stop-time-label').hidden=$('stop-choice').value==='now';$('stop-time').required=$('stop-choice').value==='earlier';};
$('stop-form').onsubmit=event=>{
    event.preventDefault();busy(event.submitter,async()=>{
        const record=await api(`sessions/${state.active.id}/stop`,'POST',{ended_at:$('stop-choice').value==='earlier'?$('stop-time').value:null});
        $('stop-dialog').close();await refreshAfterWrite();toast('Cooling session finished and saved.');openDetail(record);
    },'stop-error');
};
$('settings-form').onsubmit=event=>{
    event.preventDefault();busy(event.submitter,async()=>{
        const data=Object.fromEntries(new FormData(event.currentTarget));
        await api('settings','PUT',data);await refreshAfterWrite(true);$('settings-message').textContent='Saved. New sessions will use these settings.';toast('Settings saved.');
    }).catch(()=>{});
};
$('history-month').onchange=()=>state&&renderHistory();$('clear-filter').onclick=()=>{$('history-month').value='';if(state)renderHistory();};
$('retry-load').onclick=()=>load().catch(()=>{});
if($('invite-form'))$('invite-form').onsubmit=event=>{
    event.preventDefault();busy(event.submitter,async()=>{
        const data=await api('household/invites','POST',{email:$('invite-email').value});
        $('invite-result').innerHTML=`<label>Share this one-time invitation link<input id="invite-link" type="text" readonly value="${esc(data.invite_url)}"></label><button type="button" class="btn btn-white" data-copy-invite>Copy link</button><p>Send this link directly to ${esc(data.invite.email)}. It expires in 48 hours. They will create their own sign-in and join this AC’s existing sessions.</p>`;
        $('invite-result').hidden=false;await loadHousehold();toast('Invitation ready to share.');
    },'invite-error');
};
$('session-submit').disabled=true;$('mode-live').disabled=true;$('mode-manual').disabled=true;
let initialState;try{initialState=JSON.parse($('initial-state')?.textContent||'null');}catch{}
if(initialState)applyState(initialState);else load().catch(()=>{});
changePage(location.hash.slice(1)||'overview');
setInterval(updateTimer,1000);
setInterval(()=>{if(!document.hidden&&!document.querySelector('dialog[open]')&&Date.now()-lastLoadedAt>(state?.active?14000:29000))load().catch(()=>{});},15000);
window.addEventListener('focus',()=>{if(state&&Date.now()-lastLoadedAt>5000&&!document.querySelector('dialog[open]'))load().catch(()=>{});});
document.addEventListener('visibilitychange',()=>{if(!document.hidden&&Date.now()-lastLoadedAt>5000&&!document.querySelector('dialog[open]'))load().catch(()=>{});});
if('serviceWorker' in navigator && window.isSecureContext) navigator.serviceWorker.register('/sw.js').catch(()=>{});
