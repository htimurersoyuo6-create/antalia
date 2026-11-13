<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9">
<xsl:output method="html" encoding="UTF-8" indent="yes"/>
<xsl:template match="/">
<html lang="tr"><head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Site Haritası - Antalya Pet Otel</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#f8fafc;color:#0f172a}
header{background:#0f766e;color:#fff;padding:28px 20px}
header h1{margin:0 0 6px;font-size:24px}
header p{margin:0;opacity:.85;font-size:14px}
main{max-width:1100px;margin:24px auto;padding:0 16px}
table{border-collapse:collapse;width:100%;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08)}
th,td{padding:11px 14px;border-bottom:1px solid #e2e8f0;text-align:left;font-size:14px;vertical-align:top}
th{background:#f1f5f9;font-weight:600}
tr:last-child td{border-bottom:0}
a{color:#0f766e}
.count{color:#64748b;font-size:13px;margin-bottom:10px;display:block}
</style></head>
<body>
<header>
  <h1>Site Haritası</h1>
  <p>antalyapetotel.com - Google için yayımlanan tüm adresler</p>
</header>
<main>
  <xsl:value-of select="count(s:urlset/s:url)"/>
  <span class="count">adres listeleniyor</span>
  <table>
    <tr><th>Adres</th><th>Son değişiklik</th><th>Öncelik</th><th>Sıklık</th></tr>
    <xsl:for-each select="s:urlset/s:url">
    <tr>
      <td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td>
      <td><xsl:value-of select="s:lastmod"/></td>
      <td><xsl:value-of select="s:priority"/></td>
      <td><xsl:value-of select="s:changefreq"/></td>
    </tr>
    </xsl:for-each>
  </table>
</main></body></html>
</xsl:template>
</xsl:stylesheet>