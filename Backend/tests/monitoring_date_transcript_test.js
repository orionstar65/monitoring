#!/usr/bin/env node
// Run: node Backend/tests/monitoring_date_transcript_test.js
// Pure UI behavior tests; no browser, database, or network.
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const read=p=>fs.readFileSync(path.resolve(__dirname,'..',p),'utf8');
const between=(s,a,b)=>{
  const begin=s.indexOf(a),end=s.indexOf(b,begin);
  assert(begin>=0&&end>begin,'missing source block: '+a);
  return s.slice(begin,end);
};
const live=read('html/app/views/view/index.php');
const shared=read('html/app/views/view/partials/key_history_shared.php');
const dates=vm.runInNewContext(between(live,'function unifiedPresetRange(','function unifiedField(')+'\nunifiedPresetRange;');
const now=new Date(2026,10,6,9,0);
const ranges={
  today:['2026-11-06','2026-11-06'],
  yesterday:['2026-11-05','2026-11-05'],
  this_week:['2026-11-02','2026-11-06'],
  last_week:['2026-10-26','2026-11-01'],
  this_month:['2026-11-01','2026-11-06'],
  last_month:['2026-10-01','2026-10-31'],
  last_7_days:['2026-10-31','2026-11-06'],
  last_30_days:['2026-10-08','2026-11-06']
};
for(const [preset,expected] of Object.entries(ranges)){
  const actual=dates(preset,now);
  assert.deepEqual([actual.start,actual.end],expected,preset);
}
const renderer=vm.runInNewContext(between(shared,'function monitorTranscriptKeyRows(','/** Geometry shared')+'\nmonitorTranscriptKeyRows;');
const escape=t=>String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const event=(id,key)=>({event_id:id,key_pressed:key,key_date:'2026-11-06 09:00:01',application:'Test Application'});
const html=renderer([event(51,'LEFT'),event(52,'RIGHT'),event(53,'A')],escape);
assert.equal((html.match(/class="key-special-badge"/g)||[]).length,2);
assert(!html.includes('Other key events'),'Must not list the same key a second time');
assert(html.includes('recorded event #51')&&html.includes('recorded event #52'));
const repeated=renderer([event(51,'LEFT'),event(54,'LEFT')],escape);
assert.equal((repeated.match(/class="key-special-badge"/g)||[]).length,2,
  'Distinct database events must not be silently deduplicated');
assert(repeated.includes('recorded event #54'));
console.log('PASS: eight date presets and special-key transcript rendering/provenance');
