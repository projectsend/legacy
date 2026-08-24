# Security Policy

## Reporting a vulnerability

**Use [GitHub's private vulnerability reporting](https://github.com/projectsend/legacy/security/advisories/new).**
It is the "Report a vulnerability" button on this repository's Security tab. The report stays
private between you and the maintainers, the whole exchange lives in one place, and it is the route
that can end in a published advisory with a CVE and your name on it.

If you would rather not use GitHub, or the report does not fit that form, email
<contact@projectsend.org> instead. Either is fine. What matters is that it does not start in
public.

**Please do not open a public issue for a security report.** An issue is world-readable the moment
it is filed, including by people running the version you just described how to break.

### This repository is v1

ProjectSend v1 and ProjectSend v2 are two different applications that share a name. This
repository is v1 — the PHP application whose pages are `.php` files at the top level, with
`includes/`, `templates/` and `upload/`.
v2 is a ground-up rewrite and lives at
[projectsend/projectsend](https://github.com/projectsend/projectsend), with its own security policy
and its own reporting button.

If you are not sure which one you looked at, the file paths tell you. Anything under `includes/`
or `templates/`, or a top-level file like `process.php`, is v1 and belongs here. Anything under
`app/`, `routes/` or `resources/js/` is v2 and belongs there. Reports filed on the wrong repository cannot be moved across, so a minute spent
checking saves re-filing later.

### What helps

Enough to reproduce it, and nothing you would not want to write down:

- The version. It is in the footer of any page once you are signed in, and in
  `includes/app.php` as `CURRENT_VERSION`.
- How the installation is deployed — Apache or Nginx, PHP version, and anything unusual in front
  of it.
- The steps, and what you saw. A short recording or a `curl` command beats a description.
- What an attacker gets out of it, if it is not obvious.

You do not need a proof-of-concept exploit, and you should not run one against an installation that
is not yours.

### What to expect

An acknowledgement within a few days, and a real answer — a fix, a plan, or a reason it is not
what it looked like — once it has been reproduced. If a fix ships, you are credited by name unless
you would rather not be.

This is a small project. If a week goes by in silence, assume the message went astray rather than
that it was ignored, and send it again.

## What is in scope

Anything that lets somebody reach a file, an account, or an installation they should not: the
sharing and permission rules, the client and group assignments, authentication including two-factor
and the social and LDAP sign-in paths, public download links, the upload and download scripts,
and the install and update flows.

Some things are worth a report but are not vulnerabilities in ProjectSend:

- **An installation that has not been hardened.** Serving the `upload/files` directory straight
  from the web server is the common one, and it is a deployment problem — see
  [SECURITY_HARDENING.md](SECURITY_HARDENING.md) for the rules that prevent it and how to check
  they are working. Tell us anyway if the documentation is what led somebody there.
- **Findings from a scanner, unread.** A header a tool wanted and an exploit are different
  claims. Say which one you have.
- **Anything in a dependency**, unless ProjectSend's use of it is what makes it reachable. Those
  belong upstream.

## Supported versions

| | |
|---|---|
| **The current v1 release** — see [Releases](https://github.com/projectsend/legacy/releases/latest) | Supported for security fixes. Upgrade before reporting that an older revision behaves differently. |
| **Older v1 revisions** | Not supported. Fixes land on the current release line only. |
| **ProjectSend 2.x** | A separate application in a separate repository — see [projectsend/projectsend](https://github.com/projectsend/projectsend) for how it handles reports. Nothing here applies to it. |

## Hardening your own installation

If you are trying to configure an installation rather than report a bug,
[SECURITY_HARDENING.md](SECURITY_HARDENING.md) is what you want. It covers the web server rules
that keep uploaded files private on Nginx and Apache, how to check they are actually working,
encryption key handling, and the authentication options worth turning on.
