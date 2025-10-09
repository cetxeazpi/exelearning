<?php

namespace App\Service\net\exelearning\Service\Export;

use App\Constants;
use App\Helper\net\exelearning\Helper\FileHelper;
use App\Service\net\exelearning\Service\Api\CurrentOdeUsersServiceInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExportH5PService implements ExportServiceInterface
{
    private string $exportType;
    private FileHelper $fileHelper;
    private CurrentOdeUsersServiceInterface $currentOdeUsersService;
    private TranslatorInterface $translator;

    public function __construct(
        FileHelper $fileHelper,
        CurrentOdeUsersServiceInterface $currentOdeUsersService,
        TranslatorInterface $translator,
    ) {
        $this->exportType = Constants::EXPORT_TYPE_H5P;
        $this->fileHelper = $fileHelper;
        $this->currentOdeUsersService = $currentOdeUsersService;
        $this->translator = $translator;
    }

    public function generateExportFiles(
        $user,
        $odeSessionId,
        $odeNavStructureSyncs,
        $pagesFileData,
        $odeProperties,
        $libsResourcesPath,
        $odeComponentsSyncCloneArray,
        $idevicesMapping,
        $idevicesByPage,
        $idevicesTypesData,
        $userPreferencesDtos,
        $theme,
        $elpFileName,
        $resourcesPrefix,
        $isPreview,
        $translator,
    ) {
        $exportDirPath = $this->fileHelper->getOdeSessionUserTmpExportDir($odeSessionId, $user);
        $contentDir = $exportDirPath.'content'.DIRECTORY_SEPARATOR;
        if (!is_dir($contentDir)) {
            mkdir($contentDir, 0775, true);
        }

        $projectTitle = isset($odeProperties['pp_title'])
            ? trim(strip_tags($odeProperties['pp_title']->getValue()))
            : 'eXeLearning project';
        $language = isset($odeProperties['pp_lang'])
            ? substr($odeProperties['pp_lang']->getValue(), 0, 2)
            : 'en';

        // Choose layout (env override for tests)
        $layout = getenv('EXE_H5P_LAYOUT') ?: 'column';
        if ('course-presentation' === $layout) {
            return $this->generateCoursePresentation(
                $user,
                $odeSessionId,
                $odeNavStructureSyncs,
                $pagesFileData,
                $odeProperties,
                $contentDir,
                $exportDirPath
            );
        }

        // Build Column content
        // NOTE: Some H5P platforms/configurations do not allow rich media
        // (e.g., <img>, <audio>, <video>) inside AdvancedText HTML. To ensure
        // compatibility, we:
        //  - Keep page text in a single AdvancedText block
        //  - Extract inline <img>, <audio> and <video> elements
        //  - Copy local media files into the H5P package (content/images or content/media)
        //  - Append dedicated H5P.Image / H5P.Audio / H5P.Video blocks right after
        //    the text block.
        $items = [];
        $sessionDistDir = $this->fileHelper->getOdeSessionDistDirForUser($odeSessionId, $user);
        $sessionBaseDir = $this->fileHelper->getOdeSessionDir($odeSessionId);
        $imagesDir = $contentDir.'images'.DIRECTORY_SEPARATOR; // create lazily only if needed
        $mediaDir = $contentDir.'media'.DIRECTORY_SEPARATOR;   // audio/video dir (lazy)
        $copiedImages = [];
        $copiedMedia = [];
        foreach ($odeNavStructureSyncs as $page) {
            $pageId = $page->getOdePageId();
            if (!isset($pagesFileData[$pageId])) {
                continue;
            }
            $pageData = $pagesFileData[$pageId];

            $html = '<h2>'.htmlspecialchars($pageData['name']).'</h2>';
            foreach ($pageData['blocks'] as $block) {
                foreach ($block['idevices'] as $idevice) {
                    if (!empty($idevice['htmlView'])) {
                        $html .= $idevice['htmlView'];
                    }
                }
            }

            // Extract local images, copy to content/images and return image items
            [$cleanHtml, $imageItems] = $this->extractImagesAndBuildItems(
                $html,
                $sessionDistDir,
                $sessionBaseDir,
                $odeSessionId,
                $imagesDir,
                $copiedImages
            );

            // Extract local audio (<audio src|<source>) and add H5P.Audio items
            [$cleanHtml, $audioItems] = $this->extractAudioAndBuildItems(
                $cleanHtml,
                $sessionDistDir,
                $sessionBaseDir,
                $odeSessionId,
                $mediaDir,
                $copiedMedia
            );

            // Extract local video (<video src|<source>) and add H5P.Video items
            [$cleanHtml, $videoItems] = $this->extractVideoAndBuildItems(
                $cleanHtml,
                $sessionDistDir,
                $sessionBaseDir,
                $odeSessionId,
                $mediaDir,
                $copiedMedia
            );

            // Extract external video embeds (YouTube/Vimeo) via <iframe> or known blocks
            [$cleanHtml, $externalVideoItems] = $this->extractExternalVideoAndBuildItems(
                $cleanHtml
            );

            // Extract remaining iframes (non-YouTube/Vimeo) and PDFs into H5P.IFrameEmbed
            [$cleanHtml, $iframeItems] = $this->extractOtherIframesAndBuildItems(
                $cleanHtml,
                $sessionDistDir,
                $sessionBaseDir,
                $odeSessionId,
                $mediaDir,
                $copiedMedia
            );

            $items[] = [
                'content' => [
                    'params' => ['text' => $cleanHtml],
                    'library' => 'H5P.AdvancedText 1.1',
                    'subContentId' => uniqid(),
                    'metadata' => [
                        'title' => $pageData['name'] ?? 'Page',
                        'license' => 'U',
                    ],
                ],
                'useSeparator' => 'auto',
            ];

            // Append images as separate content blocks
            foreach ($imageItems as $imgItem) {
                $items[] = $imgItem;
            }
            // Append audio blocks
            foreach ($audioItems as $audItem) {
                $items[] = $audItem;
            }
            // Append video blocks
            foreach ($videoItems as $vidItem) {
                $items[] = $vidItem;
            }
            // Append external video blocks
            foreach ($externalVideoItems as $evidItem) {
                $items[] = $evidItem;
            }
            // Append generic iframe blocks (including local PDFs)
            foreach ($iframeItems as $ifrItem) {
                $items[] = $ifrItem;
            }
        }

        $content = [
            'content' => $items,
        ];
        file_put_contents(
            $contentDir.'content.json',
            json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        // Minimal package referencing Column (platform must have the library installed)
        $h5pJson = [
            'title' => $projectTitle,
            'language' => $language,
            'mainLibrary' => 'H5P.Column',
            'embedTypes' => ['iframe'],
            'license' => 'U',
            'preloadedDependencies' => [
                [
                    'machineName' => 'H5P.Column',
                    'majorVersion' => '1',
                    'minorVersion' => '18',
                ],
            ],
        ];
        file_put_contents(
            $exportDirPath.'h5p.json',
            json_encode($h5pJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return true;
    }

    private function generateCoursePresentation(
        $user,
        $odeSessionId,
        $odeNavStructureSyncs,
        $pagesFileData,
        $odeProperties,
        string $contentDir,
        string $exportDirPath,
    ) {
        $projectTitle = isset($odeProperties['pp_title']) ? trim(strip_tags($odeProperties['pp_title']->getValue())) : 'eXeLearning project';
        $language = isset($odeProperties['pp_lang']) ? substr($odeProperties['pp_lang']->getValue(), 0, 2) : 'en';

        $slides = [];
        foreach ($odeNavStructureSyncs as $page) {
            $pageId = $page->getOdePageId();
            if (!isset($pagesFileData[$pageId])) {
                continue;
            }
            $pageData = $pagesFileData[$pageId];
            $html = '<h2>'.htmlspecialchars($pageData['name']).'</h2>';
            foreach ($pageData['blocks'] as $block) {
                foreach ($block['idevices'] as $idevice) {
                    if (!empty($idevice['htmlView'])) {
                        $html .= $idevice['htmlView'];
                    }
                }
            }
            $element = [
                'x' => 5,
                'y' => 5,
                'width' => 90,
                'height' => 90,
                'action' => [
                    'library' => 'H5P.AdvancedText 1.1',
                    'params' => ['text' => $html],
                    'subContentId' => uniqid(),
                    'metadata' => [
                        'contentType' => 'Text',
                        'license' => 'U',
                        'title' => $pageData['name'] ?? 'Page',
                    ],
                ],
                'alwaysDisplayComments' => false,
                'backgroundOpacity' => 60,
                'displayAsButton' => false,
                'invisible' => false,
                'solution' => '',
                'buttonSize' => 'big',
                'goToSlideType' => 'specified',
            ];
            $slides[] = [
                'elements' => [$element],
                'slideBackgroundSelector' => new \stdClass(),
            ];
        }

        $content = ['presentation' => ['slides' => $slides]];
        file_put_contents($contentDir.'content.json', json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $h5pJson = [
            'title' => $projectTitle,
            'language' => $language,
            'mainLibrary' => 'H5P.CoursePresentation',
            'embedTypes' => ['iframe'],
            'license' => 'U',
            'preloadedDependencies' => [
                ['machineName' => 'H5P.CoursePresentation', 'majorVersion' => '1', 'minorVersion' => '26'],
            ],
        ];
        file_put_contents($exportDirPath.'h5p.json', json_encode($h5pJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return true;
    }

    /**
     * Find local images inside HTML, copy them into content/images and return
     * [cleanHtmlWithoutImgs, imageItems].
     */
    private function extractImagesAndBuildItems(
        string $html,
        string $sessionDistDir,
        string $sessionBaseDir,
        string $odeSessionId,
        string $imagesDir,
        array &$copiedImages,
    ): array {
        $cleanHtml = $html;
        $imageItems = [];

        // Regex for <img ... src="..." ...>
        if (!preg_match_all('/<img\b[^>]*src=[\"\']([^\"\']+)[\"\'][^>]*>/i', $html, $matches, PREG_SET_ORDER)) {
            return [$cleanHtml, $imageItems];
        }

        foreach ($matches as $m) {
            $fullTag = $m[0];
            $src = $m[1];
            $localPath = $this->resolveLocalContentPath($src, $sessionDistDir, $sessionBaseDir, $odeSessionId);
            if (!$localPath || !is_file($localPath)) {
                // Skip remote or unresolved images; leave tag untouched
                continue;
            }

            // Determine destination filename
            $baseName = basename(parse_url($localPath, PHP_URL_PATH));
            $destName = $baseName;
            $i = 1;
            while (isset($copiedImages[$destName]) && $copiedImages[$destName] !== $localPath) {
                $destName = pathinfo($baseName, PATHINFO_FILENAME).'_'.$i.'.'.pathinfo($baseName, PATHINFO_EXTENSION);
                ++$i;
            }

            // Check allowed extensions for H5P packages (raster only)
            $ext = strtolower(pathinfo($destName, PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'tif', 'tiff'];
            if (!in_array($ext, $allowed, true)) {
                // Don't package unsupported image types; leave original src
                $cleanHtml = str_replace($fullTag, '', $cleanHtml);
                continue;
            }

            // Copy if not already copied
            if (!isset($copiedImages[$destName])) {
                if (!is_dir($imagesDir)) {
                    @mkdir($imagesDir, 0775, true);
                }
                @copy($localPath, $imagesDir.$destName);
                if (is_file($imagesDir.$destName)) {
                    $copiedImages[$destName] = $localPath;
                } else {
                    // Copy failed; skip creating item
                    continue;
                }
            }
            // Create H5P.Image content block and remove the <img> tag from HTML
            [$mime, $width, $height] = $this->probeImage($localPath);
            $imageParams = [
                'contentName' => 'Image',
                'file' => [
                    'path' => 'images/'.$destName,
                    'mime' => $mime,
                    'width' => $width,
                    'height' => $height,
                    'copyright' => ['license' => 'U'],
                ],
                'alt' => '',
                'title' => '',
                'decorative' => false,
                'expandImage' => 'Expand Image',
                'minimizeImage' => 'Minimize Image',
            ];
            $imageItems[] = [
                'content' => [
                    'params' => $imageParams,
                    'library' => 'H5P.Image 1.1',
                    'subContentId' => uniqid(),
                    'metadata' => [
                        'license' => 'U',
                        'contentType' => 'Image',
                        'title' => 'Image',
                    ],
                ],
                'useSeparator' => 'auto',
            ];
            $cleanHtml = str_replace($fullTag, '', $cleanHtml);
        }

        return [$cleanHtml, $imageItems];
    }

    private function probeImage(string $path): array
    {
        $mime = 'image/jpeg';
        $width = null;
        $height = null;
        if (function_exists('getimagesize')) {
            $info = @getimagesize($path);
            if ($info) {
                $width = $info[0] ?? null;
                $height = $info[1] ?? null;
                $mime = $info['mime'] ?? $mime;
            }
        }

        return [$mime, $width, $height];
    }

    private function extractAudioAndBuildItems(
        string $html,
        string $sessionDistDir,
        string $sessionBaseDir,
        string $odeSessionId,
        string $mediaDir,
        array &$copiedMedia,
    ): array {
        $cleanHtml = $html;
        $audioItems = [];

        // Match <audio ... src="...">...</audio>
        if (preg_match_all('/<audio\b[^>]*src=[\"\']([^\"\']+)[\"\'][^>]*>.*?<\/audio>/is', $cleanHtml, $aMatches, PREG_SET_ORDER)) {
            foreach ($aMatches as $m) {
                $full = $m[0];
                $src = $m[1];
                $item = $this->buildAudioItemFromSrc($src, $sessionDistDir, $sessionBaseDir, $odeSessionId, $mediaDir, $copiedMedia);
                if ($item) {
                    $audioItems[] = $item;
                    $cleanHtml = str_replace($full, '', $cleanHtml);
                }
            }
        }

        // Match <audio ...> <source src="..."> ... </audio>
        if (preg_match_all('/<audio\b[^>]*>(.*?)<\/audio>/is', $cleanHtml, $ablocks, PREG_SET_ORDER)) {
            foreach ($ablocks as $blk) {
                $full = $blk[0];
                $inner = $blk[1];
                if (preg_match('/<source\b[^>]*src=[\"\']([^\"\']+)[\"\'][^>]*>/i', $inner, $srcm)) {
                    $src = $srcm[1];
                    $item = $this->buildAudioItemFromSrc($src, $sessionDistDir, $sessionBaseDir, $odeSessionId, $mediaDir, $copiedMedia);
                    if ($item) {
                        $audioItems[] = $item;
                        $cleanHtml = str_replace($full, '', $cleanHtml);
                    }
                }
            }
        }

        return [$cleanHtml, $audioItems];
    }

    private function buildAudioItemFromSrc(
        string $src,
        string $sessionDistDir,
        string $sessionBaseDir,
        string $odeSessionId,
        string $mediaDir,
        array &$copiedMedia,
    ) {
        $localPath = $this->resolveLocalContentPath($src, $sessionDistDir, $sessionBaseDir, $odeSessionId);
        if (!$localPath || !is_file($localPath)) {
            return null;
        }
        $baseName = basename(parse_url($localPath, PHP_URL_PATH));
        $ext = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));
        $allowed = ['mp3', 'wav', 'ogg', 'm4a'];
        if (!in_array($ext, $allowed, true)) {
            return null;
        }
        $destName = $baseName;
        $i = 1;
        while (isset($copiedMedia[$destName]) && $copiedMedia[$destName] !== $localPath) {
            $destName = pathinfo($baseName, PATHINFO_FILENAME).'_'.$i.'.'.$ext;
            ++$i;
        }
        if (!isset($copiedMedia[$destName])) {
            if (!is_dir($mediaDir)) {
                @mkdir($mediaDir, 0775, true);
            }
            @copy($localPath, $mediaDir.$destName);
            if (!is_file($mediaDir.$destName)) {
                return null;
            }
            $copiedMedia[$destName] = $localPath;
        }
        $mime = $this->guessMimeByExtension($ext, 'audio');
        $audioParams = [
            'files' => [
                [
                    'path' => 'media/'.$destName,
                    'mime' => $mime,
                ],
            ],
            'autoplay' => false,
        ];

        return [
            'content' => [
                'params' => $audioParams,
                'library' => 'H5P.Audio 1.5',
                'subContentId' => uniqid(),
                'metadata' => [
                    'license' => 'U',
                    'contentType' => 'Audio',
                    'title' => 'Audio',
                ],
            ],
            'useSeparator' => 'auto',
        ];
    }

    private function extractVideoAndBuildItems(
        string $html,
        string $sessionDistDir,
        string $sessionBaseDir,
        string $odeSessionId,
        string $mediaDir,
        array &$copiedMedia,
    ): array {
        $cleanHtml = $html;
        $videoItems = [];

        // Match <video ... src="...">...</video>
        if (preg_match_all('/<video\b[^>]*src=[\"\']([^\"\']+)[\"\'][^>]*>.*?<\/video>/is', $cleanHtml, $vMatches, PREG_SET_ORDER)) {
            foreach ($vMatches as $m) {
                $full = $m[0];
                $src = $m[1];
                $item = $this->buildVideoItemFromSrc($src, $sessionDistDir, $sessionBaseDir, $odeSessionId, $mediaDir, $copiedMedia);
                if ($item) {
                    $videoItems[] = $item;
                    $cleanHtml = str_replace($full, '', $cleanHtml);
                }
            }
        }

        // Match <video ...> <source src="..."> ... </video>
        if (preg_match_all('/<video\b[^>]*>(.*?)<\/video>/is', $cleanHtml, $vblocks, PREG_SET_ORDER)) {
            foreach ($vblocks as $blk) {
                $full = $blk[0];
                $inner = $blk[1];
                if (preg_match('/<source\b[^>]*src=[\"\']([^\"\']+)[\"\'][^>]*>/i', $inner, $srcm)) {
                    $src = $srcm[1];
                    $item = $this->buildVideoItemFromSrc($src, $sessionDistDir, $sessionBaseDir, $odeSessionId, $mediaDir, $copiedMedia);
                    if ($item) {
                        $videoItems[] = $item;
                        $cleanHtml = str_replace($full, '', $cleanHtml);
                    }
                }
            }
        }

        return [$cleanHtml, $videoItems];
    }

    private function buildVideoItemFromSrc(
        string $src,
        string $sessionDistDir,
        string $sessionBaseDir,
        string $odeSessionId,
        string $mediaDir,
        array &$copiedMedia,
    ) {
        $localPath = $this->resolveLocalContentPath($src, $sessionDistDir, $sessionBaseDir, $odeSessionId);
        if (!$localPath || !is_file($localPath)) {
            return null;
        }
        $baseName = basename(parse_url($localPath, PHP_URL_PATH));
        $ext = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));
        $allowed = ['mp4', 'webm', 'ogv', 'm4v'];
        if (!in_array($ext, $allowed, true)) {
            return null;
        }
        $destName = $baseName;
        $i = 1;
        while (isset($copiedMedia[$destName]) && $copiedMedia[$destName] !== $localPath) {
            $destName = pathinfo($baseName, PATHINFO_FILENAME).'_'.$i.'.'.$ext;
            ++$i;
        }
        if (!isset($copiedMedia[$destName])) {
            if (!is_dir($mediaDir)) {
                @mkdir($mediaDir, 0775, true);
            }
            @copy($localPath, $mediaDir.$destName);
            if (!is_file($mediaDir.$destName)) {
                return null;
            }
            $copiedMedia[$destName] = $localPath;
        }
        $mime = $this->guessMimeByExtension($ext, 'video');
        $videoParams = [
            'sources' => [
                [
                    'path' => 'media/'.$destName,
                    'mime' => $mime,
                ],
            ],
            'visuals' => ['controls' => true],
        ];

        return [
            'content' => [
                'params' => $videoParams,
                'library' => 'H5P.Video 1.6',
                'subContentId' => uniqid(),
                'metadata' => [
                    'license' => 'U',
                    'contentType' => 'Video',
                    'title' => 'Video',
                ],
            ],
            'useSeparator' => 'auto',
        ];
    }

    private function extractExternalVideoAndBuildItems(string $html): array
    {
        $cleanHtml = $html;
        $videoItems = [];

        // 1) Iframe embeds (YouTube/Vimeo)
        if (preg_match_all('/<iframe\b[^>]*src=[\"\']([^\"\']+)[\"\'][^>]*><\\/iframe>|<iframe\b[^>]*src=[\"\']([^\"\']+)[\"\'][^>]*>\s*<\\/iframe>/is', $cleanHtml, $iframes, PREG_SET_ORDER)) {
            foreach ($iframes as $m) {
                $full = $m[0];
                $src = $m[1] ?: $m[2] ?? '';
                $item = $this->buildExternalVideoItemFromUrl($src);
                if ($item) {
                    $videoItems[] = $item;
                    $cleanHtml = str_replace($full, '', $cleanHtml);
                }
            }
        }

        // 2) eXe interactive-video block with <a href="...youtube...">
        if (preg_match_all('/<div\s+class=\"[^\"]*exe-interactive-video[^\"]*\"[^>]*>(.*?)<\\/div>/is', $cleanHtml, $blocks, PREG_SET_ORDER)) {
            foreach ($blocks as $blk) {
                $full = $blk[0];
                $inner = $blk[1];
                if (preg_match('/<a\b[^>]*href=[\"\']([^\"\']+)[\"\']/i', $inner, $am)) {
                    $url = $am[1];
                    $item = $this->buildExternalVideoItemFromUrl($url);
                    if ($item) {
                        $videoItems[] = $item;
                        $cleanHtml = str_replace($full, '', $cleanHtml);
                    }
                }
            }
        }

        return [$cleanHtml, $videoItems];
    }

    private function buildExternalVideoItemFromUrl(string $url)
    {
        $u = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ('' === $u) {
            return null;
        }
        $lower = strtolower($u);
        $mime = null;
        if (str_contains($lower, 'youtube.com') || str_contains($lower, 'youtu.be')) {
            $mime = 'video/youtube';
        } elseif (str_contains($lower, 'vimeo.com')) {
            $mime = 'video/vimeo';
        } else {
            return null; // unsupported provider
        }
        $videoParams = [
            'sources' => [
                [
                    'path' => $u,
                    'mime' => $mime,
                ],
            ],
            'visuals' => ['controls' => true],
        ];

        return [
            'content' => [
                'params' => $videoParams,
                'library' => 'H5P.Video 1.6',
                'subContentId' => uniqid(),
                'metadata' => [
                    'license' => 'U',
                    'contentType' => 'Video',
                    'title' => 'Video',
                ],
            ],
            'useSeparator' => 'auto',
        ];
    }

    private function extractOtherIframesAndBuildItems(
        string $html,
        string $sessionDistDir,
        string $sessionBaseDir,
        string $odeSessionId,
        string $mediaDir,
        array &$copiedMedia,
    ): array {
        $cleanHtml = $html;
        $items = [];

        // Generic iframe finder
        if (!preg_match_all('/<iframe\b([^>]*)>(.*?)<\\/iframe>/is', $cleanHtml, $iframes, PREG_SET_ORDER)) {
            return [$cleanHtml, $items];
        }
        foreach ($iframes as $m) {
            $full = $m[0];
            $attrs = $m[1];
            $src = null;
            $width = null;
            $height = null;
            if (preg_match('/src=[\"\']([^\"\']+)[\"\']/i', $attrs, $sm)) {
                $src = html_entity_decode($sm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if (preg_match('/\bwidth=[\"\'](\d+)[\"\']/', $attrs, $wm)) {
                $width = (int) $wm[1];
            }
            if (preg_match('/\bheight=[\"\'](\d+)[\"\']/', $attrs, $hm)) {
                $height = (int) $hm[1];
            }
            if (!$src) {
                continue;
            }

            $lower = strtolower($src);
            // Skip if already handled as external video
            if (str_contains($lower, 'youtube.com') || str_contains($lower, 'youtu.be') || str_contains($lower, 'vimeo.com')) {
                continue;
            }

            $item = null;
            // PDF handling: local pdfs will be copied and embedded via iframe
            if (preg_match('/\.pdf(\?.*)?$/i', parse_url($src, PHP_URL_PATH) ?? '')) {
                $local = $this->resolveLocalContentPath($src, $sessionDistDir, $sessionBaseDir, $odeSessionId);
                if ($local && is_file($local)) {
                    $baseName = basename(parse_url($local, PHP_URL_PATH));
                    $ext = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));
                    if ('pdf' === $ext) {
                        $destName = $baseName;
                        $i = 1;
                        while (isset($copiedMedia[$destName]) && $copiedMedia[$destName] !== $local) {
                            $destName = pathinfo($baseName, PATHINFO_FILENAME).'_'.$i.'.'.$ext;
                            ++$i;
                        }
                        if (!isset($copiedMedia[$destName])) {
                            if (!is_dir($mediaDir)) {
                                @mkdir($mediaDir, 0775, true);
                            }
                            @copy($local, $mediaDir.$destName);
                            if (is_file($mediaDir.$destName)) {
                                $copiedMedia[$destName] = $local;
                            } else {
                                // Could not copy; fall back to external iframe
                            }
                        }
                        if (isset($copiedMedia[$destName])) {
                            $item = $this->buildIframeItemFromUrl('media/'.$destName, $width, $height);
                        }
                    }
                }
            }

            // Generic iframe if no special handling applied
            if (!$item) {
                $item = $this->buildIframeItemFromUrl($src, $width, $height);
            }

            if ($item) {
                $items[] = $item;
                $cleanHtml = str_replace($full, '', $cleanHtml);
            }
        }

        return [$cleanHtml, $items];
    }

    private function buildIframeItemFromUrl(string $url, ?int $width, ?int $height)
    {
        $params = [
            'source' => $url,
        ];
        if ($width) {
            $params['width'] = $width;
        }
        if ($height) {
            $params['height'] = $height;
        }

        return [
            'content' => [
                'params' => $params,
                'library' => 'H5P.IFrameEmbed 1.0',
                'subContentId' => uniqid(),
                'metadata' => [
                    'license' => 'U',
                    'contentType' => 'IFrame',
                    'title' => 'Embedded',
                ],
            ],
            'useSeparator' => 'auto',
        ];
    }

    private function guessMimeByExtension(string $ext, string $kind): string
    {
        $ext = strtolower($ext);
        $map = [
            'audio' => [
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                'ogg' => 'audio/ogg',
                'm4a' => 'audio/mp4',
            ],
            'video' => [
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
                'ogv' => 'video/ogg',
                'm4v' => 'video/x-m4v',
            ],
        ];

        return $map[$kind][$ext] ?? (('audio' === $kind) ? 'audio/mpeg' : 'video/mp4');
    }

    /**
     * Resolve a session-local content path from an img src URL.
     */
    private function resolveLocalContentPath(string $src, string $sessionDistDir, string $sessionBaseDir, string $odeSessionId): ?string
    {
        $srcClean = html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Case 1: path into dist content (content/...)
        $posContent = strpos($srcClean, '/content/');
        if (false === $posContent) {
            $posContent = strpos($srcClean, 'content/');
        }
        if (false !== $posContent) {
            $relative = substr($srcClean, $posContent);
            $relative = str_replace(['..', '\\'], ['', '/'], $relative);
            $relativeFs = str_replace('/', DIRECTORY_SEPARATOR, $relative);
            // Primary: user-specific dist dir
            $candidate = rtrim($sessionDistDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$relativeFs;
            if (is_file($candidate)) {
                return $candidate;
            }
            // Fallback: session dist dir root (without user subdir)
            $distRoot = $this->fileHelper->getOdeSessionDistDir($odeSessionId);
            if ($distRoot) {
                $candidateRoot = rtrim($distRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$relativeFs;
                if (is_file($candidateRoot)) {
                    return $candidateRoot;
                }
            }
            // Fallback: session base directory (outside dist)
            $candidateBase = rtrim($sessionBaseDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$relativeFs;
            if (is_file($candidateBase)) {
                return $candidateBase;
            }
        }

        // Case 2: url contains the session id (e.g., /files/tmp/.../<odeSessionId>/...)
        $posSess = strpos($srcClean, $odeSessionId);
        if (false !== $posSess) {
            $after = substr($srcClean, $posSess + strlen($odeSessionId));
            $after = ltrim($after, '/');
            $candidate = rtrim($sessionBaseDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $after);
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        // Case 3: placeholder {{context_path}}/...
        if (str_starts_with($srcClean, '{{context_path}}/')) {
            $after = substr($srcClean, strlen('{{context_path}}/'));
            $candidate = rtrim($sessionBaseDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $after);
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
