<?php
/** Shared CCTV range-ruler styling and safe keyboard transcript formatting. */
?>
<style>
/* Single CCTV-style key history ruler */
.key-timeline-wrapper{padding:6px 8px 10px;min-width:0}
.key-history-toolbar{display:flex;align-items:center;gap:5px;min-width:0;overflow-x:auto;white-space:nowrap;scrollbar-width:thin;padding:0 0 5px;margin-bottom:3px}
.key-history-summary{display:flex;align-items:center;gap:6px;flex:1;min-width:100px;overflow:hidden;font-size:.73rem}
.key-history-summary small{font-size:.68rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.key-history-date-field,.key-history-scale-field{display:flex;align-items:center;gap:4px;flex:none;font-size:.69rem;margin:0}
.key-history-date-field input{width:160px;max-width:none;min-height:27px;padding:2px 5px;font-size:.7rem;line-height:1.1}
.key-history-scale-field select{width:74px;min-height:27px;padding:3px 17px 3px 5px;font-size:.7rem;line-height:1.1}
.key-history-toolbar .btn{padding:3px 6px;min-width:26px;line-height:1.2}
.key-history-toolbar [hidden]{display:none!important}
.key-timeline-wrapper .key-timeline-scroller{border-radius:6px}
.key-timeline-wrapper .key-cctv-ruler{height:93px}
.key-timeline-wrapper .key-cctv-baseline{top:47px}
.key-timeline-wrapper .key-cctv-tick{top:27px;height:20px}
.key-timeline-wrapper .key-cctv-tick.major{top:24px;height:23px}
.key-timeline-wrapper .key-cctv-tick-label{font-size:.6rem}
.key-timeline-wrapper .key-cctv-selection{top:3px;bottom:3px}
.key-timeline-wrapper .key-cctv-activity{opacity:.85}
.key-timeline-wrapper .key-context-panel{margin-top:6px;padding:7px 9px;border-radius:6px;min-height:90px}
.key-transcript-header{display:flex;align-items:center;justify-content:space-between;gap:7px;border-bottom:1px solid var(--bs-border-color);margin-bottom:6px;padding-bottom:6px;font-size:.79rem}
.key-transcript-header small{font-size:.67rem;font-variant-numeric:tabular-nums;overflow:hidden;text-overflow:ellipsis}
@media(max-width:650px){.key-history-summary{min-width:65px}.key-timeline-wrapper{padding:5px}}

.key-timeline-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;font-weight:700;margin-bottom:10px}
.key-timeline-heading small{display:block;font-weight:400;color:var(--bs-secondary-color);font-size:.78rem}
.key-ruler-controls{display:flex;align-items:center;flex-wrap:wrap;gap:8px;font-size:.78rem}
.key-ruler-controls select{max-width:138px}
.key-timeline-scroller{overflow-x:auto;overflow-y:hidden;background:var(--bs-tertiary-bg,#f4f6fa);border:1px solid var(--bs-border-color);border-radius:10px;scrollbar-width:thin}
.key-cctv-ruler{height:110px;position:relative;min-width:100%;background:repeating-linear-gradient(90deg,transparent,transparent 49px,rgba(127,127,127,.05) 50px)}
.key-cctv-baseline{position:absolute;top:74px;left:0;right:0;height:2px;background:var(--bs-border-color,#a8b1c0)}
.key-cctv-tick{position:absolute;top:43px;height:32px;width:1px;background:var(--bs-secondary-color,#677585);opacity:.55;pointer-events:none}
.key-cctv-tick.major{top:34px;height:41px;opacity:.8}
.key-cctv-tick-label{position:absolute;left:4px;top:-23px;font-size:.7rem;white-space:nowrap;color:var(--bs-secondary-color,#667085)}
.key-cctv-activity{position:absolute;top:65px;width:3px;height:18px;transform:translateX(-50%);border-radius:3px;background:var(--bs-primary,#3974cc);pointer-events:none;opacity:.8}
.key-cctv-playhead{position:absolute;top:13px;height:80px;width:2px;background:var(--bs-danger,#e45858);transform:translateX(-50%);pointer-events:none;z-index:3}
.key-cctv-playhead:before{content:"";position:absolute;left:-6px;top:-3px;border-left:7px solid transparent;border-right:7px solid transparent;border-top:10px solid var(--bs-danger,#e45858)}
.key-cctv-selection{position:absolute;top:12px;bottom:12px;background:color-mix(in srgb,var(--bs-primary,#3974cc) 17%,transparent);border-left:2px solid var(--bs-primary,#3974cc);border-right:2px solid var(--bs-primary,#3974cc);pointer-events:none;z-index:2}
.key-cctv-selection::before,.key-cctv-selection::after{content:"";position:absolute;top:-4px;width:11px;height:17px;border-radius:4px;background:var(--bs-primary,#3974cc);border:2px solid var(--bs-body-bg,#fff)}
.key-cctv-selection::before{left:-7px}.key-cctv-selection::after{right:-7px}
.key-cctv-ruler{cursor:crosshair;touch-action:pan-y}
.key-cctv-ruler:focus-visible{outline:2px solid var(--bs-primary,#3974cc);outline-offset:-2px}
.key-range-inputs{display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:center;margin-top:10px}
.key-range-inputs label{font-size:.73rem;color:var(--bs-secondary-color);display:flex;gap:7px;align-items:center}
.key-range-inputs input{min-width:0;width:100%;font-size:.8rem}
.key-transcript-nav{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.key-transcript-nav .btn{white-space:nowrap}
@media(max-width:650px){.key-range-inputs{grid-template-columns:1fr}}
.key-ruler-selected{font-size:.78rem;font-variant-numeric:tabular-nums;font-weight:600}
.key-context-panel{margin-top:12px;border:1px solid var(--bs-border-color);border-radius:10px;padding:14px;min-height:110px}
.key-context-heading{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:12px;font-size:.83rem}
.key-transcript{display:grid;grid-template-columns:100px minmax(0,1fr);gap:3px 12px;align-items:start}
.key-transcript-time{font-variant-numeric:tabular-nums;color:var(--bs-secondary-color);font-size:.75rem;padding-top:4px}
.key-transcript-app{font-size:.78rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--bs-body-color)}
.key-transcript-app-row{grid-column:1/-1;margin:8px 0 4px;padding:6px 9px;border-radius:6px;background:var(--bs-secondary-bg,#edf0f5);border:1px solid var(--bs-border-color)}
.key-transcript-app-row:first-child{margin-top:1px}
.key-transcript-content{min-height:25px;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.75;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:.84rem}
.key-transcript-content .key-deleted{color:var(--bs-secondary-color);text-decoration:line-through;text-decoration-thickness:2px;background:rgba(227,75,75,.09)}
.key-control-symbol{color:var(--bs-secondary-color);font-size:.73rem;opacity:.75}
.key-special-badge{display:inline-flex;margin:0 2px;padding:1px 5px;vertical-align:baseline;border-radius:5px;background:var(--bs-secondary-bg,#ecf0f5);color:var(--bs-body-color);border:1px solid var(--bs-border-color);font-family:inherit;font-size:.7rem;line-height:1.4}
.key-remaining{display:flex;flex-wrap:wrap;gap:6px;padding-top:8px}
.key-remaining .badge{font-size:.73rem;font-weight:500}
@media(max-width:650px){.key-timeline-wrapper{padding:8px}.key-context-panel{padding:10px}.key-transcript{grid-template-columns:78px minmax(0,1fr);gap:2px 7px}.key-transcript-content{font-size:.78rem}}
</style>
<script>
function monitorTranscriptKeyRows(keys,escapeHtml) {
    const applicationName = entry => String(entry?.application || '').trim() || 'Unknown app';
    if(!Array.isArray(keys)||!keys.length)
        return '<div class="text-muted small">No keyboard events in this range.</div>';
    const lines=[{time:keys[0]?.key_date||'',app:applicationName(keys[0]),tokens:[]}];
    let current=lines[0];
    const seenIds=new Set();
    const addBadge=(raw,time,eventId)=>{
        const previous=current.tokens[current.tokens.length-1];
        const previousTime=previous?.lastTime||previous?.time||'';
        const eventMs=Date.parse(String(time).replace(' ','T'));
        const lastMs=Date.parse(String(previousTime).replace(' ','T'));
        // Only condense adjacent repeated *special* keys over a short interval.
        // Keep original events in the database, and show the repeat count.
        if(previous?.kind==='badge'&&previous.raw===raw&&
           Number.isFinite(eventMs)&&Number.isFinite(lastMs)&&
           eventMs>=lastMs&&eventMs-lastMs<=2000){
            previous.repeat=(previous.repeat||1)+1;
            previous.lastTime=time;
            return;
        }
        current.tokens.push({kind:'badge',raw,time,eventId,repeat:1,lastTime:time});
    };
    for(const entry of keys) {
        const raw=String(entry.key_pressed??'');
        const key=raw.trim().replace(/^\[(.*)\]$/,'$1').replace(/^<(.*)>$/,'$1');
        const time=String(entry.key_date||'');
        const eventId=Number(entry.event_id)||0;
        if(eventId&&seenIds.has(eventId))continue;
        if(eventId)seenIds.add(eventId);
        const app=applicationName(entry);
        // Start a new transcript row when the active application changes.
        // Do not apply Backspace to characters typed in a different app.
        if(app!==current.app){
            current={time,app,tokens:[]};
            lines.push(current);
        }
        if(/^(?:(?:shift|alt)\+)?(?:enter|return|numpadenter)$/i.test(key) || raw==='\n' || raw==='\r'){
            current.tokens.push({kind:'newline'});
            current={time,app,tokens:[]};
            lines.push(current);
        }else if(/^(?:ctrl|control)\+(?:backspace|back)$/i.test(key)){
            let count=0,started=false;
            for(let k=current.tokens.length-1;k>=0;k--){
                const token=current.tokens[k];
                if(token.kind!=='char'||token.deleted)continue;
                if(token.value.trim()==='' && started)break;
                token.deleted=true;count++;started=true;
            }
            // Strikethrough already communicates the deletion. Keep an
            // unmatched Ctrl+Backspace as a special-key event for context.
            if(!count)addBadge(raw||'Ctrl+Backspace',time,eventId);
        }else if(/^(backspace|back|bksp|vk_back)$/i.test(key)){
            let last=null;
            for(let k=current.tokens.length-1;k>=0;k--){
                if(current.tokens[k].kind==='char'&&!current.tokens[k].deleted){last=current.tokens[k];break;}
            }
            if(last){
                // Show only the struck-through character, not an extra ⌫ glyph.
                last.deleted=true;
            }else addBadge(raw||'Backspace',time,eventId);
        }else if(/^(space|spacebar)$/i.test(key)||raw===' '){
            current.tokens.push({kind:'char',value:' ',time,deleted:false});
        }else if(/^tab$/i.test(key)||raw==='\t'){
            current.tokens.push({kind:'char',value:'    ',time,deleted:false});
        }else if(raw.length===1){
            current.tokens.push({kind:'char',value:raw,time,deleted:false});
        }else{
            // Modifiers and navigation shortcuts are not inferable text edits.
            // Preserve the event without inventing a caret position.
            addBadge(raw||'Unknown',time,eventId);
        }
    }
    let lastApplication=null;
    const transcript=lines.map(line=>{
        const appHeader=line.app!==lastApplication
            ? '<div class="key-transcript-app-row"><div class="key-transcript-app" title="'+escapeHtml(line.app)+'">'+escapeHtml(line.app)+'</div></div>'
            : '';
        lastApplication=line.app;
        const content=line.tokens.map(token=>{
            if(token.kind==='char')return `<span class="${token.deleted?'key-deleted':''}" title="${escapeHtml(token.time)}">${escapeHtml(token.value)}</span>`;
            if(token.kind==='newline')return '<span class="key-control-symbol" title="Enter">↵</span>';
            const label=token.repeat>1?token.raw+' ×'+token.repeat:token.raw;
            const description=token.time+(token.repeat>1?'–'+token.lastTime+' · '+token.repeat+' consecutive events':'')+
                (token.eventId?' · first event #'+token.eventId:'');
            return `<span class="key-special-badge" title="${escapeHtml(description)}">${escapeHtml(label)}</span>`;
        }).join('');
        return appHeader+`<div class="key-transcript-time" title="${escapeHtml(line.time||'')}">${escapeHtml((line.time||'').split(' ').pop())}</div>
            <div class="key-transcript-content">${content||'<span class="text-muted"> </span>'}</div>`;
    }).join('');
    // Special keys are already rendered in their chronological transcript rows.
    // Never print the same events a second time in a separate badge collection.
    // Identical event IDs are rendered once; consecutive special-key repeats
    // are shown once with a count (e.g. UP ×8), preserving source rows in DB.
    return '<div class="key-transcript" role="table" aria-label="Keyboard events with timestamps">'+transcript+'</div>';
}


/** Geometry shared by the Keylog and Screenshot CCTV rulers. All values are milliseconds. */
const MonitorCctvRange = Object.freeze({
    clamp(value,min,max){ return Math.min(max,Math.max(min,value)); },
    point(event,ruler,state){
        const box=ruler.getBoundingClientRect();
        const ratio=this.clamp((event.clientX-box.left)/Math.max(1,box.width),0,1);
        return state.start+ratio*(state.end-state.start);
    },
    hit(state,time,width,{seek=false,cursor=NaN,playhead=false}={}){
        const pxPerMs=Math.max(1,width)/Math.max(1,state.end-state.start);
        if(seek && (playhead || (Number.isFinite(cursor)&&Math.abs(time-cursor)*pxPerMs<=8)))
            return 'seek';
        const selectedPixels=(state.rangeEnd-state.rangeStart)*pxPerMs;
        const tolerance=Math.min(12,Math.max(2,selectedPixels*.3))/pxPerMs;
        const left=Math.abs(time-state.rangeStart),right=Math.abs(time-state.rangeEnd);
        const duration=state.rangeEnd-state.rangeStart;
        if(time>state.rangeStart&&time<state.rangeEnd&&left>duration*.3&&right>duration*.3)return 'pan';
        if(left<=tolerance||right<=tolerance)return left<=right?'start':'end';
        if(time>state.rangeStart&&time<state.rangeEnd)return 'pan';
        return seek?'seek':left<=right?'start':'end';
    },
    drag(state,mode,from,to,anchor,time){
        const delta=time-anchor,minimum=1000;
        if(mode==='pan'){
            const duration=to-from;
            const start=this.clamp(from+delta,state.start,state.end-duration);
            return [start,start+duration];
        }
        if(mode==='start')return [this.clamp(from+delta,state.start,to-minimum),to];
        if(mode==='end')return [from,this.clamp(to+delta,from+minimum,state.end)];
        return [from,to];
    },
    zoomScroll({oldPx,newPx,domainStart,anchorTime,anchorX,scrollWidth}){
        const offset=(anchorTime-domainStart)/60000*newPx-anchorX;
        return this.clamp(offset,0,Math.max(0,scrollWidth));
    }
});

</script>
