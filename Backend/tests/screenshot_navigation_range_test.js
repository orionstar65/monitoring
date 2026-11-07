#!/usr/bin/env node
// Run: node Backend/tests/screenshot_navigation_range_test.js
// Exercise the actual screenshot-navigation function without a browser/server.
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const live=fs.readFileSync(path.resolve(__dirname,'../html/app/views/view/index.php'),'utf8');
const start=live.indexOf('function historySelectExternalScreenshot(');
const end=live.indexOf('\nfunction historySetRange(',start);
assert(start>=0&&end>start,'History navigation source block not found');
assert(live.includes("if (e.key === 'ArrowLeft')")&&live.includes("if (e.key === 'ArrowRight')"),
  'Screenshot keyboard navigation should remain enabled');
assert(live.includes('historySelectExternalScreenshot(screenshot);'),
  'Screenshot navigation must update the timeline');

const minute=60000;
const base=Date.UTC(2026,10,6,17,0,0);
const state={
  monitor:42,
  start:base-12*60*minute,
  end:base+12*60*minute,
  rangeStart:base-15*minute,
  rangeEnd:base+15*minute,
  shots:[{global_id:'stale'}],keys:[{ms:base}],visibleCacheKey:'cached',
  cursorTime:base,activeId:'screenshot_42_initial'
};
const counts={render:0,visual:0,transcript:0,domain:0,visible:0,shown:null};
const context={
  historyState:state,
  historyMillis:value=>Date.parse(value),
  historyImageUrl:value=>value,
  MonitorCctvRange:{clamp:(value,min,max)=>Math.max(min,Math.min(max,value))},
  historyRenderRuler:center=>{assert.equal(center,true);counts.render++;},
  historyShowShot:shot=>{counts.shown=shot.global_id;},
  historyUpdateVisual:()=>{counts.visual++;},
  historyLoadTranscript:()=>{counts.transcript++;},
  historyLoadDomain:()=>{counts.domain++;},
  historyScheduleVisibleShots:()=>{counts.visible++;},
  startHistoryViewer:()=>{throw Error('Should reuse same monitor history')}
};
vm.runInNewContext(live.slice(start,end),context);
function navigate(id,time){
  context.historySelectExternalScreenshot({
    global_id:id,
    capture_datetime:new Date(time).toISOString(),
    screenshot:'data/'+id+'.jpg',
    thumbnail:'data/thumb_'+id+'.jpg'
  });
}
navigate('screenshot_42_first',base+11*minute);
assert.equal(state.rangeStart,base-4*minute,'From should follow selected screenshot');
assert.equal(state.rangeEnd,base+26*minute,'To should follow selected screenshot');
assert.equal(state.cursorTime,base+11*minute);
assert.equal(state.activeId,'screenshot_42_first');
assert.equal(counts.shown,'screenshot_42_first');
assert.equal(counts.render,1);
assert.equal(counts.visual,1);
assert.equal(counts.transcript,1,'Keyboard transcript must follow navigation');
assert.equal(counts.visible,1);
assert.equal(counts.domain,0,'No expensive reload for shots in current domain');

const next=base+25*60*minute;
navigate('screenshot_42_next_day',next);
assert.equal(state.rangeStart,next-15*minute,'Cross-day From is centered on capture');
assert.equal(state.rangeEnd,next+15*minute,'Cross-day To is centered on capture');
assert.equal(state.start,next-12*60*minute,'Timeline domain should follow new day');
assert.equal(state.end,next+12*60*minute);
assert.equal(state.activeId,'screenshot_42_next_day');
assert.equal(counts.shown,'screenshot_42_next_day');
assert.equal(counts.render,2);
assert.equal(counts.visual,2);
assert.equal(counts.domain,1,'Cross-day navigation must load the new domain');
assert.equal(state.shots.length,0,'Old-domain thumbnails must be cleared');
assert.equal(state.keys.length,0,'Old-domain keyboard markers must be cleared');
console.log('PASS: screenshot keyboard navigation keeps time range, playhead, transcript and day in sync');
