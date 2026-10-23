# php5-windows-backports

Security backports for the final upstream release of each PHP 5.x branch on Windows x86.

```text
src/
└── 5.6.40/    Patched PHP source, upstream licenses, and version readme
```

| Source | Included work | Validation |
| --- | --- | --- |
| [PHP 5.6.40](src/5.6.40/readme.md) | Base1 mitigation for CVE-2026-109618 and CVE-2026-109619, security backports (Phar TAR CVE-2026-6103/7260, SOAP GHSA-hmxp-6pc4-f3vv/GHSA-m33r-qmcv-p97q, Windows device paths CVE-2026-17545, mysqlnd, HTTP, TLS, filters, OpenSSL, image, URL, review hardening, multipart limit warning collapsing) | Windows x86 TS/NTS runtime binaries built at r4 and reused at r5 with matching hashes; r5 PHPT/FastCGI checks rerun, real-DB results carried forward from r4 (see the version readme for results and limits) |

## Prepared Windows x86 ZIPs

PHP 5.6.40 r5 TS and NTS runtime ZIPs are staged locally for later GitHub Release attachment.
They have **not** been uploaded or published. The ZIPs are Git-ignored under
`lastest/5.6.40/`; a clone still contains source only. Each ZIP includes the matching
CLI/CGI binaries, built extensions, required dependency DLLs, app-local VC11 x86 CRT,
sample INI files and notices, so a separate VC++ runtime installation is not needed
for the packaged binaries on a compatible Windows host.

| GitHub Release asset to attach | SHA-256 |
| --- | --- |
| `php-5.6.40-backports-r5-win-x86-vc11-ts.zip` | `2c53f9bcc9f87d1fc0aab7208db8b8392eef2edefb431dd60210595437d9ae41` |
| `php-5.6.40-backports-r5-win-x86-vc11-nts.zip` | `053283d0bfd4d51fdb3a0f9f5befde0281f09f602daff478699a996f56290488` |

See the [PHP 5.6.40 package notes](src/5.6.40/readme.md#prepared-windows-x86-runtime-zips) for
contents, installation and validation limits. These are community backports, not official PHP releases.

Clone the repository and work directly in the version directory. The patch is already applied.
Each version's README records its changes, provenance, hashes, and validation limits.

The project starts with the two October 2026 CVEs, then addresses other applicable public vulnerabilities and future security fixes.
Only the final release of each PHP 5.x minor branch is in scope. Additional versions will appear after branch-specific work.

A clone supplies source. The build SDK and compiler remain separate from the prepared binary ZIPs.
See the [PHP license](src/5.6.40/LICENSE) and component notices in the source tree.
