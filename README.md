# php5-windows-backports

Security backports for the final upstream release of each PHP 5.x branch on Windows x86.

```text
src/
└── 5.6.40/    Patched PHP source, upstream licenses, and version readme
```

| Source | Included work | Validation |
| --- | --- | --- |
| [PHP 5.6.40](src/5.6.40/readme.md) | CVE-2026-109618/109619 mitigation and backports for Phar, SOAP, Windows paths, mysqlnd, HTTP, TLS and multipart parsing | Windows x86 VC11 TS/NTS: 83 PASS, 15 SKIP, 0 FAIL in the changed PHPT set per variant; persistent FastCGI 20/20. See the version readme for database results and limits. |

Download the [PHP 5.6.40 Windows x86 TS/NTS release](https://github.com/sorajate/php5-windows-backports/releases/tag/php-5.6.40-backports-r5),
including `SHA256SUMS.txt`. The ZIPs are separate from the source repository.

## Microsoft runtime for the Windows x86 build

PHP 5.6.40 Windows x86 TS and NTS builds use VC11. The matching Microsoft runtime package is
[Visual C++ 2012 Redistributable Update 4 **x86**, version 11.0.61030.0](https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist#visual-studio-2012-vc-110-update-4-no-longer-supported).
Its relevant DLLs are `msvcr110.dll` and `msvcp110.dll` (needed by Intl/ICU).
See the [version readme](src/5.6.40/readme.md#building) for the build profile.

Clone the repository and work directly in the version directory. The patch is already applied.
Each version's README records its changes, provenance, hashes, and validation limits.

The project starts with the two October 2026 CVEs, then addresses other applicable public vulnerabilities and future security fixes.
Only the final release of each PHP 5.x minor branch is in scope. Additional versions will appear after branch-specific work.

A clone supplies source. Building requires the matching x86 VC11 toolchain and dependencies.
See the [PHP license](src/5.6.40/LICENSE) and component notices in the source tree.
