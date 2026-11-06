<?php
/** Shared CCTV range-ruler styling and safe keyboard transcript formatting. */
?>
<style>
/* Single CCTV-style key history ruler */
.key-timeline-wrapper{padding:12px;min-width:0}
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
.key-transcript{display:grid;grid-template-columns:90px minmax(100px,180px) minmax(0,1fr);gap:2px 12px}
.key-transcript-time{font-variant-numeric:tabular-nums;color:var(--bs-secondary-color);font-size:.75rem;padding-top:4px}
.key-transcript-app{font-size:.76rem;font-weight:600;padding-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--bs-body-color)}
.key-transcript-content{min-height:25px;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.75;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:.84rem}
.key-transcript-content .key-deleted{color:var(--bs-secondary-color);text-decoration:line-through;text-decoration-thickness:2px;background:rgba(227,75,75,.09)}
.key-control-symbol{color:var(--bs-secondary-color);font-size:.73rem;opacity:.75}
.key-special-badge{display:inline-flex;margin:0 2px;padding:1px 5px;vertical-align:baseline;border-radius:5px;background:var(--bs-secondary-bg,#ecf0f5);color:var(--bs-body-color);border:1px solid var(--bs-border-color);font-family:inherit;font-size:.7rem;line-height:1.4}
.key-remaining{display:flex;flex-wrap:wrap;gap:6px;padding-top:8px}
.key-remaining .badge{font-size:.73rem;font-weight:500}
@media(max-width:650px){.key-timeline-wrapper{padding:8px}.key-context-panel{padding:10px}.key-transcript{grid-template-columns:68px minmax(72px,105px) minmax(0,1fr);gap:2px 7px}.key-transcript-content{font-size:.78rem}}
</style>
<script>
function monitorTranscriptKeyRows(keys,escapeHtml) {
    const applicationName = entry => String(entry?.application || '').trim() || 'Unknown app';
    const lines=[{time:keys[0]?.key_date||'',app:applicationName(keys[0]),tokens:[]}];
    const extras=[];
    let current=lines[0];
    const addBadge=(raw,time)=>{
        current.tokens.push({kind:'badge',raw,time});
        extras.push({raw,time});
    };
    for(const entry of keys) {
        const raw=String(entry.key_pressed??'');
        const key=raw.trim().replace(/^\[(.*)\]$/,'$1').replace(/^<(.*)>$/,'$1');
        const time=String(entry.key_date||'');
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
            if(count)current.tokens.push({kind:'effect',value:'⌫ word',time});
            else addBadge(raw||'Ctrl+Backspace',time);
        }else if(/^(backspace|back|bksp|vk_back)$/i.test(key)){
            let last=null;
            for(let k=current.tokens.length-1;k>=0;k--){
                if(current.tokens[k].kind==='char'&&!current.tokens[k].deleted){last=current.tokens[k];break;}
            }
            if(last){
                last.deleted=true;
                current.tokens.push({kind:'effect',value:'⌫',time});
            }else addBadge(raw||'Backspace',time);
        }else if(/^(space|spacebar)$/i.test(key)||raw===' '){
            current.tokens.push({kind:'char',value:' ',time,deleted:false});
        }else if(/^tab$/i.test(key)||raw==='\t'){
            current.tokens.push({kind:'char',value:'    ',time,deleted:false});
        }else if(raw.length===1){
            current.tokens.push({kind:'char',value:raw,time,deleted:false});
        }else{
            // Modifiers and navigation shortcuts are not inferable text edits.
            // Preserve the event without inventing a caret position.
            addBadge(raw||'Unknown',time);
        }
    }
    const transcript=lines.map(line=>{
        const content=line.tokens.map(token=>{
            if(token.kind==='char')return `<span class="${token.deleted?'key-deleted':''}" title="${escapeHtml(token.time)}">${escapeHtml(token.value)}</span>`;
            if(token.kind==='newline')return '<span class="key-control-symbol" title="Enter">↵</span>';
            if(token.kind==='effect')return `<span class="key-control-symbol" title="Backspace at ${escapeHtml(token.time)}">${token.value}</span>`;
            return `<span class="key-special-badge" title="${escapeHtml(token.time)}">${escapeHtml(token.raw)}</span>`;
        }).join('');
        return `<div class="key-transcript-time" title="${escapeHtml(line.time||'')}">${escapeHtml((line.time||'').split(' ').pop())}</div>
            <div class="key-transcript-app" title="${escapeHtml(line.app)}">${escapeHtml(line.app)}</div>
            <div class="key-transcript-content">${content||'<span class="text-muted"> </span>'}</div>`;
    }).join('');
    const specials=extras.map(item=>`<span class="badge bg-light text-dark border" title="${escapeHtml(item.time)}">${escapeHtml(item.raw)}</span>`).join('');
    return '<div class="key-transcript" role="table" aria-label="Keyboard events with timestamps">'+transcript+'</div>'
        +(specials?'<div class="small text-muted mt-3 mb-1">Other key events</div><div class="key-remaining">'+specials+'</div>':'');
}

</script>
