const fs = require('fs');
const s = fs.readFileSync('c:/Users/nbika/Downloads/autoshop/save_invoice.php', 'utf8');
let open=0, close=0;
const lines=s.split('\n');
for(let i=0;i<lines.length;i++){
  open += (lines[i].match(/\{/g)||[]).length;
  close += (lines[i].match(/\}/g)||[]).length;
  if(close>open){
    console.log('Mismatch at line', i+1, 'close', close, 'open', open);
    console.log('Line:', lines[i]);
    process.exit(0);
  }
}
console.log('Done. open',open,'close',close);
if(open!==close) process.exit(2);
