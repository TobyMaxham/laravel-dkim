# Security Policy

## Reporting a Vulnerability

**Preferred:** Use GitHub's private vulnerability reporting for this repository
(Security tab → "Report a vulnerability"). This creates a private draft security
advisory that only maintainers can see, and keeps the whole conversation — including
any patch discussion — in one place.

**Alternative:** If you can't use GitHub's reporting feature, email
**security@maxham.net**.

Please do **not** open a public GitHub issue for security vulnerabilities.

### What to Include

To help us triage quickly, please include:

- Type of vulnerability (e.g. injection, insecure deserialization, credential leak)
- Affected file(s) or component, and affected version(s)
- Steps to reproduce, or a minimal proof of concept
- Potential impact (what an attacker could achieve)
- A suggested fix or mitigation, if you have one (optional)

### What to Expect

- **Acknowledgement:** within 48 hours
- **Initial assessment** (whether it's confirmed, and a rough fix timeline): within 7
  days
- **Fix timeline:** depends on severity — critical issues are prioritized immediately;
  others are scheduled into the next release

We follow coordinated disclosure: we'll keep you updated on progress and agree on a
disclosure timeline with you before anything is made public. We won't publish details
of a reported vulnerability until a fix is available, unless you and we agree otherwise.

## Supported Versions

This package follows a rolling major-version support policy:

- **Current major version** — receives full support: new features, bug fixes, and
  security fixes.
- **Previous major version** — receives **security fixes only** (no new features, no
  routine bug fixes) for up to **2 years** after the current major version's release
  date.
- **Any version older than that** — unsupported. Please upgrade.

We deliberately don't pin exact version numbers or dates in this file — that would mean
updating this file by hand on every release. Check the repository's tags/releases page
for exact release dates if you need to calculate where a specific version currently
stands.

### Exception: PHP / Laravel end-of-life

If, at the time a new major version is released, the previous major version depends on
a PHP or Laravel version that has itself already reached end-of-life (i.e. no longer
receives security fixes from the PHP/Laravel project itself), the security-fix window
for that previous major version is shortened to **1 year (12 months)** instead of the
standard 2 years — counted from the new major version's release date, not from the
PHP/Laravel version's own end-of-life date.

**Example:** PHP 8.1 reaches end-of-life in September. We don't drop PHP 8.1 support
until version 3.0 ships in the following January. Because PHP 8.1 is already
end-of-life at that point, version 2.x (which still requires PHP 8.1) receives security
fixes only until the following January (12 months after 3.0's release) — not the usual
2 years, and independent of when PHP 8.1's own end-of-life actually was.

If, instead, the PHP/Laravel version being dropped is still officially supported at the
time of the major bump, the standard 2-year window applies as normal.

If in doubt about which case applies to a specific release, check that release's
changelog entry — it will state explicitly whether the major bump was driven by a
PHP/Laravel EOL or by an internal change.

## Scope Notes

Security reports are welcome for vulnerabilities in this package's own code. Issues in
its dependencies should generally be reported upstream, unless this package is using
them in a way that introduces additional risk (e.g. passing unsanitized input to a
vulnerable method) — in that case, please report it here as well.

## Acknowledgments

We're happy to credit reporters who responsibly disclose a vulnerability (with your
permission). No issues have been reported yet — this section will be updated if that
changes.
