#!/usr/bin/env node
/* Build the user guide without a browser.
 *
 *   node scripts/build-guide.js
 *
 * The demonstration's own code does all the work — this only supplies the few
 * DOM calls it touches on the way, and writes the result next to the demo.
 * Charts travel as configurations and are drawn by Chart.js when the guide is
 * opened, so a build with no screen still produces a guide with live charts.
 */
const fs=require('fs'),vm=require('vm');
const path=require('path');
const ROOT=path.resolve(__dirname,'..');
const F=path.join(ROOT,'fpc_eval360_demo.html');
const src=fs.readFileSync(F,'utf8');
const script=src.match(/<script>([\s\S]*)<\/script>/)[1];
const styles=Array.from(src.matchAll(/<style>([\s\S]*?)<\/style>/g)).map(m=>m[1]);
const made={};
function mk(tag){
  const e={tagName:(tag||'div').toUpperCase(),innerHTML:'',textContent:'',value:'',dataset:{},
    style:{cssText:'',setProperty(){},removeProperty(){}},offsetHeight:238,
    classList:{add(){},remove(){},contains:()=>false},
    addEventListener(){},appendChild(){},remove(){},focus(){},click(){},
    setAttribute(){},getAttribute(){},querySelector:()=>null,querySelectorAll:()=>[],
    closest:()=>null,replaceWith(){},parentNode:{replaceChild(){}}};
  return e;
}
function el(id){ if(!made[id]) made[id]=mk(); return made[id]; }
const ctx={
  document:{getElementById:el,addEventListener(){},createElement:mk,
    querySelectorAll:q=>q==='style'?styles.map(t=>({textContent:t})):[],
    querySelector:()=>null,documentElement:{style:{setProperty(){},removeProperty(){}}},
    body:{appendChild(){}}},
  window:{scrollTo(){},open:()=>({}),print(){},addEventListener(){}},
  Blob:class{},URL:{createObjectURL:()=>'',revokeObjectURL(){}},
  requestAnimationFrame:f=>setTimeout(f,0),
  console:{log(){},warn(){}},setTimeout,Number,String,Object,Array,Math,Date,JSON,isNaN,
  parseInt,parseFloat,Set,Map,Promise,FormData:class{}};
ctx.window.document=ctx.document;
vm.createContext(ctx);
vm.runInContext(script+';globalThis.showView=()=>{};globalThis.toast=()=>{};globalThis.__b=buildGuideDocument;globalThis.__n=()=>guideCharts.length;',ctx);
ctx.__b((d,t,c)=>process.stdout.write(`\r  capturing ${d}/${t} — ${c.slice(0,56).padEnd(58)}`))
 .then(html=>{
   const out=path.join(ROOT,'FPC_Eval360_User_Guide.html');
   fs.writeFileSync(out,html);
   console.log(`\n\n  written: ${out}`);
   console.log(`  ${(html.length/1024).toFixed(0)} KB · ${(html.match(/class="g-ch"/g)||[]).length} chapters · ${(html.match(/class="fig"/g)||[]).length} figures · ${ctx.__n()} charts`);
 }).catch(e=>{console.error('\nFAILED:',e);process.exit(1);});
