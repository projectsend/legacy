# Securing a ProjectSend Deployment

This document covers the things that are *not* handled for you by the installer, and that can silently leave files readable by anyone if they are wrong. For reporting a vulnerability in ProjectSend itself, see [SECURITY.md](SECURITY.md).

---

## 1. Verify that direct file access is blocked

This is the single most important check, and the only one that does not depend on
guessing how your web server is configured. **Do it on every new deployment, and
again after any web server config change.**

Upload a file through ProjectSend, then look in `upload/files/` on the server
for the name it was stored under — something like
`1754500000-3f9a2b1c8d7e6f50-report.pdf`. If you have "organize uploads in
folders by date" enabled it will be under a `YYYY/MM/` subdirectory. Then
request that path directly, bypassing the application:

```bash
curl -I https://your-domain.com/upload/files/1754500000-3f9a2b1c8d7e6f50-report.pdf
```

- **`403` or `404`** — correct. The file is only reachable through
  `download.php` / `process.php?do=serve_file`, which check permissions.
- **`200`** — **your files are public.** Anyone who learns or guesses the path
  can download them without logging in, whatever the file's client and group
  assignments say. Fix your web server config before going further.

Repeat the test with an **image** (`.jpg`, `.png`, `.svg`) as well as a
document. On Nginx it is entirely possible for PDFs to be blocked while images
are served — see the next section for why.

---

## 2. Nginx

Nginx ignores `.htaccess` files completely, so the protection Apache gets from
`upload/files/.htaccess` does not exist until you configure it yourself. A
ready-to-use server block ships with ProjectSend: [`nginx.conf.example`](nginx.conf.example).

### The `^~` is required

```nginx
location ^~ /upload/files/ {
    deny all;
}
location ^~ /upload/temp/ {
    deny all;
}
```

Nginx picks a location in this order: exact (`=`) match, then the longest
matching prefix, and then — unless that prefix is marked `^~` — it checks the
regex locations and **a matching regex wins over the prefix**.

So with a plain `location /upload/files/ { deny all; }`, a perfectly ordinary
static asset block later in the same server:

```nginx
location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
    expires 30d;
}
```

takes over every `.jpg`, `.png`, `.svg`, `.css` and `.js` under
`upload/files/`, and serves them directly off disk with no login. The
`deny all` is never consulted for those extensions. Files with other
extensions stay blocked, which is what makes this so easy to miss — testing
with a PDF passes while every uploaded image is public.

Marking the location `^~` tells Nginx to stop at the prefix and never consult
the regexes. Apply it to the other blocked directories too (`/includes/`,
`/vendor/`, `/cache/`, and the internal `/serve-file` location).

### Do not block these

`upload/thumbnails/` and `upload/admin/` must stay reachable — thumbnails and
your branding logo are served to browsers from there. Block the whole of
`/upload/` and your interface loses its images.

### What this does not break

Downloads and previews do not use these URLs, so blocking them costs you
nothing:

- Downloads go through `download.php` / `process.php?do=download`.
- PDF, video and audio previews go through `process.php?do=serve_file`, which
  re-checks permissions on every request.
- Image thumbnails and previews are served from `upload/thumbnails/`.
- X-Accel still works: the app redirects to the internal `/serve-file`
  location, which is a different URL prefix from `/upload/files/`.

The one exception is **Options → Security → "Show thumbnails for SVG files"**,
which is off by default. When it is on, SVG thumbnails are linked directly at
`upload/files/`, so they will not render once the directory is blocked. This
has always been the case on Apache, whose shipped `.htaccess` denies the same
directory; only an Nginx server missing these rules ever displayed them.

---

## 3. Apache

Apache reads `upload/files/.htaccess`, which denies direct access — but only if
the server is set to let it. If the enclosing `<Directory>` block uses
`AllowOverride None`, every `.htaccess` file in the installation is ignored and
uploaded files become publicly readable, with nothing in the logs to say so.

Make sure the vhost allows the overrides:

```apache
<Directory /var/www/html/projectsend>
    AllowOverride All
</Directory>
```

Then confirm with the `curl` check in section 1. Do not assume it works because
Apache is serving the site.

---

## 4. Back up the encryption master key

If you use encryption at rest, `ENCRYPTION_MASTER_KEY` in
`includes/sys.config.php` is what every file's individual key is encrypted
with. **Losing it means losing every encrypted file permanently** — there is no
recovery path, and no support request can undo it.

- Keep a copy somewhere other than the server it protects.
- Never change the value after files have been encrypted.
- Include `includes/sys.config.php` in your backups, and keep those backups at
  least as protected as the files themselves — the key sits in there in plain
  text next to your database credentials.

---

## 5. Clean up decrypted temporary files

If you serve downloads with **X-Accel (nginx)**, **XSendFile (apache)** or
**LiteSpeed**, an encrypted file has to be decrypted to `upload/temp/` before
the web server can send it. Those plaintext copies cannot be deleted at the end
of the request, because the web server reads them after PHP has already
finished.

They are swept up afterwards, but make sure the sweep is actually running:

- Enable **Options → Scheduled tasks (cron) → Delete temporary decrypted
  files**, and make sure your cron job is configured and running. Without cron,
  the cleanup falls back to running on admin page loads, which only happens
  when an administrator visits the site.
- Confirm `upload/temp/` is blocked from direct access (section 1) — a
  decrypted copy sitting there is the unencrypted file.

If you do not need the performance, the plain **php** download method has none
of this, as nothing is ever written out in the clear.

---

## 6. Two-factor authentication

Under **Options → Security**:

- `two_factor_required` — require a second factor for all accounts.
- `two_factor_allow_totp` — allow authenticator apps.
- `two_factor_allow_email` — allow emailed codes.

Email codes are only as strong as the mailbox they land in, and they travel
over whatever path your mail takes. If you want authenticator apps only, turn
`two_factor_allow_email` off rather than merely telling people to prefer TOTP.

---

## 7. Single sign-on

If you connect an OpenID Connect provider under **Options → Social Networks
Login**, keep **Require verified email address** enabled.

ProjectSend matches an SSO identity to an account by email address. A provider
that hands over an address it never verified — which is the default on many
self-hosted identity servers that allow self registration — is then enough for
someone to sign in as any existing user holding that address, including an
administrator. Only turn the setting off if your provider genuinely does not
send the `email_verified` claim, and you trust every address it issues.

---

## 8. General

- **Serve the site over HTTPS.** Sessions, passwords, 2FA codes and every file
  otherwise cross the network in the clear.
- **Keep ProjectSend updated.** Releases frequently contain security fixes;
  see [WHATS_NEW.md](WHATS_NEW.md) and the release notes.
- **Give roles the least privilege that works.** Permissions are per-role under
  **User Roles**; do not hand out `edit_settings` or `view_system_info` for
  convenience.
- **Protect `includes/sys.config.php`.** It holds the database credentials and
  the encryption master key. It must not be web-readable — covered by the
  `/includes/` block on Nginx and by `.htaccess` on Apache, both of which the
  section 1 check will confirm.
