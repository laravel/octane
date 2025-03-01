const chokidar = require('chokidar');

const paths = JSON.parse(process.argv[2]);
const poll = process.argv[3] ? true : false;

/*
* Chokidar removed support for glob in version 4
* As a result some minor changes are needed to support the wildcards
 */
const chokidarPackagePath = require.resolve('chokidar').replace('index.js', 'package.json');
const chokidarVersionV4 = require(chokidarPackagePath).version.startsWith('4.');

const extractWildcardExtension = (path) => {
    // Match patterns like *.php, **/*.blade.php
    const match = path.match(/\/\*\.((\w|\.)+)$/);
    return match ? match[1] : null;
};

const getPathBeforeWildcard = (path) => {
    const match = path.match(/^(.*?)(\*|$)/);
    return match ? match[1] : path;
};

const extractedPaths = paths.map(path => {
    return { path: getPathBeforeWildcard(path), extension: extractWildcardExtension(path) };
});

const watcherPaths = chokidarVersionV4 ? extractedPaths.map(ep => ep.path) : paths;
const watcherIgnoredFn = chokidarVersionV4 ? (path, stats) => {
    if (! stats?.isFile()) {
        return false;
    }
    const matchedPattern = extractedPaths.find(ep => path.startsWith(ep.path));

    if (! matchedPattern) {
        return true;
    }

    return matchedPattern.extension ? !path.endsWith(`.${matchedPattern.extension}`) : false;
} : undefined;

const watcher = chokidar.watch(watcherPaths, {
    ignoreInitial: true,
    usePolling: poll,
    ignored: watcherIgnoredFn,
});

watcher
    .on('add', () => console.log('File added...'))
    .on('change', () => console.log('File changed...'))
    .on('unlink', () => console.log('File deleted...'))
    .on('unlinkDir', () => console.log('Directory deleted...'));
