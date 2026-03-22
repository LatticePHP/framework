# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 0.x     | :white_check_mark: |

Once LatticePHP reaches 1.0, we will adopt a clear LTS and support window policy.

## Reporting a Vulnerability

**Do NOT open a public GitHub issue for security vulnerabilities.**

Please report vulnerabilities via email:

**Email:** security@latticephp.dev

Include the following in your report:

- Description of the vulnerability
- Package(s) affected (e.g., `lattice/auth`, `lattice/workflow`)
- Steps to reproduce
- Impact assessment (what an attacker could achieve)
- Suggested fix (if you have one)

## Response Timeline

| Stage | Timeframe |
|-------|-----------|
| Acknowledgement | Within 48 hours |
| Initial assessment | Within 5 business days |
| Fix development | As soon as practical |
| Coordinated disclosure | Agreed with reporter before public release |

## Disclosure Process

1. Reporter sends vulnerability to security@latticephp.dev
2. We acknowledge receipt within 48 hours
3. We investigate, confirm, and assess severity (CVSS)
4. We develop and test a fix on a private branch
5. We coordinate a disclosure date with the reporter
6. We release the fix, publish a security advisory (GitHub Security Advisories), and credit the reporter

## Security Best Practices for LatticePHP Users

- Always use the latest patch release
- Use asymmetric JWT keys (RS256/ES256) in production, not HMAC
- Enable CORS with explicit origins — never use `*` in production
- Use `#[BelongsToWorkspace]` / `#[BelongsToTenant]` on all tenant-scoped models
- Review `#[Authorize]` and `#[UseGuards]` coverage on all controller methods
- Rotate refresh tokens on every use (enabled by default in `lattice/jwt`)
- Run PHPStan at level max in CI to catch type-safety issues early
