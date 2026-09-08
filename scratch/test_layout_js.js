const fs = require('fs');
const content = fs.readFileSync('resources/views/layouts/app.php', 'utf8');
const scriptRegex = /<script>([\s\S]*?)<\/script>/gi;
let idx = 0;
let match;
while ((match = scriptRegex.exec(content)) !== null) {
  idx++;
  let code = match[1].replace(/<\?=(.*?)\?>/g, 'true').replace(/<\?php[\s\S]*?\?>/g, '');
  try {
    new Function(code);
    console.log('Script #' + idx + ' is OK');
  } catch(e) {
    console.error('Script #' + idx + ' ERROR:', e.message);
    const lines = code.split('\n');
    for (let i = 0; i < lines.length; i++) {
      try { new Function(lines.slice(0, i+1).join('\n')); }
      catch(err) {
        if (!err.message.includes('Unexpected end of input')) {
          console.log('Failing around line', i+1, ':', lines[i]);
          break;
        }
      }
    }
  }
}
