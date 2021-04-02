const chokidar = require('chokidar');

const basePath = process.argv[2];

const watcher = chokidar.watch(
    [
        basePath + '/app',
        basePath + '/bootstrap',
        basePath + '/config',
        basePath + '/database',
        basePath + '/public',
        basePath + '/resources',
        basePath + '/routes',
        basePath + '/composer.lock',
        basePath + '/.env',
    ],
    {
        ignoreInitial: true,
    }
);

watcher
    .on('add', () => console.log('File added...'))
    .on('change', () => console.log('File changed...'))
    .on('unlink', () => console.log('File deleted...'))
    .on('unlinkDir', () => console.log('Directory deleted...'));
