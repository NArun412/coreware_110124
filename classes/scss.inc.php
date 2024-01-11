<?php
if (version_compare(PHP_VERSION, '5.6') < 0) {
    throw new \Exception('scssphp requires PHP 5.6 or above');
}

if (! class_exists('ScssPhp\ScssPhp\Version', false)) {
    require_once __DIR__ . '/src/Base/Range.php';
    require_once __DIR__ . '/src/Block.php';
    require_once __DIR__ . '/src/Cache.php';
    require_once __DIR__ . '/src/Colors.php';
    require_once __DIR__ . '/src/Compiler.php';
    require_once __DIR__ . '/src/Compiler/Environment.php';
    require_once __DIR__ . '/src/Exception/CompilerException.php';
    require_once __DIR__ . '/src/Exception/ParserException.php';
    require_once __DIR__ . '/src/Exception/RangeException.php';
    require_once __DIR__ . '/src/Exception/ServerException.php';
    require_once __DIR__ . '/src/Formatter.php';
    require_once __DIR__ . '/src/Formatter/Compact.php';
    require_once __DIR__ . '/src/Formatter/Compressed.php';
    require_once __DIR__ . '/src/Formatter/Crunched.php';
    require_once __DIR__ . '/src/Formatter/Debug.php';
    require_once __DIR__ . '/src/Formatter/Expanded.php';
    require_once __DIR__ . '/src/Formatter/Nested.php';
    require_once __DIR__ . '/src/Formatter/OutputBlock.php';
    require_once __DIR__ . '/src/Node.php';
    require_once __DIR__ . '/src/Node/Number.php';
    require_once __DIR__ . '/src/Parser.php';
    require_once __DIR__ . '/src/SourceMap/Base64.php';
    require_once __DIR__ . '/src/SourceMap/Base64VLQ.php';
    require_once __DIR__ . '/src/SourceMap/SourceMapGenerator.php';
    require_once __DIR__ . '/src/Type.php';
    require_once __DIR__ . '/src/Util.php';
    require_once __DIR__ . '/src/Version.php';
}
