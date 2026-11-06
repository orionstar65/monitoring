#!/usr/bin/env node
// Run: node Backend/tests/keyboard_history_toolbar_test.js
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const file=fs.readFileSync(path.resolve(__dirname,'../html/app/views/view/logbrowse.php'),'utf8');
const shared=fs.readFileSync(path.resolve(__dirname,'../html/app/views/view/partials/key_history_shared.php'),'utf8');
const start=file.indexOf('function createKeyTimeline(logs) {');
const end=file.indexOf('async function loadFullKeyTimeline(state)',start);
assert(start>=0&&end>start,'Keyboard history renderer not found');
const fragment=file.slice(start,end);
const wrapper='const KEY_RULER_SCALES={near:55,medium:14,wide:2.5};'
    +'let keyRulerState=null;'
    +'const keyEventMillis=value=>new Date(String(value).replace(" ","T")).getTime();'
    +'const requestAnimationFrame=()=>{};'
    +fragment
    +'\nreturn createKeyTimeline([{date:"2026-11-06 14:05:00",monitor_ip_id:42}]);';
const html=new Function(wrapper)();
const controls=[
  'keyRangeStart','keyRangeEnd','keyTimelineScale',
  'keyZoomOut','keyZoomIn','keyFitTimeline',
  'keyPreviousRange','keyNextRange','keyTimelineRuler','keyContextBody'
];
for(const id of controls){
  assert.equal((html.match(new RegExp('id="'+id+'"','g'))||[]).length,1,id+' must occur exactly once');
}
assert.equal((html.match(/class="key-history-toolbar"/g)||[]).length,1);
for(const id of controls.slice(0,8)){
  assert(html.indexOf('id="'+id+'"')<html.indexOf('id="keyTimelineViewport"'),id+' must be in action bar');
}
assert(html.indexOf('id="keyContextBody"')>html.indexOf('id="keyTimelineViewport"'),'Transcript must follow the timeline');
assert(file.includes('function fitKeyRuler()'),'Fit button must implement behavior');
assert(file.includes("event.target.closest?.('#keyFitTimeline')"),'Fit button must be wired');
assert(file.includes('MonitorCctvRange.drag'),'Range pan and resize must use shared geometry');
assert(shared.includes('.key-history-toolbar{display:flex'),'Toolbar CSS is missing');
console.log('PASS: Keyboard History toolbar with range inputs, scale, zoom, fit, navigation and transcript');
