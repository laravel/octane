const chokidar = require('chokidar');

const paths = JSON.parse(process.argv[2]);
const poll = process.argv[3] ? true : false;

const watcher = chokidar.watch(paths, {
    ignoreInitial: true,
    usePolling: poll,
});

watcher
    .on('add', () => console.log('File added...'))
    .on('change', () => console.log('File changed...'))
    .on('unlink', () => console.log('File deleted...'))
    .on('unlinkDir', () => console.log('Directory deleted...'));
