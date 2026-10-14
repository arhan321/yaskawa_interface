'use strict';
/* RMS Cockpit: browser-only visualization of ACTUAL PostgreSQL-sourced data.
   No synthetic telemetry, no controller commands, no remote resources. */
(() => {
  const root = document.querySelector('[data-cockpit]');
  const d = window.__RMS_COCKPIT__;
  if (!root || !d) return;
  const axes = ['S','L','U','R','B','T'];
  const colors = {S:'#2588c8', L:'#ee8842', U:'#4aaf78', R:'#c14d56', B:'#8068ba', T:'#a67d48'};
  const state = {mode:'measurement', visible:new Set(axes), focus:'S', reducer:'S'};
  const numeric = x => typeof x === 'number' && Number.isFinite(x);
  const fmt = (x, digits=2) => numeric(Number(x)) && x !== null && x !== '' ? Number(x).toLocaleString('id-ID',{maximumFractionDigits:digits}) : '—';
  const escapeHTML = t => String(t??'').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  function setupCanvas(canvas) {
    const cssW = Math.max(320, canvas.parentElement.clientWidth);
    const cssH = window.matchMedia('(max-width:590px)').matches ? 245 : (canvas.id === 'cp-reducer-chart' ? 225 : 300);
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.round(cssW * ratio);
    canvas.height = Math.round(cssH * ratio);
    canvas.style.width = '100%'; canvas.style.height = cssH + 'px';
    const ctx = canvas.getContext('2d');
    ctx.setTransform(ratio,0,0,ratio,0,0);
    return {ctx,w:cssW,h:cssH};
  }
  function drawChart(canvas, points, series, limits=[], yLabel='SDK raw') {
    const {ctx,w,h} = setupCanvas(canvas);
    ctx.clearRect(0,0,w,h);
    const pad={left:54,right:18,top:24,bottom:44}, cw=w-pad.left-pad.right, ch=h-pad.top-pad.bottom;
    const numbers = [];
    for (const s of series) for (const v of s.values) if (numeric(v)) numbers.push(v);
    for (const l of limits) numbers.push(l.value);
    if (!numbers.length) return false;
    let lo=Math.min(...numbers), hi=Math.max(...numbers);
    if (lo===hi) { const spread=Math.max(Math.abs(lo)*.15,1); lo-=spread;hi+=spread; }
    const margin=(hi-lo)*.12;lo-=margin;hi+=margin;
    const x=i=>pad.left+(points.length===1?cw/2:(i/(points.length-1))*cw);
    const y=v=>pad.top+(hi-v)/(hi-lo)*ch;
    ctx.font='10px Segoe UI,Arial,sans-serif';ctx.lineWidth=1;
    for(let i=0;i<=4;i++){
      const yy=pad.top+i*ch/4;ctx.strokeStyle='#e5eef2';ctx.beginPath();ctx.moveTo(pad.left,yy);ctx.lineTo(w-pad.right,yy);ctx.stroke();
      const val=hi-i*(hi-lo)/4;ctx.fillStyle='#8199a6';ctx.textAlign='right';ctx.fillText(fmt(val,2),pad.left-7,yy+3);
    }
    ctx.textAlign='left';ctx.fillStyle='#66899f';ctx.fillText(yLabel,pad.left,pad.top-11);
    for(let i=0;i<points.length;i+=Math.max(1,Math.ceil(points.length/5))){
      ctx.fillStyle='#8397a3';ctx.textAlign='center';ctx.fillText(points[i].time,x(i),h-17);
    }
    if(points.length>1){ctx.fillText(points[points.length-1].time,x(points.length-1),h-17);}
    for (const line of limits){
      if(!numeric(line.value))continue;
      ctx.save();ctx.strokeStyle=line.color;ctx.setLineDash([5,5]);ctx.lineWidth=1.3;
      ctx.beginPath();ctx.moveTo(pad.left,y(line.value));ctx.lineTo(w-pad.right,y(line.value));ctx.stroke();ctx.restore();
    }
    for(const s of series){
      ctx.strokeStyle=s.color;ctx.lineWidth=2;ctx.lineJoin='round';ctx.lineCap='round';ctx.beginPath();
      let started=false;
      for(let i=0;i<s.values.length;i++){
        const v=s.values[i];if(!numeric(v)){started=false;continue;}
        if(!started){ctx.moveTo(x(i),y(v));started=true;}else ctx.lineTo(x(i),y(v));
      }
      ctx.stroke();
      const last=[...s.values].reverse().find(numeric);
      if(numeric(last)){ctx.fillStyle=s.color;ctx.beginPath();ctx.arc(x(s.values.lastIndexOf(last)),y(last),2.5,0,2*Math.PI);ctx.fill();}
    }
    return true;
  }
  function renderTorque(){
    const canvas=document.getElementById('cp-torque-chart'); if(!canvas)return;
    const points=Array.isArray(d.series)?d.series:[];
    const series=axes.filter(a=>state.visible.has(a)).map(a=>{
      const raw=points.map(p=>p.axes?.[a]);
      const baseline=raw.find(numeric);
      return {color:colors[a],values:state.mode==='variation'
        ?raw.map(v=>numeric(v)&&numeric(baseline)?v-baseline:null):raw};
    });
    const rules=d.axisStatus?.[state.focus]||{};
    const limits=[];
    if (state.mode==='measurement' && numeric(rules.warning) && numeric(rules.critical)) {
      for (const value of [rules.warning, -rules.warning]) limits.push({value,color:'#e7a64c'});
      for (const value of [rules.critical,-rules.critical]) limits.push({value,color:'#d64e4e'});
    }
    const valid=drawChart(canvas,points,series,limits,state.mode==='variation'?'Δ SDK raw':'SDK raw');
    document.getElementById('cp-torque-empty').hidden=valid;
  }
  function renderReducer(){
    const axis=state.reducer;
    const group=d.reducerTrend?.[axis] || {points:[],unit:null};
    const points=(group.points||[]).map(p=>({time:p.time,value:Number(p.value)})).filter(p=>numeric(p.value));
    const latest=d.reducerLatest?.[axis]||null;
    const limits=[];
    if (latest?.condition_unit===group.unit) {
      for(const name of ['lower_threshold','upper_threshold']){
        const v=latest[name];if(v !== null && v !== undefined && numeric(Number(v))) limits.push({value:Number(v),color:'#d96a52'});
      }
    }
    const canvas=document.getElementById('cp-reducer-chart');
    const plotted=drawChart(canvas,points,[{color:'#2884b9',values:points.map(p=>p.value)}],limits,group.unit||'Unit belum tersedia');
    document.getElementById('cp-reducer-empty').hidden=plotted;
    const meta=document.getElementById('cp-reducer-meta');
    if(!latest){meta.textContent='Tidak ada data verified untuk axis ini.';return;}
    const life=latest.remaining_life_hours===null || latest.remaining_life_hours===undefined ? 'Belum tersedia' : fmt(Number(latest.remaining_life_hours))+' jam (OEM)';
    meta.innerHTML=`<strong>Sumber:</strong> ${escapeHTML(latest.source_type)} · ${escapeHTML(latest.source_reference)}<br>`+
      `<strong>Pengukuran:</strong> ${escapeHTML(latest.measured_at)} · ${escapeHTML(latest.condition_value??'—')} ${escapeHTML(latest.condition_unit??'')}<br>`+
      `<strong>Remaining life:</strong> ${escapeHTML(life)}`;
  }
  root.querySelectorAll('[data-mode]').forEach(btn=>btn.addEventListener('click',()=>{
    state.mode=btn.dataset.mode;
    root.querySelectorAll('[data-mode]').forEach(b=>b.classList.toggle('selected',b===btn));
    renderTorque();
  }));
  root.querySelectorAll('[data-axis]').forEach(btn=>btn.addEventListener('click',()=>{
    const axis=btn.dataset.axis;
    if(state.visible.has(axis) && state.visible.size>1)state.visible.delete(axis);
    else state.visible.add(axis);
    btn.classList.toggle('is-selected',state.visible.has(axis));renderTorque();
  }));
  document.getElementById('cp-focus-axis')?.addEventListener('change',e=>{state.focus=e.target.value;renderTorque();});
  document.getElementById('cp-reducer-axis')?.addEventListener('change',e=>{state.reducer=e.target.value;renderReducer();});
  let resizeId=0;window.addEventListener('resize',()=>{cancelAnimationFrame(resizeId);resizeId=requestAnimationFrame(()=>{renderTorque();renderReducer();});});
  renderTorque();renderReducer();
})();
