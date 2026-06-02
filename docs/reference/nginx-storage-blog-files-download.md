# Nginx: allow article attachment downloads (storage/blog-files)

Summary: article attachments (guides & templates uploaded under an article) are
stored at `storage/app/public/blog-files/` and served via the `/storage`
symlink. The main Heratio nginx site has a blanket `deny all` on `/storage/`, so
office and zip downloads (.docx, .xlsx, .pptx, .zip, .csv, .txt, .odt, .ods)
return HTTP 403 unless a narrow allow rule is added. PDF and image files happen
to work without it because an earlier static-asset regex already permits those
extensions. This note records the required nginx rule so it can be re-applied if
the site config is ever regenerated from a template.

## Symptom

Downloading a guide or template from an article shows "File was not found on the
site" (an HTTP 403 from nginx, not a 404). PDFs and image covers download fine;
Word/Excel/PowerPoint/zip do not.

## Why it happens

The Heratio site config (`/etc/nginx/sites-enabled/heratio.theahg.co.za.conf`)
contains a security rule:

    location ~ ^/(?:vendor|storage|database|config|bootstrap|tests|node_modules)/ { deny all; }

That blocks the entire `/storage/` path. Two things interact with it:

- A static-asset regex earlier in the file allows specific extensions:
  `location ~* \.(css|js|map|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot|pdf)$`.
  PDFs and images match this and are served, which is why covers and PDF guides
  work.
- Office and archive extensions (.docx, .xlsx, .pptx, .odt, .ods, .csv, .txt,
  .zip) are NOT in that allow list, so they fall through to the `deny all` and
  return 403.

Application paths for reference:
- DB: `blog_attachment.file_path` holds e.g. `blog-files/<random>.docx`.
- Disk: `storage/app/public/blog-files/<random>.docx`
  (`Storage::disk('public')`).
- Public URL: `/storage/blog-files/<random>.docx` (via the `public/storage`
  symlink to `storage/app/public`).

## The fix (required nginx rule)

Add a narrow allow block that PRECEDES the blanket `/storage` deny. The `^~`
prefix makes it win over the regex deny. It scopes access to only the
`blog-files` sub-tree and forces a download:

    # Article attachments (guides & templates). The blanket /storage deny below
    # blocks office/zip downloads; allow this one public sub-tree.
    location ^~ /storage/blog-files/ {
        alias /usr/share/nginx/heratio/storage/app/public/blog-files/;
        add_header Content-Disposition "attachment";
        try_files $uri =404;
    }

Place it immediately before:

    location ~ ^/(?:vendor|storage|database|config|bootstrap|tests|node_modules)/ { deny all; }

Then validate and reload:

    nginx -t && systemctl reload nginx

## Verify

    curl -s -o /dev/null -w "%{http_code}\n" \
      "https://heratio.theahg.co.za/storage/blog-files/<filename>.docx"

Expect `200`, with `content-type` matching the file and
`content-disposition: attachment`.

## Notes

- This is server config, not in the git repo, so a release does not deploy it.
  Re-apply this rule if the nginx site config is regenerated.
- The blanket `/storage` deny is intentional and stays - it protects the rest of
  the storage tree. Only `blog-files` is opened, and only for download.
- This is a Heratio demo-site feature (articles/blog); it has no AtoM/PSIS twin.
