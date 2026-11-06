#!/usr/bin/env node
// Run: node Backend/tests/timeline_geometry_test.js
// Standalone CCTV timeline geometry test; does not need a browser, server or database.
'use strict';
const fs=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const source=fs.readFileSync(path.resolve(__dirname,'../html/app/views/view/partials/key_history_shared.php'),'utf8');
const start=source.indexOf('const MonitorCctvRange = Object.freeze(');
const end=source.indexOf('\n});',start);
assert(start>=0&&end>=0,'Shared CCTV geometry not found');
const engine=vm.runInNewContext(source.slice(start,end+4)+'\nMonitorCctvRange;',{});
const ruler={getBoundingClientRect:()=>({left:100,width:1000})};
const state={start:0,end:60000000,rangeStart:20000000,rangeEnd:21000000};
assert.equal(engine.point({clientX:100},ruler,state),state.start);
assert.equal(engine.point({clientX:1100},ruler,state),state.end);
assert.equal(engine.point({clientX:600},ruler,state),state.end/2);
assert.equal(engine.hit(state,state.rangeStart,1000),'start');
assert.equal(engine.hit(state,state.rangeEnd,1000),'end');
assert.equal(engine.hit(state,(state.rangeStart+state.rangeEnd)/2,1000),'pan');
assert.equal(engine.hit(state,30000000,1000,{seek:true,cursor:20500000}),'seek');
assert.equal(engine.hit(state,20500000,1000,{seek:true,cursor:20500000}),'seek');
const narrow={...state,rangeEnd:state.rangeStart+5000};
assert.equal(engine.hit(narrow,narrow.rangeStart+2500,1000),'pan');
function asArray(value){return Array.from(value)}
assert.deepEqual(asArray(engine.drag(state,'pan',20000000,21000000,20500000,21500000)),[21000000,22000000]);
assert.deepEqual(asArray(engine.drag(state,'pan',20000000,21000000,20500000,-90000000)),[0,1000000]);
assert.deepEqual(asArray(engine.drag(state,'start',20000000,21000000,20500000,20999999)),[20499999,21000000]);
assert.deepEqual(asArray(engine.drag(state,'end',20000000,21000000,20500000,0)),[20000000,20500000]);
const shifted=engine.drag(state,'pan',20000000,21000000,20500000,21500000);
assert.equal(shifted[1]-shifted[0],1000000);
assert.equal(engine.zoomScroll({oldPx:3,newPx:6,domainStart:0,anchorTime:600000,anchorX:20,scrollWidth:500}),40);
console.log('PASS: CCTV timeline geometry: seek, pan, resize, zoom anchor and bounds');
