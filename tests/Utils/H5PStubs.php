<?php

// Minimal H5P PHP stubs for tests, loaded only if the real library is absent.

if (!interface_exists('H5PFrameworkInterface')) {
    interface H5PFrameworkInterface {}
}

if (!class_exists('H5PCore')) {
    class H5PCore
    {
        public function __construct($framework, $path, $url) {}

        /**
         * Returns folder name for a library description.
         * @param array $library
         */
        public static function libraryToFolderName($library)
        {
            $machine = $library['machineName'] ?? 'Unknown';
            $maj = $library['majorVersion'] ?? 1;
            $min = $library['minorVersion'] ?? 0;

            return $machine.'-'.$maj.'.'.$min;
        }
    }
}

if (!class_exists('H5PValidator')) {
    class H5PValidator
    {
        private $framework;
        private $core;

        public function __construct($framework, $core)
        {
            $this->framework = $framework;
            $this->core = $core;
        }

        public function isValidPackage(): bool
        {
            if (!method_exists($this->framework, 'getUploadedH5pPath')) {
                return false;
            }
            $path = $this->framework->getUploadedH5pPath();
            if (!is_file($path)) {
                return false;
            }

            $zip = new \ZipArchive();
            if (true !== $zip->open($path)) {
                return false;
            }

            $required = [
                'h5p.json',
                'content/content.json',
                'H5P.SimpleHtml-1.0/library.json',
                'H5P.SimpleHtml-1.0/semantics.json',
                'H5P.SimpleHtml-1.0/simple-html.js',
            ];

            foreach ($required as $entry) {
                if (false === $zip->locateName($entry)) {
                    $zip->close();
                    return false;
                }
            }

            // Basic JSON validation
            $h5pJson = $zip->getFromName('h5p.json');
            $contentJson = $zip->getFromName('content/content.json');
            $libJson = $zip->getFromName('H5P.SimpleHtml-1.0/library.json');

            $h5p = json_decode($h5pJson, true);
            $content = json_decode($contentJson, true);
            $lib = json_decode($libJson, true);

            $zip->close();

            if (!is_array($h5p) || ($h5p['mainLibrary'] ?? null) !== 'H5P.SimpleHtml') {
                return false;
            }
            if (!is_array($content) || !array_key_exists('text', $content)) {
                return false;
            }
            if (!is_array($lib) || ($lib['machineName'] ?? null) !== 'H5P.SimpleHtml') {
                return false;
            }

            return true;
        }
    }
}

