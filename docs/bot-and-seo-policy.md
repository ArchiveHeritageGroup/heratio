# Bots, crawlers and search engines

> **Status: draft for approval** (heratio#1544). Wording to be approved before it is published as a help article.

Heratio wants its published records found. A description that no search engine can see is a description nobody visits, so the default is open: crawlers may read every published record page and the public hubs, and Heratio tells them where those pages are.

What an instance should not do is put a JavaScript challenge (a "prove you are human" page) in front of the catalogue. Those challenges stop scrapers, but they stop Google, Bing and the Internet Archive just as well, and the collection drops out of search results within weeks. Rate limiting does the job without that cost.

## What Heratio publishes for crawlers

- `/robots.txt` allows published content and disallows the admin, account, cart, clipboard and API surfaces. It also points to the sitemap.
- `/sitemap.xml` lists every published record page, with its last-modified date. A large catalogue gets a sitemap index over numbered pages, so it never has to be loaded into memory at once.
- Drafts are never in either file. A record appears only once it is published.

Both are served by the application, so they follow the catalogue automatically. There is nothing to regenerate.

## Recommended rate limiting

Heratio already limits its own machine endpoints: 120 requests a minute per address on the public and v1 API routes, and a per-key hourly allowance on v2 (see the agent access policy). The record pages themselves are better protected at the web server, where a burst never reaches PHP.

A starting point for nginx, in the `http` block:

```nginx
limit_req_zone $binary_remote_addr zone=heratio_pages:10m rate=2r/s;
```

and in the Heratio `server` block:

```nginx
location / {
    limit_req zone=heratio_pages burst=40 nodelay;
    limit_req_status 429;
    try_files $uri $uri/ /index.php?$query_string;
}
```

Two requests a second sustained, with a burst of 40, is far above what a person browsing needs and well below what a scraper wants. Search engine crawlers respect a 429 and slow down. Adjust the numbers to the server: an instance on a small VM may want 1r/s.

If one crawler is still too heavy, block it by user agent in nginx rather than lowering the limit for everyone. Check its reverse DNS first, because many scrapers pretend to be Googlebot.

## Checklist for an instance

1. `https://<your-site>/robots.txt` loads and names the sitemap.
2. `https://<your-site>/sitemap.xml` loads and lists published records.
3. The site is registered in Google Search Console and Bing Webmaster Tools with the sitemap URL.
4. No challenge page sits in front of public record pages.
5. A `limit_req` zone like the one above is in place.
