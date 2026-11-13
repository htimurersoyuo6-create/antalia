<?php

/**
 * Blog govdesini guvenli HTML olarak temizler.
 * Izin listesi tabanli DOM islemesi XSS etiket/ozelliklerini ciktiya tasimaz.
 */
if (!function_exists('makale_guvenli_html')) {
    function makale_guvenli_html(?string $html, ?string $sayfaBasligi = null): string
    {
        $html = (string) $html;
        if (trim($html) === '') return '';

        if ($sayfaBasligi !== null && preg_match('~^\s*<h1\b[^>]*>(.*?)</h1>\s*~is', $html, $ilk)) {
            $baslik = html_entity_decode(strip_tags($ilk[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $normal = static fn(string $v): string => mb_strtolower(trim(preg_replace('~\s+~u', ' ', $v) ?? $v), 'UTF-8');
            if ($normal($baslik) === $normal($sayfaBasligi)) {
                $html = preg_replace('~^\s*<h1\b[^>]*>.*?</h1>\s*~is', '', $html, 1) ?? $html;
            }
        }

        // Makale basligindan sonra sayfada ikinci H1 olusmasin.
        $html = preg_replace('~<h1(\s[^>]*)?>(.*?)</h1>~is', '<h2$1>$2</h2>', $html) ?? $html;

        $allowed = array_flip(explode('|', 'p|h2|h3|h4|h5|h6|ul|ol|li|strong|em|b|i|u|s|br|hr|table|thead|tbody|tfoot|tr|th|td|a|blockquote|span|div|img|figure|figcaption|code|pre|small|sup|sub|time|mark|del|ins|wbr'));
        $removeWithContents = array_flip(['script','style','iframe','object','embed','form','input','button','select','textarea','link','meta','base','applet','frame','frameset','noscript','svg','math']);
        $globalAttrs = ['class','style','title','lang','dir','role','aria-label','id'];
        $byTag = [
            'a' => ['href','target','rel'],
            'img' => ['src','alt','width','height','loading'],
            'ol' => ['start','type'],
            'li' => ['value'],
            'td' => ['colspan','rowspan','align'],
            'th' => ['colspan','rowspan','scope','align'],
            'time' => ['datetime'],
        ];
        $safeUrl = static function (string $url, bool $image = false): string {
            $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (preg_match('~[\x00-\x20\x7f]~', $url)) return '#';
            if ($url === '' || str_starts_with($url, '//')) return '#';
            if ($url[0] === '/' || $url[0] === '#' || $url[0] === '?') return $url;
            if (preg_match('~^https?://~i', $url)) return $url;
            if (!$image && preg_match('~^(mailto|tel):~i', $url)) return $url;
            if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) return '#';
            return $url;
        };
        $safeStyle = static function (string $style): string {
            $ok = [];
            $properties = ['color','background-color','font-weight','font-style','text-decoration','text-align','margin','margin-top','margin-bottom','padding','padding-left','font-size','line-height','border','border-radius','width','max-width','height','text-indent'];
            foreach (explode(';', $style) as $declaration) {
                if (!str_contains($declaration, ':')) continue;
                [$name,$value] = array_map('trim', explode(':', $declaration, 2));
                $name = strtolower($name);
                if (!in_array($name, $properties, true) || $value === '' || preg_match('~url\s*\(|expression|javascript|vbscript|@import|[<>]~i', $value)) continue;
                if (!preg_match('~^[#(),.%\w\s+\-/]+$~u', $value)) continue;
                $ok[] = $name.':'.$value;
            }
            return implode(';', $ok);
        };

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="__article_root__">'.$html.'</div></body></html>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded || !$doc->getElementsByTagName('body')->item(0)) {
            return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }
        $root = $doc->getElementsByTagName('body')->item(0)->firstChild;
        if (!$root) return '';

        $clean = function (DOMNode $parent) use (&$clean, $allowed, $removeWithContents, $globalAttrs, $byTag, $safeUrl, $safeStyle): void {
            foreach (iterator_to_array($parent->childNodes) as $node) {
                if (!$node instanceof DOMElement) continue;
                $tag = strtolower($node->tagName);
                if (isset($removeWithContents[$tag])) {
                    $parent->removeChild($node);
                    continue;
                }
                if (!isset($allowed[$tag])) {
                    $clean($node);
                    while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
                    $parent->removeChild($node);
                    continue;
                }
                foreach (iterator_to_array($node->attributes) as $attr) {
                    $name = strtolower($attr->name);
                    if (!in_array($name, $globalAttrs, true) && !in_array($name, $byTag[$tag] ?? [], true)) {
                        $node->removeAttributeNode($attr);
                        continue;
                    }
                    $value = trim($attr->value);
                    if ($name === 'style') {
                        $value = $safeStyle($value);
                        if ($value === '') $node->removeAttribute($name); else $node->setAttribute($name, $value);
                    } elseif ($name === 'id') {
                        if (!preg_match('~^[A-Za-z][A-Za-z0-9_:.-]{0,79}$~', $value)) $node->removeAttribute($name);
                    } elseif ($name === 'class') {
                        $tokens = preg_split('~\s+~', $value, -1, PREG_SPLIT_NO_EMPTY);
                        $tokens = array_filter($tokens ?: [], fn($v) => preg_match('~^[A-Za-z0-9_-]{1,60}$~', $v));
                        if (!$tokens) $node->removeAttribute($name); else $node->setAttribute($name, implode(' ', $tokens));
                    } elseif ($name === 'href' || $name === 'src') {
                        $node->setAttribute($name, $safeUrl($value, $name === 'src'));
                    } elseif ($name === 'target' && !in_array($value, ['_blank','_self'], true)) {
                        $node->removeAttribute($name);
                    } elseif (in_array($name, ['width','height','colspan','rowspan','start','value'], true) && !preg_match('~^\d{1,4}$~', $value)) {
                        $node->removeAttribute($name);
                    } elseif (in_array($name, ['aria-label','title','alt','lang','dir','role','scope','align','datetime','loading','type','rel'], true) && preg_match('~[<>]~', $value)) {
                        $node->removeAttribute($name);
                    }
                }
                if ($tag === 'a' && $node->getAttribute('target') === '_blank') $node->setAttribute('rel', 'noopener noreferrer');
                $clean($node);
            }
        };
        $clean($root);
        $result = '';
        foreach (iterator_to_array($root->childNodes) as $node) $result .= $doc->saveHTML($node);
        return $result;
    }
}
