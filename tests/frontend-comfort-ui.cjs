const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),{pathToFileURL}=require('node:url'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root=path.resolve(__dirname,'..'),output=path.join(root,'tmp/frontend-comfort');
fs.mkdirSync(output,{recursive:true});
const fixture=path.join(output,'preview.html');
fs.writeFileSync(fixture,execFileSync('php',['tests/frontend-comfort-render.php','--preview'],{cwd:root,maxBuffer:8*1024*1024}));
(async()=>{const browser=await chromium.launch({headless:true});try{
  const page=await browser.newPage({viewport:{width:1700,height:1100}});
  await page.route(/^https?:/,route=>route.abort());
  await page.goto(pathToFileURL(fixture).href);
  await page.locator('#run').click();
  await page.waitForFunction(()=>/^(PASS|FAIL):/.test(document.getElementById('results').textContent),{},{timeout:60000});
  const result=await page.locator('#results').innerText();
  fs.writeFileSync(path.join(output,'checks.txt'),result);
  assert.match(result,/^PASS:/,result);
  console.log(result.split('\n')[0]+' — real product dialog/image fallback, responsive fixtures; no database or upstream requests.');
}finally{await browser.close();}})().catch(error=>{console.error(error);process.exit(1);});
