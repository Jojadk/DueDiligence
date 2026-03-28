<?php
namespace Core;

class Asset
{
    private static $cacheDir = ASSETS_DIR . '/cache';
    private static $webCacheDir = 'assets/cache';

    public static function css($path)
    {
        return self::process($path, 'css');
    }

    public static function js($path)
    {
        return self::process($path, 'js');
    }

    private static function process($path, $type)
    {
        // $path is relative to web root, e.g. 'assets/js/core.js'
        $fsPath = ROOT_DIR . '/' . $path;

        if (!file_exists($fsPath)) {
            return $path; // Fallback
        }

        // Check global config for cache toggle
        if (defined('ASSET_CACHE_ENABLED') && !ASSET_CACHE_ENABLED) {
            // Return request to original file with cache busting query param
            return $path . '?v=' . filemtime($fsPath);
        }

        // Ensure cache dir exists
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0777, true);
        }

        $hash = md5($path . filemtime($fsPath));
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $minFilename = "{$filename}.{$hash}.min.{$type}";
        $fsCachePath = self::$cacheDir . '/' . $minFilename;
        $webCachePath = self::$webCacheDir . '/' . $minFilename;

        // Check if cached version exists
        if (file_exists($fsCachePath)) {
            return $webCachePath;
        }

        // Clean up old versions of this file
        foreach (glob(self::$cacheDir . "/{$filename}.*.min.{$type}") as $oldFile) {
            unlink($oldFile);
        }

        // Minify
        $content = file_get_contents($fsPath);
        $minified = ($type === 'css') ? self::minifyCss($content) : self::minifyJs($content);

        file_put_contents($fsCachePath, $minified);

        return $webCachePath;
    }

    private static function minifyCss($css)
    {
        // Minification disabled to prevent corruption
        return $css;
    }

    private static function minifyJs($js)
    {
        // Minification disabled to prevent corruption
        return $js;
    }

    public static function clearCache()
    {
        if (is_dir(self::$cacheDir)) {
            $files = glob(self::$cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
