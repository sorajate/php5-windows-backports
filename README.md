# php5-windows-backports

Security backports for the final upstream release of each PHP 5.x branch on Windows x86.

```text
src/
└── 5.6.40/    Patched PHP source, upstream licenses, and version readme
```

| Source | Included work | Validation |
| --- | --- | --- |
| [PHP 5.6.40](src/5.6.40/readme.md) | Base1 mitigation for CVE-2026-109618 and CVE-2026-109619, security backports (Phar TAR CVE-2026-6103/7260, SOAP GHSA-hmxp-6pc4-f3vv/GHSA-m33r-qmcv-p97q, Windows device paths CVE-2026-17545, mysqlnd, HTTP, TLS, filters, OpenSSL, image, URL, review hardening) | Built and tested on Windows x86: TS and NTS each pass the focused suite and all changed PHPTs (see the version readme for results and limits) |

Clone the repository and work directly in the version directory. The patch is already applied.
Each version's README records its changes, provenance, hashes, and validation limits.

The project starts with the two October 2026 CVEs, then addresses other applicable public vulnerabilities and future security fixes.
Only the final release of each PHP 5.x minor branch is in scope. Additional versions will appear after branch-specific work.

A clone supplies source. Matching SDKs, compilers, dependencies, and release binaries are separate.
See the [PHP license](src/5.6.40/LICENSE) and component notices in the source tree.
