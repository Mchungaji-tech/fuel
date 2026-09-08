const fs = require('fs');
const vm = require('vm');
const path = require('path');

const files = [
  'resources/views/layouts/app.php',
  'resources/views/fleet/index.php',
  'resources/views/trucks/index.php',
  'resources/views/expenses/index.php',
  'resources/views/drivers/index.php',
  'resources/views/customers/index.php',
  'resources/views/custom_tables/view.php',
  'resources/views/trips/index.php',
  'resources/views/audit/index.php'
];

let allOk = true;
for (const f of files) {
  const content = fs.readFileSync(path.join(__dirname, '..', f), 'utf8');
  const scriptRegex = /<script>([\s\S]*?)<\/script>/gi;
  let match;
  while ((match = scriptRegex.exec(content)) !== null) {
    let code = match[1]
      .replace(/<\?=[\s\S]*?\?>/g, 'true')
      .replace(/<\?php[\s\S]*?\?>/g, '');
    try {
      new vm.Script(code);
      console.log('OK:', f);
    } catch(e) {
      console.error('ERROR in ' + f + ':', e.message);
      allOk = false;
    }
  }
}
if (allOk) console.log('ALL SCRIPTS VALIDATED PERFECTLY!');
