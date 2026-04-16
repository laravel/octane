const chokidar = require('chokidar');

const options = {
    ignoreInitial: true,
    paths: JSON.parse(process.argv[2]),
    usePolling: process.argv[3] ? true : false,
};

/**
 * Chokidar +4 removed built-in glob support. For users on these versions,
 * we manually parse the paths to extract base directories and extensions,
 * ensuring Octane's wildcard configurations continue to function via
 * the manual "ignored" filter below.
 */
const chokidarMajorVersion = () => {
    const path = require
        .resolve('chokidar')
        .replace('index.js', 'package.json');

    return parseInt(require(path)
        .version
        .split('.')[0]
    );
};

if (chokidarMajorVersion() > 3) {
    const extractedPaths = options.paths.map(path => ({
        path: path.match(/^(?<path>.*?)(?=\*|$)/)?.groups?.path ?? path,
        extension: path.match(/^.+?(?<=\.)(?<extension>.+)/)?.groups?.extension ?? null,
    }));

    options.ignored = (path, stats) => {
        if (! stats?.isFile()) {
            return false;
        }

        const matchedPattern = extractedPaths.find(ep => path.startsWith(ep.path));

        if (! matchedPattern) {
            return true;
        }

        return matchedPattern.extension ? !path.endsWith(`.${matchedPattern.extension}`) : false;
    };

    options.paths = extractedPaths.map(ep => ep.path);
}

const { paths: watcherPaths, ...watcherOptions } = options;

chokidar.watch(watcherPaths, watcherOptions)
    .on('add', () => console.log('File added...'))
    .on('change', () => console.log('File changed...'))
    .on('unlink', () => console.log('File deleted...'))
    .on('unlinkDir', () => console.log('Directory deleted...'));
