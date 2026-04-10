const { exec } = require('child_process');

setTimeout(() => {
    const url = 'http://fantasyeurovision.test:8000';
    const cmd = process.platform === 'win32' ? `start ${url}`
              : process.platform === 'darwin'  ? `open ${url}`
              : `xdg-open ${url}`;
    exec(cmd);
}, 1500);
