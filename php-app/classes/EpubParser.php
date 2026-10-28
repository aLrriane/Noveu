<?php
// classes/EpubParser.php

class EpubParser {
    private $zip;
    private $file;

    public function __construct($filePath) {
        $this->file = $filePath;
        $this->zip = new ZipArchive();
    }

    public function parse() {
        if ($this->zip->open($this->file) !== TRUE) {
            return false;
        }

        $opfPath = $this->getOpfPath();
        if (!$opfPath) return false;

        $opfContent = $this->zip->getFromName($opfPath);
        if (!$opfContent) return false;

        $xml = simplexml_load_string($opfContent);
        if (!$xml) return false;

        // Get Manifesto
        $manifest = [];
        foreach ($xml->manifest->item as $item) {
            $manifest[(string)$item['id']] = (string)$item['href'];
        }

        // Iterate Spine
        $chapters = [];
        $count = 1;
        $dir = dirname($opfPath) == '.' ? '' : dirname($opfPath) . '/';

        foreach ($xml->spine->itemref as $itemref) {
            $idref = (string)$itemref['idref'];
            
            if (isset($manifest[$idref])) {
                $fileKey = $dir . $manifest[$idref];
                $htmlContent = $this->zip->getFromName($fileKey);
                
                if ($htmlContent) {
                    $parsed = $this->cleanHtml($htmlContent);
                    
                    // Only save if meaningful content exists
                    if (!empty($parsed['content']) && strlen($parsed['content']) > 50) {
                        $chapters[] = [
                            'chapter_number' => $count++,
                            'title' => $parsed['title'] ?: "Chapter " . ($count - 1),
                            'content' => $parsed['content'],
                        ];
                    }
                }
            }
        }

        $this->zip->close();
        return $chapters;
    }

    private function getOpfPath() {
        $container = $this->zip->getFromName('META-INF/container.xml');
        if (!$container) return false;

        $xml = simplexml_load_string($container);
        foreach ($xml->rootfiles->rootfile as $file) {
            if ((string)$file['media-type'] == 'application/oebps-package+xml') {
                return (string)$file['full-path'];
            }
        }
        return false;
    }

    private function cleanHtml($html) {
        // 1. Decode HTML entities first (converts &lt; back to < so we can catch it)
        $html = html_entity_decode($html, ENT_QUOTES | ENT_XML1, 'UTF-8');

        // 2. Nuke Head, Scripts, and Styles
        $html = preg_replace('/<head[^>]*>([\s\S]*?)<\/head>/i', '', $html);
        $html = preg_replace('/<script[^>]*>([\s\S]*?)<\/script>/i', '', $html);
        $html = preg_replace('/<style[^>]*>([\s\S]*?)<\/style>/i', '', $html);

        // 3. Extract Title (before stripping tags)
        $title = "";
        if (preg_match('/<h[1-2][^>]*>(.*?)<\/h[1-2]>/i', $html, $matches)) {
            $title = strip_tags($matches[1]);
        }

        // 4. Extract Body content only
        if (preg_match('/<body[^>]*>([\s\S]*?)<\/body>/i', $html, $matches)) {
            $html = $matches[1];
        }

        // 5. Convert Block Tags to Newlines (The Smart Part)
        // Convert closing paragraphs and divs to double newlines
        $html = preg_replace('/<\/(p|div|h[1-6]|li)>/i', "\n\n", $html);
        // Convert breaks to single newline
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);

        // 6. NUCLEAR OPTION: Remove ALL remaining tags
        // This regex finds anything starting with < and ending with > and deletes it
        $clean = preg_replace('/<[^>]*>/', '', $html);

        // 7. Cleanup White Space
        // Remove confusing non-breaking spaces
        $clean = str_replace("\xC2\xA0", ' ', $clean); 
        // Reduce 3+ newlines to 2
        $clean = preg_replace("/\n\s*\n\s*\n/", "\n\n", $clean);
        
        return ['title' => trim($title), 'content' => trim($clean)];
    }
}
?>