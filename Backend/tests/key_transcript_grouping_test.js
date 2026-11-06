#!/usr/bin/env node
// node Backend/tests/key_transcript_grouping_test.js
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const content=fs.readFileSync(path.resolve(__dirname,
    '../html/app/views/view/partials/key_history_shared.php'),'utf8');
const start=content.indexOf('function monitorTranscriptKeyRows(');
const end=content.indexOf('/** Geometry shared',start);
assert(start>=0 && end>start,'Shared transcript formatter missing');
const render=new Function(content.slice(start,end)+';return monitorTranscriptKeyRows;')();
const esc=x=>String(x).replace(/[&<>"']/g,
    c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const records=[
    [1,'12:00:00','Editor','a'],
    [2,'12:00:01','Editor','UP'],
    [3,'12:00:02','Editor','UP'],
    [4,'12:00:02','Editor','UP'],
    [5,'12:00:03','Editor','Enter'],
    [6,'12:00:04','Editor','b'],
    [7,'12:00:05','Browser','c'],
    [7,'12:00:05','Browser','c'],
    [8,'12:00:06','Editor','d'],
].map(([event_id,time,application,key_pressed])=>({
    event_id,key_date:'2026-11-06 '+time,application,key_pressed
}));
const html=render(records,esc);
const count=(text)=>html.split(text).length-1;
assert.equal(count('class="key-transcript-app-row"'),3,
    'Only changes of application should produce application headers');
assert.equal(count('class="key-transcript-time"'),4,
    'Enter separates time rows but does not repeat the application header');
assert.equal(count('UP ×3'),1,
    'Consecutive special keys should be represented by one counted badge');
assert.equal(count('>c</span>'),1,
    'Duplicated event ID must appear once');
assert(!html.includes('class="key-transcript-app" title="Editor"></div>'),
    'Application headings should contain their name');
const xss=render([{key_date:'2026-11-06 12:00:00',
    event_id:9,application:'<script>',key_pressed:'<img>'}],esc);
assert(!xss.includes('<script>')&&!xss.includes('<img>'),
    'Application names and key labels must escape HTML');
assert(render([],esc).includes('No keyboard events'),
    'Empty selections should show a clear empty state');

// Visible Backspace markers are redundant when the affected characters are
// already struck through. Check both the single-character and word variants.
const deleted=render([
    [10,'h'],[11,'i'],[12,'Backspace'],[13,'Ctrl+Backspace']
].map(([event_id,key_pressed])=>({
    event_id,key_pressed,key_date:'2026-11-06 12:30:00',application:'Editor'
})),esc);
assert.equal((deleted.match(/class="key-deleted"/g)||[]).length,2,
    'Each Backspace must strike through the corresponding character');
assert(!deleted.includes('⌫'),
    'Do not render a Backspace glyph in addition to the strikethrough');
assert(!deleted.includes('>Backspace</span>'),
    'Successfully applied Backspace must not produce a duplicate badge');
const unmatched=render([{event_id:14,key_pressed:'Backspace',
    key_date:'2026-11-06 12:30:00',application:'Editor'}],esc);
assert(unmatched.includes('>Backspace</span>'),
    'A Backspace with no preceding character should still be recorded as a labeled special key');

// History overrides must not reintroduce the old three-column grid.
const history=fs.readFileSync(path.resolve(__dirname,
    '../html/app/views/view/index.php'),'utf8');
const overrides=[...history.matchAll(/\.history-transcript \.key-transcript\{([^}]*)\}/g)];
assert(overrides.length>=1,'History transcript CSS overrides missing');
for(const match of overrides) {
    assert(/grid-template-columns:\s*(?:56px|64px)\s+minmax\(0,1fr\)/.test(match[1]),
        'History transcript must have only timestamp and content columns');
}
console.log('PASS app grouping, repeat counts, ID deduplication, escaping, 2-column history and Backspace strikethrough');
