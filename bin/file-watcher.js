const chokidar = require('chokidar');

const paths = JSON.parse(process.argv[2]);

const watcher = chokidar.watch(paths, {
    ignoreInitial: true,
});

watcher
    .on('add', () => console.log('File added...'))
    .on('change', () => console.log('File changed...'))
    .on('unlink', () => console.log('File deleted...'))
    .on('unlinkDir', () => console.log('Directory deleted...'));
