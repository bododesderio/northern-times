#!/usr/bin/env node
/**
 * Article extraction using Mozilla Readability (same as Firefox Reader View).
 * 
 * Usage: echo '<html>...</html>' | node extract.js [url]
 * Output: JSON { title, content, excerpt, byline, length, siteName }
 */

const { Readability } = require('@mozilla/readability');
const { JSDOM } = require('jsdom');

const url = process.argv[2] || 'https://example.com';
const chunks = [];

process.stdin.setEncoding('utf8');
process.stdin.on('data', chunk => chunks.push(chunk));
process.stdin.on('end', () => {
  const html = chunks.join('');
  
  if (!html || html.length < 200) {
    process.stdout.write(JSON.stringify({ error: 'No HTML input' }));
    process.exit(0);
  }

  try {
    const dom = new JSDOM(html, { url });
    const reader = new Readability(dom.window.document, {
      charThreshold: 100,
      keepClasses: false,
      nbTopCandidates: 10,
    });
    
    const article = reader.parse();
    
    if (!article || !article.content) {
      process.stdout.write(JSON.stringify({ error: 'Readability could not parse' }));
      process.exit(0);
    }

    // Clean wrapper divs and empty elements
    let content = article.content;
    content = content.replace(/^<div[^>]*id="readability-page-1"[^>]*>/i, '');
    content = content.replace(/<\/div>\s*$/i, '');
    content = content.replace(/<p>\s*<\/p>/gi, '');
    content = content.replace(/<div>\s*<\/div>/gi, '');
    
    process.stdout.write(JSON.stringify({
      title: article.title || '',
      content: content,
      excerpt: article.excerpt || '',
      byline: article.byline || '',
      length: article.length || 0,
      siteName: article.siteName || '',
    }));
    
  } catch (e) {
    process.stdout.write(JSON.stringify({ error: e.message }));
  }
  
  process.exit(0);
});

setTimeout(() => {
  process.stdout.write(JSON.stringify({ error: 'Timeout' }));
  process.exit(1);
}, 15000);